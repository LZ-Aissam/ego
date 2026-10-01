<div class="space-y-5">
    <header>
        <x-sys.label>TON EGO · EN LIGNE</x-sys.label>
        {{-- « Salut Prénom » reste d'un seul tenant (vérifié par LayoutTest) --}}
        <h1 class="mt-2 text-3xl font-bold leading-tight"><span class="text-ice [text-shadow:0_0_16px_var(--glow-ice)]">Salut {{ $user->first_name }}</span>.<br>Prêt à forger ?</h1>
    </header>

    <x-sys.window title="STATUT" :index="0">
        <div class="flex items-end gap-4">
            <div>
                <x-sys.label>Niveau</x-sys.label>
                <p class="sys-num text-5xl font-extrabold leading-none text-frost [text-shadow:0_0_18px_var(--glow-ice)]" data-count-up="{{ $player['level'] }}">{{ $player['level'] }}</p>
            </div>
            <div class="pb-1">
                <p class="text-sm text-mist">Rang {{ $player['rank'] }}@if ($user->equippedTitleName()) · « {{ $user->equippedTitleName() }} »@endif</p>
            </div>
        </div>
        <x-sys.xp-bar :progress="$player" class="mt-3" />
        <div class="mt-4 grid grid-cols-2 gap-x-5 gap-y-3">
            <x-sys.stat label="FORCE" :value="$stats['force']" />
            <x-sys.stat label="ENDURANCE" :value="$stats['endurance']" />
            <x-sys.stat label="PROGRESSION" :value="$stats['progression']" />
            <x-sys.stat label="RÉGULARITÉ" :value="$stats['regularite']" />
        </div>
    </x-sys.window>

    <x-sys.button :href="route('workout')" :pulse="true" class="py-5 text-lg">Séance libre <span aria-hidden="true">→</span></x-sys.button>

    @if (! $user->sex || ! $user->weight_kg)
        <a href="{{ route('profile') }}" class="block">
            <x-sys.window title="CALIBRAGE REQUIS" :index="1">
                <p class="text-sm text-mist">Complète ton profil (sexe, taille, poids) pour que TON EGO te suggère tes charges de départ. <span class="text-ice">→</span></p>
            </x-sys.window>
        </a>
    @endif

    @if ($lastWorkout)
        <a href="{{ route('history.show', $lastWorkout) }}" class="block">
            <x-sys.window title="DERNIÈRE SÉANCE" :index="2">
                <p class="font-semibold">{{ ucfirst($lastWorkout->finished_at->translatedFormat('l j F')) }}</p>
                <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                    <div><x-sys.label>Durée</x-sys.label><p class="sys-num mt-1 text-lg font-bold">{{ \App\Support\Format::duration($lastWorkout->durationMinutes()) }}</p></div>
                    <div><x-sys.label>Séries</x-sys.label><p class="sys-num mt-1 text-lg font-bold">{{ $lastWorkout->sets->count() }}</p></div>
                    <div><x-sys.label>Volume</x-sys.label><p class="sys-num mt-1 text-lg font-bold text-ice" data-count-up="{{ $lastWorkout->volume() }}" data-decimals="{{ fmod($lastWorkout->volume(), 1.0) === 0.0 ? 0 : 1 }}" data-suffix=" kg">{{ \App\Support\Format::kg($lastWorkout->volume()) }}</p></div>
                </div>
            </x-sys.window>
        </a>
    @endif

    <x-sys.window title="DERNIERS RECORDS" :index="3">
        @forelse ($recentRecords as $record)
            <div class="sys-row">
                <span>{{ $record->exercise->name }}</span>
                <span class="sys-num font-bold text-ice">{{ \App\Support\Format::kg($record->max_weight_kg) }} × {{ $record->max_weight_reps }}</span>
            </div>
        @empty
            <p class="text-sm text-mist">Tes records apparaîtront ici après ta première séance.</p>
        @endforelse
        <a href="{{ route('records') }}" class="sys-label mt-3 block text-right">Tout voir</a>
    </x-sys.window>
</div>
