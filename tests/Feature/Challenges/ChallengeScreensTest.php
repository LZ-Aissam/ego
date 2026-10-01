<?php

use App\Crew\ChallengeService;
use App\Crew\CrewService;
use App\Livewire\Crew\ChallengePage;
use App\Livewire\Crew\CrewPage;
use App\Models\Challenge;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-11 20:00'));
    $this->me = User::factory()->create(['first_name' => 'Aïssam']);
    $this->friend = User::factory()->create(['first_name' => 'Renée']);
    $this->crew = app(CrewService::class)->create($this->me, 'Les Ombres', 'violet');
    app(CrewService::class)->join($this->friend, $this->crew->code);
    $this->actingAs($this->me);
});

it('lance un défi depuis l\'onglet Défis', function () {
    Livewire::test(CrewPage::class)->set('tab', 'defis')
        ->assertSee('Lancer un défi')
        ->set('newChallengeCrew', $this->crew->id)->set('newMetric', 'strength')->set('newLift', 'squat')
        ->set('newDuration', '1w')->set('newStake', 'le perdant paie le resto')
        ->assertSee('Défi force · Squat · 1 semaine · du lundi 12 au dimanche 18 octobre')
        ->call('createChallenge')
        ->assertRedirect(route('crew.challenge', Challenge::sole()));
});

it('refuse un groupe dont on n\'est pas membre dans le formulaire', function () {
    $foreign = app(CrewService::class)->create(User::factory()->create(), 'Secret', 'red');

    Livewire::test(CrewPage::class)->set('tab', 'defis')->set('newChallengeCrew', $foreign->id)->call('createChallenge')->assertNotFound();
});

it('liste les défis en cours, à venir et terminés de mes groupes', function () {
    $upcoming = app(ChallengeService::class)->create($this->me, $this->crew, 'xp', null, '1w', 'le perdant paie le resto');
    $active = app(ChallengeService::class)->create($this->friend, $this->crew, 'sessions', null, '3d', null);
    $active->update(['status' => 'active', 'starts_at' => now()->subDay()]);
    $stranger = User::factory()->create();
    app(ChallengeService::class)->create($stranger, app(CrewService::class)->create($stranger, 'Autre', 'red'), 'xp', null, '1w', null);

    Livewire::test(CrewPage::class)->set('tab', 'defis')
        ->assertSee('EN COURS')->assertSee('Défi séances')->assertSee('encore')
        ->assertSee('À VENIR')->assertSee('Défi XP')->assertSee('le perdant paie le resto')
        ->assertDontSee('Autre');
});

it('affiche la page d\'un défi avec le classement, et permet rejoindre puis quitter avant le début', function () {
    $challenge = app(ChallengeService::class)->create($this->friend, $this->crew, 'xp', null, '1w', '<b>resto</b>');

    $this->get(route('crew.challenge', $challenge))->assertOk()
        ->assertSee('DÉFI XP')->assertSee('&lt;b&gt;resto&lt;/b&gt;', false)->assertSee('Rejoindre');

    Livewire::test(ChallengePage::class, ['challenge' => $challenge])
        ->call('join')->assertSee('Quitter')
        ->call('leave')->assertSee('Rejoindre');
});

it('laisse le créateur annuler avant le début', function () {
    $challenge = app(ChallengeService::class)->create($this->me, $this->crew, 'xp', null, '1w', null);

    Livewire::test(ChallengePage::class, ['challenge' => $challenge])->call('cancel')->assertRedirect(route('community', ['tab' => 'defis']));

    expect($challenge->fresh()->status)->toBe('cancelled');
});

it('répond 404 à un non-membre et 403 à une annulation forgée', function () {
    $challenge = app(ChallengeService::class)->create($this->friend, $this->crew, 'xp', null, '1w', null);

    Livewire::test(ChallengePage::class, ['challenge' => $challenge])->call('cancel')->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('crew.challenge', $challenge))->assertNotFound();
});

it('affiche le podium figé d\'un défi terminé', function () {
    $challenge = app(ChallengeService::class)->create($this->me, $this->crew, 'xp', null, '1w', null);
    $challenge->update(['status' => 'finished', 'final_standings' => [['user_id' => $this->friend->id, 'first_name' => 'Renée', 'value' => 640, 'rank' => 1]]]);

    $this->get(route('crew.challenge', $challenge))->assertSee('CLASSEMENT FINAL')->assertSee('Renée')->assertSee("640 XP");
});

it('reste stable quand un groupe et ses défis sont supprimés', function () {
    app(ChallengeService::class)->create($this->me, $this->crew, 'xp', null, '1w', null);
    app(CrewService::class)->delete($this->me, $this->crew);

    Livewire::test(CrewPage::class)->set('tab', 'defis')->assertOk()->assertSee('Aucun défi pour l\'instant', false);
    $this->get('/')->assertOk();
});
