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

// Programme d'avant la vague (série record, lundi → vendredi), mis à jour sur place sans le refaire.
beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-13 09:00'));                         // création du programme
    $this->user = User::factory()->withMorphology(weightKg: 70.0, level: 'advanced')->create(['first_name' => 'Aïssam']);
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
    $this->pullUp = Exercise::firstWhere('slug', 'pull-up');
    $this->user->exerciseMaxes()->create(['exercise_id' => $this->bench->id, 'e1rm_kg' => 72.8, 'source' => 'top', 'measured_at' => now()]);
    $this->user->forceFill(['calibrated_at' => now(), 'power_factor' => 1.0])->save();
    $this->program = app(ProgramBuilder::class)->build($this->user, ['level' => 'advanced', 'goal' => 'pure', 'weekdays' => [1, 2, 3, 4, 5], 'equipment' => 'gym', 'upper_only' => true]);

    // Contenu d'avant : la journée du mercredi était « Haut du corps · VITESSE » (couché vitesse, dips lourds).
    $wednesday = $this->program->days->firstWhere('position', 3);
    $wednesday->update(['name' => 'Haut du corps · VITESSE']);
    $wednesday->exercises()->delete();
    $wednesday->exercises()->create(['position' => 1, 'exercise_id' => $this->bench->id, 'slot' => 'horizontal_push', 'intent' => 'speed', 'sets' => 6, 'reps_min' => 2, 'reps_max' => 2, 'rest_seconds' => 60]);
    $this->user->workingLoads()->updateOrCreate(['exercise_id' => $this->pullUp->id], ['target_kg' => 10, 'fail_streak' => 0]);

    $this->travelTo(Carbon::parse('2026-10-28 08:00'));                         // mercredi, 3e semaine du cycle
    app(QuestScheduler::class)->sync($this->user->fresh(), now());
    expect(Quest::whereDate('date', '2026-10-28')->where('type', 'training')->value('title'))->toBe('Haut du corps · VITESSE');
    $this->upgrade = fn () => (require database_path('migrations/2026_10_25_000100_apply_wave_week_to_upper_programs.php'))->up();
});

it('réécrit les séances du programme actif sans en créer un nouveau', function () {
    ($this->upgrade)();

    $program = $this->user->fresh()->activeProgram()->with('days.exercises.exercise')->first();
    expect($program->id)->toBe($this->program->id)
        ->and(Program::count())->toBe(1)
        ->and($program->created_at->toDateString())->toBe('2026-10-13')
        ->and($program->days->pluck('name')->all())->toBe(['Couché · LOURD', 'Militaire · LOURD', 'Couché · VOLUME', 'Épaules & tirage · VITESSE', 'Couché · VITESSE + bras'])
        ->and($program->days[2]->exercises->first()->intent)->toBe('volume');
});

it('garde maxis, charges, calibrage et cycle', function () {
    ($this->upgrade)();
    $user = $this->user->fresh();

    expect((float) $user->exerciseMaxes()->where('exercise_id', $this->bench->id)->value('e1rm_kg'))->toBe(72.8)
        ->and((float) $user->workingLoads()->where('exercise_id', $this->pullUp->id)->value('target_kg'))->toBe(10.0)
        ->and($user->calibrated_at->toDateString())->toBe('2026-10-13')
        ->and(Cycle::week($user, now()))->toBe(3);
});

it('met à jour la séance du jour pas encore commencée, avec les séries de la semaine du cycle', function () {
    ($this->upgrade)();

    $quest = Quest::whereDate('date', '2026-10-28')->where('type', 'training')->first();
    expect($quest->title)->toBe('Couché · VOLUME')
        ->and(collect($quest->objectives)->pluck('intent')->all())->toContain('volume');

    $this->travelTo(Carbon::parse('2026-10-26 08:00'));                         // lundi de la même semaine : vague de semaine 3
    expect(Cycle::shape([['intent' => 'wave', 'sets' => 1, 'reps_min' => 5, 'reps_max' => 5]], Cycle::week($this->user->fresh(), now()))[0]['reps_max'])->toBe(2);
});

it('ne touche pas aux autres programmes', function () {
    $other = User::factory()->withMorphology()->create();
    $strength = app(ProgramBuilder::class)->build($other, ['level' => 'advanced', 'goal' => 'strength', 'weekdays' => [1, 2, 3, 4, 5], 'equipment' => 'gym']);
    $before = $strength->days->pluck('name')->all();

    ($this->upgrade)();

    expect($strength->fresh()->days->pluck('name')->all())->toBe($before);
});

it('laisse telle quelle la séance du jour déjà commencée', function () {
    $at = now()->toIso8601String();
    app(\App\Services\WorkoutSync::class)->sync($this->user->fresh(), ['client_id' => (string) \Illuminate\Support\Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
        'sets' => [['exercise_id' => $this->bench->id, 'weight_kg' => 40, 'reps' => 2, 'done_at' => $at]]]);

    ($this->upgrade)();

    expect(Quest::whereDate('date', '2026-10-28')->where('type', 'training')->value('title'))->toBe('Haut du corps · VITESSE');
});
