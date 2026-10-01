<?php

namespace App\Game;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class StreakCalculator
{
    public function compute(User $user, CarbonInterface $asOf): int
    {
        $asOf = Carbon::parse($asOf->toDateString());
        // On charge aussi le lendemain : sa pénalité peut sauver le dernier jour compté.
        $quests = $user->quests()->where('date', '<=', $asOf->copy()->addDay()->toDateString())->get()
            ->groupBy(fn ($quest) => $quest->date->toDateString());

        if ($quests->isEmpty()) {
            return 0;
        }

        $first = Carbon::parse($quests->keys()->min());
        if ($first->gt($asOf)) {
            return 0;
        }
        $streak = 0;

        for ($day = $asOf->copy(); $day->gte($first); $day = $day->copy()->subDay()) {
            $ofDay = $quests->get($day->toDateString(), collect());
            // Une pénalité ratée ce jour-là remet la série à 0 : ce jour ne compte pas.
            if ($ofDay->contains(fn ($q) => $q->type === 'penalty' && $q->status === 'failed')) {
                break;
            }
            $main = $ofDay->first(fn ($q) => in_array($q->type, ['training', 'calibration', 'rest'], true));

            if (! $main || in_array($main->status, ['pending', 'cancelled'], true)) {
                continue;
            }
            if ($main->status === 'done') {
                $streak++;
                continue;
            }

            $penalty = $quests->get($day->copy()->addDay()->toDateString(), collect())->firstWhere('type', 'penalty');
            // Pénalité réussie, en cours, ou annulée par une pause : le jour raté est neutre.
            if ($penalty && in_array($penalty->status, ['done', 'pending', 'cancelled'], true)) {
                continue;
            }

            break;
        }

        return $streak;
    }

    public function refresh(User $user, CarbonInterface $today): int
    {
        $streak = $this->compute($user, $today);
        $user->forceFill(['streak_days' => $streak, 'best_streak' => max((int) $user->best_streak, $streak)])->save();

        return $streak;
    }
}
