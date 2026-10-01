<div class="space-y-4">
    <a href="{{ route('history') }}" class="sys-label">Historique</a>
    <h1 class="text-2xl font-bold">{{ ucfirst($workout->finished_at->translatedFormat('l j F Y')) }}</h1>
    <div class="grid grid-cols-2 gap-3">
        <x-sys.window :index="0"><x-sys.label>Durée</x-sys.label><p class="sys-num mt-1 text-xl font-bold">{{ \App\Support\Format::duration($workout->durationMinutes()) }}</p></x-sys.window>
        <x-sys.window :index="1"><x-sys.label>Volume</x-sys.label><p class="sys-num mt-1 text-xl font-bold text-ice" data-count-up="{{ $workout->volume() }}" data-decimals="{{ fmod($workout->volume(), 1.0) === 0.0 ? 0 : 1 }}" data-suffix=" kg">{{ \App\Support\Format::kg($workout->volume()) }}</p></x-sys.window>
    </div>
    @foreach ($groups as $sets)
        <x-sys.window :index="$loop->index + 2">
            <div class="flex items-baseline justify-between gap-3">
                <a href="{{ route('progress', $sets->first()->exercise) }}" class="font-semibold">{{ $sets->first()->exercise->name }} <span class="text-ice">→</span></a>
                @php $delta = $deltas[$sets->first()->exercise_id] ?? null; @endphp
                <span @class(['sys-num shrink-0 text-xs', 'text-ice font-bold' => $delta && str_starts_with($delta, '+'), 'text-steel' => ! $delta || ! str_starts_with($delta, '+')])
                      title="Comparé à la séance précédente">{{ $delta ?? 'Première fois' }}</span>
            </div>
            <ol class="mt-2">
                @foreach ($sets as $set)
                    <li class="sys-row sys-num text-sm">
                        <span class="text-steel">SÉRIE {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="font-bold">{{ \App\Support\Format::kg($set->weight_kg) }} × {{ $set->reps }}</span>
                    </li>
                @endforeach
            </ol>
        </x-sys.window>
    @endforeach
</div>
