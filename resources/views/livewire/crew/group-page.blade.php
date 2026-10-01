<div class="space-y-5">
    <a href="{{ route('community', ['tab' => 'groupes']) }}" class="sys-label">← Groupes</a>
    <x-sys.window :title="mb_strtoupper($crew->name)">
        <div class="flex items-center gap-3">
            <span class="h-10 w-10 [clip-path:var(--sys-cut)]" style="background: {{ $crew->hex() }}; box-shadow: 0 0 20px {{ $crew->hex() }}"></span>
            <div>
                <p class="sys-label">Code d'invitation</p>
                <p class="sys-num text-2xl font-extrabold tracking-[0.3em] text-frost">{{ $crew->code }}</p>
            </div>
        </div>
        <div x-data="{ copied: false }" class="mt-3">
            <p class="sys-num break-all text-xs text-mist">{{ $inviteUrl }}</p>
            <x-sys.button variant="ghost" class="mt-2" x-on:click="navigator.clipboard?.writeText(@js($inviteUrl)); copied = true; setTimeout(() => copied = false, 2000)">
                <span x-text="copied ? 'Lien copié ✓' : 'Copier le lien d\'invitation'">Copier le lien d'invitation</span>
            </x-sys.button>
        </div>
        <a href="{{ route('community', ['groupe' => $crew->id]) }}" class="sys-label mt-3 block text-right">Classement du groupe</a>
    </x-sys.window>

    <x-sys.window title="MEMBRES" :index="1">
        @foreach ($members as $member)
            <div class="sys-row">
                <a href="{{ route('crew.member', $member) }}" class="font-semibold">
                    {{ $member->first_name }}
                    @if ($crew->owner_id === $member->id) <span class="sys-label">Créateur</span> @endif
                </a>
                <span class="flex items-center gap-3">
                    <span class="sys-num text-sm text-mist">Niv. {{ $member->player()['level'] }}</span>
                    @if ($isOwner && $member->id !== $crew->owner_id)
                        <button type="button" class="sys-label !text-alert" wire:click="kick({{ $member->id }})">Retirer</button>
                    @endif
                </span>
            </div>
        @endforeach
        <x-sys.button variant="ghost" class="mt-4" wire:click="leave">Quitter le groupe</x-sys.button>
    </x-sys.window>

    @if ($isOwner)
        <x-sys.window title="RÉGLAGES DU CRÉATEUR" :index="2">
            <form wire:submit="save" class="space-y-3" novalidate>
                <x-sys.field name="name" label="Nom du groupe" wire:model="name" maxlength="30" />
                <div class="flex flex-wrap gap-2">
                    @foreach (\App\Models\Crew::COLOR_NAMES as $key => $label)
                        <label @class(['sys-chip cursor-pointer', 'sys-chip--active' => $color === $key])>
                            <input type="radio" wire:model.live="color" value="{{ $key }}" class="sr-only">
                            <span class="mr-1 inline-block h-2.5 w-2.5 rounded-full" style="background: {{ \App\Models\Crew::COLORS[$key] }}"></span>{{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('color') <p class="sys-error">{{ $message }}</p> @enderror
                <x-sys.button type="submit">Enregistrer</x-sys.button>
            </form>
            <x-sys.button variant="ghost" class="mt-3" wire:click="regenerate">Nouveau code (l'ancien ne marchera plus)</x-sys.button>
            @if ($confirmingDelete)
                <div class="mt-4 border border-alert/50 p-3">
                    <p class="text-sm text-alert">Le groupe et ses adhésions seront supprimés. Vos séances et votre XP restent intacts.</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <x-sys.button variant="ghost" wire:click="$set('confirmingDelete', false)">Annuler</x-sys.button>
                        <x-sys.button wire:click="delete">Supprimer définitivement</x-sys.button>
                    </div>
                </div>
            @else
                <x-sys.button variant="ghost" class="mt-3 !text-alert" wire:click="askDelete">Supprimer le groupe</x-sys.button>
            @endif
        </x-sys.window>
    @endif
</div>
