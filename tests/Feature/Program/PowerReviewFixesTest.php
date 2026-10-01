<?php

use App\Models\Exercise;
use App\Models\User;
use App\Services\ProgramBuilder;
use App\Services\ProgramGenerator;
use Database\Seeders\ExerciseSeeder;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->exercises = Exercise::all();
    $this->byId = $this->exercises->keyBy('id');
    $this->power = fn (array $over = []) => (new ProgramGenerator)->generate([...['level' => 'advanced', 'goal' => 'power', 'days' => 5, 'equipment' => 'gym'], ...$over], $this->exercises);
});

it('recalcule les charges quand on régénère vers un autre objectif', function () {
    $user = User::factory()->withMorphology(weightKg: 80.0, level: 'advanced')->create();
    $bench = Exercise::firstWhere('slug', 'bench-press');
    $user->exerciseMaxes()->create(['exercise_id' => $bench->id, 'e1rm_kg' => 100, 'source' => 'calibration', 'measured_at' => now()]);
    $user->forceFill(['calibrated_at' => now(), 'power_factor' => 1.0])->save();
    $builder = app(ProgramBuilder::class);

    $builder->build($user, ['level' => 'advanced', 'goal' => 'power', 'weekdays' => [1, 2, 3, 4, 5], 'equipment' => 'gym']);
    expect((float) $user->workingLoads()->where('exercise_id', $bench->id)->value('target_kg'))->toBe(87.5);

    $builder->build($user->fresh(), ['level' => 'advanced', 'goal' => 'hypertrophy', 'weekdays' => [1, 2, 3, 4, 5], 'equipment' => 'gym']);
    expect((float) $user->workingLoads()->where('exercise_id', $bench->id)->value('target_kg'))->toBeLessThan(87.5);
});

it('ne met jamais force max, vitesse ou explosif sur un exercice d\'isolation aux haltères', function () {
    foreach (['beginner', 'advanced'] as $level) {
        foreach ([2, 3, 4, 5, 6] as $days) {
            foreach (($this->power)(['equipment' => 'dumbbells', 'level' => $level, 'days' => $days])['days'] as $day) {
                foreach ($day['exercises'] as $line) {
                    if (in_array($line['intent'], ['max', 'speed', 'explosive'], true)) {
                        expect($this->byId[$line['exercise_id']]->kind, $day['name'].' '.$this->byId[$line['exercise_id']]->slug)->toBe('compound');
                    }
                }
            }
        }
    }
});

it('garde au moins 4 exercices par séance, quel que soit le matériel', function (string $equipment) {
    foreach ([2, 3, 4, 5, 6] as $days) {
        foreach (($this->power)(['equipment' => $equipment, 'days' => $days])['days'] as $day) {
            expect(count($day['exercises']), "$equipment $days j {$day['name']}")->toBeGreaterThanOrEqual(4);
        }
    }
})->with(['gym', 'dumbbells', 'bodyweight']);
