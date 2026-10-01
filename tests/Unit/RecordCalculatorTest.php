<?php

use App\Services\RecordCalculator;

beforeEach(fn () => $this->calc = new RecordCalculator);

it('estime le 1RM avec Epley', function () {
    expect($this->calc->estimatedOneRepMax(100, 5))->toBe(116.7)
        ->and($this->calc->estimatedOneRepMax(60, 10))->toBe(80.0);
});

it('garde la charge telle quelle pour 1 répétition', function () {
    expect($this->calc->estimatedOneRepMax(140, 1))->toBe(140.0);
});

it('ajoute le poids de corps pour un exercice au poids du corps', function () {
    expect($this->calc->effectiveLoad(10, true, 80))->toBe(90.0)
        ->and($this->calc->effectiveLoad(10, true, null))->toBe(10.0)
        ->and($this->calc->effectiveLoad(60, false, 80))->toBe(60.0);
});

it('calcule le volume d\'une série', function () {
    expect($this->calc->setVolume(60, 8, false, 80))->toBe(480.0)
        ->and($this->calc->setVolume(0, 10, true, 75))->toBe(750.0)
        ->and($this->calc->setVolume(0, 10, true, null))->toBe(0.0);
});

it('calcule le ratio au poids de corps', function () {
    expect($this->calc->bodyweightRatio(100, 80))->toBe(1.25)
        ->and($this->calc->bodyweightRatio(100, null))->toBeNull()
        ->and($this->calc->bodyweightRatio(100, 0))->toBeNull();
});

it('compare les performances : charge d\'abord, puis répétitions', function () {
    expect($this->calc->beats(62.5, 5, 60, 8))->toBeTrue()
        ->and($this->calc->beats(60, 9, 60, 8))->toBeTrue()
        ->and($this->calc->beats(60, 8, 60, 8))->toBeFalse()
        ->and($this->calc->beats(57.5, 12, 60, 8))->toBeFalse();
});
