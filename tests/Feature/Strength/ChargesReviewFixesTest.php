<?php

use App\Game\Calibration;
use App\Game\CalibrationPlan;
use App\Game\LoadPrescriber;
use App\Game\QuestScheduler;
use App\Livewire\CalibrationPage;
use App\Livewire\Home;
use App\Livewire\ProgramPage;
use App\Models\Exercise;
use App\Models\Quest;
use App\Models\User;
use App\Models\Workout;
use App\Services\ProgramBuilder;
use App\Services\WorkoutBootData;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-05 10:00'));          // lundi
});

function reviewProgram(User $user, string $equipment = 'gym')
{
    return app(ProgramBuilder::class)->build($user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => $equipment]);
}

function calibrate(User $user, ?string $clientId = null, float $kg = 50): array
{
    $results = array_map(fn ($m) => ['exercise_id' => $m['exercise_id'], 'warmups' => $m['warmups'], 'test' => [$m['isBodyweight'] ? 0 : $kg, 10]], app(CalibrationPlan::class)->plan($user));

    return app(Calibration::class)->complete($user, $results, now()->subMinutes(30)->toIso8601String(), $clientId);
}

it('garde la progression des exercices non testés quand on recalibre', function () {
    $user = User::factory()->withMorphology(sex: 'male', weightKg: 80.0)->create();
    reviewProgram($user);
    calibrate($user);
    $tested = collect(app(CalibrationPlan::class)->plan($user))->pluck('exercise_id');
    $untested = $user->workingLoads()->whereNotIn('exercise_id', $tested)->firstOrFail();
    $untested->update(['target_kg' => 37.5]);

    calibrate($user->fresh());

    expect($untested->fresh()->target_kg)->toBe(37.5);
});

it('n\'enregistre qu\'une fois un calibrage envoyé deux fois', function () {
    $user = User::factory()->withMorphology()->create();
    reviewProgram($user);

    $clientId = (string) \Illuminate\Support\Str::uuid();
    calibrate($user, $clientId);
    $second = calibrate($user->fresh(), $clientId, 80);

    expect(Workout::count())->toBe(1)
        ->and($second['notifications'])->toBe([])
        ->and((float) $user->exerciseMaxes()->max('e1rm_kg'))->toBe(66.7);
});

it('ignore un second « Terminer » sur la page de calibrage', function () {
    $user = User::factory()->withMorphology()->create();
    reviewProgram($user);
    $this->actingAs($user);

    $page = Livewire::test(CalibrationPage::class);
    foreach ($page->get('movements') as $movement) {
        $page->call('toggleWarmup', 0)->call('toggleWarmup', 1)
            ->set('testKg', $movement['isBodyweight'] ? '0' : '50')->set('testReps', '10')->call('saveTest');
    }
    $page->call('saveTest');

    expect(Workout::count())->toBe(1);
});

it('refuse que le navigateur réécrive l\'état du calibrage', function (string $property, mixed $value) {
    $user = User::factory()->withMorphology()->create();
    reviewProgram($user);
    $this->actingAs($user);

    Livewire::test(CalibrationPage::class)->set($property, $value);
})->with([
    ['movements', []], ['results', [['exercise_id' => 1, 'warmups' => [], 'test' => [900, 1]]]], ['index', 5],
    ['warmupsDone', [true, true]], ['startedAt', 'nimporte'], ['clientId', 'x'],
])->throws(CannotUpdateLockedPropertyException::class);

it('déduit la charge d\'un exercice depuis l\'exercice testé du même emplacement, puis de la même région', function () {
    $user = User::factory()->withMorphology(sex: 'male', weightKg: 80.0)->create();
    $user->forceFill(['power_factor' => 1.5])->save();
    $backSquat = Exercise::firstWhere('slug', 'back-squat');           // suggestion 47,5 kg
    $user->exerciseMaxes()->create(['exercise_id' => $backSquat->id, 'e1rm_kg' => 56, 'source' => 'calibration', 'measured_at' => now()]);
    $prescriber = app(LoadPrescriber::class);

    // 56 kg → charge de départ 40 kg, soit 40 / 47,5 de la suggestion ; squat avant : 40 × 0,842 = 33,7 → 32,5
    expect($prescriber->derivedLoad(Exercise::firstWhere('slug', 'front-squat'), $user))->toBe(32.5)
        // même région (bas du corps) : leg curl 20 × 0,842 = 16,8 → 17,5
        ->and($prescriber->derivedLoad(Exercise::firstWhere('slug', 'leg-curl'), $user))->toBe(17.5)
        // haut du corps non mesuré : facteur global 1,5 (écarté 7,5 → 12,5)
        ->and($prescriber->derivedLoad(Exercise::firstWhere('slug', 'dumbbell-fly'), $user))->toBe(12.5);
});

it('précise « par haltère » pour les charges aux haltères', function () {
    $user = User::factory()->withMorphology()->create();
    reviewProgram($user, 'dumbbells');
    $this->actingAs($user);

    Livewire::test(CalibrationPage::class)->assertSee('Charge par haltère (kg)');

    calibrate($user);
    Livewire::test(ProgramPage::class)->assertSee('par haltère');

    $quest = $user->quests()->firstOrCreate(['date' => today()->toDateString(), 'type' => 'training'], ['title' => 'J1', 'status' => 'pending',
        'objectives' => $user->activeProgram->days->first()->objectives()]);
    $plan = app(WorkoutBootData::class)->forQuest($user->fresh(), $quest)['plan'];
    expect(collect($plan)->firstWhere('perDumbbell', true))->not->toBeNull();
});

it('laisse repousser le calibrage : la quête devient la séance du jour, et revient une semaine plus tard', function () {
    $user = User::factory()->withMorphology()->create();
    reviewProgram($user);
    app(QuestScheduler::class)->sync($user, now());
    $quest = Quest::sole();
    $this->actingAs($user);

    Livewire::test(Home::class)->assertSee('Plus tard')->call('postponeCalibration');

    $quest->refresh();
    $programDay = \App\Models\ProgramDay::find($quest->program_day_id);
    expect($quest->type)->toBe('training')
        ->and($quest->title)->toBe($programDay->name)
        ->and($quest->objectives)->toBe($programDay->objectives());

    $this->travelTo(Carbon::parse('2026-10-07 10:00'));              // mercredi
    app(QuestScheduler::class)->sync($user->fresh(), now());
    expect(Quest::whereDate('date', '2026-10-07')->value('type'))->toBe('training');

    $this->travelTo(Carbon::parse('2026-10-12 10:00'));              // lundi suivant
    app(QuestScheduler::class)->sync($user->fresh(), now());
    expect(Quest::whereDate('date', '2026-10-12')->value('type'))->toBe('calibration');
});
