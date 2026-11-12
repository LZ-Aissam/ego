<?php

use App\Game\GameEvents;
use App\Game\QuestScheduler;
use App\Models\Quest;
use App\Models\User;
use App\Models\XpEvent;
use App\Services\ProgramBuilder;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->create();
    $program = app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    $program->forceFill(['created_at' => Carbon::parse('2026-10-05 08:00')])->save();
    $this->user->forceFill(['calibrated_at' => now()])->save();
    $this->travelTo(Carbon::parse('2026-10-05 18:00'));
    app(QuestScheduler::class)->sync($this->user, now());
});

/** Envoie une séance qui remplit les objectifs donnés, terminée à $at (heure de Paris), puis joue les événements de jeu. */
function completeObjectives(User $user, array $objectives, string $at): array
{
    $iso = Carbon::parse($at, 'Europe/Paris')->toIso8601String();
    $sets = [];
    foreach ($objectives as $objective) {
        foreach (range(1, $objective['sets']) as $i) {
            $sets[] = ['exercise_id' => $objective['exercise_id'], 'weight_kg' => 20, 'reps' => $objective['reps_min'], 'done_at' => $iso];
        }
    }
    $sync = app(WorkoutSync::class)->sync($user, ['client_id' => (string) Str::uuid(), 'started_at' => $iso, 'finished_at' => $iso, 'sets' => $sets]);

    return app(GameEvents::class)->afterWorkout($user->fresh(), $sync['workout'], $sync['newRecords']);
}

it('accomplit la quête du jour et attribue son XP', function () {
    $quest = Quest::where('date', '2026-10-05')->sole();

    $result = completeObjectives($this->user, $quest->objectives, '2026-10-05 18:30');

    expect($quest->fresh()->status)->toBe('done')
        ->and($quest->fresh()->xp_awarded)->toBe(200)
        ->and(XpEvent::where('source', 'quest')->value('amount'))->toBe(200)
        ->and($result['notifications'][0]['title'])->toBe('QUÊTE ACCOMPLIE')
        ->and($this->user->fresh()->streak_days)->toBe(1);
});

it('ne valide pas une séance incomplète', function () {
    $quest = Quest::where('date', '2026-10-05')->sole();
    $partial = $quest->objectives;
    $partial[0]['sets'] = 1;

    completeObjectives($this->user, $partial, '2026-10-05 18:30');

    expect($quest->fresh()->status)->toBe('pending');
});

it('répare une quête ratée par une séance d\'hier envoyée aujourd\'hui', function () {
    $monday = Quest::where('date', '2026-10-05')->sole();
    $this->travelTo(Carbon::parse('2026-10-06 09:00'));
    app(QuestScheduler::class)->sync($this->user, now());
    expect($monday->fresh()->status)->toBe('failed')
        ->and($this->user->fresh()->streak_days)->toBe(1);                      // le repos du mardi seul : la série est repartie de 0

    completeObjectives($this->user, $monday->objectives, '2026-10-05 19:00');

    expect($monday->fresh()->status)->toBe('done')
        ->and($this->user->fresh()->streak_days)->toBe(2);
});

it('remet la série à 0 sur une quête ratée, sans quête de pénalité le lendemain', function () {
    $this->travelTo(Carbon::parse('2026-10-06 09:00'));
    $notes = app(QuestScheduler::class)->sync($this->user, now());

    expect(collect($notes)->pluck('title')->all())->toBe(['QUÊTE ÉCHOUÉE'])
        ->and($notes[0]['lines'][1])->toBe('Ta série retombe à 0.')
        ->and(Quest::where('date', '2026-10-06')->pluck('type')->all())->toBe(['rest'])
        ->and($this->user->fresh()->streak_days)->toBe(1);                      // le repos du mardi seul : la série est repartie de 0
});

it('applique le bonus de série à la récompense', function () {
    foreach (['2026-10-02', '2026-10-03', '2026-10-04'] as $date) {
        $this->user->quests()->create(['date' => $date, 'type' => 'rest', 'title' => 'Repos', 'objectives' => [], 'status' => 'done']);
    }
    $quest = Quest::where('date', '2026-10-05')->sole();

    completeObjectives($this->user, $quest->objectives, '2026-10-05 18:30');

    expect($quest->fresh()->xp_awarded)->toBe(260);
});

it('compte pour la veille une séance finie après minuit : pas d\'échec à 0 h 01, quête accomplie à 0 h 05', function () {
    $quest = Quest::where('date', '2026-10-05')->sole();
    $this->travelTo(Carbon::parse('2026-10-06 00:01'));
    $this->actingAs($this->user)->get('/');
    expect($quest->fresh()->status)->toBe('pending')->and(Quest::count())->toBe(1);

    $result = completeObjectives($this->user, $quest->objectives, '2026-10-06 00:05');

    expect($quest->fresh()->status)->toBe('done')
        ->and(collect($result['notifications'])->pluck('title'))->toContain('QUÊTE ACCOMPLIE')
        ->and($this->user->fresh()->streak_days)->toBe(1);
    $this->get('/')->assertSee('QUÊTE ACCOMPLIE');
});

it('montre à l\'accueil, avant 4 h, les séries faites après minuit sur la quête de la veille', function () {
    $quest = Quest::where('date', '2026-10-05')->sole();
    $first = $quest->objectives[0];
    $first['sets'] = 1;
    completeObjectives($this->user, [$first], '2026-10-06 00:10');

    $this->travelTo(Carbon::parse('2026-10-06 00:30'));
    $this->actingAs($this->user)->get('/')->assertSee('Full body A')->assertSee('1 / '.$quest->objectives[0]['sets'], false);

    $this->travelTo(Carbon::parse('2026-10-06 04:00'));
    $this->get('/')->assertSee('Repos');
    expect($quest->fresh()->status)->toBe('failed');
});

/** État historique : la quête est tombée en échec à minuit et la séance finie à $at a été rattachée au lendemain. */
function failedThenPlayedAt(User $user, Quest $quest, string $at): void
{
    $quest->update(['status' => 'failed']);
    $iso = Carbon::parse($at, 'Europe/Paris')->toIso8601String();
    $sets = [];
    foreach ($quest->objectives as $objective) {
        foreach (range(1, $objective['sets']) as $i) {
            $sets[] = ['exercise_id' => $objective['exercise_id'], 'weight_kg' => 20, 'reps' => $objective['reps_min'], 'done_at' => $iso];
        }
    }
    app(WorkoutSync::class)->sync($user, ['client_id' => (string) Str::uuid(), 'started_at' => $iso, 'finished_at' => $iso, 'sets' => $sets]);
}

it('répare après coup une quête ratée dont la séance s\'est finie entre minuit et 4 h', function () {
    $quest = Quest::where('date', '2026-10-05')->sole();
    failedThenPlayedAt($this->user, $quest, '2026-10-06 00:05');
    $this->travelTo(Carbon::parse('2026-10-07 12:00'));

    $notes = app(QuestScheduler::class)->repairFailedBeforeCutoff($this->user, \App\Game\GameDay::today());

    expect(collect($notes)->pluck('title'))->toContain('QUÊTE ACCOMPLIE')
        ->and($quest->fresh()->status)->toBe('done')
        ->and($quest->fresh()->xp_awarded)->toBe(200)
        ->and($this->user->fresh()->streak_days)->toBe(1);
});

it('ne répare pas une quête ratée dont la séance s\'est finie après 4 h', function () {
    $quest = Quest::where('date', '2026-10-05')->sole();
    failedThenPlayedAt($this->user, $quest, '2026-10-06 05:00');
    $this->travelTo(Carbon::parse('2026-10-07 12:00'));

    $notes = app(QuestScheduler::class)->repairFailedBeforeCutoff($this->user, \App\Game\GameDay::today());

    expect($notes)->toBe([])->and($quest->fresh()->status)->toBe('failed');
});
