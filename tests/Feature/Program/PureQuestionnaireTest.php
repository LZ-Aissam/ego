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
    $this->user = User::factory()->withMorphology(weightKg: 70.0)->create();
    $this->actingAs($this->user);
});

it('propose Force pure dans le questionnaire et forge le programme', function () {
    Livewire::test(ProgramPage::class)
        ->call('next')->set('level', 'advanced')->call('next')
        ->assertSeeText('Force pure')
        ->set('goal', 'pure')->call('next')
        ->set('weekdays', [1, 2, 4, 5])->call('next')
        ->set('equipment', 'gym')
        ->call('generate')->assertHasNoErrors();

    $program = $this->user->fresh()->activeProgram;
    expect($program->name)->toBe('Programme Force pure · 4 j')->and($program->split)->toBe('pure');
});

it('attache chaque séance Force pure à son jour, soulevé de terre le vendredi', function () {
    app(ProgramBuilder::class)->build($this->user, ['level' => 'advanced', 'goal' => 'pure', 'weekdays' => [1, 2, 4, 5], 'equipment' => 'gym']);
    $this->user->forceFill(['calibrated_at' => now()])->save();

    foreach (['2026-10-13' => 'Squat · LOURD', '2026-10-16' => 'Soulevé de terre · LOURD'] as $date => $name) {
        $this->travelTo(Carbon::parse($date.' 10:00'));
        app(QuestScheduler::class)->sync($this->user->fresh(), now());
        expect(Quest::whereDate('date', $date)->where('type', 'training')->value('title'), $date)->toBe($name);
    }
});

it('conseille de manger à maintien pour la force pure', function () {
    app(ProgramBuilder::class)->build($this->user, ['level' => 'advanced', 'goal' => 'pure', 'weekdays' => [1, 2, 4, 5], 'equipment' => 'gym']);

    $this->get('/program')->assertSeeText('mange à maintien')->assertSeeText('🏆 RECORD')->assertDontSeeText('léger déficit');
});
