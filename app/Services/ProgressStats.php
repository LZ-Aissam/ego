<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use App\Support\Format;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Chiffres de progression : séries par exercice, bilan hebdomadaire, meilleures progressions, écarts entre séances. */
final class ProgressStats
{
    public const PERIODS = ['3m' => 3, '1y' => 12, 'all' => null];

    public function __construct(private RecordCalculator $calc) {}

    /**
     * Une ligne par séance : charge max, 1RM estimé, volume, et « record » si le 1RM dépasse tous les précédents.
     *
     * @return Collection<int, array{date: Carbon, max_kg: float, e1rm: float, volume: float, record: bool}>
     */
    public function exerciseSeries(User $user, Exercise $exercise, string $period = 'all'): Collection
    {
        $best = 0.0;
        $rows = $this->setsOf($user, $exercise)->groupBy('workout_id')->map(function (Collection $sets) use ($exercise, &$best) {
            $workout = $sets->first()->workout;
            $e1rm = (float) $sets->max(fn (WorkoutSet $s) => $this->calc->estimatedOneRepMax(
                $this->calc->effectiveLoad($s->weight_kg, $exercise->is_bodyweight, $workout->bodyweight_kg), $s->reps,
            ));
            $record = $e1rm > $best;
            $best = max($best, $e1rm);

            return [
                'date' => $workout->finished_at,
                'max_kg' => (float) $sets->max('weight_kg'),
                'e1rm' => $e1rm,
                'volume' => round((float) $sets->sum(fn (WorkoutSet $s) => $this->calc->setVolume($s->weight_kg, $s->reps, $exercise->is_bodyweight, $workout->bodyweight_kg)), 1),
                'record' => $record,
            ];
        })->values();

        $months = self::PERIODS[$period] ?? null;

        return $months === null ? $rows : $rows->filter(fn ($r) => $r['date']->gte(now()->subMonths($months)))->values();
    }

    /** @return array{best_kg: float, gain_kg: float, gain_pct: int, trend: string}|null */
    public function summary(User $user, Exercise $exercise): ?array
    {
        $rows = $this->exerciseSeries($user, $exercise);
        if ($rows->isEmpty()) {
            return null;
        }

        $first = $rows->first()['max_kg'];
        $last = $rows->last()['max_kg'];
        $recent = $rows->filter(fn ($r) => $r['date']->gte(now()->subDays(28)))->max('e1rm');
        $before = $rows->filter(fn ($r) => $r['date']->lt(now()->subDays(28)) && $r['date']->gte(now()->subDays(56)))->max('e1rm');

        return [
            'best_kg' => (float) $rows->max('max_kg'),
            'gain_kg' => round($last - $first, 1),
            'gain_pct' => $first > 0 ? (int) round(($last - $first) / $first * 100) : 0,
            'trend' => match (true) {
                $recent === null || $before === null => 'unknown',
                $recent > $before * 1.01 => 'up',
                $recent < $before * 0.99 => 'down',
                default => 'flat',
            },
        ];
    }

    /** @return Collection<int, array{week: string, volume: float, sessions: int}> 12 semaines, du lundi le plus ancien au lundi courant */
    public function weeklyVolume(User $user, int $weeks = 12): Collection
    {
        $start = now()->startOfWeek(Carbon::MONDAY)->subWeeks($weeks - 1);
        $workouts = $user->workouts()->with('sets.exercise')->where('finished_at', '>=', $start)->get()
            ->groupBy(fn (Workout $w) => $w->finished_at->copy()->startOfWeek(Carbon::MONDAY)->toDateString());

        return collect(range(0, $weeks - 1))->map(function (int $i) use ($start, $workouts) {
            $week = $start->copy()->addWeeks($i)->toDateString();
            $list = $workouts[$week] ?? collect();

            return ['week' => $week, 'volume' => round((float) $list->sum(fn (Workout $w) => $w->volume()), 1), 'sessions' => $list->count()];
        });
    }

    /** @return Collection<int, array{exercise: Exercise, gain_pct: int, before: float, now: float}> */
    public function topProgress(User $user, int $days = 30, int $limit = 3): Collection
    {
        $since = now()->subDays($days);
        $exerciseIds = WorkoutSet::query()->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
            ->where('workouts.user_id', $user->id)->where('workouts.finished_at', '>=', $since)
            ->distinct()->pluck('workout_sets.exercise_id');

        return Exercise::whereIn('id', $exerciseIds)->get()->map(function (Exercise $exercise) use ($user, $since) {
            $rows = $this->exerciseSeries($user, $exercise);
            $before = $rows->filter(fn ($r) => $r['date']->lt($since))->max('e1rm');
            $now = $rows->filter(fn ($r) => $r['date']->gte($since))->max('e1rm');

            return $before ? ['exercise' => $exercise, 'gain_pct' => (int) round(($now - $before) / $before * 100), 'before' => $before, 'now' => $now] : null;
        })->filter(fn ($r) => $r && $r['gain_pct'] > 0)->sortByDesc('gain_pct')->take($limit)->values();
    }

    /**
     * Écart de la meilleure série de chaque exercice avec la séance précédente qui le contenait.
     *
     * @return array<int, string|null> « +2,5 kg », « −1 rep », « = », ou null pour une première fois
     */
    public function comparedToPrevious(Workout $workout): array
    {
        $workout->loadMissing('sets');
        $out = [];

        foreach ($workout->sets->groupBy('exercise_id') as $exerciseId => $sets) {
            $previous = WorkoutSet::query()->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
                ->where('workouts.user_id', $workout->user_id)->where('workout_sets.exercise_id', $exerciseId)
                ->where('workouts.finished_at', '<', $workout->finished_at)
                ->orderByDesc('workouts.finished_at')->select('workout_sets.*', 'workouts.finished_at')->get();
            if ($previous->isEmpty()) {
                $out[$exerciseId] = null;

                continue;
            }

            $lastDate = $previous->first()->finished_at;
            [$kg, $reps] = $this->bestSet($sets);
            [$oldKg, $oldReps] = $this->bestSet($previous->where('finished_at', $lastDate));
            $out[$exerciseId] = match (true) {
                abs($kg - $oldKg) >= 0.01 => ($kg > $oldKg ? '+' : '−').Format::kg(abs($kg - $oldKg)),
                $reps !== $oldReps => ($reps > $oldReps ? '+' : '−').abs($reps - $oldReps).(abs($reps - $oldReps) > 1 ? ' reps' : ' rep'),
                default => '=',
            };
        }

        return $out;
    }

    /** @return array{0: float, 1: int} charge la plus lourde, puis le plus de répétitions à cette charge */
    private function bestSet(Collection $sets): array
    {
        $best = $sets->sortByDesc(fn ($s) => [(float) $s->weight_kg, (int) $s->reps])->first();

        return [(float) $best->weight_kg, (int) $best->reps];
    }

    private function setsOf(User $user, Exercise $exercise): Collection
    {
        return WorkoutSet::query()->with('workout')
            ->whereHas('workout', fn ($q) => $q->where('user_id', $user->id))
            ->where('exercise_id', $exercise->id)
            ->get()->sortBy(fn (WorkoutSet $s) => [$s->workout->finished_at->timestamp, $s->id])->values();
    }
}
