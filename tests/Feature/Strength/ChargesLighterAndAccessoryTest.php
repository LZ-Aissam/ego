<?php

use App\Game\GameDay;
use App\Game\LoadPrescriber;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkingLoad;
use App\Services\ProgramBuilder;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(weightKg: 80.0)->create();
});

/** @param list<array{0:int,1:float,2:int}> $sets [exercise_id, kg, reps] */
function liftSets(User $user, array $sets): array
{
    $at = now()->toIso8601String();
    $sync = app(WorkoutSync::class)->sync($user, [
        'client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
        'sets' => array_map(fn ($s) => ['exercise_id' => $s[0], 'weight_kg' => $s[1], 'reps' => $s[2], 'done_at' => $at], $sets),
    ]);

    return app(LoadPrescriber::class)->progress($user, $sync['workout']);
}

function loadRow(User $user, int $exerciseId): array
{
    $l = WorkingLoad::where('user_id', $user->id)->where('exercise_id', $exerciseId)->first();

    return [$l->target_kg, $l->fail_streak];
}

// ── Fix #2 : moins lourd mais plus de reps n'est pas un échec si la meilleure série vaut la cible ──

it('ne compte pas comme un échec une séance plus légère dont la meilleure série vaut la cible', function () {
    $program = app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3], 'equipment' => 'gym']);
    $squat = $program->days->flatMap->exercises->firstWhere('slot', 'squat');     // 3 × 6–10
    $this->user->workingLoads()->create(['exercise_id' => $squat->exercise_id, 'target_kg' => 60, 'fail_streak' => 0]);

    // 55 kg × 10 : 1RM estimé 73,3 ≥ 60 × 6 (72) → la forme est là, pas un échec, la charge tient.
    liftSets($this->user, array_fill(0, 3, [$squat->exercise_id, 55, 10]));

    expect(loadRow($this->user, $squat->exercise_id))->toBe([60.0, 0]);
});

it('compte quand même un échec quand la séance plus légère reste sous la cible', function () {
    $program = app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3], 'equipment' => 'gym']);
    $squat = $program->days->flatMap->exercises->firstWhere('slot', 'squat');
    $this->user->workingLoads()->create(['exercise_id' => $squat->exercise_id, 'target_kg' => 60, 'fail_streak' => 0]);

    // 50 kg × 8 : 1RM estimé 63,3 < 72 → trop léger pour la cible, c'est un échec.
    liftSets($this->user, array_fill(0, 3, [$squat->exercise_id, 50, 8]));

    expect(loadRow($this->user, $squat->exercise_id))->toBe([60.0, 1]);
});

// ── Fix #3 : un jour accessoire ne juge pas la charge d'un jour lourd ──

it('ne décharge pas la charge lourde quand l\'exercice est fait en accessoire ce jour-là', function () {
    $bench = Exercise::firstWhere('slug', 'bench-press');
    $program = $this->user->programs()->create(['name' => 'Test', 'split' => 'custom', 'params' => ['goal' => 'pure', 'custom' => true, 'weekdays' => [1, 4]]]);
    $heavy = $program->days()->create(['position' => 1, 'name' => 'Lourd', 'type' => 'push']);
    $heavy->exercises()->create(['position' => 1, 'exercise_id' => $bench->id, 'slot' => 'horizontal_push', 'sets' => 5, 'reps_min' => 3, 'reps_max' => 5, 'rest_seconds' => 180, 'intent' => 'max']);
    $acc = $program->days()->create(['position' => 2, 'name' => 'Accessoire', 'type' => 'push']);
    $acc->exercises()->create(['position' => 1, 'exercise_id' => $bench->id, 'slot' => 'horizontal_push', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 90, 'intent' => null]);
    $this->user->forceFill(['active_program_id' => $program->id, 'training_weekdays' => [1, 4]])->save();
    $this->user->workingLoads()->create(['exercise_id' => $bench->id, 'target_kg' => 100, 'fail_streak' => 0]);

    // Quête du jour = jour accessoire (intention nulle).
    $this->user->quests()->create(['date' => GameDay::today()->toDateString(), 'type' => 'training', 'title' => 'Accessoire', 'status' => 'pending', 'objectives' => $acc->objectives()]);

    // Bench fait léger en accessoire : ne doit pas toucher la charge lourde.
    liftSets($this->user, array_fill(0, 3, [$bench->id, 60, 12]));

    expect(loadRow($this->user, $bench->id))->toBe([100.0, 0]);
});

it('juge bien la charge lourde le jour lourd', function () {
    $bench = Exercise::firstWhere('slug', 'bench-press');
    $program = $this->user->programs()->create(['name' => 'Test', 'split' => 'custom', 'params' => ['goal' => 'pure', 'custom' => true, 'weekdays' => [1, 4]]]);
    $heavy = $program->days()->create(['position' => 1, 'name' => 'Lourd', 'type' => 'push']);
    $heavy->exercises()->create(['position' => 1, 'exercise_id' => $bench->id, 'slot' => 'horizontal_push', 'sets' => 5, 'reps_min' => 3, 'reps_max' => 5, 'rest_seconds' => 180, 'intent' => 'max']);
    $acc = $program->days()->create(['position' => 2, 'name' => 'Accessoire', 'type' => 'push']);
    $acc->exercises()->create(['position' => 1, 'exercise_id' => $bench->id, 'slot' => 'horizontal_push', 'sets' => 3, 'reps_min' => 10, 'reps_max' => 15, 'rest_seconds' => 90, 'intent' => null]);
    $this->user->forceFill(['active_program_id' => $program->id, 'training_weekdays' => [1, 4]])->save();
    $this->user->workingLoads()->create(['exercise_id' => $bench->id, 'target_kg' => 100, 'fail_streak' => 0]);

    // Quête du jour = jour lourd (force max).
    $this->user->quests()->create(['date' => GameDay::today()->toDateString(), 'type' => 'training', 'title' => 'Lourd', 'status' => 'pending', 'objectives' => $heavy->objectives()]);

    // Trop léger le jour lourd : c'est bien un échec.
    liftSets($this->user, array_fill(0, 5, [$bench->id, 60, 4]));

    expect(loadRow($this->user, $bench->id))->toBe([100.0, 1]);
});
