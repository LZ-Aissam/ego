<?php

use App\Game\Leveling;

it('suit la courbe 100 × n^1,3', function () {
    expect(Leveling::xpToNext(1))->toBe(100)
        ->and(Leveling::xpToNext(2))->toBe(246)
        ->and(Leveling::xpToNext(10))->toBe((int) round(100 * 10 ** 1.3));
});

it('cumule l\'XP nécessaire pour atteindre un niveau', function () {
    expect(Leveling::cumulative(1))->toBe(0)
        ->and(Leveling::cumulative(2))->toBe(100)
        ->and(Leveling::cumulative(3))->toBe(346)
        ->and(Leveling::cumulative(10))->toBe(array_sum(array_map(fn ($n) => Leveling::xpToNext($n), range(1, 9))));
});

it('trouve le niveau aux frontières', function () {
    expect(Leveling::levelForXp(0))->toBe(1)
        ->and(Leveling::levelForXp(99))->toBe(1)
        ->and(Leveling::levelForXp(100))->toBe(2)
        ->and(Leveling::levelForXp(345))->toBe(2)
        ->and(Leveling::levelForXp(346))->toBe(3)
        ->and(Leveling::levelForXp(Leveling::cumulative(10) - 1))->toBe(9)
        ->and(Leveling::levelForXp(Leveling::cumulative(10)))->toBe(10);
});

it('attribue les rangs E à S', function (int $level, string $rank) {
    expect(Leveling::rankForLevel($level))->toBe($rank);
})->with([[1, 'E'], [9, 'E'], [10, 'D'], [19, 'D'], [20, 'C'], [29, 'C'], [30, 'B'], [44, 'B'], [45, 'A'], [59, 'A'], [60, 'S'], [99, 'S']]);

it('résume la progression', function () {
    expect(Leveling::progress(150))->toBe(['level' => 2, 'rank' => 'E', 'into' => 50, 'needed' => 246]);
});
