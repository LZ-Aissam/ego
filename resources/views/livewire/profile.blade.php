<div class="space-y-5">
    <x-sys.window title="FICHE DU JOUEUR">
        <div class="flex items-center gap-4">
            <span class="grid h-16 w-16 place-items-center text-3xl font-black text-void [clip-path:var(--sys-cut)]" style="background: {{ $user->avatar_color }}; box-shadow: 0 0 24px {{ $user->avatar_color }}">
                {{ mb_strtoupper(mb_substr($user->first_name, 0, 1)) }}
            </span>
            <div>
                <h1 class="text-2xl font-bold">{{ $user->first_name }}</h1>
                <p class="sys-num text-sm text-mist">Niveau {{ $player['level'] }} · Rang {{ $player['rank'] }} · {{ $user->birth_date->age }} ans</p>
                @if ($user->equippedTitleName()) <p class="text-sm text-ice">« {{ $user->equippedTitleName() }} »</p> @endif
            </div>
        </div>
        <x-sys.xp-bar :progress="$player" class="mt-4" />
        <div class="mt-4 grid grid-cols-2 gap-x-5 gap-y-3">
            <x-sys.stat label="FORCE" :value="$stats['force']" />
            <x-sys.stat label="ENDURANCE" :value="$stats['endurance']" />
            <x-sys.stat label="PROGRESSION" :value="$stats['progression']" />
            <x-sys.stat label="RÉGULARITÉ" :value="$stats['regularite']" />
        </div>
        @if ($stats['force'] === null)
            <p class="mt-2 text-xs text-mist">Renseigne ton poids ci-dessous pour mesurer ta FORCE.</p>
        @endif
    </x-sys.window>
    <a href="{{ route('crew.member', $user) }}" class="sys-label block text-right">Voir comme les autres</a>

    <div class="grid grid-cols-2 gap-3">
        <x-sys.button :href="route('history')" variant="ghost">Historique</x-sys.button>
        <x-sys.button :href="route('records')" variant="ghost">Records</x-sys.button>
    </div>

    <x-sys.window title="MORPHOLOGIE" :index="1">
        <form wire:submit="save" class="space-y-4" novalidate>
            <div>
                <p class="sys-label mb-1">Sexe</p>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (['male' => 'Homme', 'female' => 'Femme'] as $value => $label)
                        <label @class(['sys-chip cursor-pointer py-3 text-center text-sm', 'sys-chip--active' => $sex === $value])>
                            <input type="radio" wire:model.live="sex" value="{{ $value }}" class="sr-only">{{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('sex') <p class="sys-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-sys.field name="heightCm" label="Taille (cm)" inputmode="numeric" wire:model="heightCm" />
                <x-sys.field name="weightKg" label="Poids (kg)" inputmode="decimal" wire:model="weightKg" />
            </div>
            @if ($user->bmi())
                <p class="sys-num text-sm text-mist">IMC indicatif : {{ number_format($user->bmi(), 1, ',', ' ') }}</p>
            @endif
            <x-sys.button type="submit">Enregistrer</x-sys.button>
            @if ($saved) <p class="text-center text-sm text-ice">Profil enregistré ✓</p> @endif
        </form>
    </x-sys.window>

    <x-sys.window title="PROFIL DE FORCE" :index="3">
        @forelse ($maxes as $max)
            <div class="sys-row text-sm">
                <span>{{ $max->exercise->name }}</span>
                <span class="sys-num"><span class="font-bold text-ice">{{ \App\Support\Format::kg($max->e1rm_kg) }}</span> <span class="text-mist">· {{ $max->measured_at->translatedFormat('j M') }}</span></span>
            </div>
        @empty
            <p class="text-sm text-mist">Pas encore calibré : TON EGO ne connaît pas ta puissance.</p>
        @endforelse
        <a href="{{ route('calibration') }}" class="sys-label mt-3 block text-right">{{ $maxes->isEmpty() ? 'Lancer le calibrage' : 'Recalibrer' }}</a>
    </x-sys.window>

    <x-sys.window title="MODE PAUSE" :index="3">
        @if ($user->isPaused())
            <p class="text-sm text-mist">
                QUÊTES SUSPENDUES depuis le {{ $user->paused_at->translatedFormat('j F') }}
                {{ $user->paused_until ? 'jusqu\'au '.$user->paused_until->translatedFormat('j F').' inclus' : 'jusqu\'à ton retour' }}.
                Ta série ({{ $user->streak_days }} j) est gelée.
            </p>
            <x-sys.button class="mt-3" wire:click="resume">Reprendre les quêtes</x-sys.button>
        @else
            <p class="mb-3 text-sm text-mist">Blessure, vacances ? Suspends tes quêtes : aucune pénalité, ta série est gelée.</p>
            <x-sys.field name="pauseUntil" label="Jusqu'au (facultatif)" type="date" wire:model="pauseUntil" />
            <x-sys.button variant="ghost" class="mt-3" wire:click="pause">Suspendre les quêtes</x-sys.button>
        @endif
    </x-sys.window>

    <x-sys.window title="TITRES" :index="2">
        @foreach (\App\Game\Titles::CATALOG as $key => $title)
            @if (in_array($key, $unlocked, true))
                <button type="button" wire:click="equipTitle('{{ $key }}')" class="sys-row w-full text-left">
                    <span class="font-semibold">{{ $title['name'] }}</span>
                    <span @class(['sys-label', '!text-ice' => $user->equipped_title === $key])>{{ $user->equipped_title === $key ? 'Équipé' : 'Équiper' }}</span>
                </button>
            @else
                <div class="sys-row opacity-60">
                    <span>🔒 {{ $title['name'] }}</span>
                    <span class="text-right text-xs text-mist">{{ $title['condition'] }}</span>
                </div>
            @endif
        @endforeach
    </x-sys.window>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <x-sys.button type="submit" variant="ghost">Se déconnecter</x-sys.button>
    </form>
</div>
