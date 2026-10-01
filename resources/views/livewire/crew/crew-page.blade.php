<div class="space-y-5">
    <x-sys.label>Crew</x-sys.label>
    <div class="flex gap-2" role="tablist">
        @foreach (['classement' => 'Classement', 'membres' => 'Membres', 'groupes' => 'Groupes'] as $key => $label)
            <button type="button" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" wire:click="$set('tab', '{{ $key }}')"
                    @class(['sys-chip', 'sys-chip--active' => $tab === $key])>{{ $label }}</button>
        @endforeach
    </div>

    @if ($tab === 'classement')
        <x-sys.window title="CLASSEMENT">
            <div class="mb-3 flex flex-wrap gap-2">
                @foreach (['xp' => 'XP', 'streak' => 'Série', 'force' => 'Force'] as $key => $label)
                    <button type="button" wire:click="$set('metric', '{{ $key }}')" @class(['sys-chip', 'sys-chip--active' => $metric === $key])>{{ $label }}</button>
                @endforeach
            </div>
            @if ($metric === 'xp')
                <div class="mb-3 flex flex-wrap gap-2">
                    @foreach (['week' => 'Semaine', 'month' => 'Mois', 'all' => 'Depuis toujours'] as $key => $label)
                        <button type="button" wire:click="$set('period', '{{ $key }}')" @class(['sys-chip', 'sys-chip--active' => $period === $key])>{{ $label }}</button>
                    @endforeach
                </div>
            @elseif ($metric === 'force')
                <div class="mb-3 flex flex-wrap gap-2">
                    @foreach (['bench' => 'Développé couché', 'squat' => 'Squat', 'deadlift' => 'Soulevé de terre'] as $key => $label)
                        <button type="button" wire:click="$set('lift', '{{ $key }}')" @class(['sys-chip', 'sys-chip--active' => $lift === $key])>{{ $label }}</button>
                    @endforeach
                </div>
            @endif
            @if ($myCrews->isNotEmpty())
                <div class="mb-3 flex flex-wrap gap-2">
                    <button type="button" wire:click="$set('group', null)" @class(['sys-chip', 'sys-chip--active' => $group === null])>Tout le monde</button>
                    @foreach ($myCrews as $crew)
                        <button type="button" wire:click="$set('group', {{ $crew->id }})" @class(['sys-chip', 'sys-chip--active' => $group === $crew->id])>{{ $crew->name }}</button>
                    @endforeach
                </div>
            @endif

            @forelse ($rows as $row)
                @include('livewire.crew.leaderboard-row', ['row' => $row])
            @empty
                <p class="text-sm text-mist">Aucun chasseur classé pour l'instant.</p>
            @endforelse
            @if ($pinned)
                <div class="mt-2 border-t border-ice/30 pt-2">@include('livewire.crew.leaderboard-row', ['row' => $pinned])</div>
            @endif
        </x-sys.window>
    @elseif ($tab === 'membres')
        <x-sys.window title="MEMBRES">
            <x-sys.field name="search" label="Rechercher un prénom" wire:model.live.debounce.300ms="search" />
            <div class="mt-3">
                @forelse ($members as $member)
                    <a href="{{ route('crew.member', $member) }}" class="sys-row">
                        <span class="font-semibold">{{ $member->first_name }} @if ($member->equippedTitleName()) <span class="text-xs text-ice">« {{ $member->equippedTitleName() }} »</span> @endif</span>
                        <span class="sys-num text-sm text-mist">Niv. {{ $member->player()['level'] }} · Rang {{ $member->player()['rank'] }}</span>
                    </a>
                @empty
                    <p class="text-sm text-mist">Aucun membre ne correspond.</p>
                @endforelse
            </div>
        </x-sys.window>
    @else
        <x-sys.window title="MES GROUPES">
            @forelse ($myCrews as $crew)
                <a href="{{ route('crew.group', $crew) }}" class="sys-row">
                    <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-full" style="background: {{ $crew->hex() }}; box-shadow: 0 0 10px {{ $crew->hex() }}"></span><span class="font-semibold">{{ $crew->name }}</span></span>
                    <span class="sys-num text-sm text-mist">{{ $crew->members_count }} {{ $crew->members_count > 1 ? 'membres' : 'membre' }}</span>
                </a>
            @empty
                <p class="text-sm text-mist">Tu n'as pas encore de groupe. Crée le tien ou rejoins celui d'un ami.</p>
            @endforelse
        </x-sys.window>

        <x-sys.window title="CRÉER UN GROUPE" :index="1">
            <form wire:submit="createCrew" class="space-y-3" novalidate>
                <x-sys.field name="name" label="Nom du groupe" wire:model="newName" maxlength="30" />
                <div class="flex flex-wrap gap-2">
                    @foreach (\App\Models\Crew::COLOR_NAMES as $key => $label)
                        <label @class(['sys-chip cursor-pointer', 'sys-chip--active' => $newColor === $key])>
                            <input type="radio" wire:model.live="newColor" value="{{ $key }}" class="sr-only">
                            <span class="mr-1 inline-block h-2.5 w-2.5 rounded-full" style="background: {{ \App\Models\Crew::COLORS[$key] }}"></span>{{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('color') <p class="sys-error">{{ $message }}</p> @enderror
                <x-sys.button type="submit">Créer le groupe</x-sys.button>
            </form>
        </x-sys.window>

        <x-sys.window title="REJOINDRE AVEC UN CODE" :index="2">
            <form wire:submit="joinCrew" class="space-y-3" novalidate>
                <x-sys.field name="code" label="Code d'invitation" wire:model="joinCode" autocomplete="off" />
                @if (session('crew_error')) <p class="sys-error">{{ session('crew_error') }}</p> @endif
                <x-sys.button type="submit" variant="ghost">Rejoindre</x-sys.button>
            </form>
        </x-sys.window>
    @endif
</div>
