<?php

use App\Game\XpLedger;
use App\Models\User;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-07 12:00'));
    $this->me = User::factory()->create();
    $this->actingAs($this->me);
});

it('invite à gagner de l\'XP quand je n\'en ai pas cette semaine', function () {
    $this->get('/')->assertSee('Gagne de l\'XP pour entrer au classement', false)->assertSee(route('community'));
});

it('affiche mon rang de la semaine', function () {
    $rival = User::factory()->create();
    app(XpLedger::class)->award($rival, 'workout', 1, 300);
    app(XpLedger::class)->award($this->me, 'workout', 2, 100);

    $this->get('/')->assertSee('Classement de la semaine : tu es 2ᵉ');
});

it('écrit 1ᵉʳ pour la première place', function () {
    app(XpLedger::class)->award($this->me, 'workout', 1, 100);

    $this->get('/')->assertSee('Classement de la semaine : tu es 1ᵉʳ');
});
