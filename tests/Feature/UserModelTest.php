<?php

use App\Models\User;

it('crée un utilisateur via la factory avec une clé cohérente', function () {
    $user = User::factory()->create(['first_name' => 'Élise', 'birth_date' => '2001-04-12']);

    expect($user->identity_key)->toBe('elise|2001-04-12')
        ->and($user->avatar_color)->toBeIn(User::AVATAR_COLORS);
});

it('calcule l\'IMC quand taille et poids sont connus', function () {
    $user = User::factory()->withMorphology(heightCm: 180, weightKg: 81.0)->make();

    expect($user->bmi())->toBe(25.0);
});

it('renvoie null pour l\'IMC sans morphologie', function () {
    expect(User::factory()->make()->bmi())->toBeNull();
});

it('ne sérialise pas la clé d\'identité', function () {
    expect(User::factory()->make()->toArray())->not->toHaveKey('identity_key');
});
