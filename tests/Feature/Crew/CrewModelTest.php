<?php

use App\Models\Crew;
use App\Models\User;

it('génère des codes de 6 caractères sans caractères ambigus, uniques', function () {
    $codes = collect(range(1, 50))->map(fn () => Crew::generateCode());

    expect($codes->every(fn ($c) => preg_match('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/', $c) === 1))->toBeTrue();
});

it('normalise un code saisi avec minuscules, espaces et tirets', function () {
    expect(Crew::normalizeCode(' ab3-k9p '))->toBe('AB3K9P')
        ->and(Crew::normalizeCode('AB3 K9P'))->toBe('AB3K9P');
});

it('relie un groupe à son créateur et à ses membres', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $crew = Crew::create(['name' => 'Les Ombres', 'color' => 'violet', 'code' => Crew::generateCode(), 'owner_id' => $owner->id]);
    $crew->members()->attach($owner->id, ['joined_at' => now()]);

    expect($crew->owner->is($owner))->toBeTrue()
        ->and($crew->isOwnedBy($owner))->toBeTrue()
        ->and($crew->isOwnedBy($other))->toBeFalse()
        ->and($crew->hasMember($owner))->toBeTrue()
        ->and($crew->hasMember($other))->toBeFalse()
        ->and($owner->crews()->pluck('crews.id')->all())->toBe([$crew->id])
        ->and($crew->hex())->toBe(Crew::COLORS['violet']);
});

it('refuse deux fois le même membre dans un groupe', function () {
    $owner = User::factory()->create();
    $crew = Crew::create(['name' => 'Les Ombres', 'color' => 'ice', 'code' => Crew::generateCode(), 'owner_id' => $owner->id]);
    $crew->members()->attach($owner->id, ['joined_at' => now()]);

    $crew->members()->attach($owner->id, ['joined_at' => now()]);
})->throws(\Illuminate\Database\UniqueConstraintViolationException::class);

it('donne la progression du joueur', function () {
    $user = User::factory()->create();
    $user->forceFill(['xp_total' => 250])->save();

    expect($user->player())->toBe(\App\Game\Leveling::progress(250));
});
