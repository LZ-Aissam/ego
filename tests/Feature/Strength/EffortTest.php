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
    $this->user = User::factory()->withMorphology(weightKg: 70.0, level: 'advanced')->create();
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
    $this->user->exerciseMaxes()->create(['exercise_id' => $this->bench->id, 'e1rm_kg' => 100, 'source' => 'calibration', 'measured_at' => now()]);
    $this->user->forceFill(['calibrated_at' => now(), 'power_factor' => 1.0])->save();
    app(ProgramBuilder::class)->build($this->user, ['level' => 'advanced', 'goal' => 'pure', 'weekdays' => [1, 2, 4, 5], 'equipment' => 'gym']);
    $this->user = $this->user->fresh();
    $this->prescriber = app(LoadPrescriber::class);
    $this->top = fn () => $this->prescriber->prescriptionFor($this->user, $this->bench, 'top', 2);
    $this->heavy = fn () => (float) $this->user->workingLoads()->where('exercise_id', $this->bench->id)->value('target_kg');
});

/** @param  list<array{0: float, 1: int, 2?: ?string}>  $sets  [charge, répétitions, ressenti] */
function effortSets(User $user, array $sets): \App\Models\Workout
{
    $at = now()->toIso8601String();
    $id = Exercise::firstWhere('slug', 'bench-press')->id;

    return app(WorkoutSync::class)->sync($user, ['client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
        'sets' => array_map(fn ($s) => ['exercise_id' => $id, 'weight_kg' => $s[0], 'reps' => $s[1], 'done_at' => $at, 'effort' => $s[2] ?? null], $sets)])['workout'];
}

it('enregistre le ressenti de chaque série', function () {
    $workout = effortSets($this->user, [[92.5, 1, 'easy'], [87.5, 3, 'limit'], [87.5, 3]]);

    expect($workout->sets->pluck('effort')->all())->toBe(['easy', 'limit', null]);
});

it('refuse un ressenti inconnu', function () {
    $this->actingAs($this->user)->postJson('/workouts/sync', [
        'client_id' => (string) Str::uuid(), 'started_at' => now()->toIso8601String(), 'finished_at' => now()->toIso8601String(),
        'sets' => [['exercise_id' => $this->bench->id, 'weight_kg' => 90, 'reps' => 1, 'done_at' => now()->toIso8601String(), 'effort' => 'mega']],
    ])->assertUnprocessable()->assertJsonValidationErrors('sets.0.effort');
});

it('fait monter la série record de 2,5 kg après 2 répétitions réussies', function () {
    expect(($this->top)())->toBe(92.5);

    $notes = $this->prescriber->progress($this->user, effortSets($this->user, [[92.5, 2, 'hard']]));

    expect(($this->top)())->toBe(95.0)
        ->and($this->prescriber->prescriptionFor($this->user, $this->bench, 'speed', 2))->toBe(60.0)
        ->and(collect($notes)->pluck('type')->all())->toContain('top_up');
});

it('fait monter la série record après 1 répétition facile', function () {
    $this->prescriber->progress($this->user, effortSets($this->user, [[92.5, 1, 'easy']]));

    expect(($this->top)())->toBe(95.0);
});

it('garde la série record après 1 répétition dure ou sans ressenti, ou une série limite', function (array $set) {
    $this->prescriber->progress($this->user, effortSets($this->user, [$set]));

    expect(($this->top)())->toBe(92.5);
})->with([
    'dure' => [[92.5, 1, 'hard']],
    'sans ressenti' => [[92.5, 1]],
    'limite à 2 répétitions' => [[92.5, 2, 'limit']],
    'plus légère que prévu' => [[90.0, 2, 'easy']],
]);

it('progresse séance après séance : 3 records réussis, 3 hausses', function () {
    foreach ([92.5, 95.0, 97.5] as $kg) {
        $this->prescriber->progress($this->user, effortSets($this->user, [[$kg, 2]]));
    }

    expect(($this->top)())->toBe(100.0);
});

it('garde la série record au-dessus des séries lourdes', function () {
    $this->user->workingLoads()->where('exercise_id', $this->bench->id)->update(['target_kg' => 95]);

    expect(($this->top)())->toBe(97.5);
});

it('double la hausse des séries lourdes quand tout était facile', function () {
    $this->prescriber->progress($this->user, effortSets($this->user, array_fill(0, 4, [87.5, 3, 'easy'])));

    expect(($this->heavy)())->toBe(92.5);
});

it('garde la charge lourde quand une série réussie était limite', function () {
    $this->prescriber->progress($this->user, effortSets($this->user, [[87.5, 3, 'easy'], [87.5, 3], [87.5, 3], [87.5, 3, 'limit']]));

    expect(($this->heavy)())->toBe(87.5);
});

it('monte de 2,5 kg sans ressenti ou en dur, comme avant', function (?string $effort) {
    $this->prescriber->progress($this->user, effortSets($this->user, array_fill(0, 4, [87.5, 3, $effort])));

    expect(($this->heavy)())->toBe(90.0);
})->with([null, 'hard']);
