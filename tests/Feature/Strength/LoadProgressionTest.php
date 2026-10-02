<?php

use App\Game\GameEvents;
use App\Game\LoadPrescriber;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkingLoad;
use App\Services\ProgramBuilder;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology()->create();
    $program = app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3], 'equipment' => 'gym']);
    $this->lines = $program->days->flatMap->exercises;
    $this->squat = $this->lines->firstWhere('slot', 'squat');                    // 3 × 6–10, bas du corps
    $this->push = $this->lines->firstWhere('slot', 'horizontal_push');           // 3 × 6–10, haut du corps
    $this->user->workingLoads()->create(['exercise_id' => $this->squat->exercise_id, 'target_kg' => 60, 'fail_streak' => 0]);
    $this->user->workingLoads()->create(['exercise_id' => $this->push->exercise_id, 'target_kg' => 40, 'fail_streak' => 0]);
});

/** @param list<array{0:int,1:float,2:int}> $sets [exercise_id, kg, reps] */
function lift(User $user, array $sets): array
{
    $at = now()->toIso8601String();
    $sync = app(WorkoutSync::class)->sync($user, [
        'client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
        'sets' => array_map(fn ($s) => ['exercise_id' => $s[0], 'weight_kg' => $s[1], 'reps' => $s[2], 'done_at' => $at], $sets),
    ]);

    return app(LoadPrescriber::class)->progress($user, $sync['workout']);
}

function target(User $user, int $exerciseId): array
{
    $load = WorkingLoad::where('user_id', $user->id)->where('exercise_id', $exerciseId)->first();

    return [$load->target_kg, $load->fail_streak];
}

it('augmente de 5 kg en bas du corps et 2,5 kg en haut quand le haut de la plage est atteint', function () {
    $sq = $this->squat->exercise_id;
    $bp = $this->push->exercise_id;

    $notes = lift($this->user, [[$sq, 60, 10], [$sq, 60, 10], [$sq, 60, 10], [$bp, 40, 10], [$bp, 40, 10], [$bp, 42.5, 11]]);

    expect(target($this->user, $sq))->toBe([65.0, 0])
        ->and(target($this->user, $bp))->toBe([42.5, 0])
        ->and(collect($notes)->pluck('title')->all())->toBe(['CHARGE AUGMENTÉE', 'CHARGE AUGMENTÉE']);
});

it('consolide sans changer quand la plage n\'est pas finie', function () {
    $sq = $this->squat->exercise_id;
    lift($this->user, [[$sq, 60, 10], [$sq, 60, 9], [$sq, 60, 8]]);

    expect(target($this->user, $sq))->toBe([60.0, 0]);
});

it('décharge de 10 % après deux séances d\'échec', function () {
    $sq = $this->squat->exercise_id;
    lift($this->user, [[$sq, 60, 8], [$sq, 60, 5]]);
    expect(target($this->user, $sq))->toBe([60.0, 1]);

    $notes = lift($this->user, [[$sq, 60, 4]]);
    expect(target($this->user, $sq))->toBe([55.0, 0])
        ->and($notes[0]['title'])->toBe('DÉCHARGE');
});

it('compte une séance entièrement plus légère comme un échec et ramène la cible à la charge réelle', function () {
    $sq = $this->squat->exercise_id;
    $this->user->workingLoads()->where('exercise_id', $sq)->update(['fail_streak' => 1]);

    $notes = lift($this->user, [[$sq, 50, 4], [$sq, 50, 12], [$sq, 50, 12]]);

    // −10 % donnerait 55 kg, mais le joueur n'a soulevé que 50 kg : la cible suit la charge réelle
    expect(target($this->user, $sq))->toBe([50.0, 0])->and($notes[0]['title'])->toBe('DÉCHARGE');
});

it("monte de 1 kg les petites charges d'isolation", function () {
    $user = User::factory()->withMorphology()->create();
    $program = app(ProgramBuilder::class)->build($user, ['level' => 'advanced', 'goal' => 'hypertrophy', 'weekdays' => [1, 2, 4, 5], 'equipment' => 'gym']);
    $iso = $program->days->flatMap->exercises->firstWhere('slot', 'shoulder_iso');
    $user->workingLoads()->updateOrCreate(['exercise_id' => $iso->exercise_id], ['target_kg' => 6, 'fail_streak' => 0]);

    lift($user, array_fill(0, $iso->sets, [$iso->exercise_id, 6, $iso->reps_max]));

    expect(target($user, $iso->exercise_id))->toBe([7.0, 0]);
});

it('n\'évalue qu\'une fois un exercice présent deux fois', function () {
    $sq = $this->squat->exercise_id;
    lift($this->user, [[$sq, 60, 10], [$sq, 60, 10], [$sq, 60, 10], [$sq, 60, 10], [$sq, 60, 10], [$sq, 60, 10]]);

    expect(target($this->user, $sq))->toBe([65.0, 0]);
});

it('suggère du lest au poids du corps et ignore les exercices hors programme', function () {
    $pullUp = Exercise::firstWhere('slug', 'pull-up');
    $this->user->activeProgram->days->first()->exercises()->create(['position' => 9, 'exercise_id' => $pullUp->id, 'slot' => 'vertical_pull', 'sets' => 3, 'reps_min' => 6, 'reps_max' => 10, 'rest_seconds' => 90]);
    $curl = Exercise::firstWhere('slug', 'barbell-curl');

    $notes = lift($this->user, [[$pullUp->id, 0, 15], [$pullUp->id, 0, 15], [$pullUp->id, 0, 16], [$curl->id, 20, 15]]);

    expect(collect($notes)->pluck('title')->all())->toBe(['LESTE-TOI'])
        ->and(WorkingLoad::where('exercise_id', $curl->id)->exists())->toBeFalse();
});

it('place les notifications de charge dans les événements de jeu, sauf si la progression est coupée', function () {
    $sq = $this->squat->exercise_id;
    $at = now()->toIso8601String();
    $sync = app(WorkoutSync::class)->sync($this->user, ['client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
        'sets' => array_fill(0, 3, ['exercise_id' => $sq, 'weight_kg' => 60, 'reps' => 10, 'done_at' => $at])]);

    $off = app(GameEvents::class)->afterWorkout($this->user->fresh(), $sync['workout'], $sync['newRecords'], progressLoads: false);
    expect(collect($off['notifications'])->pluck('title'))->not->toContain('CHARGE AUGMENTÉE')
        ->and(target($this->user, $sq))->toBe([60.0, 0]);

    $sync = app(WorkoutSync::class)->sync($this->user, ['client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
        'sets' => array_fill(0, 3, ['exercise_id' => $sq, 'weight_kg' => 60, 'reps' => 10, 'done_at' => $at])]);
    $on = app(GameEvents::class)->afterWorkout($this->user->fresh(), $sync['workout'], $sync['newRecords']);
    expect(collect($on['notifications'])->pluck('title'))->toContain('CHARGE AUGMENTÉE');
});
