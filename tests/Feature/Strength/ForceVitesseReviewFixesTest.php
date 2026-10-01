<?php

use App\Crew\CrewService;
use App\Game\LoadPrescriber;
use App\Game\QuestEvaluator;
use App\Game\QuestScheduler;
use App\Models\Exercise;
use App\Models\Quest;
use App\Models\User;
use App\Services\ProgramBuilder;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-10 10:00'));                          // samedi
    $this->user = User::factory()->withMorphology(weightKg: 80.0, level: 'advanced')->create(['first_name' => 'Aïssam']);
    $this->artisan('ego:programme-force-vitesse', ['prenom' => 'Aïssam'])->assertSuccessful();
    $this->user = $this->user->fresh();                                          // la commande a changé le programme actif en base
    $this->prescriber = app(LoadPrescriber::class);
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
});

function calibrateBench(User $user, float $e1rm = 100): void
{
    $user->exerciseMaxes()->create(['exercise_id' => Exercise::firstWhere('slug', 'bench-press')->id, 'e1rm_kg' => $e1rm, 'source' => 'calibration', 'measured_at' => now()]);
    $user->forceFill(['calibrated_at' => now(), 'power_factor' => 1.0])->save();
    app(LoadPrescriber::class)->prescribeProgram($user->fresh());
}

function benchSession(User $user, float $kg, int $reps, int $sets): \App\Models\Workout
{
    $at = now()->toIso8601String();

    return app(WorkoutSync::class)->sync($user, ['client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
        'sets' => array_fill(0, $sets, ['exercise_id' => Exercise::firstWhere('slug', 'bench-press')->id, 'weight_kg' => $kg, 'reps' => $reps, 'done_at' => $at])])['workout'];
}

it('fait progresser la force max : +2,5 kg quand toutes les séries sont réussies', function () {
    calibrateBench($this->user);
    expect($this->prescriber->prescriptionFor($this->user, $this->bench, 'max', 3))->toBe(87.5);

    $notes = $this->prescriber->progress($this->user, benchSession($this->user, 87.5, 3, 5));

    expect($this->prescriber->prescriptionFor($this->user, $this->bench, 'max', 3))->toBe(90.0)
        ->and(collect($notes)->pluck('title')->all())->toContain('CHARGE AUGMENTÉE');
});

it('décharge la force max après deux échecs, sans compter les séances de vitesse comme des échecs', function () {
    calibrateBench($this->user);
    $this->prescriber->progress($this->user, benchSession($this->user, 57.5, 3, 8));       // jeudi vitesse
    $this->prescriber->progress($this->user, benchSession($this->user, 57.5, 3, 8));
    expect($this->prescriber->prescriptionFor($this->user, $this->bench, 'max', 3))->toBe(87.5);

    $this->prescriber->progress($this->user, benchSession($this->user, 87.5, 1, 5));
    $this->prescriber->progress($this->user, benchSession($this->user, 87.5, 1, 5));
    expect($this->prescriber->prescriptionFor($this->user, $this->bench, 'max', 3))->toBe(80.0);
});

it('ne fait pas chuter la charge d\'un exercice non calibré après une séance légère', function () {
    $this->user->forceFill(['power_factor' => 1.0])->save();
    $pushPress = Exercise::firstWhere('slug', 'push-press');
    $before = $this->prescriber->prescriptionFor($this->user, $pushPress, 'explosive', 3);
    $at = now()->toIso8601String();
    app(WorkoutSync::class)->sync($this->user, ['client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
        'sets' => array_fill(0, 5, ['exercise_id' => $pushPress->id, 'weight_kg' => $before, 'reps' => 3, 'done_at' => $at])]);

    expect($before)->not->toBeNull()
        ->and($this->prescriber->prescriptionFor($this->user, $pushPress, 'explosive', 3))->toBeGreaterThanOrEqual($before);
});

it('ne valide pas le développé couché lourd avec les séries d\'autres exercices de la quête', function () {
    $monday = $this->user->activeProgram->days->first()->objectives();
    $ids = Exercise::pluck('id', 'slug');
    $set = fn (string $slug) => (object) ['exercise_id' => $ids[$slug], 'exercise' => Exercise::firstWhere('slug', $slug)];
    $sets = collect([...array_fill(0, 4, $set('clap-push-up')), ...array_fill(0, 4, $set('incline-dumbbell-press'))]);

    $progress = app(QuestEvaluator::class)->progress($monday, $sets);
    $byExercise = collect($monday)->pluck('exercise_id')->combine($progress);

    expect($byExercise[$ids['bench-press']])->toBe(0)->and($byExercise[$ids['dips']])->toBe(0)
        ->and($byExercise[$ids['clap-push-up']])->toBe(4)->and($byExercise[$ids['incline-dumbbell-press']])->toBe(4);
});

it('compte encore un exercice de remplacement hors quête dans le même emplacement', function () {
    $objectives = [['exercise_id' => $this->bench->id, 'slot' => 'horizontal_push', 'sets' => 3]];
    $db = Exercise::firstWhere('slug', 'dumbbell-bench-press');

    expect(app(QuestEvaluator::class)->progress($objectives, collect(array_fill(0, 3, (object) ['exercise_id' => $db->id, 'exercise' => $db]))))->toBe([3]);
});

it('garde les intentions quand un ami copie le programme', function () {
    $friend = User::factory()->withMorphology()->create();
    $copy = app(ProgramBuilder::class)->copy($friend, $this->user->activeProgram, [1, 2, 3, 4, 5]);

    expect($copy->days->first()->exercises->pluck('intent')->all())->toBe($this->user->activeProgram->days->first()->exercises->pluck('intent')->all());
});

it('attache chaque journée du programme personnalisé à son jour de la semaine', function () {
    $this->user->forceFill(['calibrated_at' => now()])->save();

    foreach (['2026-10-14' => 'Épaules · PUISSANCE', '2026-10-16' => 'Jambes & hanches · EXPLOSIF', '2026-10-19' => 'Poussée · FORCE MAX'] as $date => $name) {
        $this->travelTo(Carbon::parse($date.' 10:00'));
        app(QuestScheduler::class)->sync($this->user->fresh(), now());
        expect(Quest::whereDate('date', $date)->where('type', 'training')->value('title'), $date)->toBe($name);
    }
});
