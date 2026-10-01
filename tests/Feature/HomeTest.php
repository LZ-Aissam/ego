<?php

use App\Models\Exercise;
use App\Models\User;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->actingAs($this->user = User::factory()->create());
});

it('propose une séance libre et invite à compléter la morphologie', function () {
    $this->get('/')->assertOk()->assertSee('Séance libre')->assertSee(route('workout'))->assertSee('Complète ton profil');
});

it('affiche la dernière séance et les derniers records', function () {
    $bench = Exercise::firstWhere('slug', 'bench-press');
    app(WorkoutSync::class)->sync($this->user, [
        'client_id' => (string) Str::uuid(), 'started_at' => '2026-09-27T10:00:00Z', 'finished_at' => '2026-09-27T10:40:00Z',
        'sets' => [['exercise_id' => $bench->id, 'weight_kg' => 60, 'reps' => 8, 'done_at' => '2026-09-27T10:10:00Z']],
    ]);

    $this->get('/')->assertSee('DERNIÈRE SÉANCE')->assertSee('40 min')->assertSee('Développé couché (barre)')->assertSee('60 kg × 8');
});

it('affiche la console du joueur en fenêtres système avec un volume animé', function () {
    $bench = Exercise::firstWhere('slug', 'bench-press');
    app(WorkoutSync::class)->sync($this->user, [
        'client_id' => (string) Str::uuid(), 'started_at' => '2026-09-27T10:00:00Z', 'finished_at' => '2026-09-27T10:40:00Z',
        'sets' => [['exercise_id' => $bench->id, 'weight_kg' => 60, 'reps' => 8, 'done_at' => '2026-09-27T10:10:00Z']],
    ]);

    $this->get('/')
        ->assertSee('SYSTÈME EGO · EN LIGNE')
        ->assertSee('DERNIÈRE SÉANCE')
        ->assertSee('DERNIERS RECORDS')
        ->assertSee('data-count-up="480"', false);
});
