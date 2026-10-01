<?php

use App\Crew\ChallengeBoard;
use App\Crew\ChallengeService;
use App\Crew\CrewService;
use App\Game\XpLedger;
use App\Models\Exercise;
use App\Models\User;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-11 20:00'));
    $this->ana = User::factory()->withMorphology()->create(['first_name' => 'Ana']);
    $this->bob = User::factory()->withMorphology()->create(['first_name' => 'Bob']);
    $this->cid = User::factory()->withMorphology()->create(['first_name' => 'Cid']);
    $crew = app(CrewService::class)->create($this->ana, 'Trio', 'ice');
    foreach ([$this->bob, $this->cid] as $u) {
        app(CrewService::class)->join($u, $crew->code);
    }
    $this->crew = $crew;
    $this->board = app(ChallengeBoard::class);
});

function challengeFor($test, string $metric, ?string $lift = null)
{
    $c = app(ChallengeService::class)->create($test->ana, $test->crew, $metric, $lift, '1w', null);
    foreach ([$test->bob, $test->cid] as $u) {
        app(ChallengeService::class)->join($u, $c);
    }

    return $c;                                               // du 12 au 19 octobre
}

function liftOn(User $user, string $at, array $sets): void
{
    $time = Carbon::parse($at);
    $ids = Exercise::pluck('id', 'slug');
    app(WorkoutSync::class)->sync($user, [
        'client_id' => (string) Str::uuid(), 'started_at' => $time->copy()->subHour()->toIso8601String(), 'finished_at' => $time->toIso8601String(),
        'sets' => array_map(fn ($s) => ['exercise_id' => $ids[$s[0]], 'weight_kg' => $s[1], 'reps' => $s[2], 'done_at' => $time->toIso8601String()], $sets),
    ]);
}

$rows = fn ($standings) => $standings->map(fn ($r) => [$r['first_name'], $r['value'], $r['rank']])->all();

it('compte l\'XP gagnée pendant la période seulement, zéros en bas', function () use ($rows) {
    $c = challengeFor($this, 'xp');
    app(XpLedger::class)->award($this->ana, 'workout', 1, 500, at: Carbon::parse('2026-10-11 23:00'));   // avant le début
    app(XpLedger::class)->award($this->ana, 'workout', 2, 100, at: Carbon::parse('2026-10-12 00:00'));
    app(XpLedger::class)->award($this->bob, 'workout', 3, 300, at: Carbon::parse('2026-10-18 23:59'));
    app(XpLedger::class)->award($this->bob, 'workout', 4, 900, at: Carbon::parse('2026-10-19 00:00'));   // après la fin

    expect($rows($this->board->compute($c)))->toBe([['Bob', 300, 1], ['Ana', 100, 2], ['Cid', 0, 3]]);
});

it('compte les séances et le volume de la période', function () use ($rows) {
    $sessions = challengeFor($this, 'sessions');
    liftOn($this->ana, '2026-10-11 19:00', [['bench-press', 50, 10]]);                    // veille du début
    liftOn($this->ana, '2026-10-13 18:00', [['bench-press', 50, 10]]);
    liftOn($this->bob, '2026-10-14 18:00', [['back-squat', 60, 10]]);
    liftOn($this->bob, '2026-10-15 18:00', [['back-squat', 60, 5]]);

    expect($rows($this->board->compute($sessions)))->toBe([['Bob', 2, 1], ['Ana', 1, 2], ['Cid', 0, 3]]);

    $sessions->update(['metric' => 'volume']);
    expect($rows($this->board->compute($sessions->fresh())))->toBe([['Bob', 900.0, 1], ['Ana', 500.0, 2], ['Cid', 0.0, 3]]);
});

it('classe la force en % de progression et ne classe pas sans référence d\'avant', function () use ($rows) {
    $c = challengeFor($this, 'strength', 'bench');
    liftOn($this->ana, '2026-10-01 18:00', [['bench-press', 50, 5]]);
    liftOn($this->ana, '2026-10-14 18:00', [['bench-press', 55, 5]]);
    liftOn($this->bob, '2026-10-01 18:00', [['bench-press', 60, 5]]);
    liftOn($this->bob, '2026-10-14 18:00', [['bench-press', 60, 5]]);
    liftOn($this->cid, '2026-10-14 18:00', [['bench-press', 80, 5]]);                     // aucune référence d'avant

    expect($rows($this->board->compute($c)))->toBe([['Ana', 10, 1], ['Bob', 0, 2]]);
});

it('partage le rang entre ex aequo', function () use ($rows) {
    $c = challengeFor($this, 'xp');
    foreach ([$this->ana, $this->bob] as $i => $u) {
        app(XpLedger::class)->award($u, 'workout', 10 + $i, 200, at: Carbon::parse('2026-10-13 10:00'));
    }

    expect($rows($this->board->compute($c)))->toBe([['Ana', 200, 1], ['Bob', 200, 1], ['Cid', 0, 3]]);
});

it('montre le classement figé d\'un défi terminé', function () {
    $c = challengeFor($this, 'xp');
    $c->update(['status' => 'finished', 'final_standings' => [['user_id' => $this->cid->id, 'first_name' => 'Cid', 'value' => 42, 'rank' => 1]]]);

    expect($this->board->standings($c->fresh())->all())->toBe([['user_id' => $this->cid->id, 'first_name' => 'Cid', 'value' => 42, 'rank' => 1]]);
});

it('formate les valeurs et le temps restant', function () {
    expect(ChallengeBoard::format('xp', 1240))->toBe("1\u{202F}240 XP")
        ->and(ChallengeBoard::format('sessions', 1))->toBe('1 séance')
        ->and(ChallengeBoard::format('sessions', 4))->toBe('4 séances')
        ->and(ChallengeBoard::format('volume', 8450.0))->toBe("8\u{202F}450 kg")
        ->and(ChallengeBoard::format('strength', 6))->toBe('+6 %')
        ->and(ChallengeBoard::format('strength', -3))->toBe('−3 %')
        ->and(ChallengeBoard::remaining(now()->addDays(3)->addHours(4)))->toBe('encore 3 j 4 h')
        ->and(ChallengeBoard::remaining(now()->addHours(5)->addMinutes(10)))->toBe('encore 5 h')
        ->and(ChallengeBoard::remaining(now()->addMinutes(12)))->toBe('encore 12 min');
});
