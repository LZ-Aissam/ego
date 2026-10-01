<?php

use App\Models\User;
use App\Models\XpEvent;
use Illuminate\Database\UniqueConstraintViolationException;

it('démarre un joueur à 0 XP sans titre équipé', function () {
    $user = User::factory()->create()->fresh();

    expect($user->xp_total)->toBe(0)->and($user->equipped_title)->toBeNull();
});

it('interdit deux événements d\'XP pour la même source', function () {
    $user = User::factory()->create();
    $user->xpEvents()->create(['source' => 'workout', 'source_id' => 1, 'amount' => 65]);

    $user->xpEvents()->create(['source' => 'workout', 'source_id' => 1, 'amount' => 65]);
})->throws(UniqueConstraintViolationException::class);

it('interdit de débloquer deux fois le même titre', function () {
    $user = User::factory()->create();
    $user->titles()->create(['title_key' => 'awakened', 'unlocked_at' => now()]);

    $user->titles()->create(['title_key' => 'awakened', 'unlocked_at' => now()]);
})->throws(UniqueConstraintViolationException::class);

it('enregistre la quantité d\'un événement', function () {
    $event = User::factory()->create()->xpEvents()->create(['source' => 'record', 'source_id' => 3, 'amount' => 100, 'quantity' => 2]);

    expect(XpEvent::find($event->id)->quantity)->toBe(2);
});
