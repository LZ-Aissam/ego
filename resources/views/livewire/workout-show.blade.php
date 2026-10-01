<div class="space-y-4">
    <a href="{{ route('history') }}" class="text-sm text-zinc-400">← Historique</a>
    <h1 class="text-2xl font-bold">{{ ucfirst($workout->finished_at->translatedFormat('l j F Y')) }}</h1>
    <p class="text-sm text-zinc-400">
        {{ \App\Support\Format::duration($workout->durationMinutes()) }} · {{ \App\Support\Format::kg($workout->volume()) }}
    </p>
    @foreach ($groups as $sets)
        <div class="card">
            <a href="{{ route('progress', $sets->first()->exercise) }}" class="font-semibold">{{ $sets->first()->exercise->name }}</a>
            <ol class="mt-2 space-y-1 text-sm">
                @foreach ($sets as $set)
                    <li class="flex justify-between">
                        <span class="text-zinc-400">Série {{ $loop->iteration }}</span>
                        <span>{{ \App\Support\Format::kg($set->weight_kg) }} × {{ $set->reps }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    @endforeach
</div>
