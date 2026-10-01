<?php

namespace App\Game;

use App\Models\Exercise;
use App\Models\User;
use App\Services\RecordCalculator;
use App\Services\WorkoutSync;
use App\Support\Format;
use App\Support\SystemMessage;
use Illuminate\Support\Str;

final class Calibration
{
    public function __construct(
        private WorkoutSync $sync,
        private GameEvents $game,
        private LoadPrescriber $prescriber,
        private CalibrationPlan $plan,
        private RecordCalculator $calc,
    ) {}

    /**
     * @param  list<array{exercise_id: int, warmups: list<array{0: float, 1: int}>, test: array{0: float, 1: int}}>  $results
     * @return array{workout: \App\Models\Workout, notifications: list<array>}
     */
    public function complete(User $user, array $results, string $startedAtIso): array
    {
        $now = now();
        $sets = [];
        foreach ($results as $result) {
            foreach ([...$result['warmups'], $result['test']] as [$kg, $reps]) {
                $sets[] = ['exercise_id' => $result['exercise_id'], 'weight_kg' => (float) $kg, 'reps' => (int) $reps, 'done_at' => $now->toIso8601String()];
            }
        }

        $synced = $this->sync->sync($user, [
            'client_id' => (string) Str::uuid(), 'started_at' => $startedAtIso, 'finished_at' => $now->toIso8601String(), 'sets' => $sets,
        ]);

        $estimates = collect($this->plan->plan($user))->keyBy('exercise_id');
        $exercises = Exercise::whereIn('id', array_column($results, 'exercise_id'))->get()->keyBy('id');
        $planLines = $this->prescriber->planLines($user);
        $lines = [];
        $ratios = [];

        foreach ($results as $result) {
            $exercise = $exercises[$result['exercise_id']];
            [$kg, $reps] = $result['test'];
            $e1rm = $this->calc->estimatedOneRepMax($this->calc->effectiveLoad((float) $kg, $exercise->is_bodyweight, $user->weight_kg), (int) $reps);

            $user->exerciseMaxes()->updateOrCreate(['exercise_id' => $exercise->id], ['e1rm_kg' => $e1rm, 'source' => 'calibration', 'measured_at' => $now]);
            $lines[] = $exercise->name.' : 1RM ≈ '.Format::kg($e1rm).((int) $reps > 12 ? ' (mesure moins précise)' : '');

            if (! $exercise->is_bodyweight) {
                $repsMax = $planLines[$exercise->id]->reps_max ?? 10;
                $estimate = $estimates[$exercise->id]['estimate'] ?? CalibrationPlan::DEFAULT_ESTIMATE;
                $ratios[] = $this->prescriber->startingLoad($e1rm, $repsMax) / $estimate;
            }
        }

        $factor = $ratios === [] ? 1.0 : min(2.0, max(0.5, array_sum($ratios) / count($ratios)));
        $user->forceFill(['calibrated_at' => $now, 'power_factor' => round($factor, 2)])->save();
        $this->prescriber->prescribeProgram($user, overwrite: true);

        $play = $this->game->afterWorkout($user->fresh(), $synced['workout'], $synced['newRecords'], progressLoads: false);

        return [
            'workout' => $synced['workout'],
            'notifications' => [
                SystemMessage::powerMeasured($lines),
                SystemMessage::workoutDone($synced['workout'], $play['xp']),
                ...$play['notifications'],
            ],
        ];
    }
}
