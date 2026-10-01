<?php

use App\Game\Calibration;
use App\Game\CalibrationPlan;
use App\Game\QuestScheduler;
use App\Livewire\Home;
use App\Livewire\ProgramPage;
use App\Models\Exercise;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-05 10:00'));          // lundi
});

function forge(User $user, string $equipment = 'gym'): void
{
    app(ProgramBuilder::class)->build($user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => $equipment]);
}

function testAll(User $user, float $kg, int $reps): array
{
    $results = array_map(fn ($m) => ['exercise_id' => $m['exercise_id'], 'warmups' => $m['warmups'], 'test' => [$m['isBodyweight'] ? 0 : $kg, $reps]], app(CalibrationPlan::class)->plan($user));

    return app(Calibration::class)->complete($user, $results, now()->subMinutes(30)->toIso8601String());
}

it('annonce les nouveaux records après un recalibrage, juste après la séance', function () {
    $user = User::factory()->withMorphology()->create();
    forge($user);
    testAll($user, 50, 10);                                      // première mesure : records créés, non annoncés (comme en séance)

    $titles = collect(testAll($user->fresh(), 60, 10)['notifications'])->pluck('title')->all();

    expect($titles[0])->toBe('PUISSANCE MESURÉE')->and($titles[1])->toBe('SÉANCE TERMINÉE')
        ->and(array_slice($titles, 2, 3))->toBe(['NOUVEAU RECORD', 'NOUVEAU RECORD', 'NOUVEAU RECORD']);
});

it('décrit la quête de calibrage par ses échauffements et sa série max', function () {
    $user = User::factory()->withMorphology()->create();
    forge($user);
    app(QuestScheduler::class)->sync($user, now());
    $this->actingAs($user);

    Livewire::test(Home::class)->assertSee('2 échauffements + 1 série max')->assertDontSee('3 × 1–30');
});

it('mesure les exercices au poids du corps en répétitions, sans avertissement', function () {
    $user = User::factory()->withMorphology()->create();
    forge($user, 'bodyweight');
    $pushUp = Exercise::firstWhere('slug', 'push-up');

    $lines = testAll($user, 0, 25)['notifications'][0]['lines'];

    expect($user->exerciseMaxes()->where('exercise_id', $pushUp->id)->value('best_reps'))->toBe(25)
        ->and($lines)->toContain('Pompes : 25 répétitions max')
        ->and(implode(' ', $lines))->not->toContain('moins précise');

    $this->actingAs($user)->get(route('profile'))->assertSee('25 répétitions max')->assertDontSee('1RM');
});

it('ne crée pas de charges en remplaçant un exercice avant le calibrage', function () {
    $user = User::factory()->withMorphology()->create();
    forge($user);
    $this->actingAs($user);
    $line = $user->activeProgram->days->first()->exercises->firstWhere('slot', 'squat');
    $front = Exercise::firstWhere('slug', 'front-squat');

    Livewire::test(ProgramPage::class)->call('replace', $line->id, $front->id);

    expect($user->workingLoads()->count())->toBe(0);
});
