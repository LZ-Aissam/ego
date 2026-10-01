<?php

use App\Livewire\ProgramPage;
use App\Models\Exercise;
use App\Models\ProgramExercise;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(sex: 'female', heightCm: 165, weightKg: 60.0)->create();
    $this->actingAs($this->user);
});

function answerQuestionnaire($component)
{
    return $component
        ->call('next')                                        // morphologie pré-remplie
        ->set('level', 'beginner')->call('next')
        ->set('goal', 'hypertrophy')->call('next')
        ->set('weekdays', [5, 1, 3])->call('next')
        ->set('equipment', 'gym');
}

it('ouvre le questionnaire quand aucun programme n\'existe', function () {
    $this->get('/program')->assertOk()->assertSee('TON EGO VA FORGER TON PROGRAMME')->assertSee('MORPHOLOGIE');
});

it('génère et active le programme à la fin du questionnaire', function () {
    answerQuestionnaire(Livewire::test(ProgramPage::class))
        ->call('generate')
        ->assertHasNoErrors()
        ->assertRedirect(route('program'));

    $user = $this->user->fresh();
    expect($user->activeProgram)->not->toBeNull()
        ->and($user->activeProgram->days)->toHaveCount(3)
        ->and($user->training_weekdays)->toBe([1, 3, 5])
        ->and($user->level)->toBe('beginner')
        ->and($user->goal)->toBe('hypertrophy')
        ->and($user->equipment)->toBe('gym');

    $this->get('/program')->assertSee('Full body A')->assertSee('Full body C');
});

it('refuse moins de 2 ou plus de 6 jours', function (array $days) {
    Livewire::test(ProgramPage::class)
        ->call('next')->set('level', 'beginner')->call('next')->set('goal', 'fitness')->call('next')
        ->set('weekdays', $days)->call('next')
        ->assertHasErrors(['weekdays'])
        ->assertSet('step', 4);
})->with([[[1]], [[1, 2, 3, 4, 5, 6, 7]]]);

it('remplace un exercice par une alternative du même emplacement uniquement', function () {
    $program = app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3], 'equipment' => 'gym']);
    $line = $program->days->first()->exercises->first();          // emplacement squat
    $frontSquat = Exercise::firstWhere('slug', 'front-squat');
    $curl = Exercise::firstWhere('slug', 'barbell-curl');

    Livewire::test(ProgramPage::class)->call('replace', $line->id, $curl->id);
    expect(ProgramExercise::find($line->id)->exercise_id)->not->toBe($curl->id);

    Livewire::test(ProgramPage::class)->call('replace', $line->id, $frontSquat->id);
    expect(ProgramExercise::find($line->id)->exercise_id)->toBe($frontSquat->id);
});

it('ignore le remplacement dans le programme d\'un autre joueur', function () {
    $other = User::factory()->create();
    $program = app(ProgramBuilder::class)->build($other, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3], 'equipment' => 'gym']);
    $line = $program->days->first()->exercises->first();

    Livewire::test(ProgramPage::class)->call('replace', $line->id, Exercise::firstWhere('slug', 'front-squat')->id);

    expect(ProgramExercise::find($line->id)->exercise_id)->toBe($line->exercise_id);
});

it('rouvre le questionnaire pour régénérer', function () {
    app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3], 'equipment' => 'gym']);

    Livewire::test(ProgramPage::class)->call('regenerate')->assertSet('editing', true)->assertSee('MORPHOLOGIE');
});

it('isole les champs de chaque étape pour que Livewire ne recycle pas les boutons d\'une étape à l\'autre', function () {
    Livewire::test(ProgramPage::class)
        ->assertSeeHtml('wire:key="program-step-1"')
        ->call('next')
        ->assertSeeHtml('wire:key="program-step-2"')
        ->assertDontSeeHtml('wire:key="program-step-1"');
});

it('affiche un message en français pour une valeur invalide, jamais une clé brute', function (string $field, string $value, int $step, string $message) {
    $component = Livewire::test(ProgramPage::class)->set('level', 'beginner')->set('goal', 'fitness')->set('weekdays', [1, 3])->set('equipment', 'gym')->set($field, $value);

    $component->call('generate')->assertHasErrors([$field])->assertSee($message)->assertDontSee('validation.')->assertSet('step', $step);
})->with([
    ['sex', 'autre', 1, 'Choisis homme ou femme.'],
    ['level', 'strength', 2, 'Choisis ton niveau.'],
    ['goal', 'beginner', 3, 'Choisis ton objectif.'],
    ['equipment', 'jardin', 5, 'Choisis ton matériel.'],
]);
