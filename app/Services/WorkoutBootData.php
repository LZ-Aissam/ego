<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSet;

final class WorkoutBootData
{
    public const REST_SECONDS = 90;

    public function __construct(private StartingLoad $startingLoad) {}

    public function for(User $user): array
    {
        $loads = $user->workingLoads()->pluck('target_kg', 'exercise_id');

        return [
            'restSeconds' => self::REST_SECONDS,
            'exercises' => Exercise::orderBy('name')->get()->map(fn (Exercise $e) => [
                'id' => $e->id,
                'slug' => $e->slug,
                'name' => $e->name,
                'muscles' => $e->primary_muscles ?? [],
                'guide' => \App\Support\ExerciseGuide::for($e->slug),
                'isBodyweight' => $e->is_bodyweight,
                'suggestedKg' => $this->startingLoad->suggest($e, $user),
                'prescribedKg' => isset($loads[$e->id]) ? (float) $loads[$e->id] : null,
                'perDumbbell' => $e->isPerDumbbell(),
            ])->values()->all(),
            'lastTime' => $this->lastTime($user),
        ];
    }

    public function forQuest(User $user, \App\Models\Quest $quest): array
    {
        $boot = $this->for($user);
        $library = collect($boot['exercises'])->keyBy('id');

        $boot['questTitle'] = $quest->title;
        $boot['plan'] = array_map(fn (array $o) => [
            'id' => $o['exercise_id'],
            'name' => $o['name'],
            'isBodyweight' => $library[$o['exercise_id']]['isBodyweight'] ?? false,
            'suggestedKg' => $library[$o['exercise_id']]['suggestedKg'] ?? null,
            'intent' => $o['intent'] ?? null,
            'intentBadge' => \App\Game\Intent::badge($o['intent'] ?? null),
            'intentCue' => \App\Game\Intent::cue($o['intent'] ?? null),
            'prescribedKg' => ($o['intent'] ?? null) === null
                ? ($library[$o['exercise_id']]['prescribedKg'] ?? null)
                : app(\App\Game\LoadPrescriber::class)->prescriptionFor($user, \App\Models\Exercise::find($o['exercise_id']), $o['intent'], $o['reps_max'], \Illuminate\Support\Carbon::parse($quest->date->toDateString())),
            'perDumbbell' => $library[$o['exercise_id']]['perDumbbell'] ?? false,
            'sets' => $o['sets'],
            'repsMin' => $o['reps_min'],
            'repsMax' => $o['reps_max'],
            'restSeconds' => $o['rest_seconds'],
        ], $quest->objectives);

        return $boot;
    }

    /** @return array<int, list<array{weightKg: float, reps: int}>> */
    private function lastTime(User $user): array
    {
        $sets = WorkoutSet::query()
            ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
            ->where('workouts.user_id', $user->id)
            ->orderByDesc('workouts.finished_at')
            ->orderByDesc('workouts.id')
            ->orderBy('workout_sets.position')
            ->get(['workout_sets.exercise_id', 'workout_sets.workout_id', 'workout_sets.weight_kg', 'workout_sets.reps']);

        $result = [];
        $latestWorkout = [];

        foreach ($sets as $set) {
            $latestWorkout[$set->exercise_id] ??= $set->workout_id;

            if ($latestWorkout[$set->exercise_id] === $set->workout_id) {
                $result[$set->exercise_id][] = ['weightKg' => $set->weight_kg, 'reps' => $set->reps];
            }
        }

        return $result;
    }
}
