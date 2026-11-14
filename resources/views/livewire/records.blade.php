<div class="space-y-4">
    <x-sys.label>Records</x-sys.label>

    {{-- Force relative : max estimé ÷ poids du corps, rang E → S par grand mouvement --}}
    <x-sys.window title="FORCE RELATIVE">
        <p class="mb-2 text-xs text-mist">Max estimé ÷ ton poids{{ $ranks && auth()->user()->weight_kg ? ' ('.\App\Support\Format::kg((float) auth()->user()->weight_kg).')' : '' }}. Tractions et dips : poids du corps compris. Repères EGO.</p>
        @foreach ($ranks as $lift)
            <div class="border-b border-white/[.06] py-2.5 last:border-0">
                <div class="flex items-baseline justify-between gap-3">
                    <p class="font-semibold">{{ $lift['name'] }}</p>
                    <p class="sys-num text-lg font-extrabold {{ $lift['rankIndex'] >= 4 ? 'text-amber' : 'text-ice' }}">{{ $lift['rank'] }}</p>
                </div>
                @if ($lift['e1rm'] !== null)
                    <p class="sys-num text-xs text-steel">
                        {{ \App\Support\Format::kg($lift['e1rm']) }} estimés · {{ number_format($lift['ratio'], 2, ',', ' ') }} × PDC
                        @if ($lift['perWeek'] !== null) · {{ $lift['perWeek'] >= 0 ? '+' : '−' }}{{ \App\Support\Format::kg(abs($lift['perWeek'])) }}/sem. @endif
                    </p>
                    @if ($lift['next'])
                        <div class="mt-1.5 h-1.5 bg-white/[.06]"><div class="h-full bg-ice shadow-[0_0_8px_var(--glow-ice)]" style="width: {{ $lift['progress'] }}%"></div></div>
                        <p class="sys-num mt-1 text-xs text-mist">
                            Rang {{ $lift['next']['rank'] }} à {{ \App\Support\Format::kg($lift['next']['kg']) }}
                            @if ($lift['weeksToNext']) · au rythme actuel, dans ~{{ $lift['weeksToNext'] }} sem. @endif
                        </p>
                    @endif
                @else
                    <p class="text-xs text-mist">Pas encore mesuré.</p>
                @endif
            </div>
        @endforeach
    </x-sys.window>

    @if ($weights->isNotEmpty())
        <x-sys.window title="POIDS DU CORPS">
            <div class="flex items-baseline justify-between">
                <p class="sys-num text-lg font-bold text-ice">{{ \App\Support\Format::kg($weights->last()->kg) }}</p>
                @if ($weightDelta !== null)
                    <p class="sys-num text-sm {{ $weightDelta > 0.5 ? 'text-amber' : 'text-steel' }}">{{ $weightDelta >= 0 ? '+' : '−' }}{{ \App\Support\Format::kg(abs($weightDelta)) }} en 3 semaines</p>
                @endif
            </div>
            @if ($weightPoints)
                <svg viewBox="0 0 320 80" class="mt-2 h-20 w-full overflow-visible" role="img" aria-label="Évolution du poids du corps">
                    <polyline points="{{ $weightPoints }}" fill="none" stroke="#5ad8ff" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
                </svg>
            @endif
            <p class="mt-1 text-xs text-mist">Force pure : le poids reste stable pendant que les rangs montent.</p>
        </x-sys.window>
    @endif

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
