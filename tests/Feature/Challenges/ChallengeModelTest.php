<?php

use App\Models\Challenge;
use App\Models\Crew;
use App\Models\User;
use Illuminate\Support\Carbon;

it('démarre le lendemain à 00:00 et finit selon la durée', function (string $now, string $duration, string $start, string $end) {
    [$s, $e] = Challenge::window($duration, Carbon::parse($now));

    expect($s->toDateTimeString())->toBe($start)->and($e->toDateTimeString())->toBe($end);
})->with([
    ['2026-10-11 23:59:00', '3d', '2026-10-12 00:00:00', '2026-10-15 00:00:00'],
    ['2026-10-11 08:00:00', '1w', '2026-10-12 00:00:00', '2026-10-19 00:00:00'],
    ['2026-10-11 08:00:00', '2w', '2026-10-12 00:00:00', '2026-10-26 00:00:00'],
    ['2027-01-30 12:00:00', '1m', '2027-01-31 00:00:00', '2027-02-28 00:00:00'],
]);

it('relie un défi à son groupe, son créateur et ses participants', function () {
    $owner = User::factory()->create();
    $crew = Crew::create(['name' => 'Les Ombres', 'color' => 'ice', 'code' => Crew::generateCode(), 'owner_id' => $owner->id]);
    [$s, $e] = Challenge::window('1w', now());
    $challenge = $crew->challenges()->create(['creator_id' => $owner->id, 'metric' => 'strength', 'lift' => 'squat', 'duration' => '1w', 'starts_at' => $s, 'ends_at' => $e, 'status' => 'upcoming']);
    $challenge->participants()->attach($owner->id, ['joined_at' => now()]);

    expect($challenge->crew->is($crew))->toBeTrue()
        ->and($challenge->creator->is($owner))->toBeTrue()
        ->and($challenge->hasParticipant($owner))->toBeTrue()
        ->and($owner->challenges()->pluck('challenges.id')->all())->toBe([$challenge->id])
        ->and($challenge->title())->toBe('Défi force · Squat')
        ->and($challenge->isOpen())->toBeTrue();
});

it('supprime les défis avec leur groupe', function () {
    $owner = User::factory()->create();
    $crew = Crew::create(['name' => 'Les Ombres', 'color' => 'ice', 'code' => Crew::generateCode(), 'owner_id' => $owner->id]);
    [$s, $e] = Challenge::window('3d', now());
    $crew->challenges()->create(['creator_id' => $owner->id, 'metric' => 'xp', 'duration' => '3d', 'starts_at' => $s, 'ends_at' => $e, 'status' => 'upcoming']);

    $crew->delete();

    expect(Challenge::count())->toBe(0);
});
