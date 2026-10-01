<?php

namespace App\Game;

use App\Models\Exercise;
use App\Models\User;
use App\Services\ProgramGenerator;
use App\Services\StartingLoad;
use Illuminate\Support\Collection;

final class CalibrationPlan
{
    /** Emplacements testés, par ordre de priorité ; chaque groupe donne au plus un mouvement. */
    private const GROUPS = [['horizontal_push'], ['horizontal_pull', 'vertical_pull'], ['squat', 'lunge'], ['hinge']];

    public const DEFAULT_ESTIMATE = 20.0;

    public function __construct(private ProgramGenerator $generator, private StartingLoad $startingLoad) {}

    public function movementsFor(User $user): Collection
    {
        $program = $user->activeProgram()->with('days.exercises.exercise')->first();
        $lines = $program ? $program->days->flatMap->exercises : collect();
        $equipment = $user->equipment ?? 'gym';
        $withHinge = in_array($user->level, ['intermediate', 'advanced'], true) && in_array($equipment, ['gym', 'dumbbells'], true);
        $library = Exercise::all();
        $picked = collect();

        foreach (self::GROUPS as $slots) {
            if ($slots === ['hinge'] && ! $withHinge) {
                continue;
            }
            // Un programme sans ce type de mouvement (ex. haut du corps seulement) : on ne le teste pas.
            if ($program && ! $lines->contains(fn ($line) => in_array($line->slot, $slots, true))) {
                continue;
            }

            foreach ($slots as $slot) {
                $exercise = $lines->firstWhere('slot', $slot)?->exercise ?? $this->generator->candidates($slot, $equipment, $library)->first();
                if ($exercise && ! $picked->contains('id', $exercise->id)) {
                    $exercise->setAttribute('test_slot', $slot);
                    $picked->push($exercise);
                    break;
                }
            }
        }

        return $picked->values();
    }

    public function plan(User $user): array
    {
        return $this->movementsFor($user)->map(function (Exercise $exercise) use ($user) {
            if ($exercise->is_bodyweight) {
                return ['exercise_id' => $exercise->id, 'slug' => $exercise->slug, 'name' => $exercise->name, 'isBodyweight' => true, 'estimate' => null, 'warmups' => [[0.0, 5], [0.0, 3]]];
            }

            $estimate = $this->startingLoad->suggest($exercise, $user) ?? self::DEFAULT_ESTIMATE;

            return [
                'exercise_id' => $exercise->id,
                'slug' => $exercise->slug,
                'name' => $exercise->name,
                'isBodyweight' => false,
                'perDumbbell' => $exercise->isPerDumbbell(),
                'estimate' => (float) $estimate,
                'warmups' => [[round($estimate * 0.5 / 2.5) * 2.5, 8], [round($estimate * 0.7 / 2.5) * 2.5, 4]],
            ];
        })->values()->all();
    }

    public function objectives(User $user): array
    {
        return $this->movementsFor($user)->map(fn (Exercise $e) => [
            'exercise_id' => $e->id, 'name' => $e->name, 'slot' => $e->test_slot,
            'sets' => 3, 'reps_min' => 1, 'reps_max' => 30, 'rest_seconds' => 120,
        ])->values()->all();
    }
}
