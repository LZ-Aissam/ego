<?php

namespace App\Game;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class StreakCalculator
{
    /** Jours consécutifs accomplis (repos compris) jusqu'à $asOf ; une quête ratée casse la série, un jour en pause est neutre. */
    public function compute(User $user, CarbonInterface $asOf): int
    {
        $asOf = Carbon::parse($asOf->toDateString());
        $quests = $user->quests()->where('date', '<=', $asOf->toDateString())
            ->whereIn('type', ['training', 'calibration', 'rest'])->get()
            ->groupBy(fn ($quest) => $quest->date->toDateString());

        if ($quests->isEmpty()) {
            return 0;
        }

        $first = Carbon::parse($quests->keys()->min());
        $streak = 0;

        for ($day = $asOf->copy(); $day->gte($first); $day = $day->copy()->subDay()) {
            $main = $quests->get($day->toDateString(), collect())->first();

            if (! $main || in_array($main->status, ['pending', 'cancelled'], true)) {
                continue;
            }
            if ($main->status !== 'done') {
                break;
            }
            $streak++;
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
