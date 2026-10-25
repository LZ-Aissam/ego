<?php

use App\Game\Cycle;
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

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-14 08:00'));                         // mercredi : le cycle part du lundi 12
    $this->user = User::factory()->withMorphology(weightKg: 70.0, level: 'advanced')->create();
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
    $this->user->exerciseMaxes()->create(['exercise_id' => $this->bench->id, 'e1rm_kg' => 100, 'source' => 'calibration', 'measured_at' => now()]);
    $this->user->forceFill(['calibrated_at' => now(), 'power_factor' => 1.0])->save();
    // Sans jambes sur 4 jours : série record et séries lourdes au couché (la semaine de 5 jours suit la vague, voir WaveTest).
    app(ProgramBuilder::class)->build($this->user, ['level' => 'advanced', 'goal' => 'pure', 'weekdays' => [1, 2, 3, 4], 'equipment' => 'gym', 'upper_only' => true]);
    $this->user = $this->user->fresh();
    $this->prescriber = app(LoadPrescriber::class);
});

it('compte les semaines du cycle : 3 de montée puis 1 allégée, et recommence', function () {
    $weeks = collect(['2026-10-12', '2026-10-18', '2026-10-19', '2026-10-26', '2026-11-02', '2026-11-08', '2026-11-09'])
        ->map(fn ($d) => Cycle::week($this->user, Carbon::parse($d)))->all();

    expect($weeks)->toBe([1, 1, 2, 3, 4, 4, 1])
        ->and(Cycle::isDeload($this->user, Carbon::parse('2026-11-04')))->toBeTrue()
        ->and(Cycle::isDeload($this->user, Carbon::parse('2026-10-28')))->toBeFalse();
});

it('ne fait pas de cycle pour un programme classique', function () {
    app(ProgramBuilder::class)->build($this->user, ['level' => 'advanced', 'goal' => 'strength', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);

    expect(Cycle::week($this->user->fresh(), Carbon::parse('2026-11-02')))->toBeNull();
});

it('allège la séance de la 4e semaine : pas de série record, moitié des séries', function () {
    $this->travelTo(Carbon::parse('2026-11-02 08:00'));                         // lundi, semaine 4
    app(QuestScheduler::class)->sync($this->user->fresh(), now());

    $objectives = collect(Quest::whereDate('date', '2026-11-02')->where('type', 'training')->value('objectives'));

    expect($objectives->pluck('intent'))->not->toContain('top')
        ->and($objectives->where('exercise_id', $this->bench->id)->pluck('sets')->all())->toBe([2])   // 4 séries lourdes → 2
        ->and($objectives->firstWhere('intent', 'explosive')['sets'])->toBe(2);                         // 4 → 2
});

it('garde la séance complète les semaines de montée', function () {
    $this->travelTo(Carbon::parse('2026-10-19 08:00'));                         // lundi, semaine 2
    app(QuestScheduler::class)->sync($this->user->fresh(), now());

    $objectives = collect(Quest::whereDate('date', '2026-10-19')->where('type', 'training')->value('objectives'));
    expect($objectives->pluck('intent'))->toContain('top');
});

it('baisse les charges lourdes de 10 % pendant la semaine allégée', function () {
    expect($this->prescriber->prescriptionFor($this->user, $this->bench, 'max', 3))->toBe(87.5);

    $this->travelTo(Carbon::parse('2026-11-03 08:00'));

    expect($this->prescriber->prescriptionFor($this->user, $this->bench, 'max', 3))->toBe(77.5)
        ->and($this->prescriber->prescriptionFor($this->user, $this->bench, 'speed', 2))->toBe(57.5);
});

it('ne fait pas progresser les charges pendant la semaine allégée', function () {
    $this->travelTo(Carbon::parse('2026-11-03 18:00'));
    $at = now()->toIso8601String();
    $workout = app(WorkoutSync::class)->sync($this->user, ['client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
        'sets' => array_fill(0, 4, ['exercise_id' => $this->bench->id, 'weight_kg' => 87.5, 'reps' => 3, 'done_at' => $at, 'effort' => 'easy'])])['workout'];

    expect($this->prescriber->progress($this->user, $workout))->toBe([])
        ->and((float) $this->user->workingLoads()->where('exercise_id', $this->bench->id)->value('target_kg'))->toBe(87.5);
});

it('annonce la semaine du cycle sur la page programme', function () {
    $this->actingAs($this->user)->get('/program')->assertSeeText('Semaine 1/4 · montée');

    $this->travelTo(Carbon::parse('2026-11-03 08:00'));
    $this->get('/program')->assertSeeText('Semaine 4/4 · allégée');
});
