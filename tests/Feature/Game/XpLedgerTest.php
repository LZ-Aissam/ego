<?php

use App\Game\XpLedger;
use App\Models\Exercise;
use App\Models\User;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->create();
    $this->ledger = app(XpLedger::class);
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
});

function workoutAt(User $user, int $exerciseId, string $finishedAt, int $sets = 3)
{
    return app(WorkoutSync::class)->sync($user, [
        'client_id' => (string) Str::uuid(), 'started_at' => $finishedAt, 'finished_at' => $finishedAt,
        'sets' => array_fill(0, $sets, ['exercise_id' => $exerciseId, 'weight_kg' => 40, 'reps' => 10, 'done_at' => $finishedAt]),
    ])['workout'];
}

it('calcule l\'XP d\'une séance : 3 séries minimum, 30 au maximum', function (int $sets, int $xp) {
    expect($this->ledger->workoutXp($sets))->toBe($xp);
})->with([[0, 0], [2, 0], [3, 65], [10, 100], [30, 200], [45, 200]]);

it('attribue une source une seule fois et tient le total à jour', function () {
    expect($this->ledger->award($this->user, 'workout', 7, 65))->toBeTrue()
        ->and($this->ledger->award($this->user, 'workout', 7, 65))->toBeFalse()
        ->and($this->user->xp_total)->toBe(65)
        ->and($this->user->fresh()->xp_total)->toBe(65);
});

it('limite l\'XP à deux séances par jour calendaire de Paris', function () {
    $a = workoutAt($this->user, $this->bench->id, '2026-10-01T06:00:00Z');
    $b = workoutAt($this->user, $this->bench->id, '2026-10-01T12:00:00Z');
    $c = workoutAt($this->user, $this->bench->id, '2026-10-01T20:00:00Z');
    $nextDay = workoutAt($this->user, $this->bench->id, '2026-10-01T22:30:00Z'); // 00:30 le 2 à Paris

    foreach ([$a, $b] as $w) {
        expect($this->ledger->isEligible($this->user, $w))->toBeTrue();
        $this->ledger->award($this->user, 'workout', $w->id, 65, at: $w->finished_at);
    }

    expect($this->ledger->isEligible($this->user, $c))->toBeFalse()
        ->and($this->ledger->isEligible($this->user, $nextDay))->toBeTrue();
});

it('recalcule le total depuis le journal', function () {
    $this->ledger->award($this->user, 'workout', 1, 65);
    $this->ledger->award($this->user, 'record', 1, 100, 2);
    $this->user->forceFill(['xp_total' => 9999])->save();

    expect($this->ledger->recalculate($this->user))->toBe(165)
        ->and($this->user->fresh()->xp_total)->toBe(165);
});
