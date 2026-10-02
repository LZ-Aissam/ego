<?php

use App\Game\Intent;
use App\Game\LoadPrescriber;
use App\Models\Exercise;
use App\Models\User;
use App\Services\ProgramBuilder;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(weightKg: 70.0, level: 'advanced')->create();
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
    $this->user->exerciseMaxes()->create(['exercise_id' => $this->bench->id, 'e1rm_kg' => 100, 'source' => 'calibration', 'measured_at' => now()]);
    $this->user->forceFill(['calibrated_at' => now(), 'power_factor' => 1.0])->save();
    app(ProgramBuilder::class)->build($this->user, ['level' => 'advanced', 'goal' => 'pure', 'weekdays' => [1, 2, 4, 5], 'equipment' => 'gym']);
    $this->user = $this->user->fresh();
    $this->prescriber = app(LoadPrescriber::class);
});

/** @param  list<array{0: float, 1: int}>  $sets  [charge, répétitions] */
function benchSets(User $user, array $sets): \App\Models\Workout
{
    $at = now()->toIso8601String();
    $id = Exercise::firstWhere('slug', 'bench-press')->id;

    return app(WorkoutSync::class)->sync($user, ['client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
        'sets' => array_map(fn ($s) => ['exercise_id' => $id, 'weight_kg' => $s[0], 'reps' => $s[1], 'done_at' => $at], $sets)])['workout'];
}

function benchTarget(User $user): array
{
    $load = $user->workingLoads()->where('exercise_id', Exercise::firstWhere('slug', 'bench-press')->id)->first();

    return [(float) $load->target_kg, $load->fail_streak];
}

it('prescrit la série record à 92,5 % du 1RM, arrondie vers le bas', function () {
    expect($this->prescriber->prescriptionFor($this->user, $this->bench, 'top', 2))->toBe(92.5)
        ->and(Intent::badge('top'))->toBe('🏆 RECORD')
        ->and(Intent::cue('top'))->toContain('1 à 2 répétitions')
        ->and(benchTarget($this->user))->toBe([87.5, 0]);
});

it('fait monter la charge lourde quand la série record est suivie de séries réussies', function () {
    $this->prescriber->progress($this->user, benchSets($this->user, [[92.5, 1], [87.5, 3], [87.5, 3], [87.5, 3], [87.5, 3]]));

    expect(benchTarget($this->user))->toBe([90.0, 0]);
});

it('ne compte pas une série record seule comme un échec', function () {
    $this->prescriber->progress($this->user, benchSets($this->user, [[92.5, 1]]));
    $this->prescriber->progress($this->user, benchSets($this->user, [[92.5, 1]]));

    expect(benchTarget($this->user))->toBe([87.5, 0]);
});
