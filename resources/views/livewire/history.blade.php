<div class="space-y-4">
    <h1 class="text-2xl font-bold">Historique</h1>
    @forelse ($workouts as $workout)
        <a href="{{ route('history.show', $workout) }}" class="card block active:bg-zinc-800">
            <p class="font-semibold">{{ ucfirst($workout->finished_at->translatedFormat('l j F')) }}</p>
            <p class="mt-1 text-sm text-zinc-400">
                {{ \App\Support\Format::duration($workout->durationMinutes()) }}
                · {{ $workout->sets->count() }} séries
                · {{ \App\Support\Format::kg($workout->volume()) }}
            </p>
            <p class="mt-1 truncate text-sm text-zinc-500">{{ $workout->sets->pluck('exercise.name')->unique()->join(', ') }}</p>
        </a>
    @empty
        <div class="card text-zinc-400">Aucune séance pour l'instant.</div>
    @endforelse
    {{ $workouts->links() }}
</div>
