<?php

use App\Crew\CrewService;
use App\Game\Calibration;
use App\Game\CalibrationPlan;
use App\Game\QuestScheduler;
use App\Game\XpLedger;
use App\Livewire\Profile;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(sex: 'male', heightCm: 182, weightKg: 81.0)->create(['first_name' => 'Aïssam']);
    app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    app(QuestScheduler::class)->sync($this->user, now());
    $results = array_map(fn ($m) => ['exercise_id' => $m['exercise_id'], 'warmups' => $m['warmups'], 'test' => [$m['isBodyweight'] ? 0 : 50, 10]], app(CalibrationPlan::class)->plan($this->user));
    app(Calibration::class)->complete($this->user, $results, now()->subHour()->toIso8601String());
    app(XpLedger::class)->award($this->user->fresh(), 'quest', 999, 50);
    $this->crew = app(CrewService::class)->create($this->user->fresh(), 'Les Ombres', 'violet');
    $this->actingAs($this->user);
});

function counts(User $user): array
{
    return [
        'workouts' => $user->workouts()->count(), 'records' => $user->personalRecords()->count(), 'maxes' => $user->exerciseMaxes()->count(),
        'loads' => $user->workingLoads()->count(), 'quests' => $user->quests()->count(), 'programs' => $user->programs()->count(),
        'xp' => $user->xpEvents()->count(), 'titles' => $user->titles()->count(),
        'notifications' => \App\Models\SystemNotification::where('user_id', $user->id)->count(),
    ];
}

it('refuse de tout effacer sans taper EFFACER', function () {
    $before = counts($this->user);

    Livewire::test(Profile::class)->call('askReset')->assertSee('Tape EFFACER pour confirmer')
        ->set('resetConfirm', 'effacé')->call('resetEverything')->assertHasErrors('resetConfirm');

    expect(counts($this->user->fresh()))->toBe($before)->and($before['workouts'])->toBeGreaterThan(0);
});

it('efface toute la progression mais garde le compte, la morphologie et les groupes', function () {
    Livewire::test(Profile::class)->call('askReset')->set('resetConfirm', ' effacer ')->call('resetEverything')
        ->assertRedirect(route('program'));

    $user = $this->user->fresh();
    expect(counts($user))->toBe(['workouts' => 0, 'records' => 0, 'maxes' => 0, 'loads' => 0, 'quests' => 0, 'programs' => 0, 'xp' => 0, 'titles' => 0, 'notifications' => 0])
        ->and([$user->xp_total, $user->streak_days, $user->best_streak, $user->equipped_title, $user->calibrated_at, $user->power_factor, $user->active_program_id, $user->training_weekdays, $user->level])
        ->toBe([0, 0, 0, null, null, null, null, null, null])
        ->and([$user->first_name, $user->sex, $user->height_cm, $user->weight_kg])->toBe(['Aïssam', 'male', 182, 81.0])
        ->and($this->crew->fresh()->hasMember($user))->toBeTrue();
});

it('demande au téléphone d\'oublier les séances locales après la réinitialisation', function () {
    Livewire::test(Profile::class)->call('askReset')->set('resetConfirm', 'EFFACER')->call('resetEverything');

    $this->get(route('program'))->assertSee('data-clear-local="1"', false);
    $this->get(route('program'))->assertDontSee('data-clear-local="1"', false);
});

it('n\'efface rien chez les autres joueurs', function () {
    $other = User::factory()->withMorphology()->create();
    app(ProgramBuilder::class)->build($other, ['level' => 'beginner', 'goal' => 'fitness', 'weekdays' => [2, 4], 'equipment' => 'gym']);

    Livewire::test(Profile::class)->call('askReset')->set('resetConfirm', 'EFFACER')->call('resetEverything');

    expect($other->fresh()->programs()->count())->toBe(1);
});
