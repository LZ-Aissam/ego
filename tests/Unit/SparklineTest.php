<?php

use App\Support\Sparkline;

it('répartit les points sur la largeur et inverse l\'axe vertical', function () {
    expect(Sparkline::points([10, 20, 30], 100, 50, 0))->toBe('0,50 50,25 100,0');
});

it('centre une série constante', function () {
    expect(Sparkline::points([60, 60], 100, 50, 0))->toBe('0,25 100,25');
});

it('renvoie une chaîne vide sans point', function () {
    expect(Sparkline::points([], 100, 50))->toBe('');
});
