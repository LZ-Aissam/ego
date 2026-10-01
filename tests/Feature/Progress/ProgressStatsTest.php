<?php

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Services\ProgressStats;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(weightKg: 80.0)->create();
    $this->stats = app(ProgressStats::class);
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
    $this->squat = Exercise::firstWhere('slug', 'back-squat');
});

/** @param list<array{0: string, 1: float, 2: int}> $sets [slug, kg, reps] */
function sessionOn(User $user, string $date, array $sets): Workout
{
    $at = Carbon::parse($date.' 18:00');
    $ids = Exercise::pluck('id', 'slug');

    return app(WorkoutSync::class)->sync($user, [
        'client_id' => (string) Str::uuid(), 'started_at' => $at->copy()->subHour()->toIso8601String(), 'finished_at' => $at->toIso8601String(),
        'sets' => array_map(fn ($s) => ['exercise_id' => $ids[$s[0]], 'weight_kg' => $s[1], 'reps' => $s[2], 'done_at' => $at->toIso8601String()], $sets),
    ])['workout'];
}

it('donne par séance la charge max, le 1RM estimé, le volume et les records', function () {
    $this->travelTo(Carbon::parse('2026-10-20 12:00'));
    sessionOn($this->user, '2026-10-01', [['bench-press', 50, 10], ['bench-press', 55, 5]]);
    sessionOn($this->user, '2026-10-08', [['bench-press', 52.5, 8]]);
    sessionOn($this->user, '2026-10-15', [['bench-press', 60, 6]]);

    $series = $this->stats->exerciseSeries($this->user, $this->bench, 'all');

    expect($series->map(fn ($r) => [$r['date']->toDateString(), $r['max_kg'], $r['e1rm'], $r['volume'], $r['record']])->all())->toBe([
        ['2026-10-01', 55.0, 66.7, 775.0, true],
        ['2026-10-08', 52.5, 66.5, 420.0, false],
        ['2026-10-15', 60.0, 72.0, 360.0, true],
    ]);
});

it('filtre la période sans oublier les records d\'avant pour marquer les nouveaux', function () {
    $this->travelTo(Carbon::parse('2026-10-20 12:00'));
    sessionOn($this->user, '2026-05-01', [['bench-press', 70, 5]]);
    sessionOn($this->user, '2026-09-01', [['bench-press', 60, 5]]);
    sessionOn($this->user, '2026-10-10', [['bench-press', 72.5, 5]]);

    $threeMonths = $this->stats->exerciseSeries($this->user, $this->bench, '3m');
    expect($threeMonths->pluck('max_kg')->all())->toBe([60.0, 72.5])
        ->and($threeMonths->pluck('record')->all())->toBe([false, true])
        ->and($this->stats->exerciseSeries($this->user, $this->bench, '1y'))->toHaveCount(3);
});

it('résume la meilleure charge, la progression depuis le début et la tendance', function () {
    $this->travelTo(Carbon::parse('2026-10-20 12:00'));
    sessionOn($this->user, '2026-08-01', [['bench-press', 50, 5]]);
    sessionOn($this->user, '2026-09-10', [['bench-press', 55, 5]]);
    sessionOn($this->user, '2026-10-15', [['bench-press', 60, 5]]);

    expect($this->stats->summary($this->user, $this->bench))->toBe([
        'best_kg' => 60.0, 'gain_kg' => 10.0, 'gain_pct' => 20, 'trend' => 'up',
    ]);
});

it('compte le volume et les séances des 12 dernières semaines, semaines vides comprises', function () {
    $this->travelTo(Carbon::parse('2026-10-21 12:00'));          // mercredi
    sessionOn($this->user, '2026-10-19', [['bench-press', 50, 10]]);
    sessionOn($this->user, '2026-10-20', [['back-squat', 60, 10]]);
    sessionOn($this->user, '2026-10-05', [['bench-press', 40, 10]]);

    $weeks = $this->stats->weeklyVolume($this->user);

    expect($weeks)->toHaveCount(12)
        ->and($weeks->last())->toBe(['week' => '2026-10-19', 'volume' => 1100.0, 'sessions' => 2])
        ->and($weeks[10])->toBe(['week' => '2026-10-12', 'volume' => 0.0, 'sessions' => 0])
        ->and($weeks[9])->toBe(['week' => '2026-10-05', 'volume' => 400.0, 'sessions' => 1]);
});

it('classe les 3 meilleures progressions du mois', function () {
    $this->travelTo(Carbon::parse('2026-10-30 12:00'));
    sessionOn($this->user, '2026-09-10', [['bench-press', 50, 5], ['back-squat', 80, 5], ['deadlift', 100, 5]]);
    sessionOn($this->user, '2026-10-20', [['bench-press', 55, 5], ['back-squat', 80, 5], ['deadlift', 120, 5], ['barbell-row', 60, 5]]);

    $top = $this->stats->topProgress($this->user);

    expect($top->map(fn ($r) => [$r['exercise']->slug, $r['gain_pct']])->all())->toBe([['deadlift', 20], ['bench-press', 10]]);
});

it('compare chaque exercice à la séance précédente', function () {
    $this->travelTo(Carbon::parse('2026-10-20 12:00'));
    sessionOn($this->user, '2026-10-10', [['bench-press', 57.5, 8], ['back-squat', 80, 6], ['barbell-row', 50, 10]]);
    $today = sessionOn($this->user, '2026-10-17', [['bench-press', 60, 8], ['back-squat', 80, 5], ['barbell-row', 50, 10], ['deadlift', 100, 5]]);

    expect($this->stats->comparedToPrevious($today))->toBe([
        $this->bench->id => '+2,5 kg',
        $this->squat->id => '−1 rep',
        Exercise::firstWhere('slug', 'barbell-row')->id => '=',
        Exercise::firstWhere('slug', 'deadlift')->id => null,
    ]);
});

it('affiche la page d\'un exercice avec résumé, courbes et périodes', function () {
    $this->travelTo(Carbon::parse('2026-10-20 12:00'));
    sessionOn($this->user, '2026-08-01', [['bench-press', 50, 5]]);
    sessionOn($this->user, '2026-10-15', [['bench-press', 60, 5]]);
    $this->actingAs($this->user);

    $this->get(route('progress', $this->bench))->assertOk()
        ->assertSee('Meilleure charge')->assertSee('+10 kg')->assertSee('+20 %')
        ->assertSee('Charge max')->assertSee('1RM estimé')->assertSee('Volume')
        ->assertSee('3 mois')->assertSee('Depuis le début');

    \Livewire\Livewire::withQueryParams(['courbe' => 'e1rm', 'periode' => '3m'])->test(\App\Livewire\Progress::class, ['exercise' => $this->bench])
        ->assertSet('metric', 'e1rm')->assertSet('period', '3m')->assertSee('70 kg');
    \Livewire\Livewire::withQueryParams(['courbe' => 'nimporte', 'periode' => ['x']])->test(\App\Livewire\Progress::class, ['exercise' => $this->bench])
        ->assertSet('metric', 'max_kg')->assertSet('period', 'all');
});

it('affiche le bilan d\'entraînement sur le profil', function () {
    $this->travelTo(Carbon::parse('2026-10-30 12:00'));
    sessionOn($this->user, '2026-09-10', [['deadlift', 100, 5]]);
    sessionOn($this->user, '2026-10-28', [['deadlift', 120, 5]]);
    $this->actingAs($this->user);

    $this->get(route('profile'))->assertOk()
        ->assertSee('BILAN')->assertSee('Volume par semaine')->assertSee('Séances cette semaine')
        ->assertSee('Meilleures progressions du mois')->assertSee('Soulevé de terre')->assertSee('+20 %');
});

it('montre l\'écart avec la séance précédente dans le détail d\'une séance', function () {
    $this->travelTo(Carbon::parse('2026-10-20 12:00'));
    sessionOn($this->user, '2026-10-10', [['bench-press', 57.5, 8]]);
    $today = sessionOn($this->user, '2026-10-17', [['bench-press', 60, 8], ['deadlift', 100, 5]]);
    $this->actingAs($this->user);

    $this->get(route('history.show', $today))->assertOk()->assertSee('+2,5 kg')->assertSee('Première fois');
});
