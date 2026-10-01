<?php

use App\Game\LoadPrescriber;
use App\Models\Exercise;
use App\Models\User;
use App\Services\ProgramBuilder;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(weightKg: 80.0)->create();
    $this->prescriber = app(LoadPrescriber::class);
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
});

it('prend le meilleur 1RM entre calibrage et record', function () {
    $this->user->exerciseMaxes()->create(['exercise_id' => $this->bench->id, 'e1rm_kg' => 100, 'source' => 'calibration', 'measured_at' => now()]);
    expect($this->prescriber->bestE1rm($this->user, $this->bench, 5))->toBe(100.0);

    $this->user->personalRecords()->create(['exercise_id' => $this->bench->id, 'max_weight_kg' => 100, 'max_weight_reps' => 4, 'best_e1rm_kg' => 113.3, 'achieved_at' => now()]);
    expect($this->prescriber->bestE1rm($this->user, $this->bench, 5))->toBe(113.3);
});

it('prescrit selon l\'intention', function () {
    $this->user->exerciseMaxes()->create(['exercise_id' => $this->bench->id, 'e1rm_kg' => 100, 'source' => 'calibration', 'measured_at' => now()]);

    expect($this->prescriber->prescriptionFor($this->user, $this->bench, 'speed', 3))->toBe(57.5)
        ->and($this->prescriber->prescriptionFor($this->user, $this->bench, 'max', 3))->toBe(87.5)
        ->and($this->prescriber->prescriptionFor($this->user, $this->bench, 'explosive', 3))->toBe(40.0)
        ->and($this->prescriber->prescriptionFor($this->user, Exercise::firstWhere('slug', 'pull-up'), 'max', 5))->toBeNull()
        ->and($this->prescriber->prescriptionFor($this->user, Exercise::firstWhere('slug', 'pull-up'), 'explosive', 3))->toBeNull();
});

it('repli sur la charge déduite sans 1RM connu, et rien sans aucune donnée', function () {
    $this->user->forceFill(['power_factor' => 1.0])->save();
    // suggestion débutant 80 kg × 0,5 = 40 kg ; 1RM estimé = 40 × (1 + 5/30) = 46,7 → vitesse 57,5 % = 26,8 → 27,5
    expect($this->prescriber->prescriptionFor($this->user, $this->bench, 'speed', 5))->toBe(27.5)
        ->and($this->prescriber->prescriptionFor(User::factory()->create(), $this->bench, 'speed', 5))->toBeNull();
});

it('ne stocke ni ne fait progresser que les lignes masse ou sans intention', function () {
    $program = app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'strength', 'weekdays' => [1, 3], 'equipment' => 'gym']);
    $lines = $program->days->flatMap->exercises;
    $lines->where('exercise_id', $this->bench->id)->each->update(['intent' => 'speed']);
    $this->user->forceFill(['calibrated_at' => now()])->save();

    $this->prescriber->prescribeProgram($this->user->fresh());

    expect($this->user->workingLoads()->where('exercise_id', $this->bench->id)->exists())->toBeFalse()
        ->and($this->user->workingLoads()->count())->toBeGreaterThan(0);

    $at = now()->toIso8601String();
    $workout = app(WorkoutSync::class)->sync($this->user, ['client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
        'sets' => array_fill(0, 5, ['exercise_id' => $this->bench->id, 'weight_kg' => 40, 'reps' => 5, 'done_at' => $at])])['workout'];
    expect($this->prescriber->progress($this->user, $workout))->toBe([]);
});
