<?php

use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create(['first_name' => 'Élise'])));

it('affiche l\'accueil avec la navigation à 4 onglets', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Salut Élise')
        ->assertSeeInOrder(['Accueil', 'Programme', 'Communauté', 'Profil'])
        ->assertSee('/manifest.json', false)
        ->assertSee('id="resume-banner"', false);
});

it('expose l\'identifiant de l\'utilisateur connecté pour ranger ses séances locales', function () {
    $this->get('/')->assertSee('data-user-id="'.auth()->id().'"', false);
});

it('affiche les pages à venir', function (string $url, string $text) {
    $this->get($url)->assertOk()->assertSee($text);
})->with([
    ['/program', 'générateur de programmes'],
    ['/community', 'classement'],
]);
