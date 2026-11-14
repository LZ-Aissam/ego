<?php

use App\Game\QuestScheduler;
use App\Game\WeeklyReport;
use App\Models\Exercise;
use App\Models\User;
use App\Services\ProgramBuilder;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-13 09:00'));                         // cycle : semaine 1 = 12/10, semaine 4 = 2/11
    $this->user = User::factory()->withMorphology(weightKg: 70.0, level: 'advanced')->create();
    $this->user->forceFill(['calibrated_at' => now(), 'power_factor' => 1.0])->save();
    app(ProgramBuilder::class)->build($this->user, ['level' => 'advanced', 'goal' => 'pure', 'weekdays' => [1, 2, 3, 4, 5, 6, 7], 'equipment' => 'gym', 'upper_only' => true]);
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
    $this->lift = function (string $date, float $kg) {
        $at = Carbon::parse($date.' 18:00')->toIso8601String();
        app(WorkoutSync::class)->sync($this->user, ['client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
            'sets' => [['exercise_id' => $this->bench->id, 'weight_kg' => $kg, 'reps' => 5, 'done_at' => $at]]]);
    };
});

it('fait le bilan du cycle le lundi qui suit la semaine allégée, une seule fois', function () {
    ($this->lift)('2026-10-10', 70);                                             // avant le cycle
    ($this->lift)('2026-11-05', 75);                                             // +5 kg en charge, ≈ +5,8 kg estimés
    $this->user->bodyWeights()->create(['date' => '2026-11-01', 'kg' => 70]);
    $this->user->bodyWeights()->create(['date' => '2026-11-08', 'kg' => 70.5]);

    $this->travelTo(Carbon::parse('2026-11-09 08:00'));
    app(QuestScheduler::class)->sync($this->user->fresh(), now());
    $report = app(WeeklyReport::class)->due($this->user->fresh(), now());

    expect($report['title'])->toBe('BILAN DU CYCLE')
        ->and($report['lines'][0])->toBe('0 / 7 séances accomplies')
        ->and($report['lines'])->toContain('Poids : 70,5 kg (+0,5 kg)')
        ->and($report['lines'])->toContain('Développé couché : +5,8 kg estimés')
        ->and(end($report['lines']))->toBe('Cette semaine : Semaine 1/4 · montée')
        ->and(app(WeeklyReport::class)->due($this->user->fresh(), now()->addDay()))->toBeNull();
});

it('fait un simple bilan de la semaine en milieu de cycle', function () {
    $this->travelTo(Carbon::parse('2026-11-16 08:00'));
    app(QuestScheduler::class)->sync($this->user->fresh(), now());

    $report = app(WeeklyReport::class)->due($this->user->fresh(), now());
    expect($report['title'])->toBe('BILAN DE LA SEMAINE')
        ->and(end($report['lines']))->toBe('Cette semaine : Semaine 2/4 · montée');
});

it('arrive par les notifications au premier passage de la semaine', function () {
    $this->travelTo(Carbon::parse('2026-11-16 08:00'));
    $this->actingAs($this->user)->get('/')->assertOk();

    expect(\App\Models\SystemNotification::where('user_id', $this->user->id)->where('type', 'weekly_report')->count())->toBe(1);
    $this->actingAs($this->user)->get('/')->assertOk();
    expect(\App\Models\SystemNotification::where('user_id', $this->user->id)->where('type', 'weekly_report')->count())->toBe(1);
});

it('exporte toutes les séries du joueur en tableur', function () {
    ($this->lift)('2026-10-14', 72.5);

    $csv = $this->actingAs($this->user)->get('/profile/series.csv')->assertOk()->streamedContent();
    expect($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('Date;Heure;Exercice')
        ->and($csv)->toContain('14/10/2026;18:00;"Développé couché (barre)";1;72,5;5');
});
