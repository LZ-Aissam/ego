<?php

use App\Models\Exercise;
use App\Services\ProgramGenerator;
use Database\Seeders\ExerciseSeeder;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->exercises = Exercise::all();
    $this->byId = $this->exercises->keyBy('id');
    $this->week = fn (array $over = []) => (new ProgramGenerator)->generate([...['level' => 'advanced', 'goal' => 'pure', 'days' => 5, 'equipment' => 'gym', 'upper_only' => true], ...$over], $this->exercises)['days'];
    $this->lines = fn (array $day) => array_map(fn ($l) => [$this->byId[$l['exercise_id']]->slug, $l['intent'], $l['sets'], $l['reps_min'], $l['reps_max'], $l['rest_seconds']], $day['exercises']);
});

it('construit la semaine vague du lundi au vendredi', function () {
    $days = ($this->week)();

    expect(array_column($days, 'name'))->toBe(['Couché · LOURD', 'Militaire · LOURD', 'Couché · VOLUME', 'Épaules & tirage · VITESSE', 'Couché · VITESSE + bras'])
        ->and(($this->lines)($days[0]))->toBe([
            ['bench-press', 'wave', 1, 5, 5, 300], ['bench-press', 'backoff', 3, 5, 5, 180], ['pull-up', 'max', 5, 3, 3, 180], ['barbell-curl', null, 4, 5, 6, 120],
        ])
        ->and(($this->lines)($days[1]))->toBe([
            ['overhead-press', 'wave', 1, 5, 5, 300], ['overhead-press', 'backoff', 3, 5, 5, 180], ['barbell-row', 'max', 5, 3, 5, 180],
            ['hammer-curl', null, 3, 6, 8, 90], ['wrist-curl', null, 3, 10, 12, 60], ['lateral-raise', null, 3, 10, 12, 60],
        ])
        ->and(($this->lines)($days[2]))->toBe([
            ['bench-press', 'volume', 5, 5, 5, 150], ['chin-up', null, 4, 5, 5, 120], ['dips', null, 4, 5, 5, 120], ['reverse-curl', null, 3, 8, 8, 60],
        ])
        ->and(($this->lines)($days[3]))->toBe([
            ['push-press', 'explosive', 5, 3, 3, 150], ['overhead-press', 'speed', 8, 3, 3, 90], ['pull-up', 'explosive', 5, 3, 3, 120],
            ['face-pull', null, 3, 12, 15, 60], ['skull-crusher', null, 3, 6, 8, 90],
        ])
        ->and(($this->lines)($days[4]))->toBe([
            ['bench-press', 'speed', 8, 3, 3, 60], ['dips', 'max', 4, 3, 5, 180], ['chest-supported-row', null, 4, 6, 6, 120],
            ['hammer-curl', null, 3, 6, 8, 90], ['wrist-extension', null, 3, 12, 12, 60], ['lateral-raise', null, 3, 10, 12, 60],
        ]);
});

it('travaille pecs, dos, épaules, biceps, triceps et avant-bras, sans exercice d\'abdos', function () {
    $muscles = collect(($this->week)())->flatMap(fn ($d) => $d['exercises'])->flatMap(fn ($l) => $this->byId[$l['exercise_id']]->primary_muscles)->unique();
    $slots = collect(($this->week)())->flatMap(fn ($d) => $d['exercises'])->pluck('slot');

    expect($muscles->all())->toContain('pectoraux', 'dos', 'épaules', 'biceps', 'triceps', 'avant-bras')
        ->and($slots->all())->not->toContain('core');
});

it('au poids du corps, pas de vague : des séries en répétitions', function () {
    $intents = collect(($this->week)(['equipment' => 'bodyweight']))->flatMap(fn ($d) => $d['exercises'])->pluck('intent')->unique();

    expect($intents->all())->not->toContain('wave')->not->toContain('backoff')->not->toContain('volume');
});
