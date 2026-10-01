<?php

use App\Game\LoadPrescriber;
use App\Models\Exercise;
use App\Services\ProgramGenerator;
use App\Support\ExerciseGuide;
use Database\Seeders\ExerciseSeeder;

it('ajoute les exercices explosifs avec leur fiche', function () {
    $this->seed(ExerciseSeeder::class);

    foreach (['push-press', 'clap-push-up', 'box-jump', 'broad-jump', 'jump-squat'] as $slug) {
        expect(Exercise::firstWhere('slug', $slug), $slug)->not->toBeNull()
            ->and(ExerciseGuide::for($slug), $slug)->not->toBeNull();
    }
    expect(Exercise::firstWhere('slug', 'push-press')->is_bodyweight)->toBeFalse()
        ->and(Exercise::firstWhere('slug', 'box-jump')->slots)->toBe(['jump']);
});

it('compte les sauts en bas du corps et ne les met pas dans les programmes générés', function () {
    expect(LoadPrescriber::LOWER_BODY_SLOTS)->toContain('jump');
    foreach (ProgramGenerator::DAY_SLOTS as $slots) {
        expect($slots)->not->toContain('jump');
    }
});
