<?php

namespace App\Services;

use App\Models\PersonalRecord;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class WorkoutSync
{
    public function __construct(private RecordCalculator $calc) {}

    /**
     * @param  array{client_id: string, started_at: string, finished_at: string, sets: list<array{exercise_id: int, weight_kg: float|int|string, reps: int|string, done_at: string}>}  $data
     * @return array{workout: Workout, newRecords: list<PersonalRecord>, created: bool}
     */
    public function sync(User $user, array $data): array
    {
        // Pas de vérification préalable : l'index unique sur client_id tranche, même entre deux envois simultanés.
        try {
            return $this->create($user, $data);
        } catch (UniqueConstraintViolationException) {
            $existing = Workout::where('client_id', $data['client_id'])->firstOrFail();

            if ($existing->user_id !== $user->id) {
                throw new WorkoutOwnedByAnotherUser;
            }

            return ['workout' => $existing->load('sets.exercise'), 'newRecords' => [], 'created' => false];
        }
    }

    /** @return array{workout: Workout, newRecords: list<PersonalRecord>, created: bool} */
    private function create(User $user, array $data): array
    {
        return DB::transaction(function () use ($user, $data) {
            $workout = $user->workouts()->create([
                'client_id' => $data['client_id'],
                'started_at' => $this->localTime($data['started_at']),
                'finished_at' => $this->localTime($data['finished_at']),
                'bodyweight_kg' => $user->weight_kg,
            ]);

            foreach (array_values($data['sets']) as $index => $set) {
                $workout->sets()->create([
                    'exercise_id' => (int) $set['exercise_id'],
                    'position' => $index + 1,
                    'weight_kg' => (float) $set['weight_kg'],
                    'reps' => (int) $set['reps'],
                    'effort' => $set['effort'] ?? null,
                    'done_at' => $this->localTime($set['done_at']),
                ]);
            }

            $workout->load('sets.exercise');

            return ['workout' => $workout, 'newRecords' => $this->updateRecords($user, $workout), 'created' => true];
        });
    }

    /** @return list<PersonalRecord> */
    private function updateRecords(User $user, Workout $workout): array
    {
        $improved = [];

        foreach ($workout->sets->groupBy('exercise_id') as $exerciseId => $sets) {
            $isBodyweight = $sets->first()->exercise->is_bodyweight;

            $best = $sets->sort(fn (WorkoutSet $a, WorkoutSet $b) => [$b->weight_kg, $b->reps] <=> [$a->weight_kg, $a->reps])->first();

            $bestE1rm = $sets->max(fn (WorkoutSet $set) => $this->calc->estimatedOneRepMax(
                $this->calc->effectiveLoad($set->weight_kg, $isBodyweight, $workout->bodyweight_kg),
                $set->reps,
            ));

            $record = PersonalRecord::firstWhere(['user_id' => $user->id, 'exercise_id' => $exerciseId]);

            if (! $record) {
                PersonalRecord::create([
                    'user_id' => $user->id,
                    'exercise_id' => $exerciseId,
                    'max_weight_kg' => $best->weight_kg,
                    'max_weight_reps' => $best->reps,
                    'best_e1rm_kg' => $bestE1rm,
                    'achieved_at' => $workout->finished_at,
                ]);

                continue;
            }

            if ($this->calc->beats($best->weight_kg, $best->reps, $record->max_weight_kg, $record->max_weight_reps)) {
                $record->fill([
                    'max_weight_kg' => $best->weight_kg,
                    'max_weight_reps' => $best->reps,
                    'achieved_at' => $workout->finished_at,
                ]);
                $improved[] = $record;
            }

            $record->best_e1rm_kg = max($record->best_e1rm_kg, $bestE1rm);
            $record->save();
        }

        foreach ($improved as $record) {
            $record->load('exercise');
        }

        return $improved;
    }

    private function localTime(string $iso): Carbon
    {
        return Carbon::parse($iso)->setTimezone(config('app.timezone'));
    }
}
