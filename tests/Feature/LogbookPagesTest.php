<?php

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(weightKg: 80.0)->create();
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
    foreach ([['2026-09-20T10:00:00Z', 60], ['2026-09-27T10:00:00Z', 65]] as [$at, $kg]) {
        app(WorkoutSync::class)->sync($this->user, [
            'client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
            'sets' => [['exercise_id' => $this->bench->id, 'weight_kg' => $kg, 'reps' => 5, 'done_at' => $at]],
        ]);
    }
    $this->actingAs($this->user);
});

it('liste l\'historique du plus récent au plus ancien', function () {
    $this->get('/history')->assertOk()->assertSeeInOrder(['Dimanche 27 septembre', 'Dimanche 20 septembre'])->assertSee('325 kg');
});

it('affiche le détail d\'une séance', function () {
    $workout = Workout::latest('finished_at')->first();
    $this->get("/history/{$workout->id}")->assertOk()->assertSee('Développé couché (barre)')->assertSee('65 kg × 5');
});

it('affiche les records avec le ratio au poids de corps', function () {
    $this->get('/records')->assertOk()->assertSee('Développé couché (barre)')->assertSee('65 kg × 5')->assertSee('0,81 × PDC');
});

it('affiche la progression d\'un exercice', function () {
    $this->get("/progress/{$this->bench->id}")->assertOk()->assertSee('<polyline', false)->assertSee('65 kg');
});

it('indique qu\'il n\'y a pas encore de données', function () {
    $squat = Exercise::firstWhere('slug', 'back-squat');
    $this->get("/progress/{$squat->id}")->assertOk()->assertSee('Pas encore de données');
});
