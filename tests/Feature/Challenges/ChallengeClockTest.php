<?php

use App\Crew\ChallengeClock;
use App\Crew\ChallengeService;
use App\Crew\CrewService;
use App\Game\XpLedger;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-11 20:00'));
    $this->ana = User::factory()->create(['first_name' => 'Ana']);
    $this->bob = User::factory()->create(['first_name' => 'Bob']);
    $crew = app(CrewService::class)->create($this->ana, 'Duo', 'ice');
    app(CrewService::class)->join($this->bob, $crew->code);
    $this->challenge = app(ChallengeService::class)->create($this->ana, $crew, 'xp', null, '3d', 'le perdant paie le resto');
    app(ChallengeService::class)->join($this->bob, $this->challenge);
    $this->clock = app(ChallengeClock::class);
});

$titles = fn (User $u) => SystemNotification::where('user_id', $u->id)->get()->pluck('payload.title')->all();

it('lance le défi à l\'heure, avec un seul message par participant', function () use ($titles) {
    $this->clock->tick($this->ana);
    expect($this->challenge->fresh()->status)->toBe('upcoming');

    $this->travelTo(Carbon::parse('2026-10-12 00:01'));
    $this->clock->tick($this->ana);
    $this->clock->tick($this->bob);

    expect($this->challenge->fresh()->status)->toBe('active')
        ->and($titles($this->ana))->toBe(['DÉFI LANCÉ'])
        ->and($titles($this->bob))->toBe(['DÉFI LANCÉ']);
});

it('clôt le défi même ouvert 3 jours après la fin, fige le classement et prévient tout le monde une fois', function () use ($titles) {
    app(XpLedger::class)->award($this->bob, 'workout', 1, 300, at: Carbon::parse('2026-10-13 10:00'));
    app(XpLedger::class)->award($this->ana, 'workout', 2, 100, at: Carbon::parse('2026-10-13 11:00'));

    $this->travelTo(Carbon::parse('2026-10-18 09:00'));
    $this->clock->tick($this->ana);
    $this->clock->tick($this->ana);

    $challenge = $this->challenge->fresh();
    expect($challenge->status)->toBe('finished')
        ->and(collect($challenge->final_standings)->pluck('first_name')->all())->toBe(['Bob', 'Ana'])
        ->and($titles($this->ana))->toBe(['DÉFI LANCÉ', 'DÉFI TERMINÉ'])
        ->and($titles($this->bob))->toBe(['DÉFI LANCÉ', 'DÉFI TERMINÉ']);

    $ana = SystemNotification::where('user_id', $this->ana->id)->get()->last()->payload['lines'];
    expect($ana)->toBe(['Défi XP · Duo', 'Tu finis 2ᵉ / 2 · Vainqueur : Bob', 'Enjeu : le perdant paie le resto']);
});

it('ne change plus le classement après la clôture', function () {
    $this->travelTo(Carbon::parse('2026-10-15 00:30'));
    $this->clock->tick($this->bob);
    app(XpLedger::class)->award($this->ana, 'workout', 3, 999, at: Carbon::parse('2026-10-14 23:00'));

    expect(app(\App\Crew\ChallengeBoard::class)->standings($this->challenge->fresh())->pluck('value')->all())->toBe([0, 0]);
});

it('n\'avance pas un défi annulé', function () {
    app(ChallengeService::class)->cancel($this->ana, $this->challenge);
    $this->travelTo(Carbon::parse('2026-10-20 10:00'));
    $this->clock->tick($this->ana);

    expect($this->challenge->fresh()->status)->toBe('cancelled')->and(SystemNotification::count())->toBe(0);
});

it('avance les défis à chaque page ouverte', function () {
    $this->travelTo(Carbon::parse('2026-10-12 08:00'));
    $this->actingAs($this->bob)->get('/')->assertOk();

    expect($this->challenge->fresh()->status)->toBe('active');
});
