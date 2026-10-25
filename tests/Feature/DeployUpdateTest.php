<?php

use App\Models\Exercise;
use App\Models\User;

it("n'existe pas tant qu'aucun code de déploiement n'est configuré", function () {
    config(['app.deploy_token' => null]);

    $this->get('/systeme/maj')->assertNotFound();
    $this->post('/systeme/maj', ['token' => ''])->assertNotFound();
});

it('affiche le formulaire sans session ni base de données', function () {
    config(['app.deploy_token' => 'secret-de-test']);

    $this->get('/systeme/maj')->assertOk()->assertSee('METTRE À JOUR LA BASE')->assertCookieMissing(config('session.cookie'));
});

it('refuse un mauvais code sans rien toucher', function () {
    config(['app.deploy_token' => 'secret-de-test']);

    $this->post('/systeme/maj', ['token' => 'faux'])->assertForbidden()->assertSee('Code invalide');
    expect(Exercise::count())->toBe(0);
});

it('met la base à jour et charge le catalogue avec le bon code', function () {
    config(['app.deploy_token' => 'secret-de-test']);

    $this->post('/systeme/maj', ['token' => 'secret-de-test'])->assertOk()->assertSee('BASE À JOUR');

    expect(Exercise::count())->toBe(count(require database_path('data/exercises.php')));
});

it("refuse l'export sans le bon code", function () {
    config(['app.deploy_token' => 'secret-de-test']);

    $this->post('/systeme/export', ['token' => 'faux'])->assertForbidden();
});

it('exporte les données de jeu sans les secrets des joueurs', function () {
    config(['app.deploy_token' => 'secret-de-test']);
    $user = User::factory()->create();

    $response = $this->post('/systeme/export', ['token' => 'secret-de-test'])->assertOk();

    $tables = $response->json();
    expect($tables)->toHaveKeys(['users', 'workouts', 'workout_sets', 'programs', 'exercise_maxes', 'working_loads'])
        ->and($tables['users'][0]['id'])->toBe($user->id)
        ->and($tables['users'][0])->not->toHaveKeys(['password', 'remember_token'])
        ->and($tables)->not->toHaveKeys(['sessions', 'cache', 'jobs', 'password_reset_tokens']);
});
