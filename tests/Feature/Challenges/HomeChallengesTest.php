<?php

use App\Crew\ChallengeService;
use App\Crew\CrewService;
use App\Game\XpLedger;
use App\Models\User;
use Illuminate\Support\Carbon;

it('affiche sur l\'accueil mon rang dans chaque défi en cours', function () {
    $this->travelTo(Carbon::parse('2026-10-11 20:00'));
    $me = User::factory()->create(['first_name' => 'Aïssam']);
    $friend = User::factory()->create(['first_name' => 'Renée']);
    $crew = app(CrewService::class)->create($me, 'Duo', 'ice');
    app(CrewService::class)->join($friend, $crew->code);
    $challenge = app(ChallengeService::class)->create($me, $crew, 'xp', null, '1w', null);
    app(ChallengeService::class)->join($friend, $challenge);
    app(XpLedger::class)->award($friend, 'workout', 1, 300, at: Carbon::parse('2026-10-13 10:00'));
    app(XpLedger::class)->award($me, 'workout', 2, 100, at: Carbon::parse('2026-10-13 11:00'));

    $this->travelTo(Carbon::parse('2026-10-14 12:00'));
    $this->actingAs($me)->get('/')->assertOk()
        ->assertSee('Défi XP · tu es 2ᵉ · encore 4 j 12 h')
        ->assertSee(route('crew.challenge', $challenge));
});
