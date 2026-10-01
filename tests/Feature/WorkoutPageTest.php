<?php

use App\Models\Exercise;
use App\Models\User;
use App\Services\WorkoutBootData;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(sex: 'male', weightKg: 80.0)->create();
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
});

function doWorkout(User $user, array $sets, string $finishedAt): void
{
    app(WorkoutSync::class)->sync($user, [
        'client_id' => (string) Str::uuid(),
        'started_at' => $finishedAt,
        'finished_at' => $finishedAt,
        'sets' => array_map(fn ($s) => ['exercise_id' => $s[0], 'weight_kg' => $s[1], 'reps' => $s[2], 'done_at' => $finishedAt], $sets),
    ]);
}

it('fournit la bibliothèque avec les charges suggérées', function () {
    $data = app(WorkoutBootData::class)->for($this->user);
    $bench = collect($data['exercises'])->firstWhere('id', $this->bench->id);

    expect($data['restSeconds'])->toBe(90)
        ->and($bench['suggestedKg'])->toBe(40.0)
        ->and($bench['isBodyweight'])->toBeFalse();
});

it('fournit les séries de la dernière séance de chaque exercice', function () {
    doWorkout($this->user, [[$this->bench->id, 50, 10]], '2026-09-20T10:00:00Z');
    doWorkout($this->user, [[$this->bench->id, 60, 8], [$this->bench->id, 57.5, 8]], '2026-09-27T10:00:00Z');

    $last = app(WorkoutBootData::class)->for($this->user)['lastTime'][$this->bench->id];

    expect($last)->toBe([['weightKg' => 60.0, 'reps' => 8], ['weightKg' => 57.5, 'reps' => 8]]);
});

it('n\'utilise pas les séances des autres', function () {
    doWorkout(User::factory()->create(), [[$this->bench->id, 100, 5]], '2026-09-27T10:00:00Z');

    expect(app(WorkoutBootData::class)->for($this->user)['lastTime'])->toBe([]);
});

it('affiche l\'écran de séance avec les données de démarrage', function () {
    $this->actingAs($this->user)
        ->get('/workout')
        ->assertOk()
        ->assertSee('id="workout-boot"', false)
        ->assertSee('x-data="workout"', false)
        ->assertSee('Développé couché (barre)');
});

it('affiche l\'écran de séance au style Système', function () {
    $this->actingAs($this->user)->get('/workout')
        ->assertSee('class="sys-ring"', false)
        ->assertSee('VALIDER LA SÉRIE')
        ->assertSee('id="sys-notifications-data"', false);
});
