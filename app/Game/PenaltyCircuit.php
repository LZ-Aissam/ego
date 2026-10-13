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

    /** Programme sans jambes : les squats laissent la place aux dips sur banc. */
    private const UPPER_ONLY_SWAP = ['bodyweight-squat' => ['bench-dips', ['triceps', ['beginner' => [3, 10], 'intermediate' => [3, 15], 'advanced' => [4, 20]]]]];

    /** @return list<array{exercise_id: int, name: string, slot: string, sets: int, reps_min: int, reps_max: int, rest_seconds: int}> */
    public function for(?string $level, bool $upperOnly = false): array
    {
        $level = in_array($level, ['beginner', 'intermediate', 'advanced'], true) ? $level : 'beginner';
        $circuit = [];
        foreach (self::CIRCUIT as $slug => $row) {
            [$slug, $row] = $upperOnly && isset(self::UPPER_ONLY_SWAP[$slug]) ? self::UPPER_ONLY_SWAP[$slug] : [$slug, $row];
            $circuit[$slug] = $row;
        }
        $exercises = Exercise::whereIn('slug', array_keys($circuit))->get()->keyBy('slug');

        $objectives = [];
        foreach ($circuit as $slug => [$slot, $byLevel]) {
            [$sets, $reps] = $byLevel[$level];
            $objectives[] = [
                'exercise_id' => $exercises[$slug]->id, 'name' => $exercises[$slug]->name, 'slot' => $slot,
                'sets' => $sets, 'reps_min' => $reps, 'reps_max' => $reps, 'rest_seconds' => 60,
            ];
        }

        return $objectives;
    }
}
