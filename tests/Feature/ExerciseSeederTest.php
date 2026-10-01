<?php

use App\Models\Exercise;
use Database\Seeders\ExerciseSeeder;

beforeEach(fn () => $this->seed(ExerciseSeeder::class));

it('charge au moins 55 exercices avec des slugs uniques', function () {
    expect(Exercise::count())->toBeGreaterThanOrEqual(55)
        ->and(Exercise::distinct()->count('slug'))->toBe(Exercise::count());
});

it('est idempotent', function () {
    $count = Exercise::count();
    $this->seed(ExerciseSeeder::class);
    expect(Exercise::count())->toBe($count);
});

it('couvre chaque emplacement avec un exercice de salle', function (string $slot) {
    $found = Exercise::all()->contains(fn ($e) => in_array($slot, $e->slots) && in_array('gym', $e->equipment));
    expect($found)->toBeTrue();
})->with(Exercise::SLOTS);

it('couvre les mouvements de base avec haltères et au poids du corps', function (string $slot, string $equipment) {
    $found = Exercise::all()->contains(fn ($e) => in_array($slot, $e->slots) && in_array($equipment, $e->equipment));
    expect($found)->toBeTrue();
})->with(function () {
    foreach (['horizontal_push', 'vertical_push', 'horizontal_pull', 'vertical_pull', 'squat', 'hinge', 'lunge', 'core'] as $slot) {
        foreach (['dumbbells', 'bodyweight'] as $equipment) {
            yield "$slot / $equipment" => [$slot, $equipment];
        }
    }
});

it('calcule les ratios de départ', function () {
    $bench = Exercise::firstWhere('slug', 'bench-press');

    expect($bench->is_bodyweight)->toBeFalse()
        ->and($bench->start_ratio['male']['beginner'])->toBe(0.5)
        ->and($bench->start_ratio['male']['advanced'])->toEqual(1.0)
        ->and($bench->start_ratio['female']['beginner'])->toBe(0.3);

    $pullUp = Exercise::firstWhere('slug', 'pull-up');
    expect($pullUp->is_bodyweight)->toBeTrue()->and($pullUp->start_ratio)->toBeNull();
});
