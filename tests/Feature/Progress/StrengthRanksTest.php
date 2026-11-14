<?php

use App\Game\StrengthRanks;
use App\Models\Exercise;
use App\Models\User;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-11-14 10:00'));
    $this->user = User::factory()->withMorphology(weightKg: 70.0, level: 'advanced')->create();
    $this->id = fn (string $slug) => Exercise::firstWhere('slug', $slug)->id;
    $this->lift = function (string $date, array $sets) {
        $at = Carbon::parse($date.' 10:00')->toIso8601String();
        $sync = app(WorkoutSync::class)->sync($this->user, [
            'client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
            'sets' => array_map(fn ($s) => ['exercise_id' => ($this->id)($s[0]), 'weight_kg' => $s[1], 'reps' => $s[2], 'done_at' => $at], $sets),
        ]);

        return $sync['workout'];
    };
    $this->ranks = fn () => collect(app(StrengthRanks::class)->for($this->user->fresh()))->keyBy('key');
});

it('rapporte le max estimé au poids du corps et en déduit un rang par mouvement', function () {
    ($this->lift)('2026-11-10', [['bench-press', 75, 5], ['overhead-press', 45, 5]]);    // 87,5 et 52,5 estimés

    $ranks = ($this->ranks)();
    expect($ranks['bench']['e1rm'])->toBe(87.5)
        ->and($ranks['bench']['ratio'])->toBe(1.25)
        ->and($ranks['bench']['rank'])->toBe('B')
        ->and($ranks['bench']['next'])->toMatchArray(['rank' => 'A', 'kg' => 105.0])
        ->and($ranks['press']['rank'])->toBe('C')                                        // 0,75 × PDC
        ->and($ranks['row']['e1rm'])->toBeNull();
});

it('compte le poids du corps aux tractions et aux dips, sans gonfler par les longues séries', function () {
    ($this->lift)('2026-11-10', [['pull-up', 20, 5], ['dips', 0, 25]]);

    $ranks = ($this->ranks)();
    expect($ranks['pullup']['e1rm'])->toBe(105.0)                                       // (70 + 20) × (1 + 5/30)
        ->and($ranks['dips']['e1rm'])->toBe(93.3)                                       // 70 × (1 + 10/30), pas × 25
        ->and($ranks['pullup']['ratio'])->toBe(1.5);
});

it('prend le poids du corps de la dernière pesée', function () {
    ($this->lift)('2026-11-10', [['bench-press', 75, 5]]);
    $this->user->bodyWeights()->create(['date' => '2026-11-13', 'kg' => 87.5]);
    $this->user->forceFill(['weight_kg' => 87.5])->save();

    expect(($this->ranks)()['bench']['ratio'])->toBe(1.0);
});

it('projette la date du prochain rang d\'après la tendance des 8 dernières semaines', function () {
    ($this->lift)('2026-10-20', [['bench-press', 70, 5]]);                              // 81,7
    ($this->lift)('2026-10-27', [['bench-press', 72.5, 5]]);                            // 84,6
    ($this->lift)('2026-11-03', [['bench-press', 75, 5]]);                              // 87,5
    ($this->lift)('2026-11-10', [['bench-press', 77.5, 5]]);                            // 90,4

    $bench = ($this->ranks)()['bench'];
    // +2,9 kg par semaine, 105 kg visés pour le rang A : environ 5 semaines.
    expect($bench['perWeek'])->toBe(2.9)
        ->and($bench['weeksToNext'])->toBe(5);
});

it('pas de projection sans progression', function () {
    ($this->lift)('2026-11-03', [['bench-press', 75, 5]]);
    ($this->lift)('2026-11-10', [['bench-press', 72.5, 5]]);

    expect(($this->ranks)()['bench']['weeksToNext'])->toBeNull();
});

it('débloque chaque rang une seule fois, avec de l\'XP et un message', function () {
    $workout = ($this->lift)('2026-11-10', [['bench-press', 75, 5]]);
    $notes = app(StrengthRanks::class)->unlock($this->user->fresh());

    expect($notes)->toHaveCount(1)
        ->and($notes[0]['title'])->toBe('RANG DE FORCE B')
        ->and($notes[0]['lines'][0])->toContain('Développé couché')
        ->and($this->user->fresh()->xp_total)->toBeGreaterThan(0)
        ->and(app(StrengthRanks::class)->unlock($this->user->fresh()))->toBe([]);
});
