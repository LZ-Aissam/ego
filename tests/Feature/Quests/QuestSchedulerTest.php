<?php

use App\Game\QuestScheduler;
use App\Models\Quest;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->create();
    $this->scheduler = app(QuestScheduler::class);
});

/** Programme lundi / mercredi / vendredi, créé le lundi 5 octobre 2026. */
function programOn(User $user, string $createdAt = '2026-10-05', array $weekdays = [1, 3, 5])
{
    $program = app(ProgramBuilder::class)->build($user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => $weekdays, 'equipment' => 'gym']);
    $program->forceFill(['created_at' => Carbon::parse($createdAt.' 09:00')])->save();

    return $program;
}

function day(string $date): Carbon
{
    return Carbon::parse($date);
}

it('ne fait rien sans programme', function () {
    expect($this->scheduler->sync($this->user, day('2026-10-05')))->toBe([])
        ->and(Quest::count())->toBe(0);
});

it('assigne la séance du jour un jour d\'entraînement', function () {
    programOn($this->user);

    $this->scheduler->sync($this->user, day('2026-10-05'));

    $quest = Quest::sole();
    expect($quest->type)->toBe('training')->and($quest->title)->toBe('Full body A')
        ->and($quest->status)->toBe('pending')->and($quest->objectives)->toHaveCount(5)
        ->and($quest->objectives[0])->toHaveKeys(['exercise_id', 'name', 'slot', 'sets', 'reps_min', 'reps_max', 'rest_seconds']);
});

it('constate l\'échec de la veille, crée la pénalité et le repos du jour, une seule fois', function () {
    programOn($this->user);
    $this->scheduler->sync($this->user, day('2026-10-05'));

    $notes = $this->scheduler->sync($this->user, day('2026-10-06'));
    $again = $this->scheduler->sync($this->user, day('2026-10-06'));

    expect(collect($notes)->pluck('title')->all())->toBe(['QUÊTE ÉCHOUÉE'])
        ->and($again)->toBe([])
        ->and(Quest::where('date', '2026-10-05')->value('status'))->toBe('failed')
        ->and(Quest::where('date', '2026-10-06')->where('type', 'rest')->value('status'))->toBe('done')
        ->and(Quest::where('date', '2026-10-06')->where('type', 'penalty')->first()->objectives)->toHaveCount(3);
});

it('passe au jour suivant du programme après une quête accomplie, répète après un échec', function () {
    programOn($this->user);
    $this->scheduler->sync($this->user, day('2026-10-05'));
    Quest::where('date', '2026-10-05')->update(['status' => 'done']);

    $this->scheduler->sync($this->user, day('2026-10-07'));
    expect(Quest::where('date', '2026-10-07')->value('title'))->toBe('Full body B');

    $this->scheduler->sync($this->user, day('2026-10-09'));     // mercredi raté
    expect(Quest::where('date', '2026-10-09')->value('title'))->toBe('Full body B');
});

it('rattrape une absence de plusieurs jours dans l\'ordre, sans pénalité en cascade', function () {
    programOn($this->user);
    $this->scheduler->sync($this->user, day('2026-10-05'));

    $notes = $this->scheduler->sync($this->user, day('2026-10-09'));

    expect(collect($notes)->pluck('title')->all())->toBe(['QUÊTE ÉCHOUÉE', 'PÉNALITÉ ÉCHOUÉE', 'QUÊTE ÉCHOUÉE', 'PÉNALITÉ ÉCHOUÉE'])
        ->and(Quest::where('type', 'penalty')->count())->toBe(2)
        ->and(Quest::where('date', '2026-10-09')->where('type', 'training')->value('status'))->toBe('pending')
        ->and(Quest::where('date', '2026-10-09')->where('type', 'penalty')->exists())->toBeFalse()
        ->and($this->user->fresh()->streak_days)->toBe(0);
});

it('ne crée ni quête ni pénalité pendant une pause, et la lève automatiquement', function () {
    programOn($this->user);
    $this->scheduler->sync($this->user, day('2026-10-05'));
    $this->user->forceFill(['paused_at' => '2026-10-06', 'paused_until' => '2026-10-07'])->save();

    $notes = $this->scheduler->sync($this->user, day('2026-10-08'));

    expect(collect($notes)->pluck('title')->all())->toBe(['RETOUR DE TON EGO', 'QUÊTE ÉCHOUÉE'])
        ->and(Quest::whereIn('date', ['2026-10-06', '2026-10-07'])->count())->toBe(0)
        ->and(Quest::where('date', '2026-10-08')->value('type'))->toBe('rest')
        ->and($this->user->fresh()->paused_at)->toBeNull();
});

it('garde les objectifs des quêtes existantes quand le programme est régénéré', function () {
    programOn($this->user);
    $this->scheduler->sync($this->user, day('2026-10-05'));
    $before = Quest::sole()->objectives;

    app(ProgramBuilder::class)->build($this->user, ['level' => 'advanced', 'goal' => 'strength', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    $this->scheduler->sync($this->user, day('2026-10-07'));

    expect(Quest::where('date', '2026-10-05')->first()->objectives)->toBe($before)
        ->and(Quest::where('date', '2026-10-07')->first()->objectives)->toHaveCount(7);
});

it('calcule la récompense avec le bonus de série plafonné', function () {
    expect($this->scheduler->rewardFor(0))->toBe(200)
        ->and($this->scheduler->rewardFor(3))->toBe(260)
        ->and($this->scheduler->rewardFor(12))->toBe(300);
});
