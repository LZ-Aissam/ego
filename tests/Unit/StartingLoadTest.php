<?php

use App\Models\Exercise;
use App\Models\User;
use App\Services\StartingLoad;
use Database\Seeders\ExerciseSeeder;

function weighted(float $maleBeginner): Exercise
{
    return new Exercise(['is_bodyweight' => false, 'start_ratio' => ExerciseSeeder::ratios($maleBeginner)]);
}

function lifter(?string $sex, ?float $weight, ?string $level = 'beginner'): User
{
    return new User(['sex' => $sex, 'weight_kg' => $weight, 'level' => $level]);
}

beforeEach(fn () => $this->load = new StartingLoad);

it('suggère poids de corps × ratio, arrondi à 2,5 kg', function () {
    expect($this->load->suggest(weighted(0.5), lifter('male', 80)))->toBe(40.0)
        ->and($this->load->suggest(weighted(0.5), lifter('female', 60)))->toBe(17.5)
        ->and($this->load->suggest(weighted(0.5), lifter('male', 80, 'advanced')))->toBe(80.0);
});

it('considère un niveau inconnu comme débutant', function () {
    expect($this->load->suggest(weighted(0.5), lifter('male', 80, null)))->toBe(40.0);
});

it('ne descend jamais sous 2,5 kg', function () {
    expect($this->load->suggest(weighted(0.05), lifter('female', 50)))->toBe(2.5);
});

it('ne suggère rien sans morphologie ou pour le poids du corps', function () {
    expect($this->load->suggest(weighted(0.5), lifter(null, 80)))->toBeNull()
        ->and($this->load->suggest(weighted(0.5), lifter('male', null)))->toBeNull()
        ->and($this->load->suggest(new Exercise(['is_bodyweight' => true, 'start_ratio' => null]), lifter('male', 80)))->toBeNull();
});
