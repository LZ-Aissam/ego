<?php

namespace App\Livewire;

use App\Models\Exercise;
use App\Models\ProgramExercise;
use App\Services\ProgramBuilder;
use App\Services\ProgramGenerator;
use App\Support\SystemMessage;
use App\Support\SystemNotifier;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Programme')]
class ProgramPage extends Component
{
    public int $step = 1;

    public bool $editing = false;

    public ?string $sex = null;

    public $heightCm = null;

    public $weightKg = null;

    public ?string $level = null;

    public ?string $goal = null;

    public array $weekdays = [];

    public ?string $equipment = null;

    public ?int $replacing = null;

    public function mount(): void
    {
        $user = auth()->user();
        $this->sex = $user->sex;
        $this->heightCm = $user->height_cm;
        $this->weightKg = $user->weight_kg;
        $this->level = $user->level;
        $this->goal = $user->goal;
        $this->weekdays = $user->training_weekdays ?? [];
        $this->equipment = $user->equipment;
    }

    public function next(): void
    {
        $this->validateStep($this->step);
        $this->step = min(5, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function regenerate(): void
    {
        $this->editing = true;
        $this->step = 1;
    }

    public function generate()
    {
        $builder = app(ProgramBuilder::class);
        $notifier = app(SystemNotifier::class);
        foreach (range(1, 5) as $step) {
            $this->validateStep($step);
        }

        $user = auth()->user();
        $user->forceFill([
            'sex' => $this->sex,
            'height_cm' => (int) $this->heightCm,
            'weight_kg' => (float) str_replace(',', '.', (string) $this->weightKg),
        ])->save();

        $program = $builder->build($user, [
            'level' => $this->level, 'goal' => $this->goal, 'weekdays' => $this->weekdays, 'equipment' => $this->equipment,
        ]);

        $notifier->push($user, SystemMessage::programForged($program->name));

        return $this->redirectRoute('program');
    }

    public function replace(int $programExerciseId, int $exerciseId): void
    {
        $generator = app(ProgramGenerator::class);
        $user = auth()->user();
        $line = ProgramExercise::with('day.program')->find($programExerciseId);

        if (! $line || $line->day->program->id !== $user->active_program_id) {
            return;
        }

        $allowed = $generator->alternativesFor($line->slot, $user->equipment ?? 'gym', Exercise::all(), $line->exercise_id)->pluck('id');
        if ($allowed->contains($exerciseId)) {
            $line->update(['exercise_id' => $exerciseId]);
        }

        $this->replacing = null;
    }

    private function validateStep(int $step): void
    {
        if ($step === 1 && is_string($this->weightKg)) {
            $this->weightKg = str_replace(',', '.', $this->weightKg);
        }

        $rules = match ($step) {
            1 => ['sex' => ['required', 'in:male,female'], 'heightCm' => ['required', 'integer', 'between:100,250'], 'weightKg' => ['required', 'numeric', 'between:30,300']],
            2 => ['level' => ['required', 'in:beginner,intermediate,advanced']],
            3 => ['goal' => ['required', 'in:strength,hypertrophy,fitness']],
            4 => ['weekdays' => ['required', 'array', 'min:2', 'max:6'], 'weekdays.*' => ['integer', 'between:1,7', 'distinct']],
            5 => ['equipment' => ['required', 'in:gym,dumbbells,bodyweight']],
        };

        try {
            $this->validate($rules, [
                'sex.required' => 'Choisis homme ou femme.',
                'sex.in' => 'Choisis homme ou femme.',
                'heightCm.*' => 'Indique ta taille en cm (100 à 250).',
                'weightKg.*' => 'Indique ton poids en kg (30 à 300).',
                'level.required' => 'Choisis ton niveau.',
                'level.in' => 'Choisis ton niveau.',
                'goal.required' => 'Choisis ton objectif.',
                'goal.in' => 'Choisis ton objectif.',
                'weekdays.required' => 'Coche tes jours d\'entraînement.',
                'weekdays.min' => 'Coche au moins 2 jours.',
                'weekdays.max' => 'Coche au plus 6 jours : garde au moins un jour de repos.',
                'weekdays.*.*' => 'Jour invalide.',
                'equipment.required' => 'Choisis ton matériel.',
                'equipment.in' => 'Choisis ton matériel.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->step = $step;
            throw $e;
        }
    }

    public function render(ProgramGenerator $generator)
    {
        $user = auth()->user();
        $program = $user->activeProgram()->with('days.exercises.exercise')->first();
        $alternatives = [];

        if ($program && $this->replacing) {
            $line = $program->days->flatMap->exercises->firstWhere('id', $this->replacing);
            if ($line) {
                $alternatives = $generator->alternativesFor($line->slot, $user->equipment ?? 'gym', Exercise::all(), $line->exercise_id);
            }
        }

        return view('livewire.program-page', [
            'program' => $program,
            'showQuestionnaire' => ! $program || $this->editing,
            'alternatives' => $alternatives,
            'days' => ['1' => 'Lun', '2' => 'Mar', '3' => 'Mer', '4' => 'Jeu', '5' => 'Ven', '6' => 'Sam', '7' => 'Dim'],
        ]);
    }
}
