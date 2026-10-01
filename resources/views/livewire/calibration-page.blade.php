<div class="space-y-5">
    <x-sys.label>Calibrage · mouvement {{ $index + 1 }} / {{ count($movements) }}</x-sys.label>
    <h1 class="text-2xl font-bold">TON EGO ÉVALUE TA PUISSANCE</h1>

    @if ($current)
        <x-sys.window :title="$current['name']" wire:key="calibration-{{ $index }}">
            <x-sys.label>Échauffement</x-sys.label>
            @foreach ($current['warmups'] as $i => $warmup)
                <div @class(['sys-row gap-2', 'text-ice' => $warmupsDone[$i] ?? false]) wire:key="warmup-{{ $index }}-{{ $i }}">
                    @if ($current['isBodyweight'])
                        <span class="sys-num text-sm">Poids du corps</span>
                    @else
                        <label class="flex items-center gap-1 text-sm"><input type="text" inputmode="decimal" class="sys-input sys-num !w-16 !py-1 text-center" wire:model="warmupKg.{{ $i }}" aria-label="Charge de l'échauffement {{ $i + 1 }}"> kg</label>
                    @endif
                    <label class="flex items-center gap-1 text-sm">× <input type="text" inputmode="numeric" class="sys-input sys-num !w-14 !py-1 text-center" wire:model="warmupReps.{{ $i }}" aria-label="Répétitions de l'échauffement {{ $i + 1 }}"></label>
                    <button type="button" class="sys-label ml-auto" wire:click="toggleWarmup({{ $i }})">{{ ($warmupsDone[$i] ?? false) ? 'Fait ✓' : 'Valider' }}</button>
                </div>
            @endforeach
            @error('warmups') <p class="sys-error">{{ $message }}</p> @enderror

            <x-sys.label class="mt-5">Série test : le maximum de répétitions</x-sys.label>
            <p class="mb-3 text-sm text-mist">
                @if ($current['isBodyweight'])
                    Sans lest, fais le plus de répétitions possible avec une exécution propre.
                @else
                    Prends une charge que tu penses soulever 5 à 10 fois, puis fais le plus de répétitions possible, proprement.
                @endif
            </p>
            <div class="grid grid-cols-2 gap-3">
                <x-sys.field name="testKg" label="{{ ($current['perDumbbell'] ?? false) ? 'Charge par haltère (kg)' : 'Charge (kg)' }}" inputmode="decimal" wire:model="testKg" />
                <x-sys.field name="testReps" label="Répétitions" inputmode="numeric" wire:model="testReps" />
            </div>
            <x-sys.button class="mt-4" :pulse="true" wire:click="saveTest" wire:loading.attr="disabled">
                {{ $index + 1 < count($movements) ? 'Valider et passer au suivant' : 'Terminer le calibrage' }}
            </x-sys.button>
        </x-sys.window>
    @endif

    @if ($results !== [])
        <x-sys.window title="DÉJÀ MESURÉ" :index="1">
            @foreach ($results as $r)
                <div class="sys-row sys-num text-sm">
                    <span>{{ collect($movements)->firstWhere('exercise_id', $r['exercise_id'])['name'] ?? '' }}</span>
                    <span class="text-ice">{{ \App\Support\Format::kg($r['test'][0]) }} × {{ $r['test'][1] }}</span>
                </div>
            @endforeach
        </x-sys.window>
    @endif
</div>
