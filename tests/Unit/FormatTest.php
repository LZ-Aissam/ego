<?php

use App\Support\Format;

it('formate les kilos à la française', function () {
    expect(Format::kg(62.5))->toBe('62,5 kg')
        ->and(Format::kg(60))->toBe('60 kg')
        ->and(Format::kg(1250))->toBe('1 250 kg');
});

it('formate les durées', function () {
    expect(Format::duration(52))->toBe('52 min')
        ->and(Format::duration(65))->toBe('1 h 05');
});
