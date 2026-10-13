<?php

use App\Game\LoadPrescriber;
use App\Models\Exercise;
use App\Models\User;
use App\Services\ProgramBuilder;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(weightKg: 70.0, level: 'advanced')->create();
    $this->pullUp = Exercise::firstWhere('slug', 'pull-up');
    $this->dips = Exercise::firstWhere('slug', 'dips');
    app(ProgramBuilder::class)->build($this->user, ['level' => 'advanced', 'goal' => 'pure', 'weekdays' => [1, 2, 3, 4, 5], 'equipment' => 'gym', 'upper_only' => true]);
    $this->prescriber = app(LoadPrescriber::class);
    $this->lest = fn (Exercise $e) => $this->user->workingLoads()->where('exercise_id', $e->id)->value('target_kg');
});

/** @param  list<array{0: float, 1: int, 2?: ?string}>  $sets  [lest, répétitions, ressenti] */
function bodyweightSets(User $user, Exercise $exercise, array $sets): \App\Models\Workout
{
    $at = now()->toIso8601String();

    return app(WorkoutSync::class)->sync($user, ['client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
        'sets' => array_map(fn ($s) => ['exercise_id' => $exercise->id, 'weight_kg' => $s[0], 'reps' => $s[1], 'done_at' => $at, 'effort' => $s[2] ?? null], $sets)])['workout'];
}

function calibrateWith(User $user, Exercise $exercise, float $e1rm): void
{
    $user->exerciseMaxes()->create(['exercise_id' => $exercise->id, 'e1rm_kg' => $e1rm, 'best_reps' => 12, 'source' => 'calibration', 'measured_at' => now()]);
    $user->forceFill(['calibrated_at' => now(), 'power_factor' => 1.0])->save();
    app(LoadPrescriber::class)->prescribeProgram($user->fresh());
}

it('prescrit le lest des tractions force max : 87,5 % de la force totale moins le poids du corps', function () {
    calibrateWith($this->user, $this->pullUp, 98.0);                             // 70 kg × 12 répétitions

    expect((float) ($this->lest)($this->pullUp))->toBe(15.0)
        ->and($this->prescriber->prescriptionFor($this->user->fresh(), $this->pullUp, 'max', 3))->toBe(15.0);
});

it('ne prescrit pas de lest négatif : poids du corps seul', function () {
    calibrateWith($this->user, $this->pullUp, 77.0);

    expect((float) ($this->lest)($this->pullUp))->toBe(0.0);
});

it('fait monter le lest de 2,5 kg quand toutes les séries sont réussies, comme une barre', function () {
    calibrateWith($this->user, $this->pullUp, 98.0);

    $notes = $this->prescriber->progress($this->user->fresh(), bodyweightSets($this->user, $this->pullUp, array_fill(0, 5, [15.0, 3])));

    expect((float) ($this->lest)($this->pullUp))->toBe(17.5)
        ->and(collect($notes)->pluck('type')->all())->toContain('load_up');
});

it('suit le ressenti sur le lest : tout facile, double hausse', function () {
    calibrateWith($this->user, $this->pullUp, 98.0);

    $this->prescriber->progress($this->user->fresh(), bodyweightSets($this->user, $this->pullUp, array_fill(0, 5, [15.0, 3, 'easy'])));

    expect((float) ($this->lest)($this->pullUp))->toBe(20.0);
});

it('lance le lest des dips après une première séance, sans calibrage de cet exercice', function () {
    calibrateWith($this->user, $this->pullUp, 98.0);
    expect(($this->lest)($this->dips))->toBeNull();

    $this->prescriber->progress($this->user->fresh(), bodyweightSets($this->user, $this->dips, array_fill(0, 4, [0.0, 5])));

    expect((float) ($this->lest)($this->dips))->toBe(2.5);
});

it('affiche le lest dans le programme', function () {
    calibrateWith($this->user, $this->pullUp, 98.0);

    $this->actingAs($this->user)->get('/program')->assertSeeText('+15 kg de lest');
});

it('fait les tractions explosives sans lest, même après une séance lestée', function () {
    calibrateWith($this->user, $this->pullUp, 98.0);

    expect($this->prescriber->prescriptionFor($this->user->fresh(), $this->pullUp, 'explosive', 3))->toBe(0.0)
        ->and($this->prescriber->prescriptionFor($this->user->fresh(), Exercise::firstWhere('slug', 'hanging-leg-raise'), 'mass', 12))->toBeNull();
});

it('ne juge pas le lest sur les tractions explosives du jour de vitesse, même à 0 kg de lest', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-15 08:00'));      // jeudi : Épaules & tractions · VITESSE
    calibrateWith($this->user, $this->pullUp, 77.0);                             // lest 0
    app(\App\Game\QuestScheduler::class)->sync($this->user->fresh(), now());
    expect(\App\Models\Quest::whereDate('date', '2026-10-15')->where('type', 'training')->value('title'))->toBe('Épaules & tractions · VITESSE');

    $this->prescriber->progress($this->user->fresh(), bodyweightSets($this->user, $this->pullUp, array_fill(0, 5, [0.0, 3])));

    expect((float) ($this->lest)($this->pullUp))->toBe(0.0);
});

it('ne décharge pas un lest déjà à 0 kg : aucun message', function () {
    calibrateWith($this->user, $this->pullUp, 77.0);

    $notes = [];
    foreach ([1, 2] as $_) {
        array_push($notes, ...$this->prescriber->progress($this->user->fresh(), bodyweightSets($this->user, $this->pullUp, array_fill(0, 5, [0.0, 1]))));
    }

    expect(collect($notes)->pluck('type')->all())->not->toContain('deload')
        ->and((float) ($this->lest)($this->pullUp))->toBe(0.0);
});
