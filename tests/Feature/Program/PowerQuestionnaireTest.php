<?php

use App\Game\QuestScheduler;
use App\Livewire\ProgramPage;
use App\Models\Quest;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-12 08:00'));                         // lundi
    $this->user = User::factory()->withMorphology(weightKg: 80.0)->create();
    $this->actingAs($this->user);
});

it('propose Force × Vitesse dans le questionnaire et forge le programme', function () {
    Livewire::test(ProgramPage::class)
        ->call('next')->set('level', 'advanced')->call('next')
        ->assertSeeText('Force × Vitesse')
        ->set('goal', 'power')->call('next')
        ->set('weekdays', [1, 3, 5])->call('next')
        ->set('equipment', 'gym')
        ->call('generate')->assertHasNoErrors();

    $program = $this->user->fresh()->activeProgram;
    expect($program->name)->toBe('Programme Force × Vitesse · 3 j')
        ->and($program->split)->toBe('power')
        ->and($this->user->fresh()->goal)->toBe('power')
        ->and($program->days->flatMap->exercises->pluck('intent')->filter()->count())->toBe($program->days->flatMap->exercises->count());
});

it('attache chaque séance Force × Vitesse à son jour de la semaine', function () {
    app(ProgramBuilder::class)->build($this->user, ['level' => 'advanced', 'goal' => 'power', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    $this->user->forceFill(['calibrated_at' => now()])->save();

    foreach (['2026-10-14' => 'Tirage · FORCE MAX', '2026-10-16' => 'Jambes & hanches · EXPLOSIF'] as $date => $name) {
        $this->travelTo(Carbon::parse($date.' 10:00'));
        app(QuestScheduler::class)->sync($this->user->fresh(), now());
        expect(Quest::whereDate('date', $date)->where('type', 'training')->value('title'), $date)->toBe($name);
    }
});

it('garde les jours attachés quand un ami copie un programme Force × Vitesse', function () {
    $source = app(ProgramBuilder::class)->build(User::factory()->withMorphology()->create(['first_name' => 'Aïssam']), ['level' => 'advanced', 'goal' => 'power', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    app(ProgramBuilder::class)->copy($this->user, $source, [2, 4, 6]);
    $this->user->forceFill(['calibrated_at' => now()])->save();

    $this->travelTo(Carbon::parse('2026-10-17 10:00'));                         // samedi = 3ᵉ jour coché
    app(QuestScheduler::class)->sync($this->user->fresh(), now());
    expect(Quest::whereDate('date', '2026-10-17')->where('type', 'training')->value('title'))->toBe('Jambes & hanches · EXPLOSIF');
});

it('retire les intentions quand on régénère en Prise de muscle', function () {
    $builder = app(ProgramBuilder::class);
    $builder->build($this->user, ['level' => 'advanced', 'goal' => 'power', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    $program = $builder->build($this->user->fresh(), ['level' => 'advanced', 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);

    expect($program->days->flatMap->exercises->pluck('intent')->filter()->all())->toBe([]);
});
