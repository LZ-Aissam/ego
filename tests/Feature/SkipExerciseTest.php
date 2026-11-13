<?php

use App\Game\GameDay;
use App\Game\QuestScheduler;
use App\Models\Exercise;
use App\Models\Quest;
use App\Models\User;
use App\Services\ProgramBuilder;
use App\Services\WorkoutBootData;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(weightKg: 75.0)->create();
    $this->sync = app(WorkoutSync::class);
    $this->scheduler = app(QuestScheduler::class);
});

/** Programme du jour (un jour d'entraînement = aujourd'hui), calibré, et la quête d'entraînement créée. */
function questToday(User $user): Quest
{
    $iso = GameDay::today()->isoWeekday();
    $weekdays = array_values(array_unique([$iso, $iso % 7 + 1, ($iso + 1) % 7 + 1])); // aujourd'hui + 2 autres jours
    app(ProgramBuilder::class)->build($user, [
        'level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => $weekdays, 'equipment' => 'gym',
    ]);
    $user->forceFill(['calibrated_at' => now()])->save();
    app(QuestScheduler::class)->sync($user, GameDay::today());

    return $user->quests()->where('type', 'training')->where('date', GameDay::today()->toDateString())->firstOrFail();
}

function skipPayload(array $objectives, array $skip, ?string $clientId = null): array
{
    $sets = [];
    foreach ($objectives as $o) {
        if (in_array($o['exercise_id'], $skip, true)) {
            continue;
        }
        for ($i = 0; $i < $o['sets']; $i++) {
            $sets[] = ['exercise_id' => $o['exercise_id'], 'weight_kg' => 20, 'reps' => max(1, $o['reps_min']), 'done_at' => now()->toIso8601String()];
        }
    }

    return [
        'client_id' => $clientId ?? (string) Str::uuid(),
        'started_at' => now()->subHour()->toIso8601String(),
        'finished_at' => now()->toIso8601String(),
        'skipped' => array_values($skip),
        'sets' => $sets,
    ];
}

it('enregistre les exercices passés sur la séance', function () {
    $bench = Exercise::firstWhere('slug', 'bench-press');
    $data = [
        'client_id' => (string) Str::uuid(), 'started_at' => now()->subHour()->toIso8601String(), 'finished_at' => now()->toIso8601String(),
        'skipped' => [$bench->id], 'sets' => [['exercise_id' => $bench->id, 'weight_kg' => 40, 'reps' => 8, 'done_at' => now()->toIso8601String()]],
    ];

    $workout = $this->sync->sync($this->user, $data)['workout'];

    expect($workout->fresh()->skipped_exercise_ids)->toBe([$bench->id]);
});

it('accomplit la quête quand le seul objectif manquant a été passé', function () {
    $quest = questToday($this->user);
    $objectives = $quest->objectives;
    $skipped = end($objectives)['exercise_id'];

    $data = skipPayload($objectives, [$skipped]);
    $workout = $this->sync->sync($this->user, $data)['workout'];
    $this->scheduler->onWorkout($this->user, $workout);

    expect($quest->fresh()->status)->toBe('done');
});

it('ne compte pas un objectif ni fait ni passé', function () {
    $quest = questToday($this->user);
    $objectives = $quest->objectives;
    $missing = end($objectives)['exercise_id'];

    // Rien pour le dernier objectif, et il n'est pas passé : la quête n'est pas accomplie.
    $data = skipPayload($objectives, []);
    $data['sets'] = array_values(array_filter($data['sets'], fn ($s) => $s['exercise_id'] !== $missing));
    $workout = $this->sync->sync($this->user, $data)['workout'];
    $this->scheduler->onWorkout($this->user, $workout);

    expect($quest->fresh()->status)->toBe('pending');
});

/** Un exercice absent des objectifs de la quête du jour (pour isoler la remontée « à reprendre »). */
function debtExercise(Quest $quest): Exercise
{
    return Exercise::whereNotIn('id', array_column($quest->objectives, 'exercise_id'))->firstOrFail();
}

it('remonte à la séance suivante un exercice passé la fois d\'avant, pas réentraîné depuis', function () {
    $quest = questToday($this->user);
    $debt = debtExercise($quest);
    $this->user->workouts()->create([
        'client_id' => (string) Str::uuid(), 'started_at' => now()->subDay()->subHour(), 'finished_at' => now()->subDay(),
        'skipped_exercise_ids' => [$debt->id],
    ]);

    $plan = app(WorkoutBootData::class)->forQuest($this->user, $quest->fresh())['plan'];

    $resume = collect($plan)->firstWhere('id', $debt->id);
    expect($resume)->not->toBeNull()
        ->and($resume['resume'] ?? false)->toBeTrue();
});

it('ne remonte pas un exercice passé mais réentraîné depuis', function () {
    $quest = questToday($this->user);
    $debt = debtExercise($quest);
    $this->user->workouts()->create([
        'client_id' => (string) Str::uuid(), 'started_at' => now()->subDays(2)->subHour(), 'finished_at' => now()->subDays(2),
        'skipped_exercise_ids' => [$debt->id],
    ]);
    // Réentraîné hier : la dette est soldée.
    $later = $this->user->workouts()->create(['client_id' => (string) Str::uuid(), 'started_at' => now()->subDay()->subHour(), 'finished_at' => now()->subDay()]);
    $later->sets()->create(['exercise_id' => $debt->id, 'position' => 1, 'weight_kg' => 20, 'reps' => 10, 'done_at' => now()->subDay()]);

    $plan = app(WorkoutBootData::class)->forQuest($this->user, $quest->fresh())['plan'];

    expect(collect($plan)->firstWhere(fn ($p) => $p['id'] === $debt->id && ($p['resume'] ?? false)))->toBeNull();
});

it('refuse un exercice passé inconnu', function () {
    $this->actingAs($this->user)->postJson('/workouts/sync', [
        'client_id' => (string) Str::uuid(), 'started_at' => now()->subHour()->toIso8601String(), 'finished_at' => now()->toIso8601String(),
        'skipped' => [999999], 'sets' => [['exercise_id' => Exercise::first()->id, 'weight_kg' => 20, 'reps' => 5, 'done_at' => now()->toIso8601String()]],
    ])->assertStatus(422)->assertJsonValidationErrorFor('skipped.0');
});
