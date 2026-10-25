<div class="space-y-4">
    <x-sys.label>Jeu maison</x-sys.label>

    <x-sys.window title="JEU MAISON">
        <p class="text-sm text-mist">Chaque jour, quand tu veux : ton maximum d'affilée. Bats ton dernier score pour gagner de l'XP. Ça ne touche pas à ton programme.</p>
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach (\App\Game\HomeGame::CATALOG as $key => [$label])
                <button type="button" wire:click="toggle('{{ $key }}')" wire:key="chip-{{ $key }}"
                    @class(['sys-chip px-3 py-2', 'sys-chip--active' => in_array($key, $chosen, true)])>{{ $label }}</button>
            @endforeach
        </div>
        @if ($chosen === [])
            <p class="mt-3 text-sm text-amber">Choisis au moins un défi pour commencer.</p>
        @endif
    </x-sys.window>

    @foreach ($board as $index => $card)
        <x-sys.window :title="mb_strtoupper($card['label'])" :index="$index + 1" wire:key="card-{{ $card['key'] }}">
            @php $unit = $card['unit'] === 's' ? ' s' : ''; $max = max(1, collect($card['history'])->filter()->max() ?? 1); @endphp
            <div class="grid grid-cols-2 gap-3 text-center sm:grid-cols-4">
                <div><x-sys.label>Aujourd'hui</x-sys.label><p class="sys-num mt-1 text-lg font-bold">{{ $card['today'] !== null ? $card['today'].$unit : '—' }}</p></div>
                <div><x-sys.label>À battre</x-sys.label><p class="sys-num mt-1 text-lg font-bold text-ice">{{ $card['target'] !== null ? $card['target'].$unit : '—' }}@if ($card['target'] !== null && $card['today'] !== null && $card['today'] >= $card['target']) ✓@endif</p></div>
                <div><x-sys.label>Record</x-sys.label><p class="sys-num mt-1 text-lg font-bold">{{ $card['record'] !== null ? $card['record'].$unit : '—' }}</p></div>
                <div><x-sys.label>Série</x-sys.label><p class="sys-num mt-1 text-lg font-bold">{{ $card['streak'] }} j</p></div>
            </div>

            <div class="mt-3 flex h-12 items-end gap-px" aria-label="Tes {{ \App\Game\HomeGame::HISTORY_DAYS }} derniers jours">
                @foreach ($card['history'] as $date => $value)
                    <div class="flex-1 rounded-t-sm {{ $value === null ? 'bg-steel/20' : 'bg-ice/70' }}" style="height: {{ $value === null ? 4 : max(8, round($value / $max * 100)) }}%" title="{{ $date }} : {{ $value ?? '—' }}"></div>
                @endforeach
            </div>

            @if ($card['pull'] && $freshWorkout)
                <p class="mt-3 text-xs text-amber">Pour un vrai max de tractions, attends au moins 4 h après ta séance (ou fais-le un jour de repos) : tes tirages lourds de demain te remercieront.</p>
            @endif

            <form wire:submit="play('{{ $card['key'] }}')" class="mt-3 flex gap-2">
                <input type="number" inputmode="numeric" min="1" max="1000" class="sys-input flex-1" wire:model="values.{{ $card['key'] }}"
                    placeholder="{{ $card['unit'] === 's' ? 'Secondes tenues' : 'Répétitions d\'affilée' }}" aria-label="Ton score en {{ $card['label'] }}">
                <x-sys.button type="submit" class="!w-auto px-5">Valider</x-sys.button>
            </form>
            @error('values.'.$card['key']) <p class="sys-error">{{ $message }}</p> @enderror
        </x-sys.window>
    @endforeach
</div>
