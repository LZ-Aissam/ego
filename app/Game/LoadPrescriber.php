<?php

namespace App\Game;

use App\Models\Exercise;
use App\Models\ProgramExercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use App\Services\StartingLoad;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class LoadPrescriber
{
    /** Isolation sous ce seuil : la charge monte de 1 kg, pas de 2,5 kg. */
    public const SMALL_LOAD_KG = 20.0;

    /** Force max : autant de répétitions au-delà du haut de la plage, et la charge est clairement trop légère. */
    public const EXCEEDED_REPS = 3;

    public const LOWER_BODY_SLOTS = ['squat', 'hinge', 'lunge', 'quads_iso', 'hamstrings_iso', 'calves', 'jump'];

    /** Au poids du corps, deux prises d'un même mouvement : la mesure de l'une sert à l'autre tant qu'elle n'a pas la sienne. */
    public const BODYWEIGHT_TWINS = ['pull-up' => 'chin-up', 'chin-up' => 'pull-up'];

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

    /**
     * Meilleur 1RM estimé : calibrage ou record ; sinon charge déduite rapportée à un 1RM (Epley sur repsMax).
     * Réglé par les séries record (source « top »), il suit la forme du moment, à la hausse comme à la baisse.
     */
    public function bestE1rm(User $user, Exercise $exercise, int $repsMax): ?float
    {
        $max = $user->exerciseMaxes()->where('exercise_id', $exercise->id)->first(['e1rm_kg', 'source']);
        if (in_array($max?->source, ['top', 'wave'], true)) {
            return round((float) $max->e1rm_kg, 1);
        }
        $calibrated = (float) $max?->e1rm_kg;
        $record = (float) $user->personalRecords()->where('exercise_id', $exercise->id)->value('best_e1rm_kg');
        if ($calibrated <= 0 && $record <= 0 && $twin = self::BODYWEIGHT_TWINS[$exercise->slug] ?? null) {
            $calibrated = (float) $user->exerciseMaxes()->whereHas('exercise', fn ($q) => $q->where('slug', $twin))->value('e1rm_kg');
        }
        if ($calibrated > 0) {
            return round(max($calibrated, $record), 1);
        }

        // Sans calibrage : une séance légère ne doit pas faire chuter l'estimation déduite.
        $derived = $this->derivedLoad($exercise, $user);
        $estimate = $derived === null ? 0.0 : $derived * (1 + $repsMax / 30);
        $best = max($record, $estimate);

        return $best > 0 ? round($best, 1) : null;
    }

    /**
     * Charge prescrite pour une ligne de programme selon son intention (null : rien à prescrire).
     * Au poids du corps, seule la force max a une charge : le lest. Semaine allégée du cycle : force max −10 %.
     */
    public function prescriptionFor(User $user, Exercise $exercise, ?string $intent, int $repsMax, ?CarbonInterface $on = null): ?float
    {
        if (in_array($intent, ['wave', 'backoff'], true)) {
            return $this->waveLoad($user, $exercise, $intent, $on ?? now());
        }
        $load = $this->basePrescription($user, $exercise, $intent, $repsMax);
        if ($load === null || $intent !== 'max' || ! Cycle::isDeload($user, $on ?? now())) {
            return $load;
        }

        return max($exercise->is_bodyweight ? 0.0 : 2.5, floor($load * Cycle::DELOAD_FACTOR / 2.5) * 2.5);
    }

    private function basePrescription(User $user, Exercise $exercise, ?string $intent, int $repsMax): ?float
    {
        if ($exercise->is_bodyweight && $intent !== 'max') {
            // Explosif ou vitesse : sans lest, même si la dernière séance de l'exercice était lestée.
            return in_array($intent, ['speed', 'explosive'], true) ? 0.0 : null;
        }

        $pct = \App\Game\Intent::META[$intent]['pct'] ?? null;
        if ($intent === 'top') {
            return $this->topLoad($user, $exercise, $repsMax);
        }
        if ($pct === null || $intent === 'max') {
            $stored = $user->workingLoads()->where('exercise_id', $exercise->id)->value('target_kg');
            if ($stored !== null || $intent !== 'max') {
                return $stored === null ? null : (float) $stored;
            }
        }

        return $this->percentLoad($user, $exercise, $intent, $repsMax);
    }

    /** Vague 5/3/1 : part du max d'entraînement (90 % de la base) selon la semaine ; séries de suite à 93 %. */
    private function waveLoad(User $user, Exercise $exercise, string $intent, CarbonInterface $on): ?float
    {
        if ($exercise->is_bodyweight) {
            return null;
        }
        $base = $this->bestE1rm($user, $exercise, 5);
        if ($base === null) {
            return null;
        }
        $wave = max(2.5, floor($base * Cycle::TRAINING_MAX * Cycle::WAVE[Cycle::week($user, $on) ?? 1][0] / 2.5) * 2.5);

        return $intent === 'wave' ? $wave : max(2.5, floor($wave * Cycle::BACKOFF_FACTOR / 2.5) * 2.5);
    }

    /**
     * Fin de vague (semaine 3 réussie) : le max d'entraînement monte d'un cran, une fois par cycle.
     * Série de la vague ratée à la limite : la base redescend de 5 %.
     */
    private function progressWave(User $user, ProgramExercise $line, Collection $sets, CarbonInterface $day): ?array
    {
        $week = Cycle::week($user, $day) ?? 1;
        $kg = $this->waveLoad($user, $line->exercise, 'wave', $day);
        if ($kg === null) {
            return null;
        }
        [, $min] = Cycle::WAVE[$week];
        $done = $sets->filter(fn (WorkoutSet $s) => $s->weight_kg + 0.001 >= $kg);
        $missed = $done->contains(fn (WorkoutSet $s) => $s->reps < $min && $s->effort === Effort::LIMIT);
        $raised = ! $missed && $week === 3 && $done->contains(fn (WorkoutSet $s) => $s->reps >= $min);
        if (! $missed && ! $raised) {
            return null;
        }

        $current = $user->exerciseMaxes()->where('exercise_id', $line->exercise_id)->first();
        if ($raised && $current?->source === 'wave' && $current->measured_at && $current->measured_at->gte(Carbon::parse($day)->startOfWeek())) {
            return null;
        }
        $base = (float) $this->bestE1rm($user, $line->exercise, 5);
        $base = $raised ? $base + $this->step($line->exercise) / Cycle::TRAINING_MAX : $base * 0.95;
        $user->exerciseMaxes()->updateOrCreate(['exercise_id' => $line->exercise_id], ['e1rm_kg' => round($base, 1), 'source' => 'wave', 'measured_at' => $day]);

        // Message : la série de la vague à venir (semaine 1 du prochain cycle après une hausse, la même sinon).
        $next = $this->waveLoad($user, $line->exercise, 'wave', $raised ? Carbon::parse($day)->addWeeks(2) : $day);

        return $raised ? \App\Support\SystemMessage::topUp($line->exercise->name, $next) : \App\Support\SystemMessage::topDown($line->exercise->name, $next);
    }

    /** Série record : 92,5 % du meilleur 1RM, et toujours au-dessus des séries lourdes. */
    private function topLoad(User $user, Exercise $exercise, int $repsMax): ?float
    {
        $percent = $this->percentLoad($user, $exercise, 'top', $repsMax);
        $heavy = $user->workingLoads()->where('exercise_id', $exercise->id)->value('target_kg');
        if ($heavy !== null && ($percent === null || $percent <= (float) $heavy)) {
            return (float) $heavy + $this->step($exercise);
        }

        return $percent;
    }

    /** Pas d'une hausse de charge sur un poly-articulaire : 5 kg pour les jambes, 2,5 kg pour le haut. */
    private function step(Exercise $exercise): float
    {
        return $this->isLowerBody($exercise->slots ?? []) ? 5.0 : 2.5;
    }

    /**
     * Série record réussie (toutes ses répétitions sans « limite », ou une seule « facile ») :
     * le 1RM estimé est relevé pour que la prochaine série record pèse un cran de plus.
     */
    private function progressTop(User $user, ProgramExercise $top, float $topKg, Collection $sets): ?array
    {
        $done = $sets->filter(fn (WorkoutSet $s) => $s->weight_kg + 0.001 >= $topKg);
        $success = $done->contains(fn (WorkoutSet $s) => ($s->reps >= $top->reps_max && $s->effort !== Effort::LIMIT)
            || ($s->reps >= $top->reps_min && $s->effort === Effort::EASY));
        // Moins de répétitions que visé, à la limite : la forme du jour est sous la charge, on redescend d'un cran.
        $missed = ! $success && $done->contains(fn (WorkoutSet $s) => $s->reps < $top->reps_max && $s->effort === Effort::LIMIT);
        if (! $success && ! $missed) {
            return null;
        }

        // Faite plus lourde que prévu : on repart de la vraie charge.
        $next = $success
            ? max($topKg, floor($done->max('weight_kg') / 2.5) * 2.5) + $this->step($top->exercise)
            : $topKg - $this->step($top->exercise);
        // Arrondi au dixième supérieur : 92,5 % du 1RM stocké retombe bien sur la charge visée.
        $e1rm = ceil($next / Intent::META['top']['pct'] * 10) / 10;
        $current = $user->exerciseMaxes()->where('exercise_id', $top->exercise_id)->first();
        if ($missed || ! $current || (float) $current->e1rm_kg < $e1rm || $current->source !== 'top') {
            $user->exerciseMaxes()->updateOrCreate(['exercise_id' => $top->exercise_id], ['e1rm_kg' => $e1rm, 'source' => 'top', 'measured_at' => now()]);
        }

        // La série record reste au-dessus des séries lourdes : le message annonce la charge réellement prescrite.
        $kg = $this->prescriptionFor($user, $top->exercise, 'top', $top->reps_max);

        return $success ? \App\Support\SystemMessage::topUp($top->exercise->name, $kg) : \App\Support\SystemMessage::topDown($top->exercise->name, $kg);
    }

    /** Charge d'une intention à pourcentage du meilleur 1RM (force max arrondie vers le bas). */
    private function percentLoad(User $user, Exercise $exercise, string $intent, int $repsMax): ?float
    {
        $pct = $intent === 'max' ? $this->maxPct($repsMax) : \App\Game\Intent::META[$intent]['pct'];

        $best = $this->bestE1rm($user, $exercise, $repsMax);
        if ($best === null) {
            return null;
        }

        return $this->loadAt($user, $exercise, $intent, $best * $pct);
    }

    /** Force max en 3 à 5 répétitions : 87,5 % serait presque un maximum, la double progression ne passerait jamais. */
    private function maxPct(int $repsMax): float
    {
        return min(\App\Game\Intent::META['max']['pct'], 1 / (1 + ($repsMax + 1) / 30));
    }

    /** Charge à mettre sur la barre pour une valeur visée (lest au poids du corps). */
    private function loadAt(User $user, Exercise $exercise, string $intent, float $value): ?float
    {
        // Au poids du corps, le 1RM compte le corps : la charge à prescrire est le lest, jamais négatif.
        if ($exercise->is_bodyweight) {
            return $user->weight_kg ? max(0.0, floor(($value - $user->weight_kg) / 2.5) * 2.5) : null;
        }
        $rounded = in_array($intent, ['max', 'top'], true) ? floor($value / 2.5) * 2.5 : round($value / 2.5) * 2.5;

        return max(2.5, $rounded);
    }

    /** Lignes dont la charge est stockée et progresse par les répétitions : force max, masse ou aucune intention. */
    public function loadLines(User $user): Collection
    {
        $program = $user->activeProgram()->with('days.exercises.exercise')->first();
        if (! $program) {
            return collect();
        }

        return $program->days->flatMap->exercises
            ->filter(fn (ProgramExercise $l) => in_array($l->intent, [null, 'mass', 'max'], true))
            ->groupBy('exercise_id')->map(function (Collection $occurrences) {
                // Un exercice lourd (🔥) un jour et accessoire un autre (dips) : la ligne lourde mène sa charge et son lest.
                $heavy = $occurrences->where('intent', 'max');
                $lead = $heavy->isNotEmpty() ? $heavy : $occurrences;
                $first = clone $lead->first();
                $first->reps_max = $lead->min('reps_max');

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
            if (($line->exercise->is_bodyweight && $line->intent !== 'max') || ($existing->has($exerciseId) && ! in_array($exerciseId, $overwrite, true))) {
                continue;
            }

            $target = match (true) {
                $line->intent === 'max' => $this->percentLoad($user, $line->exercise, 'max', $line->reps_max),
                $maxes->has($exerciseId) => $this->startingLoad((float) $maxes[$exerciseId], $line->reps_max),
                default => $this->derivedLoad($line->exercise, $user, $measured),
            };

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
        // Semaine allégée : on recharge, les charges ne bougent pas.
        if ($plan->isEmpty() || Cycle::isDeload($user, $workout->finished_at)) {
            return [];
        }

        $loads = $user->workingLoads()->get()->keyBy('exercise_id');
        $notes = [];
        $lines = $user->activeProgram()->with('days.exercises.exercise')->first()?->days->flatMap->exercises ?? collect();
        $fastIds = $lines->whereIn('intent', ['speed', 'explosive'])->pluck('exercise_id')->unique()->all();
        $topLines = $lines->where('intent', 'top')->keyBy('exercise_id');
        // Charges des séries record d'avant la séance : la hausse ne doit pas changer le tri des séries lourdes.
        $topKgs = $topLines->map(fn (ProgramExercise $top) => $this->prescriptionFor($user, $top->exercise, 'top', $top->reps_max));
        $bySets = $workout->sets->groupBy('exercise_id');
        // Intentions de la quête du jour par exercice : une séance de vitesse ne juge pas la charge lourde.
        $quest = $user->quests()->where('type', 'training')->whereDate('date', $workout->finished_at->toDateString())->first();
        $todayIntents = $quest ? collect($quest->objectives)->groupBy('exercise_id')->map(fn (Collection $o) => $o->pluck('intent')->all()) : null;

        foreach ($lines->where('intent', 'wave')->keyBy('exercise_id') as $exerciseId => $wave) {
            if (isset($bySets[$exerciseId]) && $note = $this->progressWave($user, $wave, $bySets[$exerciseId], $workout->finished_at)) {
                $notes[] = $note;
            }
        }

        foreach ($topLines as $exerciseId => $top) {
            if (isset($bySets[$exerciseId]) && $topKgs[$exerciseId] !== null && $note = $this->progressTop($user, $top, $topKgs[$exerciseId], $bySets[$exerciseId])) {
                $notes[] = $note;
            }
        }

        foreach ($bySets as $exerciseId => $sets) {
            $line = $plan[$exerciseId] ?? null;
            if (! $line) {
                continue;
            }

            // Au poids du corps hors force max : on prévient seulement quand il est temps de se lester.
            if ($line->exercise->is_bodyweight && $line->intent !== 'max') {
                if ($sets->count() >= $line->sets && $sets->every(fn (WorkoutSet $s) => $s->reps >= $line->reps_max + 5)) {
                    $notes[] = \App\Support\SystemMessage::addWeight($line->exercise->name);
                }
                continue;
            }

            // Force max au poids du corps sans lest suivi : il part de cette séance (le record vient d'être mis à jour).
            if ($line->exercise->is_bodyweight && ! isset($loads[$exerciseId])) {
                $start = $this->percentLoad($user, $line->exercise, 'max', $line->reps_max);
                if ($start === null) {
                    continue;
                }
                $loads[$exerciseId] = $user->workingLoads()->create(['exercise_id' => $exerciseId, 'target_kg' => $start, 'fail_streak' => 0]);
            }

            $load = $loads[$exerciseId] ?? null;
            if (! $load) {
                continue;
            }

            // Séance de vitesse d'un exercice aussi travaillé lourd : elle ne juge pas la charge lourde. La quête du jour
            // le dit ; sans quête, les charges légères le trahissent (pas au poids du corps, où le lest peut être à 0).
            $fastToday = $todayIntents !== null && isset($todayIntents[$exerciseId])
                ? array_intersect($todayIntents[$exerciseId], [null, 'mass', 'max']) === []
                : in_array((int) $exerciseId, $fastIds, true) && $sets->max('weight_kg') < $load->target_kg * 0.75;
            if ($fastToday) {
                continue;
            }

            // Série record (🏆) : faite à sa propre charge, plus lourde, elle ne juge pas la charge lourde (🔥).
            if (isset($topLines[$exerciseId])) {
                $topKg = $topKgs[$exerciseId];
                if ($topKg !== null && $topKg > $load->target_kg) {
                    $sets = $sets->filter(fn (WorkoutSet $s) => $s->weight_kg + 0.001 < $topKg);
                    if ($sets->isEmpty()) {
                        continue;
                    }
                }
            }

            $small = $line->exercise->kind === 'isolation' && $load->target_kg < self::SMALL_LOAD_KG;
            $kept = $sets->filter(fn (WorkoutSet $s) => $s->weight_kg + 0.001 >= $load->target_kg);

            // Force max faite bien au-delà des répétitions visées : la charge repart de la meilleure série du jour,
            // et les dernières séries qui lâchent (fatigue de ces séries trop longues) ne comptent pas comme un échec.
            if ($line->intent === 'max' && $kept->contains(fn (WorkoutSet $s) => $s->reps >= $line->reps_max + self::EXCEEDED_REPS)) {
                $bodyweight = $line->exercise->is_bodyweight ? (float) $user->weight_kg : 0.0;
                $e1rm = $kept->max(fn (WorkoutSet $s) => ($s->weight_kg + $bodyweight) * (1 + $s->reps / 30));
                $raised = $this->loadAt($user, $line->exercise, 'max', $e1rm * $this->maxPct($line->reps_max));
                $load->fail_streak = 0;
                if ($raised !== null && $raised > (float) $load->target_kg) {
                    $load->target_kg = $raised;
                    $notes[] = \App\Support\SystemMessage::loadUp($line->exercise->name, $load->target_kg);
                }
                $load->save();

                continue;
            }
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
                    $before = (float) $load->target_kg;
                    $load->target_kg = max($line->exercise->is_bodyweight ? 0.0 : $step, $target);
                    $load->fail_streak = 0;
                    if ((float) $load->target_kg < $before) {
                        $notes[] = \App\Support\SystemMessage::deload($line->exercise->name, $load->target_kg);
                    }
                }
            } elseif ($kept->count() >= $line->sets && $kept->every(fn (WorkoutSet $s) => $s->reps >= $line->reps_max)) {
                // Ressenti : une série « limite » garde la charge, tout « facile » double la hausse.
                $load->fail_streak = 0;
                if (! $kept->contains('effort', Effort::LIMIT)) {
                    $step = $small ? 1.0 : (in_array($line->slot, self::LOWER_BODY_SLOTS, true) ? 5.0 : 2.5);
                    $load->target_kg += $kept->every(fn (WorkoutSet $s) => $s->effort === Effort::EASY) ? 2 * $step : $step;
                    $notes[] = \App\Support\SystemMessage::loadUp($line->exercise->name, $load->target_kg);
                }
            } else {
                $load->fail_streak = 0;
            }

            $load->save();
        }

        return $notes;
    }
}
