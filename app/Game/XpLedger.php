<?php

namespace App\Game;

use App\Models\User;
use App\Models\Workout;
use App\Models\XpEvent;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;

final class XpLedger
{
    public const DAILY_WORKOUTS = 2;

    public function workoutXp(int $setsCount): int
    {
        return $setsCount < 3 ? 0 : 50 + 5 * min($setsCount, 30);
    }

    public function isEligible(User $user, Workout $workout): bool
    {
        $awardedThatDay = XpEvent::query()
            ->join('workouts', 'workouts.id', '=', 'xp_events.source_id')
            ->where('xp_events.user_id', $user->id)
            ->where('xp_events.source', 'workout')
            ->where('workouts.id', '!=', $workout->id)
            ->whereDate('workouts.finished_at', $workout->finished_at->toDateString())
            ->count();

        return $awardedThatDay < self::DAILY_WORKOUTS;
    }

    public function award(User $user, string $source, int $sourceId, int $amount, int $quantity = 1, ?CarbonInterface $at = null): bool
    {
        try {
            $user->xpEvents()->create([
                'source' => $source,
                'source_id' => $sourceId,
                'amount' => $amount,
                'quantity' => $quantity,
                'created_at' => $at ?? now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        User::whereKey($user->id)->increment('xp_total', $amount);
        $user->xp_total = ($user->xp_total ?? 0) + $amount;

        return true;
    }

    public function recalculate(User $user): int
    {
        $total = (int) $user->xpEvents()->sum('amount');
        $user->forceFill(['xp_total' => $total])->save();

        return $total;
    }
}
