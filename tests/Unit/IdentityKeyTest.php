<?php

use App\Support\IdentityKey;

it('normalise le prénom : minuscules, sans accents, espaces fusionnés', function (string $input, string $expected) {
    expect(IdentityKey::normalizeFirstName($input))->toBe($expected);
})->with([
    ['Élise', 'elise'],
    ['ELISE', 'elise'],
    ['  élise  ', 'elise'],
    ['Jean   Marc', 'jean marc'],
    ['Zoë', 'zoe'],
    ['José', 'jose'],
]);

it('construit la clé prénom|date', function () {
    expect(IdentityKey::make('Élise', '2001-04-12'))->toBe('elise|2001-04-12');
});

it('donne la même clé quelle que soit la casse ou les accents', function () {
    expect(IdentityKey::make('ELISE ', '2001-04-12'))
        ->toBe(IdentityKey::make('élise', '2001-04-12'));
});

it('garde le prénom affiché tel que saisi, espaces nettoyés', function () {
    expect(IdentityKey::displayFirstName('  Jean   Marc '))->toBe('Jean Marc');
});
