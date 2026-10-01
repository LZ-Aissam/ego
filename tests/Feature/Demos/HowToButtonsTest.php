<?php

use App\Models\Quest;
use App\Models\User;
use App\Services\ProgramBuilder;
use App\Services\WorkoutBootData;
use App\Support\ExerciseGuide;
use Database\Seeders\ExerciseSeeder;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology()->create();
    $this->program = app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    $this->actingAs($this->user);
});

it('propose « Comment faire ? » pour chaque exercice du programme, avec ses consignes', function () {
    $line = $this->program->days->first()->exercises->first();

    $this->get(route('program'))->assertOk()
        ->assertSee('Comment faire ?')
        ->assertSee("exerciseDemo('{$line->exercise->slug}')", false)
        ->assertSee(ExerciseGuide::for($line->exercise->slug)['steps'][0]);
});

it('propose « Comment faire ? » pendant le calibrage', function () {
    $this->get(route('calibration'))->assertOk()->assertSee('Comment faire ?')->assertSee('exerciseDemo(', false);
});

it('fournit la fiche de chaque exercice à l\'écran de séance', function () {
    $quest = $this->user->quests()->create(['date' => today(), 'type' => 'training', 'title' => 'J1', 'status' => 'pending',
        'objectives' => $this->program->days->first()->objectives()]);
    $boot = app(WorkoutBootData::class)->forQuest($this->user, $quest);
    $bench = collect($boot['exercises'])->firstWhere('slug', 'bench-press');

    expect($bench['guide'])->toBe(ExerciseGuide::for('bench-press'));
    $this->get(route('workout'))->assertOk()->assertSee('Comment faire ?')->assertSee('openDemo(', false);
});
