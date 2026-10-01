<?php

namespace App\Services;

use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Remise à zéro complète d'un joueur : séances, records, force, jeu, quêtes, charges et programme.
 * Le compte (prénom, date de naissance), la morphologie et les groupes Crew sont gardés.
 */
final class AccountReset
{
    public function reset(User $user): void
    {
        DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $user->refresh();                   // sinon une copie périmée en mémoire ne serait pas réécrite

            $user->forceFill([
                'active_program_id' => null,
                'xp_total' => 0, 'equipped_title' => null, 'streak_days' => 0, 'best_streak' => 0,
                'paused_at' => null, 'paused_until' => null,
                'calibrated_at' => null, 'calibration_snoozed_until' => null, 'power_factor' => null,
                'level' => null, 'goal' => null, 'days_per_week' => null, 'equipment' => null, 'training_weekdays' => null,
            ])->save();

            $user->workouts()->delete();
            $user->personalRecords()->delete();
            $user->exerciseMaxes()->delete();
            $user->workingLoads()->delete();
            $user->quests()->delete();
            $user->programs()->delete();
            $user->xpEvents()->delete();
            $user->titles()->delete();
            SystemNotification::where('user_id', $user->id)->delete();
        });
    }
}
