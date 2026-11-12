<?php

use App\Game\GameDay;
use Illuminate\Support\Carbon;

it('fait finir la journée de jeu à 4 h du matin', function (string $at, string $day) {
    expect(GameDay::of(Carbon::parse($at))->toDateString())->toBe($day);
})->with([
    ['2026-10-05 23:59', '2026-10-05'],
    ['2026-10-06 00:00', '2026-10-05'],
    ['2026-10-06 03:59', '2026-10-05'],
    ['2026-10-06 04:00', '2026-10-06'],
    ['2026-10-05T22:30:00Z', '2026-10-05'],                      // 00:30 le 6 à Paris (heure d'été)
]);

it('donne les bornes des séances d\'un jour de jeu', function () {
    [$from, $to] = GameDay::bounds(Carbon::parse('2026-10-05'));

    expect($from->toDateTimeString())->toBe('2026-10-05 04:00:00')
        ->and($to->toDateTimeString())->toBe('2026-10-06 04:00:00');
});

it('donne le jour de jeu courant', function () {
    $this->travelTo(Carbon::parse('2026-10-06 00:30'));
    expect(GameDay::today()->toDateString())->toBe('2026-10-05');

    $this->travelTo(Carbon::parse('2026-10-06 04:00'));
    expect(GameDay::today()->toDateString())->toBe('2026-10-06');
});
