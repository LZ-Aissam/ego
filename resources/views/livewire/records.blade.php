<div class="space-y-4">
    <h1 class="text-2xl font-bold">Records</h1>
    @forelse ($records as ['record' => $record, 'ratio' => $ratio])
        <a href="{{ route('progress', $record->exercise) }}" class="card flex items-center justify-between active:bg-zinc-800">
            <div>
                <p class="font-semibold">{{ $record->exercise->name }}</p>
                <p class="text-sm text-zinc-400">1RM estimé : {{ \App\Support\Format::kg($record->best_e1rm_kg) }}</p>
            </div>
            <div class="text-right">
                <p class="font-bold text-lime-400">{{ \App\Support\Format::kg($record->max_weight_kg) }} × {{ $record->max_weight_reps }}</p>
                @if ($ratio)
                    <p class="text-xs text-zinc-400">{{ number_format($ratio, 2, ',', ' ') }} × PDC</p>
                @endif
            </div>
        </a>
    @empty
        <div class="card text-zinc-400">Termine une séance pour voir tes records.</div>
    @endforelse
</div>
