<?php

use App\Game\HomeGame;
use App\Livewire\HomeGamesPage;
use App\Models\User;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-26 20:00'));
    $this->user = User::factory()->withMorphology(weightKg: 70.0)->create(['home_games' => ['push-ups', 'pull-ups']]);
    $this->game = app(HomeGame::class);
    $this->play = function (string $date, string $game, int $value) {
        $this->travelTo(Carbon::parse($date.' 20:00'));

        return $this->game->record($this->user->fresh(), $game, $value);
    };
    $this->card = fn (string $game) => collect($this->game->board($this->user->fresh()))->firstWhere('key', $game);
});

it('laisse chaque joueur choisir ses défis dans la liste', function () {
    $this->actingAs($this->user);

    Livewire::test(HomeGamesPage::class)
        ->call('toggle', 'pull-ups')->call('toggle', 'dips')->call('toggle', 'inconnu');

    expect($this->user->fresh()->home_games)->toBe(['push-ups', 'dips']);
});

it('garde le meilleur essai du jour et vise hier + 1', function () {
    ($this->play)('2026-10-26', 'push-ups', 25);
    ($this->play)('2026-10-26', 'push-ups', 30);
    expect(($this->card)('push-ups'))->toMatchArray(['today' => 30, 'target' => null, 'record' => 30]);

    $this->travelTo(Carbon::parse('2026-10-27 08:00'));
    expect(($this->card)('push-ups'))->toMatchArray(['today' => null, 'target' => 31, 'record' => 30]);
});

it('donne l\'XP une fois par jour quand on bat la veille, plus un bonus de record', function () {
    ($this->play)('2026-10-26', 'push-ups', 30);
    $notes = ($this->play)('2026-10-27', 'push-ups', 31);
    ($this->play)('2026-10-27', 'push-ups', 33);

    expect(collect($notes)->pluck('title')->all())->toContain('RECORD MAISON')
        ->and($this->user->xpEvents()->where('source', 'home_beat')->count())->toBe(1)
        ->and($this->user->xpEvents()->where('source', 'home_record')->count())->toBe(1);
});

it('compte la série de jours où on bat ou égale la veille, cassée par un jour sauté', function () {
    ($this->play)('2026-10-26', 'pull-ups', 10);
    ($this->play)('2026-10-27', 'pull-ups', 11);
    ($this->play)('2026-10-28', 'pull-ups', 11);
    expect(($this->card)('pull-ups')['streak'])->toBe(3);

    ($this->play)('2026-10-30', 'pull-ups', 14);
    expect(($this->card)('pull-ups')['streak'])->toBe(1)
        ->and(($this->card)('pull-ups')['history'])->toHaveCount(30);
});

it('reste séparé du programme : pas de séance, pas de charge touchée', function () {
    ($this->play)('2026-10-26', 'pull-ups', 12);

    expect($this->user->workouts()->count())->toBe(0)
        ->and($this->user->workingLoads()->count())->toBe(0);
});

it('rappelle d\'attendre après la séance pour un vrai max de tractions', function () {
    $at = now()->subHour()->toIso8601String();
    app(WorkoutSync::class)->sync($this->user, ['client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
        'sets' => [['exercise_id' => \App\Models\Exercise::firstWhere('slug', 'pull-up')->id, 'weight_kg' => 10, 'reps' => 3, 'done_at' => $at]]]);

    $this->actingAs($this->user)->get('/maison')->assertOk()->assertSeeText('JEU MAISON')
        ->assertSeeText('Pompes')->assertSeeText('Tractions')->assertSeeText('4 h après ta séance');
});

it('enregistre un essai depuis la page et l\'annonce sur l\'accueil', function () {
    $this->actingAs($this->user);
    Livewire::test(HomeGamesPage::class)->set('values.push-ups', '27')->call('play', 'push-ups')->assertHasNoErrors();

    expect(($this->card)('push-ups')['today'])->toBe(27);
    $this->get('/')->assertSeeText('JEU MAISON');
});

it('refuse un essai vide ou absurde', function () {
    $this->actingAs($this->user);
    Livewire::test(HomeGamesPage::class)->set('values.push-ups', '0')->call('play', 'push-ups')->assertHasErrors('values.push-ups');

    expect(($this->card)('push-ups')['today'])->toBeNull();
});

it('efface les scores maison avec la remise à zéro des stats, et les exporte', function () {
    ($this->play)('2026-10-26', 'push-ups', 30);
    config(['app.deploy_token' => 'secret-de-test']);
    expect($this->post('/systeme/export', ['token' => 'secret-de-test'])->json('home_attempts'))->toHaveCount(1);

    app(\App\Services\AccountReset::class)->reset($this->user->fresh());

    expect($this->user->homeAttempts()->count())->toBe(0);
});
