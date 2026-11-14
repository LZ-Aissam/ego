<?php

use App\Livewire\ProgramPage;
use App\Models\Exercise;
use App\Models\User;
use App\Services\ProgramGenerator;
use Database\Seeders\ExerciseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->exercises = Exercise::all();
    $this->byId = $this->exercises->keyBy('id');
    $this->week = fn (array $over = []) => (new ProgramGenerator)->generate([...['level' => 'advanced', 'goal' => 'pure', 'days' => 7, 'equipment' => 'gym', 'upper_only' => true], ...$over], $this->exercises)['days'];
    $this->lines = fn (array $day) => array_map(fn ($l) => [$this->byId[$l['exercise_id']]->slug, $l['intent'], $l['sets'], $l['reps_min'], $l['reps_max'], $l['rest_seconds']], $day['exercises']);
});

it('construit la semaine de 7 jours : 3 jours lourds, volume, 2 jours de vitesse, une récupération active', function () {
    $days = ($this->week)();

    expect(array_column($days, 'name'))->toBe([
        'Couché · LOURD', 'Militaire · LOURD', 'Dos · LOURD', 'Couché · VOLUME + triceps',
        'Épaules & tirage · VITESSE', 'Couché · VITESSE + bras', 'Épaules & poignets · RÉCUP',
    ])
        ->and(($this->lines)($days[0]))->toBe([
            ['bench-press', 'wave', 1, 5, 5, 300], ['bench-press', 'backoff', 3, 5, 5, 180], ['pull-up', 'max', 5, 3, 3, 180],
            ['barbell-curl', null, 4, 5, 6, 120], ['face-pull', null, 3, 12, 15, 60],
        ])
        ->and(($this->lines)($days[1]))->toBe([
            ['overhead-press', 'wave', 1, 5, 5, 300], ['overhead-press', 'backoff', 3, 5, 5, 180], ['dips', 'max', 4, 3, 5, 180],
            ['overhead-cable-extension', null, 3, 8, 10, 90], ['lateral-raise', null, 3, 10, 12, 60], ['wrist-curl', null, 3, 10, 12, 60],
        ])
        ->and(($this->lines)($days[2]))->toBe([
            ['barbell-row', 'max', 5, 3, 5, 180], ['chin-up', 'max', 4, 3, 5, 180], ['incline-dumbbell-curl', null, 3, 6, 8, 90],
            ['hammer-curl', null, 3, 6, 8, 90], ['rear-delt-fly', null, 3, 12, 15, 60], ['reverse-curl', null, 3, 8, 10, 60],
        ])
        ->and(($this->lines)($days[3]))->toBe([
            ['bench-press', 'volume', 5, 5, 5, 150], ['close-grip-bench-press', null, 4, 5, 6, 150], ['triceps-pushdown', null, 3, 8, 10, 90],
            ['cable-lateral-raise', null, 3, 12, 15, 60], ['wrist-extension', null, 3, 12, 12, 60],
        ])
        ->and(($this->lines)($days[5]))->toBe([
            ['bench-press', 'speed', 8, 3, 3, 60], ['chest-supported-row', null, 4, 6, 6, 120], ['concentration-curl', null, 3, 8, 10, 60],
            ['overhead-dumbbell-extension', null, 3, 8, 10, 90], ['lateral-raise', null, 3, 10, 12, 60],
        ])
        ->and(($this->lines)($days[6]))->toBe([
            ['face-pull', null, 3, 15, 15, 60], ['rear-delt-fly', null, 3, 15, 15, 60], ['wrist-curl', null, 3, 12, 15, 60],
            ['wrist-extension', null, 3, 12, 15, 60], ['reverse-curl', null, 3, 10, 12, 60],
        ]);
});

it('couvre biceps longue et courte portion, triceps long et court, avant-bras, pecs, dos et épaules', function () {
    $slugs = collect(($this->week)())->flatMap(fn ($d) => $d['exercises'])->map(fn ($l) => $this->byId[$l['exercise_id']]->slug)->unique();

    expect($slugs->all())->toContain(
        'incline-dumbbell-curl', 'concentration-curl',          // biceps : longue, courte portion
        'overhead-cable-extension', 'skull-crusher',             // triceps : longue portion
        'triceps-pushdown', 'close-grip-bench-press',            // triceps : chefs latéral et médial
        'wrist-curl', 'wrist-extension', 'reverse-curl',         // avant-bras
        'bench-press', 'overhead-press', 'pull-up', 'barbell-row',
    );
});

it('un seul jour très lourd par mouvement de base, et jamais deux jours lourds du même mouvement d\'affilée', function () {
    $heavy = collect(($this->week)())->map(fn ($d) => collect($d['exercises'])->whereIn('intent', ['wave', 'max', 'top'])->map(fn ($l) => $this->byId[$l['exercise_id']]->slug)->unique()->values()->all());

    $all = $heavy->flatten();
    expect($all->duplicates()->all())->toBe([]);
    foreach ($heavy->values() as $i => $slugs) {
        expect(array_intersect($slugs, $heavy[($i + 1) % 7]))->toBe([]);
    }
});

it('propose 7 jours sur 7 dans le questionnaire', function () {
    $this->actingAs(User::factory()->withMorphology()->create());

    Livewire::test(ProgramPage::class)
        ->call('next')->set('level', 'advanced')->call('next')->set('goal', 'pure')->call('next')
        ->set('weekdays', [1, 2, 3, 4, 5, 6, 7])->call('next')
        ->assertHasNoErrors(['weekdays'])
        ->assertSet('step', 5);
});

it('construit 7 jours pour chaque objectif', function (string $goal) {
    $days = (new ProgramGenerator)->generate(['level' => 'intermediate', 'goal' => $goal, 'days' => 7, 'equipment' => 'gym'], $this->exercises)['days'];

    expect($days)->toHaveCount(7);
    foreach ($days as $day) {
        expect($day['exercises'])->not->toBeEmpty();
    }
})->with(['strength', 'hypertrophy', 'fitness', 'power', 'pure']);
