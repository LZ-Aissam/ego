<?php

use App\Models\Exercise;
use App\Services\ProgramGenerator;
use Database\Seeders\ExerciseSeeder;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->exercises = Exercise::all();
    $this->byId = $this->exercises->keyBy('id');
    $this->pure = fn (array $over = []) => (new ProgramGenerator)->generate([...['level' => 'advanced', 'goal' => 'pure', 'days' => 4, 'equipment' => 'gym'], ...$over], $this->exercises);
    $this->lines = fn (array $day) => array_map(fn ($l) => [$this->byId[$l['exercise_id']]->slug, $l['intent'], $l['sets'], $l['reps_min'], $l['reps_max'], $l['rest_seconds']], $day['exercises']);
});

it('construit la semaine Force pure de 4 jours en salle, niveau avancé', function () {
    $program = ($this->pure)();

    expect($program['split'])->toBe('pure')
        ->and(array_column($program['days'], 'name'))->toBe(['Couché · LOURD', 'Squat · LOURD', 'Haut du corps · VITESSE', 'Soulevé de terre · LOURD'])
        ->and(($this->lines)($program['days'][0]))->toBe([
            ['clap-push-up', 'explosive', 4, 3, 3, 120], ['bench-press', 'top', 1, 1, 2, 300], ['bench-press', 'max', 4, 2, 3, 240],
            ['pull-up', 'max', 5, 3, 3, 180], ['face-pull', 'mass', 3, 12, 15, 60], ['hanging-leg-raise', 'mass', 3, 8, 12, 60],
        ])
        ->and(($this->lines)($program['days'][1]))->toBe([
            ['box-jump', 'explosive', 4, 3, 3, 120], ['back-squat', 'max', 5, 2, 3, 240], ['overhead-press', 'max', 5, 3, 5, 180], ['cable-crunch', 'mass', 3, 8, 12, 60],
        ])
        ->and(($this->lines)($program['days'][3]))->toBe([
            ['broad-jump', 'explosive', 4, 3, 3, 120], ['deadlift', 'top', 1, 1, 2, 300], ['deadlift', 'max', 3, 2, 3, 240],
            ['back-squat', 'speed', 6, 2, 2, 90], ['hip-thrust', 'max', 4, 3, 5, 180], ['cable-crunch', 'mass', 3, 8, 12, 60],
        ]);
});

it('enchaîne les séances selon le nombre de jours', function (int $days, array $names) {
    expect(array_column(($this->pure)(['days' => $days])['days'], 'name'))->toBe($names);
})->with([
    [2, ['Full · COUCHÉ & SQUAT', 'Full · TERRE & ÉPAULES']],
    [3, ['Couché · LOURD', 'Squat · LOURD', 'Soulevé de terre · LOURD']],
    [5, ['Couché · LOURD', 'Squat · LOURD', 'Haut du corps · VITESSE', 'Soulevé de terre · LOURD', 'Épaules & tractions · VITESSE']],
    [6, ['Couché · LOURD', 'Squat · LOURD', 'Haut du corps · VITESSE', 'Soulevé de terre · LOURD', 'Épaules & tractions · VITESSE', 'Jambes · VITESSE']],
]);

it('reprend l\'exercice de la série record pour les séries lourdes, quel que soit le matériel', function (string $equipment) {
    foreach (($this->pure)(['equipment' => $equipment, 'days' => 2])['days'] as $day) {
        $top = collect($day['exercises'])->firstWhere('intent', 'top');
        $next = $day['exercises'][array_search($top, $day['exercises'], true) + 1];
        expect($next['exercise_id'])->toBe($top['exercise_id']);
    }
})->with(['gym', 'dumbbells']);

it('ne donne pas de série record à un débutant', function () {
    foreach ([2, 3, 4, 5, 6] as $days) {
        $intents = collect(($this->pure)(['level' => 'beginner', 'days' => $days])['days'])->flatMap(fn ($d) => $d['exercises'])->pluck('intent');
        expect($intents->all())->not->toContain('top')->not->toContain('max');
    }
});

it('respecte le matériel', function (string $equipment) {
    foreach ([2, 3, 4, 5, 6] as $days) {
        foreach (($this->pure)(['equipment' => $equipment, 'days' => $days])['days'] as $day) {
            expect($day['exercises'])->not->toBeEmpty();
            foreach ($day['exercises'] as $line) {
                expect($this->byId[$line['exercise_id']]->equipment)->toContain($equipment);
            }
        }
    }
})->with(['dumbbells', 'bodyweight']);

it('sans jambes, remplace squat et soulevé de terre par des séances du haut du corps', function () {
    $program = ($this->pure)(['upper_only' => true]);

    expect(array_column($program['days'], 'name'))->toBe(['Couché · LOURD', 'Développé militaire · LOURD', 'Haut du corps · VITESSE', 'Couché · LOURD B'])
        ->and(($this->lines)($program['days'][1]))->toBe([
            ['push-press', 'explosive', 4, 3, 3, 150], ['overhead-press', 'top', 1, 1, 2, 300], ['overhead-press', 'max', 4, 2, 3, 240],
            ['chest-supported-row', 'mass', 3, 6, 8, 120], ['skull-crusher', 'mass', 3, 8, 10, 90], ['hanging-leg-raise', 'mass', 3, 8, 12, 60],
        ])
        ->and(($this->lines)($program['days'][3]))->toBe([
            ['bench-press', 'top', 1, 1, 2, 300], ['bench-press', 'max', 4, 2, 3, 240], ['incline-bench-press', 'max', 4, 3, 5, 180],
            ['chin-up', 'max', 5, 3, 3, 180], ['rear-delt-fly', 'mass', 3, 12, 15, 60], ['cable-crunch', 'mass', 3, 8, 12, 60],
        ]);
});

it('sans jambes, aucun exercice de jambes quel que soit le nombre de jours et le matériel', function (string $equipment) {
    $legs = ['squat', 'hinge', 'lunge', 'quads_iso', 'hamstrings_iso', 'calves', 'jump'];
    foreach ([2, 3, 4, 5, 6] as $days) {
        $days = ($this->pure)(['upper_only' => true, 'equipment' => $equipment, 'days' => $days])['days'];
        foreach ($days as $day) {
            expect($day['exercises'])->not->toBeEmpty();
            foreach ($day['exercises'] as $line) {
                expect(array_intersect($this->byId[$line['exercise_id']]->slots, $legs))->toBe([]);
            }
        }
    }
})->with(['gym', 'dumbbells', 'bodyweight']);

it('sans jambes sur 5 jours, couché un jour sur deux et militaire 2 fois', function () {
    expect(array_column(($this->pure)(['upper_only' => true, 'days' => 5])['days'], 'name'))
        ->toBe(['Couché · LOURD', 'Développé militaire · LOURD', 'Haut du corps · VITESSE', 'Épaules & tractions · VITESSE', 'Couché · LOURD B']);
});

it('ne met jamais deux fois le même exercice dans une séance, hors série record suivie de ses séries lourdes', function (string $equipment, bool $upper) {
    foreach ([2, 3, 4, 5, 6] as $days) {
        foreach (($this->pure)(['equipment' => $equipment, 'days' => $days, 'upper_only' => $upper])['days'] as $day) {
            $ids = array_column($day['exercises'], 'exercise_id');
            expect($ids)->not->toBeEmpty();
            foreach (array_diff_assoc($ids, array_unique($ids)) as $i => $id) {
                expect($day['exercises'][$i - 1]['intent'] ?? null)->toBe('top', $day['name'].' : '.$this->byId[$id]->slug.' en double');
            }
        }
    }
})->with([['bodyweight', true], ['bodyweight', false], ['dumbbells', true], ['gym', true]]);

it('sans ceinture de lest, charge la force max du tirage et des triceps sur la poulie et la barre', function () {
    $program = ($this->pure)(['days' => 5, 'upper_only' => true, 'no_belt' => true]);
    $max = fn (int $day, string $slot) => collect($program['days'][$day]['exercises'])->where('slot', $slot)->where('intent', 'max')->map(fn ($l) => $this->byId[$l['exercise_id']]->slug)->values()->all();
    $explosive = collect($program['days'][3]['exercises'])->where('slot', 'vertical_pull')->map(fn ($l) => [$this->byId[$l['exercise_id']]->slug, $l['intent']])->values()->all();

    expect($max(0, 'vertical_pull'))->toBe(['lat-pulldown'])
        ->and($max(2, 'triceps'))->toBe(['close-grip-bench-press'])
        ->and($max(4, 'vertical_pull'))->toBe(['lat-pulldown'])
        ->and($explosive)->toBe([['pull-up', 'explosive']])
        ->and(collect(($this->pure)(['days' => 5, 'upper_only' => true, 'no_belt' => true, 'equipment' => 'bodyweight'])['days'][0]['exercises'])->where('slot', 'vertical_pull')->map(fn ($l) => $this->byId[$l['exercise_id']]->slug)->values()->all())->toBe(['pull-up'])
        // Avec une ceinture : tractions lestées, comme avant.
        ->and(collect(($this->pure)(['days' => 5, 'upper_only' => true])['days'][0]['exercises'])->where('slot', 'vertical_pull')->map(fn ($l) => $this->byId[$l['exercise_id']]->slug)->values()->all())->toBe(['pull-up']);
});
