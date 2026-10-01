<?php

namespace App\Game;

use App\Models\Exercise;

final class PenaltyCircuit
{
    /** slug => [emplacement, [séries, reps] par niveau] */
    private const CIRCUIT = [
        'push-up' => ['horizontal_push', ['beginner' => [3, 10], 'intermediate' => [3, 20], 'advanced' => [4, 25]]],
        'bodyweight-squat' => ['squat', ['beginner' => [3, 15], 'intermediate' => [3, 25], 'advanced' => [4, 30]]],
        'crunch' => ['core', ['beginner' => [3, 15], 'intermediate' => [3, 25], 'advanced' => [4, 30]]],
    ];

    /** @return list<array{exercise_id: int, name: string, slot: string, sets: int, reps_min: int, reps_max: int, rest_seconds: int}> */
    public function for(?string $level): array
    {
        $level = in_array($level, ['beginner', 'intermediate', 'advanced'], true) ? $level : 'beginner';
        $exercises = Exercise::whereIn('slug', array_keys(self::CIRCUIT))->get()->keyBy('slug');

        $objectives = [];
        foreach (self::CIRCUIT as $slug => [$slot, $byLevel]) {
            [$sets, $reps] = $byLevel[$level];
            $objectives[] = [
                'exercise_id' => $exercises[$slug]->id, 'name' => $exercises[$slug]->name, 'slot' => $slot,
                'sets' => $sets, 'reps_min' => $reps, 'reps_max' => $reps, 'rest_seconds' => 60,
            ];
        }

        return $objectives;
    }
}
