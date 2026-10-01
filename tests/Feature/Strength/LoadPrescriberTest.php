<?php

use App\Game\LoadPrescriber;
use App\Models\Exercise;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->prescriber = app(LoadPrescriber::class);
});

it('calcule la charge de départ avec 2 répétitions en réserve, arrondie à 2,5 kg en dessous', function (float $e1rm, int $repsMax, float $expected) {
    expect($this->prescriber->startingLoad($e1rm, $repsMax))->toBe($expected);
})->with([
    [100, 5, 80.0],
    [100, 10, 70.0],
    [82.5, 10, 57.5],
    [5, 15, 2.5],
]);

it('déduit la charge d\'un exercice non testé avec le facteur de puissance borné', function () {
    $user = User::factory()->withMorphology(sex: 'male', weightKg: 80.0, level: 'beginner')->create();
    $fly = Exercise::firstWhere('slug', 'dumbbell-fly');          // suggestion : 80 × 0,08 = 6,4 → arrondi 7,5 kg

    $user->forceFill(['power_factor' => 1.5])->save();
    expect($this->prescriber->derivedLoad($fly, $user->fresh()))->toBe(12.5);   // 7,5 × 1,5 = 11,25 → 12,5

    $user->forceFill(['power_factor' => 9])->save();
    expect($this->prescriber->derivedLoad($fly, $user->fresh()))->toBe(15.0);   // facteur borné à 2

    $user->forceFill(['power_factor' => null])->save();
    expect($this->prescriber->derivedLoad($fly, $user->fresh()))->toBe(7.5);

    expect($this->prescriber->derivedLoad(Exercise::firstWhere('slug', 'pull-up'), $user))->toBeNull()
        ->and($this->prescriber->derivedLoad($fly, User::factory()->create()))->toBeNull();
});

it('prescrit tout le programme : 1RM mesuré si connu, sinon déduit, rien au poids du corps', function () {
    $user = User::factory()->withMorphology(sex: 'male', weightKg: 80.0)->create();
    $program = app(ProgramBuilder::class)->build($user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3], 'equipment' => 'gym']);
    $squat = $program->days->first()->exercises->first();       // squat, 3 × 6–10
    $user->exerciseMaxes()->create(['exercise_id' => $squat->exercise_id, 'e1rm_kg' => 100, 'source' => 'calibration', 'measured_at' => now()]);
    $user->forceFill(['power_factor' => 1.0])->save();

    $this->prescriber->prescribeProgram($user->fresh());

    $loads = $user->workingLoads()->pluck('target_kg', 'exercise_id');
    expect($loads[$squat->exercise_id])->toBe(70.0)
        ->and($loads->count())->toBe($program->days->flatMap->exercises->filter(fn ($l) => ! $l->exercise->is_bodyweight)->unique('exercise_id')->count());
});

it('ne touche pas aux charges existantes sauf celles à recalculer', function () {
    $user = User::factory()->withMorphology()->create();
    $program = app(ProgramBuilder::class)->build($user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3], 'equipment' => 'gym']);
    $line = $program->days->first()->exercises->first();
    $user->workingLoads()->create(['exercise_id' => $line->exercise_id, 'target_kg' => 99, 'fail_streak' => 1]);

    $this->prescriber->prescribeProgram($user);
    expect($user->workingLoads()->where('exercise_id', $line->exercise_id)->value('target_kg'))->toBe(99.0);

    $user->exerciseMaxes()->create(['exercise_id' => $line->exercise_id, 'e1rm_kg' => 100, 'source' => 'calibration', 'measured_at' => now()]);
    $this->prescriber->prescribeProgram($user, overwrite: [$line->exercise_id]);
    $load = $user->workingLoads()->where('exercise_id', $line->exercise_id)->first();
    expect($load->target_kg)->toBe(70.0)->and($load->fail_streak)->toBe(0);
});
