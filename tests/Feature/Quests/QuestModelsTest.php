<?php

use App\Models\Exercise;
use App\Models\Program;
use App\Models\Quest;
use App\Models\User;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(fn () => $this->seed(ExerciseSeeder::class));

it('relie programme, jours et exercices, et produit les objectifs d\'un jour', function () {
    $user = User::factory()->create();
    $program = $user->programs()->create(['name' => 'Test', 'split' => 'full_body', 'params' => ['goal' => 'hypertrophy']]);
    $day = $program->days()->create(['position' => 1, 'name' => 'Full body A', 'type' => 'full_body']);
    $bench = Exercise::firstWhere('slug', 'bench-press');
    $day->exercises()->create(['position' => 1, 'exercise_id' => $bench->id, 'slot' => 'horizontal_push', 'sets' => 3, 'reps_min' => 6, 'reps_max' => 10, 'rest_seconds' => 120]);
    $user->forceFill(['active_program_id' => $program->id])->save();

    expect($user->fresh()->activeProgram->id)->toBe($program->id)
        ->and($program->fresh()->days->first()->objectives())->toBe([[
            'exercise_id' => $bench->id, 'name' => 'Développé couché (barre)', 'slot' => 'horizontal_push',
            'sets' => 3, 'reps_min' => 6, 'reps_max' => 10, 'rest_seconds' => 120, 'intent' => null,
        ]]);
});

it('interdit deux quêtes du même type le même jour', function () {
    $user = User::factory()->create();
    $user->quests()->create(['date' => '2026-10-05', 'type' => 'training', 'title' => 'A', 'objectives' => [], 'status' => 'pending']);

    $user->quests()->create(['date' => '2026-10-05', 'type' => 'training', 'title' => 'B', 'objectives' => [], 'status' => 'pending']);
})->throws(UniqueConstraintViolationException::class);

it('sait si le joueur est en pause un jour donné', function () {
    $user = User::factory()->create();
    expect($user->isPaused(now()))->toBeFalse();

    $user->forceFill(['paused_at' => '2026-10-05', 'paused_until' => '2026-10-07'])->save();
    $user = $user->fresh();

    expect($user->isPaused(\Illuminate\Support\Carbon::parse('2026-10-04')))->toBeFalse()
        ->and($user->isPaused(\Illuminate\Support\Carbon::parse('2026-10-06')))->toBeTrue()
        ->and($user->isPaused(\Illuminate\Support\Carbon::parse('2026-10-08')))->toBeFalse();

    $user->forceFill(['paused_until' => null])->save();
    expect($user->fresh()->isPaused(\Illuminate\Support\Carbon::parse('2027-01-01')))->toBeTrue();
});

it('caste les champs de quête', function () {
    $quest = User::factory()->create()->quests()->create(['date' => '2026-10-05', 'type' => 'rest', 'title' => 'Repos', 'objectives' => [], 'status' => 'done']);

    expect(Quest::find($quest->id)->date->toDateString())->toBe('2026-10-05')
        ->and(Quest::find($quest->id)->objectives)->toBe([]);
});
