<?php

use App\Livewire\Profile;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs($this->user = User::factory()->create(['first_name' => 'Élise'])));

it('affiche le profil', function () {
    $this->get('/profile')->assertOk()->assertSee('Élise')->assertSee('Historique')->assertSee('Records')->assertSee('Se déconnecter');
});

it('enregistre la morphologie et affiche l\'IMC', function () {
    Livewire::test(Profile::class)
        ->set('sex', 'female')
        ->set('heightCm', '165')
        ->set('weightKg', '58,5')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Profil enregistré')
        ->assertSee('21,5');

    $this->user->refresh();
    expect($this->user->sex)->toBe('female')
        ->and($this->user->height_cm)->toBe(165)
        ->and($this->user->weight_kg)->toBe(58.5);
});

it('vide un champ laissé blanc', function () {
    $this->user->update(['height_cm' => 180]);

    Livewire::test(Profile::class)->set('heightCm', '')->call('save')->assertHasNoErrors();

    expect($this->user->fresh()->height_cm)->toBeNull();
});

it('refuse des valeurs hors limites', function (string $field, string $value) {
    Livewire::test(Profile::class)->set($field, $value)->call('save')->assertHasErrors([$field]);
})->with([
    ['heightCm', '90'],
    ['heightCm', '260'],
    ['weightKg', '25'],
    ['weightKg', '350'],
    ['sex', 'autre'],
]);

it('présente le profil comme une fiche du joueur', function () {
    $this->get('/profile')->assertSee('FICHE DU JOUEUR')->assertSee('MORPHOLOGIE')->assertSee('class="sys-input"', false);
});
