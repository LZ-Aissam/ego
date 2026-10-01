<?php

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(weightKg: 80.0)->create();
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
    $this->body = [
        'client_id' => (string) Str::uuid(),
        'started_at' => '2026-10-01T16:00:00.000Z',
        'finished_at' => '2026-10-01T16:45:00.000Z',
        'sets' => [
            ['exercise_id' => $this->bench->id, 'weight_kg' => 60, 'reps' => 8, 'done_at' => '2026-10-01T16:10:00.000Z'],
        ],
    ];
});

it('enregistre une séance et renvoie le résumé', function () {
    $this->actingAs($this->user)
        ->postJson('/workouts/sync', $this->body)
        ->assertCreated()
        ->assertJson([
            'created' => true,
            'duration_minutes' => 45,
            'volume_kg' => 480,
            'sets_count' => 1,
            'new_records' => [],
        ]);
});

it('renvoie 200 sans doublon au deuxième envoi', function () {
    $this->actingAs($this->user)->postJson('/workouts/sync', $this->body)->assertCreated();
    $this->actingAs($this->user)->postJson('/workouts/sync', $this->body)->assertOk()->assertJson(['created' => false]);

    expect(Workout::count())->toBe(1);
});

it('renvoie les records battus', function () {
    $this->actingAs($this->user)->postJson('/workouts/sync', $this->body);

    $this->body['client_id'] = (string) Str::uuid();
    $this->body['sets'][0]['weight_kg'] = 62.5;

    $this->actingAs($this->user)
        ->postJson('/workouts/sync', $this->body)
        ->assertJsonPath('new_records.0.exercise', 'Développé couché (barre)')
        ->assertJsonPath('new_records.0.weight_kg', 62.5);
});

it('refuse un contenu invalide', function (string $case, string $field) {
    // Les modifications sont définies ici et non dans le dataset : Pest exécute lui-même les closures d'un dataset.
    $mutations = [
        'client_id absent' => fn (array $b) => array_diff_key($b, ['client_id' => 1]),
        'client_id pas uuid' => fn (array $b) => [...$b, 'client_id' => 'abc'],
        'aucune série' => fn (array $b) => [...$b, 'sets' => []],
        'exercice inconnu' => function (array $b) { $b['sets'][0]['exercise_id'] = 999999; return $b; },
        'charge négative' => function (array $b) { $b['sets'][0]['weight_kg'] = -5; return $b; },
        'zéro répétition' => function (array $b) { $b['sets'][0]['reps'] = 0; return $b; },
        'fin avant début' => fn (array $b) => [...$b, 'finished_at' => '2026-10-01T15:00:00.000Z'],
    ];

    $this->actingAs($this->user)->postJson('/workouts/sync', $mutations[$case]($this->body))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    ['client_id absent', 'client_id'],
    ['client_id pas uuid', 'client_id'],
    ['aucune série', 'sets'],
    ['exercice inconnu', 'sets.0.exercise_id'],
    ['charge négative', 'sets.0.weight_kg'],
    ['zéro répétition', 'sets.0.reps'],
    ['fin avant début', 'finished_at'],
]);

it('répond 409 si la séance appartient à un autre utilisateur', function () {
    $this->actingAs(User::factory()->create())->postJson('/workouts/sync', $this->body)->assertCreated();

    $this->actingAs($this->user)->postJson('/workouts/sync', $this->body)->assertConflict();
});

it('refuse un invité', function () {
    $this->postJson('/workouts/sync', $this->body)->assertUnauthorized();
});
