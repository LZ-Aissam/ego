<?php

use App\Game\Intent;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;

it('décrit chaque intention', function () {
    expect(Intent::KEYS)->toBe(['speed', 'max', 'explosive', 'top', 'mass'])
        ->and(Intent::badge('speed'))->toBe('⚡ VITESSE')
        ->and(Intent::badge(null))->toBe('')
        ->and(Intent::cue('max'))->toBe('Lourd : peu de répétitions, toutes parfaites, repos complet.')
        ->and(Intent::META['speed']['pct'])->toBe(0.575);
});

it('porte l\'intention dans les objectifs de la quête', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->withMorphology()->create();
    $program = app(ProgramBuilder::class)->build($user, ['level' => 'beginner', 'goal' => 'strength', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    $line = $program->days->first()->exercises->first();
    $line->update(['intent' => 'speed']);

    $objectives = $program->days->first()->fresh()->objectives();
    expect($objectives[0]['intent'])->toBe('speed')->and($objectives[1]['intent'])->toBeNull();
});
