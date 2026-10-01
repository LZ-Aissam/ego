<?php

use App\Models\Exercise;
use App\Services\ProgramGenerator;
use Database\Seeders\ExerciseSeeder;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->exercises = Exercise::all();
    $this->byId = $this->exercises->keyBy('id');
    $this->power = fn (array $over = []) => (new ProgramGenerator)->generate([...['level' => 'advanced', 'goal' => 'power', 'days' => 5, 'equipment' => 'gym'], ...$over], $this->exercises);
});

it('reproduit exactement le programme Force × Vitesse en 5 jours, salle, avancé', function () {
    $program = ($this->power)();
    $expected = require database_path('data/programs/force-vitesse.php');

    expect($program['split'])->toBe('power')
        ->and(array_column($program['days'], 'name'))->toBe(array_column($expected, 0));

    foreach ($expected as $i => [, $lines]) {
        $got = array_map(fn ($l) => [$this->byId[$l['exercise_id']]->slug, $l['intent'], $l['sets'], $l['reps_min'], $l['reps_max'], $l['rest_seconds']], $program['days'][$i]['exercises']);
        expect($got)->toBe($lines);
    }
});

it('enchaîne les séances selon le nombre de jours, jambes explosives en dernier', function (int $days, array $names) {
    expect(array_column(($this->power)(['days' => $days])['days'], 'name'))->toBe($names);
})->with([
    [2, ['Haut du corps · FORCE MAX', 'Jambes & hanches · EXPLOSIF']],
    [3, ['Poussée · FORCE MAX', 'Tirage · FORCE MAX', 'Jambes & hanches · EXPLOSIF']],
    [4, ['Poussée · FORCE MAX', 'Tirage · FORCE MAX', 'Haut du corps · VITESSE', 'Jambes & hanches · EXPLOSIF']],
    [6, ['Poussée · FORCE MAX', 'Tirage · FORCE MAX', 'Jambes · FORCE MAX', 'Épaules · PUISSANCE', 'Haut du corps · VITESSE', 'Jambes & hanches · EXPLOSIF']],
]);

it('limite les lignes selon le niveau et garde les abdos en dernier', function (string $level, int $max) {
    foreach ([2, 3, 4, 5, 6] as $days) {
        foreach (($this->power)(['level' => $level, 'days' => $days])['days'] as $day) {
            expect(count($day['exercises']))->toBeLessThanOrEqual($max)
                ->and(end($day['exercises'])['slot'])->toBe('core');
        }
    }
})->with([['beginner', 5], ['intermediate', 6], ['advanced', 7]]);

it('ne donne pas de force max 87,5 % à un débutant', function () {
    $days = ($this->power)(['level' => 'beginner', 'days' => 6])['days'];
    $lines = collect($days)->flatMap(fn ($d) => $d['exercises']);

    expect($lines->pluck('intent')->all())->not->toContain('max')
        ->and(collect(end($days)['exercises'])->firstWhere('slot', 'squat'))->toMatchArray(['intent' => 'speed']);
});

it('respecte le matériel, sans doublon dans une séance', function (string $equipment) {
    foreach ([2, 3, 4, 5, 6] as $days) {
        foreach (($this->power)(['equipment' => $equipment, 'days' => $days])['days'] as $day) {
            $ids = array_column($day['exercises'], 'exercise_id');
            expect($ids)->not->toBeEmpty()->and($ids)->toBe(array_values(array_unique($ids)));
            foreach ($ids as $id) {
                expect($this->byId[$id]->equipment)->toContain($equipment);
            }
        }
    }
})->with(['dumbbells', 'bodyweight']);

it('au poids du corps, aucune force max ni vitesse sur un exercice sans charge', function () {
    foreach (($this->power)(['equipment' => 'bodyweight', 'days' => 6])['days'] as $day) {
        foreach ($day['exercises'] as $line) {
            if ($this->byId[$line['exercise_id']]->is_bodyweight) {
                expect($line['intent'])->not->toBeIn(['max', 'speed']);
            }
        }
    }
});

it('laisse les autres objectifs sans intention', function () {
    $program = (new ProgramGenerator)->generate(['level' => 'advanced', 'goal' => 'strength', 'days' => 5, 'equipment' => 'gym'], $this->exercises);

    expect($program['split'])->toBe('ppl_ul')
        ->and(collect($program['days'])->flatMap(fn ($d) => $d['exercises'])->pluck('intent')->filter()->all())->toBe([]);
});
