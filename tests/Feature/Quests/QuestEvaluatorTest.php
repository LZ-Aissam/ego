<?php

use App\Game\QuestEvaluator;
use App\Models\Exercise;
use App\Models\WorkoutSet;
use Database\Seeders\ExerciseSeeder;

beforeEach(fn () => $this->seed(ExerciseSeeder::class));

function fakeSets(array $slugCounts)
{
    return collect($slugCounts)->flatMap(function ($count, $slug) {
        $exercise = Exercise::firstWhere('slug', $slug);

        return collect(range(1, $count))->map(fn () => (new WorkoutSet(['exercise_id' => $exercise->id]))->setRelation('exercise', $exercise));
    })->values();
}

it('valide une quête quand chaque objectif a assez de séries', function () {
    $objectives = [
        ['exercise_id' => Exercise::firstWhere('slug', 'back-squat')->id, 'slot' => 'squat', 'sets' => 3],
        ['exercise_id' => Exercise::firstWhere('slug', 'bench-press')->id, 'slot' => 'horizontal_push', 'sets' => 2],
    ];
    $evaluator = app(QuestEvaluator::class);

    expect($evaluator->isAccomplished($objectives, fakeSets(['back-squat' => 3, 'bench-press' => 1])))->toBeFalse()
        ->and($evaluator->progress($objectives, fakeSets(['back-squat' => 3, 'bench-press' => 1])))->toBe([3, 1])
        ->and($evaluator->isAccomplished($objectives, fakeSets(['back-squat' => 3, 'bench-press' => 2])))->toBeTrue();
});

it('accepte un exercice équivalent du même emplacement', function () {
    $objectives = [['exercise_id' => Exercise::firstWhere('slug', 'back-squat')->id, 'slot' => 'squat', 'sets' => 3]];

    expect(app(QuestEvaluator::class)->isAccomplished($objectives, fakeSets(['leg-press' => 3])))->toBeTrue()
        ->and(app(QuestEvaluator::class)->isAccomplished($objectives, fakeSets(['barbell-curl' => 5])))->toBeFalse()
        ->and(app(QuestEvaluator::class)->isAccomplished([], fakeSets(['leg-press' => 3])))->toBeFalse();
});
