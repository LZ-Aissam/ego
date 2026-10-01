<?php

use App\Livewire\ProgramPage;
use App\Models\Exercise;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-05 10:00'));
    $this->user = User::factory()->withMorphology()->create(['calibrated_at' => null]);
    $this->user->forceFill(['calibrated_at' => now(), 'power_factor' => 1.0])->save();
    $this->program = app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    $this->squat = $this->program->days->first()->exercises->firstWhere('slot', 'squat');
    $this->user->workingLoads()->updateOrCreate(['exercise_id' => $this->squat->exercise_id], ['target_kg' => 62.5, 'fail_streak' => 0]);
    $this->actingAs($this->user);
});

it('prescrit les charges dès la génération quand le joueur est calibré', function () {
    expect($this->user->workingLoads()->count())->toBeGreaterThan(1);
});

it('affiche la charge prescrite dans la quête du jour, le programme et la séance', function () {
    $this->get('/')->assertSee('· 62,5 kg', false);
    $this->get('/program')->assertSee('62,5 kg')->assertSee('Recalibrer');

    $quest = $this->user->quests()->where('type', 'training')->first();
    $this->get(route('workout.quest', $quest))->assertSee('"prescribedKg":62.5', false);
});

it('affiche le profil de force dans la fiche du joueur', function () {
    $this->user->exerciseMaxes()->create(['exercise_id' => $this->squat->exercise_id, 'e1rm_kg' => 95, 'source' => 'calibration', 'measured_at' => now()]);

    $this->get('/profile')->assertSee('PROFIL DE FORCE')->assertSee('95 kg');
});

it('prescrit une charge pour un exercice remplacé', function () {
    $front = Exercise::firstWhere('slug', 'front-squat');
    $this->user->workingLoads()->where('exercise_id', $front->id)->delete();

    Livewire::test(ProgramPage::class)->call('replace', $this->squat->id, $front->id);

    expect($this->user->workingLoads()->where('exercise_id', $front->id)->exists())->toBeTrue();
});
