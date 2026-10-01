<?php

namespace App\Game;

use App\Models\Exercise;
use App\Models\ProgramExercise;
use App\Models\User;
use App\Services\StartingLoad;
use Illuminate\Support\Collection;

final class LoadPrescriber
{
    public const LOWER_BODY_SLOTS = ['squat', 'hinge', 'lunge', 'quads_iso', 'hamstrings_iso', 'calves'];

    public function __construct(private StartingLoad $startingLoad) {}

    public function startingLoad(float $e1rm, int $repsMax): float
    {
        return max(2.5, floor($e1rm / (1 + ($repsMax + 2) / 30) / 2.5) * 2.5);
    }

    public function derivedLoad(Exercise $exercise, User $user): ?float
    {
        $suggestion = $this->startingLoad->suggest($exercise, $user);
        if ($suggestion === null) {
            return null;
        }

        $factor = min(2.0, max(0.5, $user->power_factor ?? 1.0));

        return max(2.5, round($suggestion * $factor / 2.5) * 2.5);
    }

    /** Première occurrence de chaque exercice du programme actif, avec le reps_max le plus bas de ses occurrences. */
    public function planLines(User $user): Collection
    {
        $program = $user->activeProgram()->with('days.exercises.exercise')->first();
        if (! $program) {
            return collect();
        }

        $lines = $program->days->flatMap->exercises;

        return $lines->groupBy('exercise_id')->map(function (Collection $occurrences) {
            $first = clone $occurrences->first();
            $first->reps_max = $occurrences->min('reps_max');

            return $first;
        });
    }

    public function prescribeProgram(User $user, bool $overwrite = false): void
    {
        $maxes = $user->exerciseMaxes()->pluck('e1rm_kg', 'exercise_id');
        $existing = $user->workingLoads()->pluck('target_kg', 'exercise_id');

        foreach ($this->planLines($user) as $exerciseId => $line) {
            /** @var ProgramExercise $line */
            if ($line->exercise->is_bodyweight || ($existing->has($exerciseId) && ! $overwrite)) {
                continue;
            }

            $target = $maxes->has($exerciseId)
                ? $this->startingLoad((float) $maxes[$exerciseId], $line->reps_max)
                : $this->derivedLoad($line->exercise, $user);

            if ($target === null) {
                continue;
            }

            $user->workingLoads()->updateOrCreate(['exercise_id' => $exerciseId], ['target_kg' => $target, 'fail_streak' => 0]);
        }
    }
}
