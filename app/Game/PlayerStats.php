<?php

namespace App\Game;

use App\Models\PersonalRecord;
use App\Models\User;
use App\Models\WorkoutSet;

final class PlayerStats
{
    public const WINDOW_DAYS = 28;

    /** @return array{force: ?int, endurance: int, progression: int, regularite: ?int} */
    public function for(User $user): array
    {
        $since = now()->subDays(self::WINDOW_DAYS);

        return [
            'force' => $this->force($user),
            'endurance' => intdiv((int) WorkoutSet::query()
                ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
                ->where('workouts.user_id', $user->id)
                ->where('workouts.finished_at', '>=', $since)
                ->sum('workout_sets.reps'), 50),
            'progression' => 10 * (int) $user->xpEvents()->where('source', 'record')->where('created_at', '>=', $since)->sum('quantity'),
            'regularite' => $this->regularity($user),
        ];
    }

    private function regularity(User $user): ?int
    {
        $base = $user->quests()->whereIn('type', ['training', 'rest'])->whereIn('status', ['done', 'failed'])
            ->where('date', '>=', now()->subDays(self::WINDOW_DAYS)->toDateString())
            ->where('date', '<', now()->toDateString());

        $total = (clone $base)->count();

        return $total === 0 ? null : (int) round((clone $base)->where('status', 'done')->count() / $total * 100);
    }

    private function force(User $user): ?int
    {
        if (! $user->weight_kg) {
            return null;
        }

        $best = PersonalRecord::query()
            ->join('exercises', 'exercises.id', '=', 'personal_records.exercise_id')
            ->where('personal_records.user_id', $user->id)
            ->where('exercises.kind', 'compound')
            ->orderByDesc('personal_records.best_e1rm_kg')
            ->limit(3)
            ->pluck('personal_records.best_e1rm_kg');

        if ($best->isEmpty()) {
            return 0;
        }

        return (int) round($best->avg() / $user->weight_kg * 50);
    }
}
