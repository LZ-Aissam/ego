<?php

use App\Game\PlayerStats;
use App\Game\XpLedger;
use App\Models\Exercise;
use App\Models\PersonalRecord;
use App\Models\User;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(weightKg: 80.0)->create();
});

function recordFor(User $user, string $slug, float $e1rm): void
{
    PersonalRecord::create([
        'user_id' => $user->id, 'exercise_id' => Exercise::firstWhere('slug', $slug)->id,
        'max_weight_kg' => $e1rm * 0.8, 'max_weight_reps' => 5, 'best_e1rm_kg' => $e1rm, 'achieved_at' => now(),
    ]);
}

function repsAt(User $user, int $reps, string $finishedAt): void
{
    app(WorkoutSync::class)->sync($user, [
        'client_id' => (string) Str::uuid(), 'started_at' => $finishedAt, 'finished_at' => $finishedAt,
        'sets' => [['exercise_id' => Exercise::firstWhere('slug', 'push-up')->id, 'weight_kg' => 0, 'reps' => $reps, 'done_at' => $finishedAt]],
    ]);
}

it('arrondit la Force : moyenne 100 kg pour 80 kg de poids → 63', function () {
    recordFor($this->user, 'back-squat', 120);
    recordFor($this->user, 'bench-press', 100);
    recordFor($this->user, 'deadlift', 80);
    recordFor($this->user, 'barbell-row', 60);
    recordFor($this->user, 'leg-extension', 300);

    expect(app(PlayerStats::class)->for($this->user)['force'])->toBe(63);
});

it('ne calcule pas la Force sans poids de corps', function () {
    $user = User::factory()->create();
    recordFor($user, 'back-squat', 120);

    expect(app(PlayerStats::class)->for($user)['force'])->toBeNull();
});

it('compte l\'Endurance et la Progression sur 28 jours glissants', function () {
    repsAt($this->user, 120, now()->subDays(3)->toIso8601String());
    repsAt($this->user, 500, now()->subDays(40)->toIso8601String());
    app(XpLedger::class)->award($this->user, 'record', 1, 100, 2, now()->subDays(2));
    app(XpLedger::class)->award($this->user, 'record', 2, 150, 3, now()->subDays(35));

    $stats = app(PlayerStats::class)->for($this->user);

    expect($stats['endurance'])->toBe(2)
        ->and($stats['progression'])->toBe(20)
        ->and($stats['regularite'])->toBeNull();
});
