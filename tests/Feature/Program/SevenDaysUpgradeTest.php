<?php

use App\Game\Cycle;
use App\Game\QuestScheduler;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\Quest;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;

// Aïssam passe de 5 à 7 jours par semaine : son programme est agrandi sur place, sans le refaire.
beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-13 09:00'));
    $this->user = User::factory()->withMorphology(weightKg: 70.0, level: 'advanced')->create(['first_name' => 'Aïssam']);
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
    $this->user->exerciseMaxes()->create(['exercise_id' => $this->bench->id, 'e1rm_kg' => 80.0, 'source' => 'wave', 'measured_at' => now()]);
    $this->user->forceFill(['calibrated_at' => now(), 'power_factor' => 1.0])->save();
    $this->program = app(ProgramBuilder::class)->build($this->user, ['level' => 'advanced', 'goal' => 'pure', 'weekdays' => [1, 2, 3, 4, 5], 'equipment' => 'gym', 'upper_only' => true]);
    $this->dayIds = $this->program->days->sortBy('position')->pluck('id')->all();

    $this->travelTo(Carbon::parse('2026-11-14 09:00'));                         // samedi : jour de repos à 5 jours
    app(QuestScheduler::class)->sync($this->user->fresh(), now());
    expect(Quest::whereDate('date', '2026-11-14')->value('type'))->toBe('rest');
    $this->upgrade = fn () => (require database_path('migrations/2026_11_14_000100_seven_days_for_aissam.php'))->up();
});

it('agrandit le même programme à 7 jours, du lundi au dimanche', function () {
    ($this->upgrade)();

    $user = $this->user->fresh();
    $program = $user->activeProgram()->with('days')->first();
    expect($program->id)->toBe($this->program->id)
        ->and(Program::count())->toBe(1)
        ->and($program->days)->toHaveCount(7)
        ->and(array_slice($program->days->sortBy('position')->pluck('id')->all(), 0, 5))->toBe($this->dayIds)
        ->and($program->days->sortBy('position')->pluck('name')->all())->toBe([
            'Couché · LOURD', 'Militaire · LOURD', 'Dos · LOURD', 'Couché · VOLUME + triceps',
            'Épaules & tirage · VITESSE', 'Couché · VITESSE + bras', 'Épaules & poignets · RÉCUP',
        ])
        ->and($program->params['weekdays'])->toBe([1, 2, 3, 4, 5, 6, 7])
        ->and($program->name)->toContain('7 j')
        ->and($user->training_weekdays)->toBe([1, 2, 3, 4, 5, 6, 7])
        ->and($user->days_per_week)->toBe(7);
});

it('garde maxis, calibrage et semaine du cycle', function () {
    $week = Cycle::week($this->user->fresh(), now());
    ($this->upgrade)();
    $user = $this->user->fresh();

    expect((float) $user->exerciseMaxes()->where('exercise_id', $this->bench->id)->value('e1rm_kg'))->toBe(80.0)
        ->and($user->calibrated_at->toDateString())->toBe('2026-10-13')
        ->and(Cycle::week($user, now()))->toBe($week);
});

it('remplace le repos d\'aujourd\'hui par la séance du samedi', function () {
    ($this->upgrade)();
    app(QuestScheduler::class)->sync($this->user->fresh(), now());

    $today = Quest::whereDate('date', '2026-11-14')->whereIn('type', ['training', 'rest'])->get();
    expect($today)->toHaveCount(1)
        ->and($today->first()->type)->toBe('training')
        ->and($today->first()->title)->toBe('Couché · VITESSE + bras');

    $this->travelTo(Carbon::parse('2026-11-15 09:00'));
    app(QuestScheduler::class)->sync($this->user->fresh(), now());
    expect(Quest::whereDate('date', '2026-11-15')->value('title'))->toBe('Épaules & poignets · RÉCUP');
});

it('ne touche pas aux autres joueurs', function () {
    $other = User::factory()->withMorphology(level: 'advanced')->create(['first_name' => 'Nitia']);
    $program = app(ProgramBuilder::class)->build($other, ['level' => 'advanced', 'goal' => 'pure', 'weekdays' => [1, 2, 3, 4, 5], 'equipment' => 'gym', 'upper_only' => true]);

    ($this->upgrade)();

    expect($program->fresh()->days)->toHaveCount(5)
        ->and($other->fresh()->training_weekdays)->toBe([1, 2, 3, 4, 5]);
});
