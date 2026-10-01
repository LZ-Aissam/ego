<?php

namespace App\Support;

final class Format
{
    public static function kg(float $kg): string
    {
        $decimals = fmod($kg, 1.0) === 0.0 ? 0 : 1;

        return number_format($kg, $decimals, ',', ' ').' kg';
    }

    public static function duration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' min';
        }

        return intdiv($minutes, 60).' h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }
}
