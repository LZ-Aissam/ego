<div class="space-y-4">
    <x-sys.label>Historique</x-sys.label>
    <x-sys.window title="JOURNAL DES SÉANCES">
        @forelse ($workouts as $workout)
            <a href="{{ route('history.show', $workout) }}" class="sys-row">
                <div class="min-w-0">
                    <p class="font-semibold">{{ ucfirst($workout->finished_at->translatedFormat('l j F')) }}</p>
                    <p class="truncate text-sm text-steel">{{ $workout->sets->pluck('exercise.name')->unique()->join(', ') }}</p>
                </div>
                <div class="shrink-0 text-right">
                    <p class="sys-num font-bold text-ice">{{ \App\Support\Format::kg($workout->volume()) }}</p>
                    <p class="sys-num text-xs text-steel">{{ \App\Support\Format::duration($workout->durationMinutes()) }} · {{ $workout->sets->count() }} séries</p>
                </div>
            </a>
        @empty
            <p class="text-mist">Aucune séance pour l'instant.</p>
        @endforelse
    </x-sys.window>
    {{ $workouts->links() }}
</div>
