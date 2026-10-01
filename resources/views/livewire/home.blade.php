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
                <p class="text-sm text-mist">Rang {{ $player['rank'] }} · Série {{ $user->streak_days }} j @if ($user->equippedTitleName()) · « {{ $user->equippedTitleName() }} »@endif</p>
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

    @if ($needsCalibration)
        <x-sys.window title="TON EGO DOIT MESURER TA PUISSANCE" variant="alert" :index="1">
            <p class="text-sm text-mist">Sans ce test, TON EGO ne connaît pas tes charges de départ et ne peut pas suivre ta progression : 3 ou 4 mouvements, environ 15 minutes.</p>
            <x-sys.button class="mt-3" :pulse="true" :href="route('calibration')">Lancer le test</x-sys.button>
        </x-sys.window>
    @endif

    <a href="{{ route('community') }}" class="sys-row sys-num text-sm">
        @if ($weekRank)
            <span>Classement de la semaine : tu es {{ $weekRank }}{{ $weekRank === 1 ? 'ᵉʳ' : 'ᵉ' }}</span>
        @else
            <span class="text-mist">Gagne de l'XP pour entrer au classement</span>
        @endif
        <span class="sys-label">Crew →</span>
    </a>

    @if ($penalty && $penalty->status !== 'cancelled')
        <x-sys.window title="PÉNALITÉ" variant="alert" :index="1">
            <p class="mb-2 text-sm text-mist">Quête d'hier non accomplie. Termine ce circuit avant minuit pour sauver ta série.</p>
            @foreach ($penalty->objectives as $i => $objective)
                <div class="sys-row text-sm">
                    <span>{{ $objective['name'] }} · {{ $objective['sets'] }} × {{ $objective['reps_min'] }}</span>
                    <span class="sys-num text-mist">{{ min($penaltyProgress[$i] ?? 0, $objective['sets']) }} / {{ $objective['sets'] }}</span>
                </div>
            @endforeach
            @if ($penalty->status === 'pending')
                <x-sys.button class="mt-3" :href="route('workout.quest', $penalty)">Accepter la pénalité</x-sys.button>
            @else
                <p class="mt-3 text-center text-sm text-ice">PÉNALITÉ ACCOMPLIE ✓</p>
            @endif
        </x-sys.window>
    @endif

    @if ($user->isPaused())
        <x-sys.window title="QUÊTES SUSPENDUES" :index="2">
            <p class="text-sm text-mist">TON EGO attend ton retour. Ta série ({{ $user->streak_days }} j) est gelée.</p>
            <a href="{{ route('profile') }}" class="sys-label mt-2 block text-right">Reprendre</a>
        </x-sys.window>
    @elseif ($quest && in_array($quest->type, ['training', 'calibration'], true))
        <x-sys.window title="QUÊTE DU JOUR" :index="2">
            <p class="mb-2 text-center text-sm text-mist">TON EGO t'assigne : <span class="font-bold text-frost">{{ $quest->title }}</span></p>
            @foreach ($quest->objectives as $i => $objective)
                @php $done = min($questProgress[$i] ?? 0, $objective['sets']); @endphp
                <div @class(['sys-row text-sm', 'line-through opacity-60' => $done >= $objective['sets']])>
                    <span>{{ $objective['name'] }} · @if ($quest->type === 'calibration')2 échauffements + 1 série max @else{{ $objective['sets'] }} × {{ $objective['reps_min'] }}–{{ $objective['reps_max'] }}@endif@if (isset($loads[$objective['exercise_id']]) && $quest->type === 'training') · {{ \App\Support\Format::kg($loads[$objective['exercise_id']]) }}{{ in_array($objective['exercise_id'], $perDumbbell, true) ? ' par haltère' : '' }}@endif</span>
                    <span class="sys-num text-ice">{{ $done }} / {{ $objective['sets'] }}</span>
                </div>
            @endforeach
            <p class="sys-num mt-2 text-center text-xs text-mist">Récompense : +{{ $quest->status === 'done' ? $quest->xp_awarded : $reward }} XP · avant minuit</p>
            @if ($quest->status === 'pending')
                <x-sys.button class="mt-3" :pulse="true" :href="$quest->type === 'calibration' ? route('calibration.quest', $quest) : route('workout.quest', $quest)">Accepter la quête</x-sys.button>
            @elseif ($quest->status === 'done')
                <p class="mt-3 text-center font-bold text-ice">QUÊTE ACCOMPLIE ✓</p>
            @endif
        </x-sys.window>
    @elseif ($quest && $quest->type === 'rest')
        <x-sys.window title="QUÊTE DU JOUR" :index="2">
            <p class="text-center text-sm text-mist"><span class="font-bold text-frost">Repos</span> — récupère. TON EGO compte ce jour dans ta série.</p>
        </x-sys.window>
    @elseif (! $user->active_program_id)
        <x-sys.window title="AUCUN PROGRAMME" :index="2">
            <p class="text-sm text-mist">Laisse TON EGO forger ton programme : il t'assignera ensuite une quête chaque jour.</p>
            <x-sys.button class="mt-3" :href="route('program')">Forger mon programme</x-sys.button>
        </x-sys.window>
    @endif

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
