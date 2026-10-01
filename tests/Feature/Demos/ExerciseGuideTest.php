<?php

use App\Models\Exercise;
use App\Support\ExerciseGuide;
use Database\Seeders\ExerciseSeeder;

it('a une fiche complète pour chaque exercice du catalogue', function () {
    $this->seed(ExerciseSeeder::class);

    foreach (Exercise::all() as $exercise) {
        $guide = ExerciseGuide::for($exercise->slug);
        expect($guide, $exercise->slug)->not->toBeNull()
            ->and($guide['muscles'], $exercise->slug)->not->toBeEmpty()
            ->and($guide['tempo'], $exercise->slug)->not->toBeEmpty()
            ->and(count($guide['steps']), $exercise->slug)->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(4)
            ->and($guide['mistake'], $exercise->slug)->not->toBeEmpty();
    }
});

it('ne renvoie rien pour un exercice inconnu', function () {
    expect(ExerciseGuide::for('inconnu'))->toBeNull();
});
