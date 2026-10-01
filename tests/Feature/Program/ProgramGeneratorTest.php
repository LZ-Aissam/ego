<?php

use App\Models\Exercise;
use App\Services\ProgramGenerator;
use Database\Seeders\ExerciseSeeder;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->exercises = Exercise::all();
    $this->generator = new ProgramGenerator;
    $this->params = fn (array $over = []) => [...['level' => 'intermediate', 'goal' => 'hypertrophy', 'days' => 3, 'equipment' => 'gym'], ...$over];
});

it('choisit le découpage selon le nombre de jours', function (int $days, string $split, array $names) {
    $program = $this->generator->generate(($this->params)(['days' => $days]), $this->exercises);

    expect($program['split'])->toBe($split)
        ->and(array_column($program['days'], 'name'))->toBe($names);
})->with([
    [2, 'full_body', ['Full body A', 'Full body B']],
    [3, 'full_body', ['Full body A', 'Full body B', 'Full body C']],
    [4, 'upper_lower', ['Haut A', 'Bas A', 'Haut B', 'Bas B']],
    [5, 'ppl_ul', ['Push', 'Pull', 'Legs', 'Haut', 'Bas']],
    [6, 'ppl', ['Push A', 'Pull A', 'Legs A', 'Push B', 'Pull B', 'Legs B']],
]);

it('met 5, 6 ou 7 exercices par jour selon le niveau (salle complète)', function (string $level, int $count) {
    foreach ([2, 4, 5, 6] as $days) {
        foreach ($this->generator->generate(($this->params)(['level' => $level, 'days' => $days]), $this->exercises)['days'] as $day) {
            expect($day['exercises'])->toHaveCount($count);
        }
    }
})->with([['beginner', 5], ['intermediate', 6], ['advanced', 7]]);

it('respecte le matériel et ne répète pas un exercice dans une journée', function (string $equipment) {
    $byId = $this->exercises->keyBy('id');

    foreach ($this->generator->generate(($this->params)(['equipment' => $equipment, 'days' => 6, 'level' => 'advanced']), $this->exercises)['days'] as $day) {
        $ids = array_column($day['exercises'], 'exercise_id');
        expect($ids)->toBe(array_values(array_unique($ids)))->and($ids)->not->toBeEmpty();
        foreach ($ids as $id) {
            expect($byId[$id]->equipment)->toContain($equipment);
        }
    }
})->with(['gym', 'dumbbells', 'bodyweight']);

it('applique séries, répétitions et repos selon l\'objectif et le niveau', function () {
    $day = $this->generator->generate(($this->params)(['goal' => 'hypertrophy', 'level' => 'beginner', 'days' => 4]), $this->exercises)['days'][0];
    $kinds = $this->exercises->keyBy('id');

    foreach ($day['exercises'] as $e) {
        $expected = $kinds[$e['exercise_id']]->kind === 'compound' ? [3, 6, 10, 120] : [3, 10, 15, 60];
        expect([$e['sets'], $e['reps_min'], $e['reps_max'], $e['rest_seconds']])->toBe($expected);
    }

    $strength = $this->generator->generate(($this->params)(['goal' => 'strength', 'level' => 'intermediate']), $this->exercises)['days'][0]['exercises'][0];
    expect([$strength['sets'], $strength['reps_min'], $strength['reps_max'], $strength['rest_seconds']])->toBe([5, 3, 5, 180]);
});

it('fait varier les exercices entre les variantes A et B', function () {
    [$a, $b] = $this->generator->generate(($this->params)(['days' => 2]), $this->exercises)['days'];

    expect($a['exercises'][0]['slot'])->toBe('squat')
        ->and($a['exercises'][0]['exercise_id'])->not->toBe($b['exercises'][0]['exercise_id']);
});

it('est déterministe', function () {
    expect($this->generator->generate(($this->params)(), $this->exercises))->toBe($this->generator->generate(($this->params)(), $this->exercises));
});

it('propose des alternatives du même emplacement et du même matériel', function () {
    $current = Exercise::firstWhere('slug', 'back-squat');
    $alternatives = $this->generator->alternativesFor('squat', 'gym', $this->exercises, $current->id);

    expect($alternatives->pluck('id'))->not->toContain($current->id)
        ->and($alternatives)->not->toBeEmpty()
        ->and($alternatives->every(fn ($e) => in_array('squat', $e->slots) && in_array('gym', $e->equipment)))->toBeTrue();
});
