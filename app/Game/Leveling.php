<?php

namespace App\Game;

final class Leveling
{
    private const RANKS = [60 => 'S', 45 => 'A', 30 => 'B', 20 => 'C', 10 => 'D', 1 => 'E'];

    public static function xpToNext(int $level): int
    {
        return (int) round(100 * $level ** 1.3);
    }

    public static function cumulative(int $level): int
    {
        $total = 0;
        for ($n = 1; $n < $level; $n++) {
            $total += self::xpToNext($n);
        }

        return $total;
    }

    public static function levelForXp(int $xp): int
    {
        $level = 1;
        $next = self::xpToNext(1);
        while ($xp >= $next) {
            $xp -= $next;
            $level++;
            $next = self::xpToNext($level);
        }

        return $level;
    }

    public static function rankForLevel(int $level): string
    {
        foreach (self::RANKS as $min => $rank) {
            if ($level >= $min) {
                return $rank;
            }
        }

        return 'E';
    }

    /** @return array{level: int, rank: string, into: int, needed: int} */
    public static function progress(int $xp): array
    {
        $level = self::levelForXp($xp);

        return [
            'level' => $level,
            'rank' => self::rankForLevel($level),
            'into' => $xp - self::cumulative($level),
            'needed' => self::xpToNext($level),
        ];
    }
}
