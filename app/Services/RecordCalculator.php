<?php

namespace App\Services;

final class RecordCalculator
{
    public function estimatedOneRepMax(float $load, int $reps): float
    {
        if ($reps <= 1) {
            return round($load, 1);
        }

        return round($load * (1 + $reps / 30), 1);
    }

    public function effectiveLoad(float $weightKg, bool $isBodyweight, ?float $bodyweightKg): float
    {
        return $isBodyweight ? ($bodyweightKg ?? 0.0) + $weightKg : $weightKg;
    }

    public function setVolume(float $weightKg, int $reps, bool $isBodyweight, ?float $bodyweightKg): float
    {
        return round($this->effectiveLoad($weightKg, $isBodyweight, $bodyweightKg) * $reps, 1);
    }

    public function bodyweightRatio(float $loadKg, ?float $bodyweightKg): ?float
    {
        if (! $bodyweightKg) {
            return null;
        }

        return round($loadKg / $bodyweightKg, 2);
    }

    public function beats(float $weightKg, int $reps, float $recordWeightKg, int $recordReps): bool
    {
        if (abs($weightKg - $recordWeightKg) < 0.001) {
            return $reps > $recordReps;
        }

        return $weightKg > $recordWeightKg;
    }
}
