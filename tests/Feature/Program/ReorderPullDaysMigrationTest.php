<?php

use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;

it('réordonne les journées Pull des programmes existants sans changer les exercices', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->withMorphology()->create();
    $program = app(ProgramBuilder::class)->build($user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 2, 3, 4, 5, 6], 'equipment' => 'gym']);
    $pull = $program->days->firstWhere('type', 'pull');
    // Ancien ordre : tirage vertical, tirage horizontal, charnière, biceps, épaules
    foreach (['vertical_pull', 'horizontal_pull', 'hinge', 'biceps', 'shoulder_iso'] as $i => $slot) {
        $pull->exercises()->where('slot', $slot)->update(['position' => $i + 1]);
    }
    $before = $pull->exercises()->pluck('exercise_id')->sort()->values()->all();

    (require database_path('migrations/2026_10_07_000100_reorder_pull_days.php'))->up();

    $after = $pull->fresh()->exercises;
    expect($after->pluck('slot')->all())->toBe(['hinge', 'vertical_pull', 'horizontal_pull', 'shoulder_iso', 'biceps'])
        ->and($after->pluck('position')->all())->toBe([1, 2, 3, 4, 5])
        ->and($after->pluck('exercise_id')->sort()->values()->all())->toBe($before);
});
