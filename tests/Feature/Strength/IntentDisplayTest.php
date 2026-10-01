<?php

use App\Models\Exercise;
use App\Models\User;
use App\Services\ProgramBuilder;
use App\Services\WorkoutBootData;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-12 10:00'));                         // lundi
    $this->user = User::factory()->withMorphology(weightKg: 80.0)->create();
    $this->user->forceFill(['calibrated_at' => now()])->save();
    $this->program = app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'strength', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
    $this->user->exerciseMaxes()->create(['exercise_id' => $this->bench->id, 'e1rm_kg' => 100, 'source' => 'calibration', 'measured_at' => now()]);
    $this->program->days->first()->exercises->where('exercise_id', $this->bench->id)->each->update(['intent' => 'speed', 'sets' => 8, 'reps_min' => 3, 'reps_max' => 3]);
    $this->actingAs($this->user);
});

it('montre l\'intention et la charge de chaque objectif sur l\'accueil', function () {
    $this->get('/')->assertSeeText('⚡ Développé couché (barre)')->assertSeeText('8 × 3–3 · 57,5 kg');
});

it('donne à la séance l\'intention, la consigne et la charge de l\'objectif', function () {
    $this->get('/');                                                                 // la quête du jour se crée à la première page ouverte
    $quest = $this->user->quests()->where('date', today()->toDateString())->firstOrFail();
    $plan = collect(app(WorkoutBootData::class)->forQuest($this->user, $quest)['plan'])->firstWhere('id', $this->bench->id);

    expect($plan['intent'])->toBe('speed')
        ->and($plan['intentBadge'])->toBe('⚡ VITESSE')
        ->and($plan['intentCue'])->toBe('Charge légère : chaque répétition le plus vite possible, arrête la série dès que ça ralentit.')
        ->and($plan['prescribedKg'])->toBe(57.5);
});

it('garde la charge stockée en séance libre, sans intention', function () {
    $boot = app(WorkoutBootData::class)->for($this->user);
    $bench = collect($boot['exercises'])->firstWhere('id', $this->bench->id);

    expect(array_key_exists('intent', $bench))->toBeFalse();
});

it('affiche l\'intention et le conseil sur la page Programme', function () {
    $this->get(route('program'))->assertSee('⚡ VITESSE')->assertSee('Corps sec : vise 1,8 à 2 g de protéines par kg');
});

it('n\'affiche pas le conseil pour un programme sans intention', function () {
    $this->program->days->flatMap->exercises->each->update(['intent' => null]);
    $this->get(route('program'))->assertDontSee('Corps sec');
});
