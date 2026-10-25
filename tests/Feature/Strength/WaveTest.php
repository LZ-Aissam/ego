<?php

use App\Game\Intent;
use App\Game\LoadPrescriber;
use App\Game\QuestScheduler;
use App\Models\Exercise;
use App\Models\Quest;
use App\Models\User;
use App\Services\ProgramBuilder;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

// Cycle : semaine 1 le 26/10, 2 le 2/11, 3 le 9/11, 4 (allégée) le 16/11, puis semaine 1 le 23/11.
beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-26 08:00'));
    $this->user = User::factory()->withMorphology(weightKg: 70.0, level: 'advanced')->create();
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
    $this->user->exerciseMaxes()->create(['exercise_id' => $this->bench->id, 'e1rm_kg' => 100, 'source' => 'calibration', 'measured_at' => now()]);
    $this->user->forceFill(['calibrated_at' => now(), 'power_factor' => 1.0])->save();
    app(ProgramBuilder::class)->build($this->user, ['level' => 'advanced', 'goal' => 'pure', 'weekdays' => [1, 2, 3, 4, 5], 'equipment' => 'gym', 'upper_only' => true]);
    $this->user = $this->user->fresh();
    $this->prescriber = app(LoadPrescriber::class);
    $this->kg = fn (string $intent, string $date) => $this->prescriber->prescriptionFor($this->user->fresh(), $this->bench, $intent, 5, Carbon::parse($date));
});

/** @param  list<array{0: float, 1: int, 2?: string}>  $sets  [charge, répétitions, ressenti] */
function waveBench(User $user, string $date, array $sets): \App\Models\Workout
{
    $at = Carbon::parse($date.' 18:00')->toIso8601String();
    $id = Exercise::firstWhere('slug', 'bench-press')->id;

    return app(WorkoutSync::class)->sync($user, ['client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
        'sets' => array_map(fn ($s) => ['exercise_id' => $id, 'weight_kg' => $s[0], 'reps' => $s[1], 'effort' => $s[2] ?? null, 'done_at' => $at], $sets)])['workout'];
}

it('décrit les trois nouvelles intentions', function () {
    expect(Intent::badge('wave'))->toBe('🌊 VAGUE')
        ->and(Intent::badge('backoff'))->toBe('🔁 SUITE')
        ->and(Intent::badge('volume'))->toBe('📦 VOLUME')
        ->and(Intent::cue('wave'))->toContain('série de moins');
});

it('fait monter la série de la vague semaine après semaine sur 90 % du max', function () {
    // Max d'entraînement : 90 kg (90 % de 100).
    expect(($this->kg)('wave', '2026-10-26'))->toBe(75.0)        // 85 % → 76,5
        ->and(($this->kg)('wave', '2026-11-02'))->toBe(80.0)     // 90 % → 81
        ->and(($this->kg)('wave', '2026-11-09'))->toBe(85.0)     // 95 % → 85,5
        ->and(($this->kg)('wave', '2026-11-16'))->toBe(52.5)     // allégée : 60 % → 54
        ->and(($this->kg)('backoff', '2026-11-02'))->toBe(72.5)  // 93 % de 80 → 74,4
        ->and(($this->kg)('volume', '2026-11-02'))->toBe(70.0);  // 70 % de 100 : toujours sous le jour lourd
});

it('réécrit séries et répétitions de la vague dans la quête du jour', function () {
    $shape = function (string $date) {
        $this->travelTo(Carbon::parse($date.' 08:00'));
        app(QuestScheduler::class)->sync($this->user->fresh(), now());
        $objectives = collect(Quest::whereDate('date', $date)->where('type', 'training')->value('objectives'));

        return $objectives->whereIn('intent', ['wave', 'backoff'])->where('exercise_id', $this->bench->id)
            ->map(fn ($o) => [$o['intent'], $o['sets'], $o['reps_min'], $o['reps_max']])->values()->all();
    };

    expect($shape('2026-10-26'))->toBe([['wave', 1, 5, 5], ['backoff', 3, 5, 5]])
        ->and($shape('2026-11-02'))->toBe([['wave', 1, 3, 3], ['backoff', 3, 3, 3]])
        ->and($shape('2026-11-09'))->toBe([['wave', 1, 1, 2], ['backoff', 2, 2, 2]]);
});

it('relève le max d\'entraînement d\'un cran en fin de vague, une seule fois par cycle', function () {
    $this->travelTo(Carbon::parse('2026-11-09 18:00'));
    $notes = $this->prescriber->progress($this->user, waveBench($this->user, '2026-11-09', [[85, 2, 'hard'], [77.5, 2], [77.5, 2]]));
    $this->prescriber->progress($this->user->fresh(), waveBench($this->user, '2026-11-09', [[85, 2]]));

    expect(collect($notes)->pluck('title')->all())->toContain('RECORD RELEVÉ')
        ->and(($this->kg)('wave', '2026-11-23'))->toBe(77.5);            // max d'entraînement 92,5 kg × 85 % = 78,6
});

it('ne relève rien avant la fin de la vague, et redescend après une série ratée à la limite', function () {
    $this->travelTo(Carbon::parse('2026-11-02 18:00'));
    $this->prescriber->progress($this->user, waveBench($this->user, '2026-11-02', [[80, 3, 'easy']]));
    expect(($this->kg)('wave', '2026-11-02'))->toBe(80.0);

    $notes = $this->prescriber->progress($this->user->fresh(), waveBench($this->user, '2026-11-02', [[80, 2, 'limit']]));
    expect(collect($notes)->pluck('title')->all())->toContain('RECORD AJUSTÉ')
        ->and(($this->kg)('wave', '2026-11-02'))->toBe(75.0);            // max −5 % : 95 × 0,9 × 0,9 = 76,95
});

it('allège la vague en 4e semaine : série à 60 % gardée, moitié des séries de suite', function () {
    $this->travelTo(Carbon::parse('2026-11-16 08:00'));
    app(QuestScheduler::class)->sync($this->user->fresh(), now());
    $objectives = collect(Quest::whereDate('date', '2026-11-16')->where('type', 'training')->value('objectives'));

    expect($objectives->whereIn('intent', ['wave', 'backoff'])->map(fn ($o) => [$o['intent'], $o['sets'], $o['reps_min']])->values()->all())
        ->toBe([['wave', 1, 5], ['backoff', 2, 5]]);
});

it('montre sur la page Programme les séries de la vague de la semaine en cours', function () {
    $this->travelTo(Carbon::parse('2026-11-02 08:00'));

    $this->actingAs($this->user->fresh())->get('/program')
        ->assertSeeText('🌊 VAGUE')->assertSeeText('1 × 3+ · 80 kg')->assertSeeText('3 × 3–3 · 72,5 kg');
});

it('fait la série de la vague au maximum de répétitions propres', function () {
    expect(Intent::cue('wave'))->toContain('maximum de répétitions propres')
        ->toContain('sans rebond')->toContain('pas d')->toContain('ralentit');
});

it('relève le max dès la séance quand la série de la vague dépasse largement l\'objectif', function () {
    $this->travelTo(Carbon::parse('2026-10-26 18:00'));                         // semaine 1 : 75 kg × 5+
    $notes = $this->prescriber->progress($this->user, waveBench($this->user, '2026-10-26', [[80, 10]]));

    // 80 kg × 10 ≈ 106,7 kg de 1RM : max d'entraînement 96 kg, semaine 2 à 90 % → 86,4.
    expect(collect($notes)->pluck('title')->all())->toContain('RECORD RELEVÉ')
        ->and(($this->kg)('wave', '2026-11-02'))->toBe(85.0);
});

it('ne fait pas baisser le max quand la série de la vague colle juste à l\'objectif', function () {
    $this->travelTo(Carbon::parse('2026-10-26 18:00'));
    $notes = $this->prescriber->progress($this->user, waveBench($this->user, '2026-10-26', [[75, 5]]));

    expect($notes)->toBe([])->and(($this->kg)('wave', '2026-11-02'))->toBe(80.0);
});

it('compte au plus 10 répétitions pour estimer le max', function () {
    $this->travelTo(Carbon::parse('2026-10-26 18:00'));
    $this->prescriber->progress($this->user, waveBench($this->user, '2026-10-26', [[75, 25]]));

    expect(($this->kg)('wave', '2026-11-02'))->toBe(80.0);                         // 75 × (1 + 10/30) = 100 : inchangé
});
