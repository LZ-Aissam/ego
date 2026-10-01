<?php

use App\Game\QuestScheduler;
use App\Livewire\Home;
use App\Models\Exercise;
use App\Models\Quest;
use App\Models\User;
use App\Services\ProgramBuilder;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-05 10:00'));          // lundi
    $this->user = User::factory()->withMorphology()->create();
    app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    $this->actingAs($this->user);
});

it('remplace la séance du jour en attente par le calibrage tant que le joueur n\'est pas calibré', function () {
    $this->user->forceFill(['calibrated_at' => now()])->save();
    app(QuestScheduler::class)->sync($this->user, now());
    expect(Quest::sole()->type)->toBe('training');

    $this->user->forceFill(['calibrated_at' => null])->save();
    app(QuestScheduler::class)->sync($this->user->fresh(), now());

    $quest = Quest::sole();
    expect($quest->type)->toBe('calibration')->and($quest->title)->toBe('Calibrage')
        ->and(array_column($quest->objectives, 'sets'))->toBe([3, 3, 3]);
});

it('renvoie une séance de quête vers le calibrage tant que le joueur n\'est pas calibré', function () {
    $quest = $this->user->quests()->create(['date' => today(), 'type' => 'training', 'title' => 'Full body A', 'objectives' => [], 'status' => 'pending']);

    $this->get(route('workout.quest', $quest))->assertRedirect(route('calibration.quest', $quest));
});

it('n\'offre plus de « Plus tard » sur la quête de calibrage', function () {
    app(QuestScheduler::class)->sync($this->user, now());

    Livewire::test(Home::class)->assertSee('Accepter la quête')->assertDontSee('Plus tard');
});

it('rappelle le test sur l\'accueil même un jour de repos', function () {
    $this->travelTo(Carbon::parse('2026-10-06 10:00'));          // mardi : repos

    $this->get('/')->assertSee('TON EGO DOIT MESURER TA PUISSANCE')->assertSee(route('calibration'));

    $this->user->forceFill(['calibrated_at' => now()])->save();
    $this->get('/')->assertDontSee('TON EGO DOIT MESURER TA PUISSANCE');
});

it('ne valide pas la quête de calibrage avec une séance libre', function () {
    app(QuestScheduler::class)->sync($this->user, now());
    $sets = [];
    foreach (['bench-press', 'barbell-row', 'back-squat'] as $slug) {
        foreach (range(1, 3) as $i) {
            $sets[] = ['exercise_id' => Exercise::firstWhere('slug', $slug)->id, 'weight_kg' => 40, 'reps' => 8, 'done_at' => now()->toIso8601String()];
        }
    }

    $synced = app(WorkoutSync::class)->sync($this->user, ['client_id' => (string) \Illuminate\Support\Str::uuid(), 'started_at' => now()->subHour()->toIso8601String(), 'finished_at' => now()->toIso8601String(), 'sets' => $sets]);
    app(\App\Game\GameEvents::class)->afterWorkout($this->user->fresh(), $synced['workout'], $synced['newRecords']);

    expect(Quest::where('type', 'calibration')->value('status'))->toBe('pending');
});
