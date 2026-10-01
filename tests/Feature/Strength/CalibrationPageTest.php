<?php

use App\Livewire\CalibrationPage;
use App\Models\Quest;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(sex: 'male', weightKg: 80.0)->create();
    $this->actingAs($this->user);
});

it('renvoie vers le programme sans programme actif', function () {
    $this->get('/calibration')->assertRedirect(route('program'));
});

it('mène le calibrage mouvement par mouvement jusqu\'à la mesure', function () {
    app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);

    $this->get('/calibration')->assertOk()->assertSee('TON EGO ÉVALUE TA PUISSANCE')->assertSee('Développé couché (barre)');

    $page = Livewire::test(CalibrationPage::class);
    foreach (range(0, count($page->get('movements')) - 1) as $i) {
        $page->call('saveTest')->assertHasErrors(['warmups']);      // échauffements d'abord
        $page->call('toggleWarmup', 0)->call('toggleWarmup', 1)
            ->set('testKg', $page->get('movements')[$i]['isBodyweight'] ? '0' : '50')
            ->set('testReps', '10')
            ->call('saveTest')->assertHasNoErrors();
    }

    $page->assertRedirect(route('home'));
    expect($this->user->fresh()->calibrated_at)->not->toBeNull()
        ->and(SystemNotification::where('type', 'power')->exists())->toBeTrue();
});

it('refuse une série test vide ou hors limites', function (string $kg, string $reps) {
    app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);

    Livewire::test(CalibrationPage::class)
        ->call('toggleWarmup', 0)->call('toggleWarmup', 1)
        ->set('testKg', $kg)->set('testReps', $reps)
        ->call('saveTest')->assertHasErrors()->assertSee('Indique la charge et le nombre de répétitions réalisées');
})->with([['', '10'], ['0', '10'], ['50', '0'], ['50', '31']]);

it('n\'ouvre la quête de calibrage que pour son propriétaire, le jour même', function () {
    app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    $quest = $this->user->quests()->create(['date' => today(), 'type' => 'calibration', 'title' => 'Calibrage', 'objectives' => [], 'status' => 'pending']);

    $this->get(route('calibration.quest', $quest))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('calibration.quest', $quest))->assertForbidden();
    $quest->update(['status' => 'done']);
    $this->actingAs($this->user)->get(route('calibration.quest', $quest))->assertRedirect(route('home'));
});
