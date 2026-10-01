<div class="space-y-4">
    <a href="{{ route('records') }}" class="sys-label">Records</a>
    <h1 class="text-2xl font-bold">{{ $exercise->name }}</h1>

    @if ($summary)
        <div class="grid grid-cols-3 gap-3">
            <x-sys.window :index="0" class="!p-3">
                <x-sys.label>Meilleure charge</x-sys.label>
                <p class="sys-num mt-1 text-lg font-bold text-ice">{{ \App\Support\Format::kg($summary['best_kg']) }}</p>
            </x-sys.window>
            <x-sys.window :index="1" class="!p-3">
                <x-sys.label>Depuis le début</x-sys.label>
                <p class="sys-num mt-1 text-lg font-bold">{{ $summary['gain_kg'] >= 0 ? '+' : '−' }}{{ \App\Support\Format::kg(abs($summary['gain_kg'])) }}</p>
                <p class="sys-num text-xs text-mist">{{ $summary['gain_pct'] >= 0 ? '+' : '−' }}{{ abs($summary['gain_pct']) }} %</p>
            </x-sys.window>
            <x-sys.window :index="2" class="!p-3">
                <x-sys.label>4 semaines</x-sys.label>
                <p class="sys-num mt-1 text-lg font-bold">
                    {{ ['up' => '↗ En hausse', 'flat' => '→ Stable', 'down' => '↘ En baisse', 'unknown' => '—'][$summary['trend']] }}
                </p>
            </x-sys.window>
        </div>
    @endif

    @if ($rows->isEmpty())
        <x-sys.window><p class="text-mist">Pas encore de données pour cet exercice sur cette période.</p></x-sys.window>
    @endif

    <x-sys.window :title="mb_strtoupper(\App\Livewire\Progress::METRICS[$metric]).' PAR SÉANCE'" :index="3">
        <div class="mb-3 flex flex-wrap gap-2">
            @foreach (\App\Livewire\Progress::METRICS as $key => $label)
                <button type="button" wire:click="$set('metric', '{{ $key }}')" @class(['sys-chip', 'sys-chip--active' => $metric === $key])>{{ $label }}</button>
            @endforeach
        </div>
        <div class="mb-3 flex flex-wrap gap-2">
            @foreach (\App\Livewire\Progress::PERIODS as $key => $label)
                <button type="button" wire:click="$set('period', '{{ $key }}')" @class(['sys-chip', 'sys-chip--active' => $period === $key])>{{ $label }}</button>
            @endforeach
        </div>
        @if ($points !== [])
            <div class="sys-num flex justify-between text-xs text-steel">
                <span>max {{ \App\Support\Format::kg($maxValue) }}</span><span>min {{ \App\Support\Format::kg($minValue) }}</span>
            </div>
            <svg viewBox="0 0 320 140" class="h-44 w-full overflow-visible" role="img" aria-label="{{ \App\Livewire\Progress::METRICS[$metric] }} par séance">
                <defs>
                    <filter id="neon" x="-20%" y="-20%" width="140%" height="140%"><feGaussianBlur stdDeviation="3" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
                </defs>
                <line x1="0" y1="132" x2="320" y2="132" stroke="#3d6bff" stroke-opacity=".3"/>
                <polyline points="{{ implode(' ', $points) }}" fill="none" stroke="#5ad8ff" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" filter="url(#neon)"/>
                @foreach ($points as $i => $point)
                    @php [$cx, $cy] = explode(',', $point); $record = $rows[$i]['record']; @endphp
                    <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $record ? 5 : 3.5 }}" fill="{{ $record ? '#ffb547' : '#04060a' }}" stroke="{{ $record ? '#ffb547' : '#5ad8ff' }}" stroke-width="2"/>
                @endforeach
            </svg>
            <div class="sys-num mt-2 flex justify-between text-xs text-steel">
                <span>{{ $rows->first()['date']->translatedFormat('j M Y') }}</span>
                <span class="text-amber">● record</span>
                <span>{{ $rows->last()['date']->translatedFormat('j M Y') }}</span>
            </div>
        @endif
    </x-sys.window>

    @if ($rows->isNotEmpty())
        <x-sys.window :index="4">
            @foreach ($rows->reverse() as $row)
                <div class="sys-row text-sm">
                    <span class="text-steel">{{ $row['date']->translatedFormat('D j M') }} @if ($row['record'])<span class="sys-label !text-amber">record</span>@endif</span>
                    <span class="sys-num font-bold">{{ \App\Support\Format::kg($row[$metric]) }}</span>
                </div>
            @endforeach
        </x-sys.window>
    @endif
</div>
