<?php

use App\Game\Calibration;
use App\Game\CalibrationPlan;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;

beforeEach(fn () => $this->seed(ExerciseSeeder::class));

function calibratedProgram(User $user, string $level = 'beginner', string $equipment = 'gym')
{
    return app(ProgramBuilder::class)->build($user, ['level' => $level, 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => $equipment]);
}

it('choisit les mouvements testés dans le programme selon le niveau et le matériel', function () {
    $beginner = User::factory()->withMorphology()->create();
    calibratedProgram($beginner);
    expect(app(CalibrationPlan::class)->movementsFor($beginner)->pluck('test_slot')->all())->toBe(['horizontal_push', 'horizontal_pull', 'squat']);

    $advanced = User::factory()->withMorphology(level: 'advanced')->create();
    calibratedProgram($advanced, 'advanced');
    expect(app(CalibrationPlan::class)->movementsFor($advanced)->pluck('test_slot')->all())->toBe(['horizontal_push', 'horizontal_pull', 'squat', 'hinge']);

    $bodyweight = User::factory()->withMorphology(level: 'advanced')->create();
    calibratedProgram($bodyweight, 'advanced', 'bodyweight');
    expect(app(CalibrationPlan::class)->movementsFor($bodyweight)->pluck('test_slot')->all())->toBe(['horizontal_push', 'horizontal_pull', 'squat']);
});

it('propose des échauffements à 50 % puis 70 % de l\'estimation', function () {
    $user = User::factory()->withMorphology(sex: 'male', weightKg: 80.0)->create();
    calibratedProgram($user);

    $first = app(CalibrationPlan::class)->plan($user)[0];          // développé couché : estimation 40 kg

    expect($first['estimate'])->toBe(40.0)->and($first['warmups'])->toBe([[20.0, 8], [27.5, 4]]);
});

it('termine le calibrage : séance, profil de force, facteur, charges et notifications, sans progression', function () {
    $user = User::factory()->withMorphology(sex: 'male', weightKg: 80.0)->create();
    $program = calibratedProgram($user);
    $plan = app(CalibrationPlan::class)->plan($user);
    $results = array_map(fn ($m) => ['exercise_id' => $m['exercise_id'], 'warmups' => $m['warmups'], 'test' => [$m['isBodyweight'] ? 0 : 50, 10]], $plan);

    $out = app(Calibration::class)->complete($user, $results, now()->subMinutes(30)->toIso8601String());

    $user = $user->fresh();
    $bench = Exercise::firstWhere('slug', 'bench-press');
    expect(Workout::count())->toBe(1)
        ->and($out['workout']->sets)->toHaveCount(3 * count($plan))
        ->and($user->calibrated_at)->not->toBeNull()
        ->and($user->exerciseMaxes()->where('exercise_id', $bench->id)->value('e1rm_kg'))->toBe(66.7)
        ->and($user->workingLoads()->where('exercise_id', $bench->id)->value('target_kg'))->toBe(47.5)
        ->and($user->power_factor)->toBeGreaterThan(0.5)
        ->and($out['notifications'][0]['title'])->toBe('PUISSANCE MESURÉE')
        ->and($out['notifications'][0]['lines'][0])->toContain('Développé couché (barre) : 1RM ≈ 66,7 kg')
        ->and(collect($out['notifications'])->pluck('title'))->not->toContain('CHARGE AUGMENTÉE');
});

it('fonctionne sans poids ni sexe connus, avec 20 kg d\'estimation', function () {
    $user = User::factory()->create();
    calibratedProgram($user);
    $plan = app(CalibrationPlan::class)->plan($user);

    expect($plan[0]['estimate'])->toBe(20.0);

    $results = array_map(fn ($m) => ['exercise_id' => $m['exercise_id'], 'warmups' => $m['warmups'], 'test' => [$m['isBodyweight'] ? 0 : 30, 8]], $plan);
    app(Calibration::class)->complete($user, $results, now()->toIso8601String());

    expect($user->fresh()->calibrated_at)->not->toBeNull();
});

it('recalibre : charges des exercices testés recalculées et compteur d\'échecs remis à zéro', function () {
    $user = User::factory()->withMorphology()->create();
    calibratedProgram($user);
    $bench = Exercise::firstWhere('slug', 'bench-press');
    $user->workingLoads()->create(['exercise_id' => $bench->id, 'target_kg' => 100, 'fail_streak' => 1]);
    $plan = app(CalibrationPlan::class)->plan($user);
    $results = array_map(fn ($m) => ['exercise_id' => $m['exercise_id'], 'warmups' => $m['warmups'], 'test' => [$m['isBodyweight'] ? 0 : 50, 10]], $plan);

    app(Calibration::class)->complete($user, $results, now()->toIso8601String());

    $load = $user->workingLoads()->where('exercise_id', $bench->id)->first();
    expect($load->target_kg)->toBe(47.5)->and($load->fail_streak)->toBe(0);
});

it('ne teste que les mouvements présents dans le programme (programme sans jambes)', function () {
    $user = User::factory()->withMorphology(level: 'advanced')->create();
    $program = calibratedProgram($user, 'advanced');
    $program->days->flatMap->exercises->whereIn('slot', ['squat', 'lunge', 'hinge', 'quads_iso', 'hamstrings_iso', 'calves'])->each->delete();

    expect(app(CalibrationPlan::class)->movementsFor($user->fresh())->pluck('test_slot')->all())->toBe(['horizontal_push', 'horizontal_pull']);
});

it('teste un exercice chargé plutôt qu\'un exercice au poids du corps du même emplacement', function () {
    $user = User::factory()->withMorphology(level: 'advanced')->create(['first_name' => 'Aïssam']);
    $this->artisan('ego:programme-force-vitesse', ['prenom' => 'Aïssam'])->assertSuccessful();

    expect(app(CalibrationPlan::class)->movementsFor($user->fresh())->pluck('slug')->all())
        ->toBe(['bench-press', 'barbell-row', 'back-squat', 'deadlift']);
});
