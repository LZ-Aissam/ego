<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\User;

final class StartingLoad
{
    public function suggest(Exercise $exercise, User $user): ?float
    {
        if ($exercise->is_bodyweight || ! $exercise->start_ratio || ! $user->sex || ! $user->weight_kg) {
            return null;
        }

        $ratio = $exercise->start_ratio[$user->sex][$user->level ?? 'beginner'] ?? null;

        if ($ratio === null) {
            return null;
        }

        return max(2.5, round($user->weight_kg * $ratio / 2.5) * 2.5);
    }
}
