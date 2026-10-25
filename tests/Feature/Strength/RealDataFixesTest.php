<?php

use App\Game\GameEvents;
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

// Cas réels de la production (Aïssam, Force pure · haut du corps · 5 j, octobre 2026).
beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-13 22:26'));                          // mardi
    $this->user = User::factory()->withMorphology(weightKg: 70.0, level: 'advanced')->create();
    app(ProgramBuilder::class)->build($this->user, ['level' => 'advanced', 'goal' => 'pure', 'weekdays' => [1, 2, 3, 4, 5], 'equipment' => 'gym', 'upper_only' => true]);
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
    $this->user->exerciseMaxes()->create(['exercise_id' => $this->bench->id, 'e1rm_kg' => 66.7, 'source' => 'calibration', 'measured_at' => now()]);
    $this->user->forceFill(['calibrated_at' => now(), 'power_factor' => 1.0])->save();
    app(LoadPrescriber::class)->prescribeProgram($this->user->fresh());
    app(QuestScheduler::class)->sync($this->user->fresh(), now());

    $this->play = function (string $finishedAt, array $sets) {
        $at = Carbon::parse($finishedAt)->toIso8601String();
        $result = app(WorkoutSync::class)->sync($this->user->fresh(), ['client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
            'sets' => array_map(fn ($s) => ['exercise_id' => $this->bench->id, 'weight_kg' => $s[0], 'reps' => $s[1], 'effort' => $s[2] ?? null, 'done_at' => $at], $sets)]);

        return app(GameEvents::class)->afterWorkout($this->user->fresh(), $result['workout'], $result['newRecords']);
    };
    $this->heavy = fn () => (float) $this->user->workingLoads()->where('exercise_id', $this->bench->id)->value('target_kg');
    $this->prescribed = fn (string $intent, int $repsMax) => app(LoadPrescriber::class)->prescriptionFor($this->user->fresh(), $this->bench, $intent, $repsMax);
});

it('remonte les séries lourdes quand elles dépassent largement les répétitions visées, même si les dernières lâchent', function () {
    expect(($this->heavy)())->toBe(57.5);

    $this->travelTo(Carbon::parse('2026-10-19 23:50'));                          // lundi : Couché · LOURD
    app(QuestScheduler::class)->sync($this->user->fresh(), now());
    ($this->play)('2026-10-19 23:50', [[60.0, 6, 'limit'], [57.5, 8, 'limit'], [57.5, 8, 'limit'], [57.5, 2, 'limit'], [57.5, 1, 'limit']]);

    // 57,5 kg × 8 ≈ 72,8 kg de 1RM : 87,5 % pour 2-3 répétitions, au lieu d'une baisse à 52,5 kg.
    expect(($this->heavy)())->toBe(62.5)
        ->and(($this->prescribed)('top', 2))->toBe(65.0)
        ->and($this->user->workingLoads()->where('exercise_id', $this->bench->id)->value('fail_streak'))->toBe(0);
});

it('ne juge pas comme lourde une séance de vitesse envoyée en retard pour son jour', function () {
    $this->travelTo(Carbon::parse('2026-10-19 22:20'));                          // ouverte lundi, faite mercredi 14
    app(QuestScheduler::class)->sync($this->user->fresh(), now());
    ($this->play)('2026-10-14 23:35', array_fill(0, 6, [37.5, 2]));

    expect(($this->heavy)())->toBe(57.5)
        ->and($this->user->workingLoads()->where('exercise_id', $this->bench->id)->value('fail_streak'))->toBe(0);
});

it('ne met pas de squats dans la pénalité du programme sans jambes', function () {
    $this->travelTo(Carbon::parse('2026-10-15 09:00'));                          // mercredi 14 manqué
    app(QuestScheduler::class)->sync($this->user->fresh(), now());

    $penalty = Quest::where('type', 'penalty')->whereDate('date', '2026-10-15')->firstOrFail();
    expect(array_column($penalty->objectives, 'name'))->not->toContain('Squat au poids du corps')
        ->and(array_column($penalty->objectives, 'slot'))->toContain('triceps');
});

it('ne crée pas après coup de pénalités pour des jours déjà passés', function () {
    $this->travelTo(Carbon::parse('2026-10-19 22:20'));                          // de retour lundi après mer., jeu., ven. manqués
    app(QuestScheduler::class)->sync($this->user->fresh(), now());

    expect(Quest::where('type', 'penalty')->count())->toBe(0)
        ->and(Quest::whereDate('date', '2026-10-15')->where('type', 'training')->value('status'))->toBe('failed');
});
