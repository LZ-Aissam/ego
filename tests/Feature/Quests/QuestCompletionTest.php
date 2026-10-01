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

it('répare une quête ratée par une séance d\'hier envoyée aujourd\'hui et annule la pénalité', function () {
    $monday = Quest::where('date', '2026-10-05')->sole();
    $this->travelTo(Carbon::parse('2026-10-06 09:00'));
    app(QuestScheduler::class)->sync($this->user, now());
    expect($monday->fresh()->status)->toBe('failed');

    completeObjectives($this->user, $monday->objectives, '2026-10-05 19:00');

    expect($monday->fresh()->status)->toBe('done')
        ->and(Quest::where('date', '2026-10-06')->where('type', 'penalty')->value('status'))->toBe('cancelled')
        ->and($this->user->fresh()->streak_days)->toBe(2);
});

it('sauve la série avec la pénalité, +50 XP et le titre Revenant', function () {
    $this->travelTo(Carbon::parse('2026-10-06 09:00'));
    app(QuestScheduler::class)->sync($this->user, now());
    $penalty = Quest::where('date', '2026-10-06')->where('type', 'penalty')->sole();

    $result = completeObjectives($this->user, $penalty->objectives, '2026-10-06 12:00');

    $titles = collect($result['notifications'])->pluck('title');
    expect($penalty->fresh()->status)->toBe('done')
        ->and(XpEvent::where('source', 'penalty')->value('amount'))->toBe(50)
        ->and($titles)->toContain('PÉNALITÉ ACCOMPLIE')
        ->and(collect($result['notifications'])->pluck('lines.0'))->toContain('Revenant')
        ->and($this->user->fresh()->streak_days)->toBe(1);
});

it('applique le bonus de série à la récompense', function () {
    foreach (['2026-10-02', '2026-10-03', '2026-10-04'] as $date) {
        $this->user->quests()->create(['date' => $date, 'type' => 'rest', 'title' => 'Repos', 'objectives' => [], 'status' => 'done']);
    }
    $quest = Quest::where('date', '2026-10-05')->sole();

    completeObjectives($this->user, $quest->objectives, '2026-10-05 18:30');

    expect($quest->fresh()->xp_awarded)->toBe(260);
});
