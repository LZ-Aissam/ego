<?php

use App\Models\User;
use Database\Seeders\ExerciseSeeder;

beforeEach(fn () => $this->seed(ExerciseSeeder::class));

it('installe le programme Force × Vitesse et l\'active', function () {
    $user = User::factory()->withMorphology(level: 'advanced')->create(['first_name' => 'Aïssam']);

    $this->artisan('ego:programme-force-vitesse', ['prenom' => 'Aïssam'])->assertSuccessful();

    $program = $user->fresh()->activeProgram()->with('days.exercises.exercise')->first();
    $days = $program->days->map(fn ($d) => [$d->name, $d->exercises->map(fn ($l) => [$l->exercise->slug, $l->intent, $l->sets, $l->reps_min, $l->reps_max, $l->rest_seconds])->all()])->all();

    expect($program->name)->toBe('Programme Force × Vitesse · 5 j')
        ->and($user->fresh()->training_weekdays)->toBe([1, 2, 3, 4, 5])
        ->and($days)->toBe(require database_path('data/programs/force-vitesse.php'));
});

it('refuse un prénom inconnu sans rien modifier', function () {
    $this->artisan('ego:programme-force-vitesse', ['prenom' => 'Personne'])
        ->expectsOutput('Aucun joueur nommé « Personne ».')->assertFailed();
});
