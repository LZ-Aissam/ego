<div class="space-y-5">
    @if ($showQuestionnaire)
        <x-sys.label>Programme</x-sys.label>
        <h1 class="text-2xl font-bold">TON EGO VA FORGER TON PROGRAMME</h1>
        <p class="sys-num text-sm text-mist">Étape {{ $step }} / 5</p>

        {{-- wire:key par étape : Livewire reconstruit les champs au lieu de recycler les boutons radio
             de l'étape précédente (qui garderaient leur ancien wire:model et écraseraient le niveau). --}}
        <div wire:key="program-step-{{ $step }}">
        <x-sys.window :title="['MORPHOLOGIE', 'NIVEAU', 'OBJECTIF', 'JOURS D\'ENTRAÎNEMENT', 'MATÉRIEL'][$step - 1]">
            @if ($step === 1)
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (['male' => 'Homme', 'female' => 'Femme'] as $value => $label)
                            <label @class(['sys-chip cursor-pointer py-3 text-center text-sm', 'sys-chip--active' => $sex === $value])>
                                <input type="radio" wire:model.live="sex" value="{{ $value }}" class="sr-only">{{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('sex') <p class="sys-error">{{ $message }}</p> @enderror
                    <div class="grid grid-cols-2 gap-3">
                        <x-sys.field name="heightCm" label="Taille (cm)" inputmode="numeric" wire:model="heightCm" />
                        <x-sys.field name="weightKg" label="Poids (kg)" inputmode="decimal" wire:model="weightKg" />
                    </div>
                </div>
            @elseif ($step === 2)
                <div class="grid gap-2">
                    @foreach (\App\Services\ProgramBuilder::LEVELS as $value => $label)
                        <label @class(['sys-chip cursor-pointer py-3 text-center', 'sys-chip--active' => $level === $value])>
                            <input type="radio" wire:model.live="level" value="{{ $value }}" class="sr-only">{{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('level') <p class="sys-error">{{ $message }}</p> @enderror
            @elseif ($step === 3)
                <div class="grid gap-2">
                    @foreach (\App\Services\ProgramBuilder::GOALS as $value => $label)
                        <label @class(['sys-chip cursor-pointer py-3 text-center', 'sys-chip--active' => $goal === $value])>
                            <input type="radio" wire:model.live="goal" value="{{ $value }}" class="sr-only">{{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('goal') <p class="sys-error">{{ $message }}</p> @enderror
            @elseif ($step === 4)
                <p class="mb-3 text-sm text-mist">Coche les jours où tu t'entraînes (2 à 6). Les autres seront des jours de repos.</p>
                <div class="grid grid-cols-7 gap-1">
                    @foreach ($days as $value => $label)
                        <label @class(['sys-chip cursor-pointer px-0 py-3 text-center text-xs', 'sys-chip--active' => in_array((int) $value, array_map('intval', $weekdays), true)])>
                            <input type="checkbox" wire:model.live="weekdays" value="{{ $value }}" class="sr-only">{{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('weekdays') <p class="sys-error">{{ $message }}</p> @enderror
            @else
                <div class="grid gap-2">
                    @foreach (\App\Services\ProgramBuilder::EQUIPMENT as $value => $label)
                        <label @class(['sys-chip cursor-pointer py-3 text-center', 'sys-chip--active' => $equipment === $value])>
                            <input type="radio" wire:model.live="equipment" value="{{ $value }}" class="sr-only">{{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('equipment') <p class="sys-error">{{ $message }}</p> @enderror
            @endif
        </x-sys.window>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <x-sys.button variant="ghost" wire:click="back" :disabled="$step === 1">Retour</x-sys.button>
            @if ($step < 5)
                <x-sys.button wire:click="next">Suivant</x-sys.button>
            @else
                <x-sys.button :pulse="true" wire:click="generate" wire:loading.attr="disabled">Forger</x-sys.button>
            @endif
        </div>
    @else
        <x-sys.label>Programme</x-sys.label>
        <h1 class="text-2xl font-bold">{{ $program->name }}</h1>
        <p class="text-sm text-mist">
            {{ \App\Services\ProgramBuilder::LEVELS[$program->params['level']] ?? '' }} · {{ \App\Services\ProgramBuilder::EQUIPMENT[$program->params['equipment']] ?? '' }}
            · {{ collect($program->params['weekdays'] ?? [])->map(fn ($d) => $days[(string) $d])->join(', ') }}
        </p>

        @foreach ($program->days as $day)
            <x-sys.window :title="$day->name" :index="$loop->index">
                @foreach ($day->exercises as $line)
                    <div class="sys-row">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $line->exercise->name }}</p>
                            <p class="sys-num text-xs text-mist">{{ $line->sets }} × {{ $line->reps_min }}–{{ $line->reps_max }}@if (isset($loads[$line->exercise_id])) · <span class="text-ice">{{ \App\Support\Format::kg($loads[$line->exercise_id]) }}{{ $line->exercise->isPerDumbbell() ? ' par haltère' : '' }}</span>@endif · repos {{ $line->rest_seconds }} s</p>
                        </div>
                        <button type="button" class="sys-label shrink-0" wire:click="$set('replacing', {{ $replacing === $line->id ? 'null' : $line->id }})">Remplacer</button>
                    </div>
                    @if ($replacing === $line->id)
                        <ul class="mb-2 border-l border-ice/40 pl-3">
                            @forelse ($alternatives as $alt)
                                <li><button type="button" class="py-1.5 text-left text-sm text-ice" wire:click="replace({{ $line->id }}, {{ $alt->id }})">→ {{ $alt->name }}</button></li>
                            @empty
                                <li class="py-1.5 text-sm text-mist">Aucune alternative avec ton matériel.</li>
                            @endforelse
                        </ul>
                    @endif
                @endforeach
            </x-sys.window>
        @endforeach

        <div class="grid grid-cols-2 gap-3">
            <x-sys.button variant="ghost" :href="route('calibration')">Recalibrer</x-sys.button>
            <x-sys.button variant="ghost" wire:click="regenerate">Régénérer le programme</x-sys.button>
        </div>
    @endif
</div>
