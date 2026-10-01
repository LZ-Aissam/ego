<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Livewire\Livewire;

it('connecte un utilisateur existant, casse et accents ignorés', function () {
    $user = User::factory()->create(['first_name' => 'Élise', 'birth_date' => '2001-04-12']);

    Livewire::test(Login::class)
        ->set('firstName', 'ELISE ')
        ->set('birthDate', '2001-04-12')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

it('refuse un couple inconnu', function () {
    Livewire::test(Login::class)
        ->set('firstName', 'Inconnu')
        ->set('birthDate', '2000-01-01')
        ->call('login')
        ->assertHasErrors(['firstName'])
        ->assertSee('Aucun compte');

    $this->assertGuest();
});

it('bloque après 5 tentatives échouées', function () {
    User::factory()->create(['first_name' => 'Élise', 'birth_date' => '2001-04-12']);

    foreach (range(1, 5) as $i) {
        Livewire::test(Login::class)->set('firstName', 'Faux')->set('birthDate', '2000-01-01')->call('login');
    }

    Livewire::test(Login::class)
        ->set('firstName', 'Élise')
        ->set('birthDate', '2001-04-12')
        ->call('login')
        ->assertHasErrors(['firstName'])
        ->assertSee('Trop de tentatives');

    $this->assertGuest();
});

it('déconnecte', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('redirige un invité vers la connexion', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('ne remet pas le compteur à zéro après une connexion réussie', function () {
    User::factory()->create(['first_name' => 'Élise', 'birth_date' => '2001-04-12']);
    $fail = fn () => Livewire::test(Login::class)->set('firstName', 'Faux')->set('birthDate', '2000-01-01')->call('login');

    foreach (range(1, 4) as $i) {
        $fail();
    }
    Livewire::test(Login::class)->set('firstName', 'Élise')->set('birthDate', '2001-04-12')->call('login')->assertHasNoErrors();
    auth()->logout();
    $fail();

    Livewire::test(Login::class)
        ->set('firstName', 'Élise')
        ->set('birthDate', '2001-04-12')
        ->call('login')
        ->assertSee('Trop de tentatives');
});

it('présente la connexion comme une fenêtre système IDENTIFICATION', function () {
    $this->get('/login')->assertSee('IDENTIFICATION')->assertSee('class="sys-window"', false);
});
