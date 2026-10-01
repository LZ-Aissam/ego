<?php

namespace App\Crew;

use App\Models\Crew;
use App\Models\Exercise;
use App\Models\PersonalRecord;
use App\Models\User;
use App\Models\XpEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class Leaderboard
{
    public const METRICS = ['xp', 'streak', 'force'];

    public const PERIODS = ['week', 'month', 'all'];

    public const LIFTS = ['bench' => 'bench-press', 'squat' => 'back-squat', 'deadlift' => 'deadlift'];

    /** @return Collection<int, array{user: User, value: int|float, rank: int}> */
    public function rank(string $metric, string $period = 'week', string $lift = 'bench', ?Crew $crew = null): Collection
    {
        $users = $crew ? $crew->members()->get() : User::all();

        $values = match ($metric) {
            'xp' => $this->xp($users, $period),
            'streak' => $users->mapWithKeys(fn (User $u) => [$u->id => (int) $u->streak_days]),
            'force' => $this->force($users, $lift),
        };

        $rows = $users->filter(fn (User $u) => $values->has($u->id))
            ->map(fn (User $u) => ['user' => $u, 'value' => $values[$u->id]])
            ->sort(fn ($a, $b) => [$b['value'], $a['user']->first_name, $a['user']->id] <=> [$a['value'], $b['user']->first_name, $b['user']->id])
            ->values();

        $previous = null;
        $rank = 0;

        return $rows->map(function (array $row, int $i) use (&$previous, &$rank) {
            if ($previous === null || $row['value'] != $previous) {
                $rank = $i + 1;
            }
            $previous = $row['value'];

            return [...$row, 'rank' => $rank];
        });
    }

    public static function format(string $metric, int|float $value): string
    {
        return match ($metric) {
            'xp' => number_format($value, 0, ',', ' ').' XP',
            'streak' => $value.' j',
            'force' => '×'.number_format($value, 2, ',', ' '),
        };
    }

    private function xp(Collection $users, string $period): Collection
    {
        if ($period === 'all') {
            return $users->mapWithKeys(fn (User $u) => [$u->id => (int) $u->xp_total]);
        }

        $since = $period === 'month' ? Carbon::now()->startOfMonth() : Carbon::now()->startOfWeek(Carbon::MONDAY);
        $totals = XpEvent::whereIn('user_id', $users->modelKeys())->where('created_at', '>=', $since)
            ->groupBy('user_id')->selectRaw('user_id, SUM(amount) AS total')->pluck('total', 'user_id');

        return $users->mapWithKeys(fn (User $u) => [$u->id => (int) ($totals[$u->id] ?? 0)]);
    }

    private function force(Collection $users, string $lift): Collection
    {
        $exerciseId = Exercise::where('slug', self::LIFTS[$lift] ?? self::LIFTS['bench'])->value('id');
        $records = PersonalRecord::where('exercise_id', $exerciseId)->whereIn('user_id', $users->modelKeys())->pluck('best_e1rm_kg', 'user_id');

        return $users->filter(fn (User $u) => $u->weight_kg > 0 && $records->has($u->id))
            ->mapWithKeys(fn (User $u) => [$u->id => round((float) $records[$u->id] / $u->weight_kg, 2)]);
    }
}
