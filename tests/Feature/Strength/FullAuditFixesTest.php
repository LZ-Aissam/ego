<?php

use App\Game\CalibrationPlan;
use App\Game\LoadPrescriber;
use App\Game\PenaltyCircuit;
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
    $this->travelTo(Carbon::parse('2026-10-13 09:00'));                          // mardi
    $this->user = User::factory()->withMorphology(weightKg: 70.0, level: 'advanced')->create();
    $this->user->forceFill(['calibrated_at' => now(), 'power_factor' => 1.0])->save();
    $this->build = fn (array $params) => app(ProgramBuilder::class)->build($this->user->fresh(), ['level' => 'advanced', 'goal' => 'pure', 'equipment' => 'gym', ...$params]);
    $this->todayTraining = fn () => Quest::whereDate('date', '2026-10-13')->where('type', 'training')->first();
});

it('remplace la séance du jour quand le programme est refait', function () {
    ($this->build)(['weekdays' => [1, 2, 4, 5]]);
    app(QuestScheduler::class)->sync($this->user->fresh(), now());
    expect(($this->todayTraining)()->title)->toBe('Squat · LOURD');

    ($this->build)(['weekdays' => [1, 2, 3, 4, 5], 'upper_only' => true]);
    app(QuestScheduler::class)->sync($this->user->fresh(), now());               // la page suivante resynchronise

    $quest = ($this->todayTraining)();
    expect($quest->title)->toBe('Militaire · LOURD')
        ->and($quest->status)->toBe('pending')
        ->and(collect($quest->objectives)->pluck('name'))->not->toContain('Squat (barre)');
});

it('garde la séance du jour déjà commencée quand le programme est refait', function () {
    ($this->build)(['weekdays' => [1, 2, 4, 5]]);
    app(QuestScheduler::class)->sync($this->user->fresh(), now());
    $squat = Exercise::firstWhere('slug', 'back-squat');
    $at = now()->toIso8601String();
    app(WorkoutSync::class)->sync($this->user, ['client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
        'sets' => [['exercise_id' => $squat->id, 'weight_kg' => 100, 'reps' => 3, 'done_at' => $at]]]);

    ($this->build)(['weekdays' => [1, 2, 3, 4, 5], 'upper_only' => true]);
    app(QuestScheduler::class)->sync($this->user->fresh(), now());

    expect(($this->todayTraining)()->title)->toBe('Squat · LOURD');
});

it('transforme le repos du jour en séance quand le jour devient un jour d\'entraînement', function () {
    ($this->build)(['weekdays' => [1, 4]]);
    app(QuestScheduler::class)->sync($this->user->fresh(), now());
    expect(Quest::whereDate('date', '2026-10-13')->value('type'))->toBe('rest');

    ($this->build)(['weekdays' => [1, 2, 4]]);
    app(QuestScheduler::class)->sync($this->user->fresh(), now());

    expect(Quest::whereDate('date', '2026-10-13')->pluck('type')->all())->toBe(['training']);
});

it('teste au calibrage chaque mouvement à série record, dont le développé militaire', function () {
    ($this->build)(['weekdays' => [1, 2, 3, 4, 5], 'upper_only' => true]);

    expect(app(CalibrationPlan::class)->movementsFor($this->user->fresh())->pluck('slug')->all())
        ->toBe(['bench-press', 'barbell-row', 'overhead-press', 'pull-up', 'dips']);
});

it('adapte le pourcentage de la force max à la fourchette de répétitions', function () {
    ($this->build)(['weekdays' => [1, 2, 3, 4, 5], 'upper_only' => true]);
    $prescriber = app(LoadPrescriber::class);
    foreach (['bench-press', 'barbell-row'] as $slug) {
        $this->user->exerciseMaxes()->create(['exercise_id' => Exercise::firstWhere('slug', $slug)->id, 'e1rm_kg' => 100, 'source' => 'calibration', 'measured_at' => now()]);
    }
    $this->user->workingLoads()->delete();                                       // charge d'après le 1RM, pas une charge déjà suivie
    $user = $this->user->fresh();

    expect($prescriber->prescriptionFor($user, Exercise::firstWhere('slug', 'bench-press'), 'max', 3))->toBe(87.5)   // 2-3 répétitions
        ->and($prescriber->prescriptionFor($user, Exercise::firstWhere('slug', 'barbell-row'), 'max', 5))->toBe(82.5); // 3-5 répétitions
});

it('affiche le lest sur l\'accueil, pas une charge', function () {
    ($this->build)(['weekdays' => [1, 2, 3, 4, 5], 'upper_only' => true]);
    $dips = Exercise::firstWhere('slug', 'dips');
    $this->travelTo(Carbon::parse('2026-10-14 09:00'));                          // mercredi : Haut du corps · VITESSE, dips force max
    $this->user->workingLoads()->updateOrCreate(['exercise_id' => $dips->id], ['target_kg' => 10, 'fail_streak' => 0]);

    $this->actingAs($this->user)->get('/')->assertSeeText('+10 kg de lest');
});

it('ne met pas de squats dans la pénalité d\'un programme sans jambes', function () {
    $legs = Exercise::whereIn('slug', ['bodyweight-squat'])->pluck('id')->all();

    expect(array_column(app(PenaltyCircuit::class)->for('advanced', upperOnly: true), 'exercise_id'))->not->toContain($legs[0])
        ->and(array_column(app(PenaltyCircuit::class)->for('advanced'), 'exercise_id'))->toContain($legs[0]);
});

it('teste aussi au calibrage les tractions et les dips lestés, une seule traction pour les deux prises', function () {
    ($this->build)(['weekdays' => [1, 2, 3, 4, 5], 'upper_only' => true]);

    expect(app(CalibrationPlan::class)->movementsFor($this->user->fresh())->pluck('slug')->all())
        ->toBe(['bench-press', 'barbell-row', 'overhead-press', 'pull-up', 'dips']);
});

it('part du lest mesuré dès la première séance : tractions et tractions supination', function () {
    ($this->build)(['weekdays' => [1, 2, 3, 4, 5], 'upper_only' => true]);
    $pullUp = Exercise::firstWhere('slug', 'pull-up');
    $chinUp = Exercise::firstWhere('slug', 'chin-up');
    $this->user->exerciseMaxes()->create(['exercise_id' => $pullUp->id, 'e1rm_kg' => 105, 'best_reps' => 15, 'source' => 'calibration', 'measured_at' => now()]);
    app(LoadPrescriber::class)->prescribeProgram($this->user->fresh());

    $user = $this->user->fresh();
    expect(app(LoadPrescriber::class)->prescriptionFor($user, $pullUp, 'max', 3))->toBe(20.0)        // 105 × 87,5 % − 70 = 21,9 → 20
        ->and(app(LoadPrescriber::class)->prescriptionFor($user, $chinUp, 'max', 3))->toBe(20.0);
});
