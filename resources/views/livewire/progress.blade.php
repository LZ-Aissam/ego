<div class="space-y-4">
    <a href="{{ route('records') }}" class="text-sm text-zinc-400">← Records</a>
    <h1 class="text-2xl font-bold">{{ $exercise->name }}</h1>
    @if ($rows->isEmpty())
        <div class="card text-zinc-400">Pas encore de données pour cet exercice.</div>
    @else
        <div class="card">
            <p class="mb-2 text-sm text-zinc-400">Charge max. par séance</p>
            <svg viewBox="0 0 320 140" class="h-40 w-full" role="img" aria-label="Progression de la charge maximale">
                <polyline points="{{ $points }}" fill="none" stroke="#a3e635" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
            </svg>
            <div class="mt-2 flex justify-between text-xs text-zinc-500">
                <span>{{ $rows->first()['date']->translatedFormat('j M') }}</span>
                <span>{{ $rows->last()['date']->translatedFormat('j M') }}</span>
            </div>
        </div>
        <ul class="card divide-y divide-zinc-800 text-sm">
            @foreach ($rows->reverse() as $row)
                <li class="flex justify-between py-2">
                    <span class="text-zinc-400">{{ $row['date']->translatedFormat('D j M') }}</span>
                    <span class="font-semibold">{{ \App\Support\Format::kg($row['kg']) }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
