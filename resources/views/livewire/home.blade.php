<div class="space-y-6">
    <h1 class="text-2xl font-bold">Salut {{ $user->first_name }} 👋</h1>

    <a href="{{ route('workout') }}" class="btn-primary py-5 text-lg">Séance libre</a>

    @if (! $user->sex || ! $user->weight_kg)
        <a href="{{ route('profile') }}" class="card block border-amber-500/40 text-sm text-amber-300">
            Complète ton profil (sexe, taille, poids) pour obtenir des charges de départ suggérées →
        </a>
    @endif

    @if ($lastWorkout)
        <a href="{{ route('history.show', $lastWorkout) }}" class="card block active:bg-zinc-800">
            <p class="text-xs uppercase tracking-wide text-zinc-400">Dernière séance</p>
            <p class="mt-1 font-semibold">{{ ucfirst($lastWorkout->finished_at->translatedFormat('l j F')) }}</p>
            <p class="text-sm text-zinc-400">
                {{ \App\Support\Format::duration($lastWorkout->durationMinutes()) }}
                · {{ $lastWorkout->sets->count() }} séries
                · {{ \App\Support\Format::kg($lastWorkout->volume()) }}
            </p>
        </a>
    @endif

    <section>
        <div class="mb-2 flex items-center justify-between">
            <h2 class="font-bold">Derniers records</h2>
            <a href="{{ route('records') }}" class="text-sm text-lime-400">Tout voir</a>
        </div>
        @forelse ($recentRecords as $record)
            <div class="card mb-2 flex justify-between">
                <span>{{ $record->exercise->name }}</span>
                <span class="font-bold text-lime-400">{{ \App\Support\Format::kg($record->max_weight_kg) }} × {{ $record->max_weight_reps }}</span>
            </div>
        @empty
            <p class="text-sm text-zinc-400">Tes records apparaîtront ici après ta première séance.</p>
        @endforelse
    </section>
</div>
