<?php

use App\Models\Exercise;
use App\Models\PersonalRecord;
use App\Models\User;
use App\Models\Workout;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(weightKg: 80.0)->create();
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
    $this->pullUp = Exercise::firstWhere('slug', 'pull-up');
    $this->sync = app(WorkoutSync::class);
});

function payload(array $sets, ?string $clientId = null): array
{
    return [
        'client_id' => $clientId ?? (string) Str::uuid(),
        'started_at' => '2026-10-01T16:00:00.000Z',
        'finished_at' => '2026-10-01T17:05:00.000Z',
        'sets' => array_map(fn ($s) => [
            'exercise_id' => $s[0], 'weight_kg' => $s[1], 'reps' => $s[2], 'done_at' => '2026-10-01T16:10:00.000Z',
        ], $sets),
    ];
}

it('enregistre la séance, ses séries et le poids de corps', function () {
    $result = $this->sync->sync($this->user, payload([[$this->bench->id, 60, 8], [$this->bench->id, 60, 7]]));

    $workout = $result['workout'];
    expect($result['created'])->toBeTrue()
        ->and($workout->sets)->toHaveCount(2)
        ->and($workout->sets->pluck('position')->all())->toBe([1, 2])
        ->and($workout->bodyweight_kg)->toBe(80.0)
        ->and($workout->durationMinutes())->toBe(65)
        ->and($workout->volume())->toBe(900.0);
});

it('convertit les heures en heure de Paris', function () {
    $workout = $this->sync->sync($this->user, payload([[$this->bench->id, 60, 8]]))['workout'];

    expect($workout->fresh()->started_at->format('H:i'))->toBe('18:00');
});

it('est idempotent sur client_id', function () {
    $data = payload([[$this->bench->id, 60, 8]]);
    $this->sync->sync($this->user, $data);
    $second = $this->sync->sync($this->user, $data);

    expect($second['created'])->toBeFalse()
        ->and($second['newRecords'])->toBe([])
        ->and(Workout::count())->toBe(1);
});

it('renvoie la séance existante si un envoi concurrent l\'a créée entre-temps', function () {
    $data = payload([[$this->bench->id, 60, 8]]);
    // Simule l'envoi concurrent : la ligne existe déjà quand l'insertion est tentée.
    $this->user->workouts()->create(['client_id' => $data['client_id'], 'started_at' => now(), 'finished_at' => now()]);

    $result = $this->sync->sync($this->user, $data);

    expect($result['created'])->toBeFalse()
        ->and(Workout::count())->toBe(1);
});

it('refuse un client_id déjà utilisé par un autre utilisateur', function () {
    $data = payload([[$this->bench->id, 60, 8]]);
    $this->sync->sync(User::factory()->create(), $data);

    $this->sync->sync($this->user, $data);
})->throws(App\Services\WorkoutOwnedByAnotherUser::class);

it('crée un record à la première séance sans le signaler', function () {
    $result = $this->sync->sync($this->user, payload([[$this->bench->id, 60, 8], [$this->bench->id, 62.5, 5]]));

    $record = PersonalRecord::sole();
    expect($result['newRecords'])->toBe([])
        ->and($record->max_weight_kg)->toBe(62.5)
        ->and($record->max_weight_reps)->toBe(5)
        ->and($record->best_e1rm_kg)->toBe(76.0);
});

it('signale un record battu', function () {
    $this->sync->sync($this->user, payload([[$this->bench->id, 60, 8]]));
    $result = $this->sync->sync($this->user, payload([[$this->bench->id, 60, 9]]));

    expect($result['newRecords'])->toHaveCount(1)
        ->and($result['newRecords'][0]->max_weight_reps)->toBe(9);
});

it('ne signale rien si le record n\'est pas battu', function () {
    $this->sync->sync($this->user, payload([[$this->bench->id, 60, 8]]));
    $result = $this->sync->sync($this->user, payload([[$this->bench->id, 55, 12]]));

    expect($result['newRecords'])->toBe([])
        ->and(PersonalRecord::sole()->max_weight_kg)->toBe(60.0);
});

it('compte le poids de corps dans le volume et le 1RM des exercices au poids du corps', function () {
    $workout = $this->sync->sync($this->user, payload([[$this->pullUp->id, 0, 10]]))['workout'];

    expect($workout->volume())->toBe(800.0)
        ->and(PersonalRecord::sole()->best_e1rm_kg)->toBe(106.7);
});
