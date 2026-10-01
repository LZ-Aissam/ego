<?php

use App\Models\Exercise;
use App\Models\User;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(fn () => $this->seed(ExerciseSeeder::class));

it('stocke le profil de force et la charge prescrite d\'un joueur', function () {
    $user = User::factory()->create();
    $bench = Exercise::firstWhere('slug', 'bench-press');

    $user->exerciseMaxes()->create(['exercise_id' => $bench->id, 'e1rm_kg' => 82.5, 'source' => 'calibration', 'measured_at' => now()]);
    $user->workingLoads()->create(['exercise_id' => $bench->id, 'target_kg' => 57.5, 'fail_streak' => 0]);
    $user->forceFill(['calibrated_at' => now(), 'power_factor' => 1.25])->save();

    $user = $user->fresh();
    expect($user->exerciseMaxes->first()->e1rm_kg)->toBe(82.5)
        ->and($user->workingLoads->first()->target_kg)->toBe(57.5)
        ->and($user->workingLoads->first()->exercise->slug)->toBe('bench-press')
        ->and($user->power_factor)->toBe(1.25)
        ->and($user->calibrated_at)->not->toBeNull();
});

it('n\'a qu\'une charge prescrite par exercice', function () {
    $user = User::factory()->create();
    $bench = Exercise::firstWhere('slug', 'bench-press');
    $user->workingLoads()->create(['exercise_id' => $bench->id, 'target_kg' => 50, 'fail_streak' => 0]);

    $user->workingLoads()->create(['exercise_id' => $bench->id, 'target_kg' => 60, 'fail_streak' => 0]);
})->throws(UniqueConstraintViolationException::class);
