<?php

use App\Models\Exercise;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->create();
    $bench = Exercise::firstWhere('slug', 'bench-press')->id;
    // Séances enregistrées « avant » la livraison B : aucune XP attribuée.
    foreach ([['2026-09-01T10:00:00Z', 40], ['2026-09-02T10:00:00Z', 42.5], ['2026-09-03T10:00:00Z', 45]] as [$at, $kg]) {
        app(WorkoutSync::class)->sync($this->user, [
            'client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
            'sets' => array_fill(0, 3, ['exercise_id' => $bench, 'weight_kg' => $kg, 'reps' => 10, 'done_at' => $at]),
        ]);
    }
});

it('attribue rétroactivement l\'XP des séances et des records battus, sans notification', function () {
    $this->artisan('ego:backfill-xp')->assertSuccessful();

    $user = $this->user->fresh();
    expect($user->xp_total)->toBe(3 * 65 + 2 * 50)
        ->and($user->equipped_title)->toBe('awakened')
        ->and(SystemNotification::count())->toBe(0);
});

it('est idempotent', function () {
    $this->artisan('ego:backfill-xp');
    $this->artisan('ego:backfill-xp');

    expect($this->user->fresh()->xp_total)->toBe(295)
        ->and($this->user->xpEvents()->count())->toBe(5)
        ->and($this->user->titles()->count())->toBe(1);
});
