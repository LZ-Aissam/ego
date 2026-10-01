<?php

namespace App\Support;

final class Sparkline
{
    /** @param list<float|int> $values */
    public static function points(array $values, int $width, int $height, int $padding = 8): string
    {
        if ($values === []) {
            return '';
        }

        $min = min($values);
        $max = max($values);
        $count = count($values);
        $innerW = $width - 2 * $padding;
        $innerH = $height - 2 * $padding;

        $points = [];
        foreach (array_values($values) as $i => $value) {
            $x = $padding + ($count === 1 ? $innerW / 2 : $innerW * $i / ($count - 1));
            $y = $max == $min ? $padding + $innerH / 2 : $padding + $innerH * (1 - ($value - $min) / ($max - $min));
            $points[] = round($x, 1).','.round($y, 1);
        }

        return implode(' ', $points);
    }
}
