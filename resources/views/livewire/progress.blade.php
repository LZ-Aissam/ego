<div class="space-y-4">
    <a href="{{ route('records') }}" class="sys-label">Records</a>
    <h1 class="text-2xl font-bold">{{ $exercise->name }}</h1>
    @if ($rows->isEmpty())
        <x-sys.window><p class="text-mist">Pas encore de données pour cet exercice.</p></x-sys.window>
    @else
        <x-sys.window title="CHARGE MAX. PAR SÉANCE">
            <svg viewBox="0 0 320 140" class="h-44 w-full overflow-visible" role="img" aria-label="Progression de la charge maximale">
                <defs>
                    <filter id="neon" x="-20%" y="-20%" width="140%" height="140%"><feGaussianBlur stdDeviation="3" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
                </defs>
                <polyline points="{{ $points }}" fill="none" stroke="#5ad8ff" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" filter="url(#neon)"/>
                @foreach (explode(' ', $points) as $point)
                    @php [$cx, $cy] = explode(',', $point); @endphp
                    <circle cx="{{ $cx }}" cy="{{ $cy }}" r="3.5" fill="#04060a" stroke="#5ad8ff" stroke-width="2"/>
                @endforeach
            </svg>
            <div class="sys-num mt-2 flex justify-between text-xs text-steel">
                <span>{{ $rows->first()['date']->translatedFormat('j M') }}</span>
                <span>{{ $rows->last()['date']->translatedFormat('j M') }}</span>
            </div>
        </x-sys.window>
        <x-sys.window :index="1">
            @foreach ($rows->reverse() as $row)
                <div class="sys-row text-sm">
                    <span class="text-steel">{{ $row['date']->translatedFormat('D j M') }}</span>
                    <span class="sys-num font-bold">{{ \App\Support\Format::kg($row['kg']) }}</span>
                </div>
            @endforeach
        </x-sys.window>
    @endif
</div>
