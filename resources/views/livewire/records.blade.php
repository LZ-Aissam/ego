<div class="space-y-4">
    <x-sys.label>Records</x-sys.label>
    <x-sys.window title="TABLEAU DES RECORDS">
        @forelse ($records as ['record' => $record, 'ratio' => $ratio])
            <a href="{{ route('progress', $record->exercise) }}" class="sys-row">
                <div>
                    <p class="font-semibold">{{ $record->exercise->name }}</p>
                    <p class="sys-num text-xs text-steel">1RM estimé : {{ \App\Support\Format::kg($record->best_e1rm_kg) }}</p>
                </div>
                <div class="text-right">
                    <p class="sys-num font-bold text-ice">{{ \App\Support\Format::kg($record->max_weight_kg) }} × {{ $record->max_weight_reps }}</p>
                    @if ($ratio)
                        <p class="sys-num text-xs text-steel">{{ number_format($ratio, 2, ',', ' ') }} × PDC</p>
                    @endif
                </div>
            </a>
        @empty
            <p class="text-mist">Termine une séance pour voir tes records.</p>
        @endforelse
    </x-sys.window>
</div>
