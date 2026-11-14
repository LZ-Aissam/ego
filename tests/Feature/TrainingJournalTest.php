<?php

use App\Game\LoadPrescriber;
use App\Models\Exercise;
use App\Models\ExerciseNote;
use App\Models\User;
use App\Models\WorkingLoad;
use App\Services\ProgramBuilder;
use App\Services\WorkoutBootData;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(weightKg: 70.0, level: 'advanced')->create();
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
    $this->curl = Exercise::firstWhere('slug', 'barbell-curl');
    $this->at = now()->toIso8601String();
    $this->body = fn (array $extra = []) => [
        'client_id' => (string) Str::uuid(), 'started_at' => $this->at, 'finished_at' => $this->at,
        'sets' => [['exercise_id' => $this->bench->id, 'weight_kg' => 60, 'reps' => 5, 'done_at' => $this->at]],
        ...$extra,
    ];
});

it('enregistre la forme du jour, les notes et les douleurs de la séance', function () {
    $this->actingAs($this->user)->postJson('/workouts/sync', ($this->body)([
        'readiness' => 'low',
        'notes' => [
            ['exercise_id' => $this->bench->id, 'text' => 'prise trop large', 'pain_zone' => 'shoulder'],
            ['exercise_id' => $this->curl->id, 'text' => '   ', 'pain_zone' => null],
        ],
    ]))->assertCreated();

    expect($this->user->workouts()->first()->readiness)->toBe('low')
        ->and(ExerciseNote::count())->toBe(1)
        ->and(ExerciseNote::first()->only(['exercise_id', 'text', 'pain_zone']))->toBe(['exercise_id' => $this->bench->id, 'text' => 'prise trop large', 'pain_zone' => 'shoulder']);
});

it('refuse une forme ou une zone inconnue', function () {
    $this->actingAs($this->user)->postJson('/workouts/sync', ($this->body)(['readiness' => 'zombie']))->assertUnprocessable();
    $this->actingAs($this->user)->postJson('/workouts/sync', ($this->body)(['notes' => [['exercise_id' => $this->bench->id, 'pain_zone' => 'genou']]]))->assertUnprocessable();
});

it('relit la dernière note et la douleur récente à la séance suivante, la douleur expire après 14 jours', function () {
    $this->user->exerciseNotes()->create(['exercise_id' => $this->bench->id, 'text' => 'coude sensible', 'pain_zone' => 'elbow']);
    $bench = collect(app(WorkoutBootData::class)->for($this->user)['exercises'])->firstWhere('id', $this->bench->id);

    expect($bench['lastNote']['text'])->toBe('coude sensible')
        ->and($bench['pain']['zone'])->toBe('Coude')
        ->and($bench['isBarbell'])->toBeTrue();

    $this->travel(15)->days();
    $bench = collect(app(WorkoutBootData::class)->for($this->user)['exercises'])->firstWhere('id', $this->bench->id);
    expect($bench['pain'])->toBeNull()->and($bench['lastNote']['text'])->toBe('coude sensible');
});

it('donne à la séance de quête le record à battre de la série 5+ et des remplaçants du même emplacement', function () {
    $this->user->forceFill(['calibrated_at' => now(), 'power_factor' => 1.0])->save();
    $this->user->exerciseMaxes()->create(['exercise_id' => $this->bench->id, 'e1rm_kg' => 90.0, 'source' => 'wave', 'measured_at' => now()]);
    app(ProgramBuilder::class)->build($this->user, ['level' => 'advanced', 'goal' => 'pure', 'weekdays' => [1, 2, 3, 4, 5, 6, 7], 'equipment' => 'gym', 'upper_only' => true]);
    $this->travelTo(Carbon::parse('2026-11-16 09:00'));                         // lundi : couché lourd
    app(\App\Game\QuestScheduler::class)->sync($this->user->fresh(), now());
    $quest = $this->user->quests()->whereDate('date', '2026-11-16')->where('type', 'training')->first();

    $plan = collect(app(WorkoutBootData::class)->forQuest($this->user->fresh(), $quest)['plan']);
    $wave = $plan->firstWhere('intent', 'wave');
    $curl = $plan->firstWhere('id', $this->curl->id);

    expect($wave['recordE1rm'])->toBe(90.0)
        ->and($plan->firstWhere('intent', 'backoff')['recordE1rm'])->toBeNull()
        ->and($curl['alternatives'])->not->toBeEmpty()
        ->and(Exercise::whereIn('id', $curl['alternatives'])->get()->every(fn ($e) => in_array('biceps', $e->slots, true)))->toBeTrue()
        ->and($curl['alternatives'])->not->toContain($this->curl->id);
});

it('un jour fatigué, une force max ratée n\'est pas un échec', function () {
    $program = app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3], 'equipment' => 'gym']);
    $squat = $program->days->flatMap->exercises->firstWhere('slot', 'squat');
    $this->user->workingLoads()->create(['exercise_id' => $squat->exercise_id, 'target_kg' => 60, 'fail_streak' => 1]);

    $sync = app(WorkoutSync::class)->sync($this->user, ($this->body)([
        'readiness' => 'low',
        'sets' => array_fill(0, 3, ['exercise_id' => $squat->exercise_id, 'weight_kg' => 50, 'reps' => 6, 'done_at' => $this->at]),
    ]));
    app(LoadPrescriber::class)->progress($this->user, $sync['workout']);

    $load = WorkingLoad::where('user_id', $this->user->id)->where('exercise_id', $squat->exercise_id)->first();
    expect([(float) $load->target_kg, $load->fail_streak])->toBe([60.0, 1]);
});

it('un exercice remplacé pour une douleur n\'est pas réclamé « à reprendre »', function () {
    $this->user->forceFill(['calibrated_at' => now(), 'power_factor' => 1.0])->save();
    app(ProgramBuilder::class)->build($this->user, ['level' => 'advanced', 'goal' => 'pure', 'weekdays' => [1, 2, 3, 4, 5, 6, 7], 'equipment' => 'gym', 'upper_only' => true]);
    $this->actingAs($this->user)->postJson('/workouts/sync', ($this->body)([
        'skipped' => [$this->curl->id],
        'notes' => [['exercise_id' => $this->curl->id, 'pain_zone' => 'elbow']],
    ]))->assertCreated();

    $this->travel(1)->days();
    app(\App\Game\QuestScheduler::class)->sync($this->user->fresh(), \App\Game\GameDay::today());
    $quest = $this->user->quests()->where('type', 'training')->where('status', 'pending')->orderByDesc('date')->first();
    $plan = collect(app(WorkoutBootData::class)->forQuest($this->user->fresh(), $quest)['plan']);

    expect($plan->where('resume', true)->pluck('id')->all())->not->toContain($this->curl->id);
});
