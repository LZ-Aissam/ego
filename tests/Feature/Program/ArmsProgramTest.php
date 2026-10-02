<?php

use App\Models\Exercise;
use App\Services\ProgramGenerator;
use Database\Seeders\ExerciseSeeder;

const ARM_SLOTS = ['biceps', 'triceps', 'forearms'];

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->exercises = Exercise::all();
    $this->generate = fn (array $over = []) => (new ProgramGenerator)->generate([...['level' => 'beginner', 'goal' => 'hypertrophy', 'days' => 3, 'equipment' => 'gym'], ...$over], $this->exercises);
});

it('ajoute trois exercices d\'avant-bras au catalogue, avec fiche', function () {
    foreach (['wrist-curl', 'wrist-extension', 'reverse-curl'] as $slug) {
        $exercise = Exercise::firstWhere('slug', $slug);
        expect($exercise)->not->toBeNull()
            ->and($exercise->slots)->toContain('forearms')
            ->and($exercise->primary_muscles)->toContain('avant-bras')
            ->and($exercise->equipment)->toContain('dumbbells')
            ->and(\App\Support\ExerciseGuide::for($slug))->not->toBeNull();
    }
});

it('termine chaque séance par un exercice de bras, en tournant biceps, triceps, avant-bras', function () {
    $days = ($this->generate)()['days'];

    expect(array_map(fn ($d) => end($d['exercises'])['slot'], $days))->toBe(['biceps', 'triceps', 'forearms']);
});

it('met 6, 7 ou 8 exercices par séance selon le niveau, bras compris', function (string $level, int $count) {
    foreach ([2, 3, 4, 5, 6] as $days) {
        foreach (($this->generate)(['level' => $level, 'days' => $days])['days'] as $day) {
            $slots = array_column($day['exercises'], 'slot');
            expect($day['exercises'])->toHaveCount($count)
                ->and(array_intersect($slots, ARM_SLOTS))->not->toBeEmpty();
        }
    }
})->with([['beginner', 6], ['intermediate', 7], ['advanced', 8]]);

it('garde biceps et triceps dans les séances haut du corps d\'un débutant', function () {
    $days = ($this->generate)(['days' => 4])['days'];
    $upper = array_column($days[0]['exercises'], 'slot');

    expect($days[0]['name'])->toBe('Haut A')->and($upper)->toContain('biceps')->and($upper)->toContain('triceps');
});

it('choisit un autre groupe de bras quand celui du tour manque pour le matériel', function () {
    foreach (($this->generate)(['equipment' => 'bodyweight', 'days' => 3])['days'] as $day) {
        expect(array_intersect(array_column($day['exercises'], 'slot'), ARM_SLOTS))->not->toBeEmpty();
    }
});

it('ne touche pas aux programmes Force × Vitesse', function () {
    $power = ($this->generate)(['goal' => 'power', 'level' => 'advanced', 'days' => 5]);

    expect(array_map(fn ($d) => end($d['exercises'])['slot'], $power['days']))->each->toBe('core');
});
