<?php

use App\Livewire\Auth\Register;
use App\Models\User;
use Livewire\Livewire;

it('affiche la page d\'inscription', function () {
    $this->get('/register')->assertOk()->assertSee('Créer mon compte');
});

it('inscrit et connecte un nouvel utilisateur', function () {
    Livewire::test(Register::class)
        ->set('firstName', '  Élise ')
        ->set('birthDate', '2001-04-12')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $user = User::sole();
    expect($user->first_name)->toBe('Élise')
        ->and($user->identity_key)->toBe('elise|2001-04-12');
    $this->assertAuthenticatedAs($user);
});

it('refuse un doublon même avec une autre casse ou sans accent', function () {
    User::factory()->create(['first_name' => 'Élise', 'birth_date' => '2001-04-12']);

    Livewire::test(Register::class)
        ->set('firstName', 'ELISE')
        ->set('birthDate', '2001-04-12')
        ->call('register')
        ->assertHasErrors(['firstName'])
        ->assertSee('déjà utilisés');

    expect(User::count())->toBe(1);
});

it('valide les champs', function (string $first, string $date, string $field) {
    Livewire::test(Register::class)
        ->set('firstName', $first)
        ->set('birthDate', $date)
        ->call('register')
        ->assertHasErrors([$field]);
})->with([
    'prénom vide' => ['   ', '2001-04-12', 'firstName'],
    'date vide' => ['Élise', '', 'birthDate'],
    'date future' => ['Élise', '2999-01-01', 'birthDate'],
    'date invalide' => ['Élise', 'pas-une-date', 'birthDate'],
]);

it('limite les tentatives d\'inscription pour empêcher de deviner une date de naissance', function () {
    User::factory()->create(['first_name' => 'Élise', 'birth_date' => '2001-04-12']);

    foreach (range(1, 5) as $day) {
        Livewire::test(Register::class)->set('firstName', 'Élise')->set('birthDate', "2001-04-0{$day}")->call('register');
    }

    Livewire::test(Register::class)
        ->set('firstName', 'Élise')
        ->set('birthDate', '2001-04-12')
        ->call('register')
        ->assertHasErrors(['firstName'])
        ->assertSee('Trop de tentatives')
        ->assertDontSee('déjà utilisés');
});

it('présente l\'inscription comme une fenêtre système ÉVEIL', function () {
    $this->get('/register')->assertSee('ÉVEIL')->assertSee('class="sys-window"', false)->assertSee('class="sys-input"', false);
});

it('présente le Système sous son nom, TON EGO', function () {
    $this->get('/register')->assertSee('TON EGO a besoin de ton identité')->assertDontSee('Le Système');
});
