<?php

use App\Game\GameEvents;
use App\Game\QuestScheduler;
use App\Game\StreakCalculator;
use App\Livewire\Profile;
use App\Models\Quest;
use App\Models\User;
use App\Services\ProgramBuilder;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->create();
});

function buildProgram(User $user, array $weekdays, string $createdAt)
{
    $program = app(ProgramBuilder::class)->build($user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => $weekdays, 'equipment' => 'gym']);
    $program->forceFill(['created_at' => Carbon::parse($createdAt)])->save();
    $user->forceFill(['calibrated_at' => now()])->save();

    return $program->fresh('days');
}

/** Envoie une séance remplissant $objectives, terminée à $at (Paris), puis les événements de jeu. */
function playObjectives(User $user, array $objectives, string $at): array
{
    $iso = Carbon::parse($at, 'Europe/Paris')->toIso8601String();
    $sets = [];
    foreach ($objectives as $o) {
        foreach (range(1, $o['sets']) as $i) {
            $sets[] = ['exercise_id' => $o['exercise_id'], 'weight_kg' => 20, 'reps' => $o['reps_min'], 'done_at' => $iso];
        }
    }
    $sync = app(WorkoutSync::class)->sync($user, ['client_id' => (string) Str::uuid(), 'started_at' => $iso, 'finished_at' => $iso, 'sets' => $sets]);

    return app(GameEvents::class)->afterWorkout($user->fresh(), $sync['workout'], $sync['newRecords']);
}

function q2(User $user, string $date, string $type, string $status): void
{
    $user->quests()->create(['date' => $date, 'type' => $type, 'title' => $type, 'objectives' => [], 'status' => $status]);
}

it('valide la quête d\'une séance envoyée avant toute ouverture de page ce jour-là', function () {
    $program = buildProgram($this->user, [1, 3, 5], '2026-10-05 08:00');
    $this->travelTo(Carbon::parse('2026-10-05 19:00'));

    $result = playObjectives($this->user, $program->days->first()->objectives(), '2026-10-05 18:00');

    expect(collect($result['notifications'])->pluck('title'))->toContain('QUÊTE ACCOMPLIE')
        ->and(Quest::where('date', '2026-10-05')->where('type', 'training')->value('status'))->toBe('done');
});

it('valide une quête créée après coup quand les séances du jour sont déjà là', function () {
    $program = buildProgram($this->user, [1, 3, 5], '2026-10-05 08:00');
    $this->user->forceFill(['active_program_id' => null])->save();
    $this->travelTo(Carbon::parse('2026-10-05 19:00'));
    playObjectives($this->user, $program->days->first()->objectives(), '2026-10-05 18:00');
    $this->user->forceFill(['active_program_id' => $program->id])->save();

    app(QuestScheduler::class)->sync($this->user, now());

    expect(Quest::where('date', '2026-10-05')->where('type', 'training')->value('status'))->toBe('done');
});

it('ne punit pas les jours de pause quand on reprend à la main', function () {
    buildProgram($this->user, [1, 3, 5], '2026-10-01 08:00');
    foreach (['2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04'] as $date) {
        q2($this->user, $date, 'rest', 'done');
    }
    $this->travelTo(Carbon::parse('2026-10-05 09:00'));
    $this->actingAs($this->user);
    Livewire::test(Profile::class)->call('pause');

    $this->travelTo(Carbon::parse('2026-10-12 09:00'));
    Livewire::test(Profile::class)->call('resume');
    $notes = app(QuestScheduler::class)->sync($this->user, now());

    expect(collect($notes)->pluck('title'))->not->toContain('QUÊTE ÉCHOUÉE')
        ->and(Quest::where('status', 'failed')->count())->toBe(0)
        ->and(Quest::where('type', 'penalty')->count())->toBe(0)
        ->and($this->user->fresh()->streak_days)->toBe(4);
});

it('compte une veille ratée comme sauvée tant que la pénalité du jour est en cours', function () {
    q2($this->user, '2026-10-05', 'rest', 'done');
    q2($this->user, '2026-10-06', 'training', 'failed');
    q2($this->user, '2026-10-07', 'penalty', 'pending');

    expect(app(StreakCalculator::class)->compute($this->user, Carbon::parse('2026-10-06')))->toBe(1);
});

it('gèle la série quand la pause annule la pénalité', function () {
    q2($this->user, '2026-10-05', 'rest', 'done');
    q2($this->user, '2026-10-06', 'training', 'failed');
    q2($this->user, '2026-10-07', 'penalty', 'cancelled');

    expect(app(StreakCalculator::class)->compute($this->user, Carbon::parse('2026-10-07')))->toBe(1);
});

it('crée une pénalité annulée quand le lendemain est en pause', function () {
    buildProgram($this->user, [1, 3, 5], '2026-10-05 08:00');
    app(QuestScheduler::class)->sync($this->user, Carbon::parse('2026-10-05'));
    $this->user->forceFill(['paused_at' => '2026-10-06', 'paused_until' => '2026-10-07'])->save();

    app(QuestScheduler::class)->sync($this->user, Carbon::parse('2026-10-08'));

    expect(Quest::where('date', '2026-10-06')->where('type', 'penalty')->value('status'))->toBe('cancelled');
});

it('clôt sans bruit les quêtes en attente trop anciennes', function () {
    buildProgram($this->user, [1, 3, 5], '2026-10-05 08:00');
    app(QuestScheduler::class)->sync($this->user, Carbon::parse('2026-10-05'));

    app(QuestScheduler::class)->sync($this->user, Carbon::parse('2027-01-15'));

    expect(Quest::where('date', '2026-10-05')->value('status'))->toBe('failed')
        ->and(Quest::where('status', 'pending')->where('date', '<', '2027-01-15')->count())->toBe(0);
});

it('réassigne la quête du jour après la réparation de la veille', function () {
    $program = buildProgram($this->user, [1, 2, 3, 4, 5, 6], '2026-10-05 08:00');
    app(QuestScheduler::class)->sync($this->user, Carbon::parse('2026-10-05'));
    $this->travelTo(Carbon::parse('2026-10-06 09:00'));
    app(QuestScheduler::class)->sync($this->user, now());
    expect(Quest::where('date', '2026-10-06')->where('type', 'training')->value('title'))->toBe('Push A');

    playObjectives($this->user, Quest::where('date', '2026-10-05')->value('objectives'), '2026-10-05 19:00');

    $today = Quest::where('date', '2026-10-06')->where('type', 'training')->first();
    expect($today->title)->toBe('Pull A')
        ->and($today->objectives)->toBe($program->days[1]->objectives());
});
