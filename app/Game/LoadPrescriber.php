<?php

namespace App\Game;

use App\Models\Exercise;
use App\Models\ProgramExercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use App\Services\StartingLoad;
use Illuminate\Support\Collection;

final class LoadPrescriber
{
    /** Isolation sous ce seuil : la charge monte de 1 kg, pas de 2,5 kg. */
    public const SMALL_LOAD_KG = 20.0;

    public const LOWER_BODY_SLOTS = ['squat', 'hinge', 'lunge', 'quads_iso', 'hamstrings_iso', 'calves', 'jump'];

    public function __construct(private StartingLoad $startingLoad) {}

    public function startingLoad(float $e1rm, int $repsMax): float
    {
        return max(2.5, floor($e1rm / (1 + ($repsMax + 2) / 30) / 2.5) * 2.5);
    }

    /** @param  list<array{slots: list<string>, ratio: float}>|null  $measured  facteurs mesurés (calculés si absents) */
    public function derivedLoad(Exercise $exercise, User $user, ?array $measured = null): ?float
    {
        $suggestion = $this->startingLoad->suggest($exercise, $user);
        if ($suggestion === null) {
            return null;
        }

        $factor = $this->factorFor($exercise, $measured ?? $this->measuredFactors($user)) ?? $user->power_factor ?? 1.0;
        $factor = min(2.0, max(0.5, $factor));

        return max(2.5, round($suggestion * $factor / 2.5) * 2.5);
    }

    /**
     * Rapport charge mesurée / suggestion de chaque exercice testé.
     *
     * @return list<array{slots: list<string>, ratio: float}>
     */
    public function measuredFactors(User $user): array
    {
        $repsMax = $this->planLines($user)->map(fn (ProgramExercise $line) => $line->reps_max);

        return $user->exerciseMaxes()->with('exercise')->get()->map(function ($max) use ($user, $repsMax) {
            $suggestion = $this->startingLoad->suggest($max->exercise, $user);

            return $suggestion === null ? null : [
                'slots' => $max->exercise->slots ?? [],
                'ratio' => $this->startingLoad((float) $max->e1rm_kg, $repsMax[$max->exercise_id] ?? 10) / $suggestion,
            ];
        })->filter()->values()->all();
    }

    /** Facteur des exercices testés du même emplacement, sinon de la même région (haut / bas du corps). */
    private function factorFor(Exercise $exercise, array $measured): ?float
    {
        $slots = $exercise->slots ?? [];
        $same = array_filter($measured, fn (array $m) => array_intersect($m['slots'], $slots) !== []);

        if ($same === []) {
            $lower = $this->isLowerBody($slots);
            $same = array_filter($measured, fn (array $m) => $this->isLowerBody($m['slots']) === $lower);
        }

        return $same === [] ? null : array_sum(array_column($same, 'ratio')) / count($same);
    }

    private function isLowerBody(array $slots): bool
    {
        return array_intersect($slots, self::LOWER_BODY_SLOTS) !== [];
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

    /** Meilleur 1RM estimé : calibrage ou record ; sinon charge déduite rapportée à un 1RM (Epley sur repsMax). */
    public function bestE1rm(User $user, Exercise $exercise, int $repsMax): ?float
    {
        $calibrated = $user->exerciseMaxes()->where('exercise_id', $exercise->id)->value('e1rm_kg');
        $record = $user->personalRecords()->where('exercise_id', $exercise->id)->value('best_e1rm_kg');
        $best = max((float) $calibrated, (float) $record);
        if ($best > 0) {
            return round($best, 1);
        }

        $derived = $this->derivedLoad($exercise, $user);

        return $derived === null ? null : round($derived * (1 + $repsMax / 30), 1);
    }

    /** Charge prescrite pour une ligne de programme selon son intention (null : rien à prescrire). */
    public function prescriptionFor(User $user, Exercise $exercise, ?string $intent, int $repsMax): ?float
    {
        if ($exercise->is_bodyweight) {
            return null;
        }

        $pct = \App\Game\Intent::META[$intent]['pct'] ?? null;
        if ($pct === null) {
            $stored = $user->workingLoads()->where('exercise_id', $exercise->id)->value('target_kg');

            return $stored === null ? null : (float) $stored;
        }

        $best = $this->bestE1rm($user, $exercise, $repsMax);
        if ($best === null) {
            return null;
        }

        $value = $best * $pct;
        $rounded = $intent === 'max' ? floor($value / 2.5) * 2.5 : round($value / 2.5) * 2.5;

        return max(2.5, $rounded);
    }

    /** Lignes dont la charge est stockée et progresse par les répétitions : intention « masse » ou aucune. */
    public function loadLines(User $user): Collection
    {
        $program = $user->activeProgram()->with('days.exercises.exercise')->first();
        if (! $program) {
            return collect();
        }

        return $program->days->flatMap->exercises
            ->filter(fn (ProgramExercise $l) => $l->intent === null || $l->intent === 'mass')
            ->groupBy('exercise_id')->map(function (Collection $occurrences) {
                $first = clone $occurrences->first();
                $first->reps_max = $occurrences->min('reps_max');

                return $first;
            });
    }

    /** @param  list<int>  $overwrite  exercices dont la charge est recalculée même si elle existe déjà */
    public function prescribeProgram(User $user, array $overwrite = []): void
    {
        $maxes = $user->exerciseMaxes()->pluck('e1rm_kg', 'exercise_id');
        $existing = $user->workingLoads()->pluck('target_kg', 'exercise_id');
        $measured = $this->measuredFactors($user);

        foreach ($this->loadLines($user) as $exerciseId => $line) {
            /** @var ProgramExercise $line */
            if ($line->exercise->is_bodyweight || ($existing->has($exerciseId) && ! in_array($exerciseId, $overwrite, true))) {
                continue;
            }

            $target = $maxes->has($exerciseId)
                ? $this->startingLoad((float) $maxes[$exerciseId], $line->reps_max)
                : $this->derivedLoad($line->exercise, $user, $measured);

            if ($target === null) {
                continue;
            }

            $user->workingLoads()->updateOrCreate(['exercise_id' => $exerciseId], ['target_kg' => $target, 'fail_streak' => 0]);
        }
    }

    /** Double progression après une séance. @return list<array> notifications */
    public function progress(User $user, Workout $workout): array
    {
        $plan = $this->loadLines($user);
        if ($plan->isEmpty()) {
            return [];
        }

        $loads = $user->workingLoads()->get()->keyBy('exercise_id');
        $notes = [];

        foreach ($workout->sets->groupBy('exercise_id') as $exerciseId => $sets) {
            $line = $plan[$exerciseId] ?? null;
            if (! $line) {
                continue;
            }

            if ($line->exercise->is_bodyweight) {
                if ($sets->count() >= $line->sets && $sets->every(fn (WorkoutSet $s) => $s->reps >= $line->reps_max + 5)) {
                    $notes[] = \App\Support\SystemMessage::addWeight($line->exercise->name);
                }
                continue;
            }

            $load = $loads[$exerciseId] ?? null;
            if (! $load) {
                continue;
            }

            $small = $line->exercise->kind === 'isolation' && $load->target_kg < self::SMALL_LOAD_KG;
            $kept = $sets->filter(fn (WorkoutSet $s) => $s->weight_kg + 0.001 >= $load->target_kg);
            // Tout fait plus léger que la cible : la charge est trop lourde, cela compte comme un échec.
            $failed = $kept->isEmpty() || $kept->contains(fn (WorkoutSet $s) => $s->reps < $line->reps_min);

            if ($failed) {
                $load->fail_streak += 1;
                if ($load->fail_streak >= 2) {
                    $step = $small ? 1.0 : 2.5;
                    $target = round($load->target_kg * 0.9 / $step) * $step;
                    if ($kept->isEmpty()) {
                        $target = min($target, floor($sets->max('weight_kg') / $step) * $step);
                    }
                    $load->target_kg = max($step, $target);
                    $load->fail_streak = 0;
                    $notes[] = \App\Support\SystemMessage::deload($line->exercise->name, $load->target_kg);
                }
            } elseif ($kept->count() >= $line->sets && $kept->every(fn (WorkoutSet $s) => $s->reps >= $line->reps_max)) {
                $load->target_kg += $small ? 1.0 : (in_array($line->slot, self::LOWER_BODY_SLOTS, true) ? 5.0 : 2.5);
                $load->fail_streak = 0;
                $notes[] = \App\Support\SystemMessage::loadUp($line->exercise->name, $load->target_kg);
            } else {
                $load->fail_streak = 0;
            }

            $load->save();
        }

        return $notes;
    }
}
