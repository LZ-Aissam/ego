<?php

namespace App\Crew;

use App\Models\Challenge;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use App\Models\XpEvent;
use App\Services\RecordCalculator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class ChallengeBoard
{
    public function __construct(private RecordCalculator $calc) {}

    /** Classement figé pour un défi terminé, sinon calculé en direct. */
    public function standings(Challenge $challenge): Collection
    {
        return $challenge->status === 'finished' && $challenge->final_standings !== null
            ? collect($challenge->final_standings)
            : $this->compute($challenge);
    }

    /** @return Collection<int, array{user_id: int, first_name: string, value: int|float, rank: int}> */
    public function compute(Challenge $challenge): Collection
    {
        $users = $challenge->participants()->get();
        $from = $challenge->starts_at;
        $to = $challenge->ends_at;

        $values = match ($challenge->metric) {
            'xp' => $this->xp($users, $from, $to),
            'sessions' => $this->workouts($users, $from, $to)->map->count(),
            'volume' => $this->workouts($users, $from, $to)->map(fn (Collection $ws) => round((float) $ws->sum(fn (Workout $w) => $w->volume()), 1)),
            'strength' => $this->strength($users, $challenge->lift, $from),
        };

        if ($challenge->metric !== 'strength') {
            $zero = $challenge->metric === 'volume' ? 0.0 : 0;
            $values = $users->mapWithKeys(fn (User $u) => [$u->id => $values[$u->id] ?? $zero]);
        }

        $rows = $users->filter(fn (User $u) => $values->has($u->id))
            ->sort(fn (User $a, User $b) => [$values[$b->id], Leaderboard::sortKey($a), $a->id] <=> [$values[$a->id], Leaderboard::sortKey($b), $b->id])
            ->values();

        $previous = null;
        $rank = 0;

        return $rows->map(function (User $u, int $i) use ($values, &$previous, &$rank) {
            if ($previous === null || $values[$u->id] != $previous) {
                $rank = $i + 1;
            }
            $previous = $values[$u->id];

            return ['user_id' => $u->id, 'first_name' => $u->first_name, 'value' => $values[$u->id], 'rank' => $rank];
        });
    }

    public static function format(string $metric, int|float $value): string
    {
        return match ($metric) {
            'xp' => number_format($value, 0, ',', "\u{202F}").' XP',
            'sessions' => $value.($value > 1 ? ' séances' : ' séance'),
            'volume' => number_format($value, 0, ',', "\u{202F}").' kg',
            'strength' => ($value < 0 ? '−' : '+').abs($value).' %',
        };
    }

    public static function remaining(Carbon $end): string
    {
        $minutes = max(0, (int) floor(now()->diffInMinutes($end, false)));
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);

        return match (true) {
            $days > 0 => "encore {$days} j {$hours} h",
            $hours > 0 => "encore {$hours} h",
            default => 'encore '.$minutes.' min',
        };
    }

    private function xp(Collection $users, Carbon $from, Carbon $to): Collection
    {
        return XpEvent::whereIn('user_id', $users->modelKeys())->where('created_at', '>=', $from)->where('created_at', '<', $to)
            ->groupBy('user_id')->selectRaw('user_id, SUM(amount) AS total')->pluck('total', 'user_id')->map(fn ($v) => (int) $v);
    }

    private function workouts(Collection $users, Carbon $from, Carbon $to): Collection
    {
        return Workout::with('sets.exercise')->whereIn('user_id', $users->modelKeys())
            ->where('finished_at', '>=', $from)->where('finished_at', '<', $to)->get()->groupBy('user_id');
    }

    /** Gain en % du meilleur 1RM estimé pendant le défi sur le meilleur d'avant ; non classé sans l'un des deux. */
    private function strength(Collection $users, ?string $lift, Carbon $from): Collection
    {
        $exerciseId = Exercise::where('slug', Leaderboard::LIFTS[$lift] ?? 'bench-press')->value('id');
        $sets = WorkoutSet::with('workout', 'exercise')->where('exercise_id', $exerciseId)
            ->whereHas('workout', fn ($q) => $q->whereIn('user_id', $users->modelKeys()))->get()
            ->groupBy(fn (WorkoutSet $s) => $s->workout->user_id);

        return $users->mapWithKeys(function (User $u) use ($sets, $from) {
            $mine = $sets[$u->id] ?? collect();
            $best = fn (Collection $list) => $list->max(fn (WorkoutSet $s) => $this->calc->estimatedOneRepMax(
                $this->calc->effectiveLoad($s->weight_kg, $s->exercise->is_bodyweight, $s->workout->bodyweight_kg), $s->reps,
            ));
            $before = $best($mine->filter(fn (WorkoutSet $s) => $s->workout->finished_at->lt($from)));
            $during = $best($mine->filter(fn (WorkoutSet $s) => $s->workout->finished_at->gte($from)));

            return $before && $during ? [$u->id => (int) round(($during - $before) / $before * 100)] : [];
        });
    }
}
