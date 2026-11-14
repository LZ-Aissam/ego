<?php

namespace App\Game;

use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSet;
use App\Support\Format;
use App\Support\SystemMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Force relative : maximum estimé des grands mouvements du haut du corps rapporté au poids du corps,
 * rang E → S par mouvement (repères EGO), projection du prochain rang et succès débloqués une fois.
 */
final class StrengthRanks
{
    public const RANKS = ['E', 'D', 'C', 'B', 'A', 'S'];

    /** XP d'un rang débloqué, par rang (D = 1 … S = 5). */
    public const RANK_XP = 100;

    /** Fenêtre de la tendance pour la projection. */
    public const TREND_WEEKS = 8;

    /**
     * clé => [nom, exercices (le meilleur des deux compte), seuils D, C, B, A, S en × poids du corps].
     * Tractions et dips : le poids du corps compte dans la charge (1,5 × PDC à 70 kg = +35 kg de lest).
     */
    public const LIFTS = [
        'bench' => ['Développé couché', ['bench-press'], [0.75, 1.0, 1.25, 1.5, 1.75]],
        'press' => ['Développé militaire', ['overhead-press'], [0.5, 0.65, 0.8, 0.95, 1.1]],
        'row' => ['Rowing barre', ['barbell-row'], [0.75, 0.95, 1.15, 1.35, 1.55]],
        'pullup' => ['Tractions lestées', ['pull-up', 'chin-up'], [1.15, 1.3, 1.5, 1.7, 1.9]],
        'dips' => ['Dips lestés', ['dips'], [1.25, 1.45, 1.65, 1.85, 2.1]],
    ];

    /**
     * @return list<array{key: string, name: string, e1rm: ?float, ratio: ?float, rank: string, rankIndex: int,
     *   next: ?array{rank: string, kg: float, ratio: float}, progress: int, perWeek: ?float, weeksToNext: ?int}>
     */
    public function for(User $user): array
    {
        $bodyweight = (float) $user->weight_kg;
        $out = [];

        foreach (self::LIFTS as $key => [$name, $slugs, $thresholds]) {
            $points = $this->points($user, $slugs);
            $best = $points->isEmpty() ? null : (float) $points->max('e1rm');
            $e1rm = $best === null ? null : round($best, 1);
            $ratio = $e1rm !== null && $bodyweight > 0 ? round($e1rm / $bodyweight, 2) : null;
            $index = $ratio === null ? 0 : count(array_filter($thresholds, fn (float $t) => $ratio + 1e-9 >= $t));
            $next = $index < count($thresholds) && $bodyweight > 0
                ? ['rank' => self::RANKS[$index + 1], 'ratio' => $thresholds[$index], 'kg' => round($thresholds[$index] * $bodyweight, 1)]
                : null;
            $from = $index === 0 ? 0.0 : $thresholds[$index - 1];
            $perWeek = $this->trend($points);

            $out[] = [
                'key' => $key, 'name' => $name, 'e1rm' => $e1rm, 'ratio' => $ratio,
                'rank' => self::RANKS[$index], 'rankIndex' => $index, 'next' => $next,
                'progress' => $next && $ratio !== null ? (int) round(max(0, min(1, ($ratio - $from) / ($next['ratio'] - $from))) * 100) : ($next ? 0 : 100),
                'perWeek' => $perWeek === null ? null : round($perWeek, 1),
                'weeksToNext' => $next && $e1rm !== null && $perWeek !== null && $perWeek > 0.05
                    ? (int) max(1, ceil(($next['kg'] - $best) / $perWeek - 1e-6)) : null,
            ];
        }

        return $out;
    }

    /** Meilleur max estimé d'un mouvement avant une date (bilan de cycle). */
    public function bestBefore(User $user, string $key, Carbon $before): ?float
    {
        $best = $this->points($user, self::LIFTS[$key][1])->filter(fn (array $p) => $p['date']->lt($before))->max('e1rm');

        return $best === null ? null : round((float) $best, 1);
    }

    /**
     * Rangs atteints pas encore récompensés : XP pour chacun, un message par mouvement (le plus haut). Idempotent.
     *
     * @return list<array>
     */
    public function unlock(User $user): array
    {
        $ledger = app(XpLedger::class);
        $keys = array_keys(self::LIFTS);
        $notes = [];

        foreach ($this->for($user) as $lift) {
            $new = null;
            for ($rank = 1; $rank <= $lift['rankIndex']; $rank++) {
                $sourceId = (array_search($lift['key'], $keys, true) + 1) * 10 + $rank;
                if ($ledger->award($user, 'strength_rank', $sourceId, self::RANK_XP * $rank)) {
                    $new = $rank;
                }
            }
            if ($new !== null) {
                $notes[] = SystemMessage::strengthRank($lift['name'], self::RANKS[$new], $lift['ratio'], $lift['next']);
            }
        }

        return $notes;
    }

    /**
     * Meilleur max estimé de chaque séance (Epley, 10 répétitions comptées au plus, poids du corps compris au poids du corps).
     *
     * @return Collection<int, array{date: Carbon, e1rm: float}>
     */
    private function points(User $user, array $slugs): Collection
    {
        $exercises = Exercise::whereIn('slug', $slugs)->get()->keyBy('id');
        if ($exercises->isEmpty()) {
            return collect();
        }

        return WorkoutSet::query()->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
            ->where('workouts.user_id', $user->id)->whereIn('workout_sets.exercise_id', $exercises->keys())
            ->get(['workout_sets.exercise_id', 'workout_sets.weight_kg', 'workout_sets.reps', 'workouts.id as wid', 'workouts.finished_at', 'workouts.bodyweight_kg'])
            ->groupBy('wid')
            ->map(function (Collection $sets) use ($exercises, $user) {
                $first = $sets->first();
                $bodyweight = (float) ($first->bodyweight_kg ?? $user->weight_kg);

                return [
                    'date' => Carbon::parse($first->finished_at),
                    'e1rm' => (float) $sets->max(fn ($s) => ((float) $s->weight_kg + ($exercises[$s->exercise_id]->is_bodyweight ? $bodyweight : 0.0))
                        * (1 + min((int) $s->reps, LoadPrescriber::WAVE_REPS_CAP) / 30)),
                ];
            })
            ->filter(fn (array $p) => $p['e1rm'] > 0)
            ->sortBy('date')->values();
    }

    /** Pente (kg par semaine) des meilleures séances des 8 dernières semaines, au moins deux séances. */
    private function trend(Collection $points): ?float
    {
        $since = now()->subWeeks(self::TREND_WEEKS);
        $recent = $points->filter(fn (array $p) => $p['date']->gte($since))->values();
        if ($recent->count() < 2) {
            return null;
        }

        $xs = $recent->map(fn (array $p) => $recent->first()['date']->diffInDays($p['date']) / 7)->all();
        $ys = $recent->pluck('e1rm')->all();
        $n = count($xs);
        $mx = array_sum($xs) / $n;
        $my = array_sum($ys) / $n;
        $den = array_sum(array_map(fn ($x) => ($x - $mx) ** 2, $xs));
        if ($den < 1e-9) {
            return null;
        }

        return array_sum(array_map(fn ($x, $y) => ($x - $mx) * ($y - $my), $xs, $ys)) / $den;
    }
}
