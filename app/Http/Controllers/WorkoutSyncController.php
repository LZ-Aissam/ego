<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncWorkoutRequest;
use App\Models\PersonalRecord;
use App\Services\WorkoutOwnedByAnotherUser;
use App\Services\WorkoutSync;
use App\Support\SystemMessage;
use Illuminate\Http\JsonResponse;

class WorkoutSyncController extends Controller
{
    public function __invoke(SyncWorkoutRequest $request, WorkoutSync $sync): JsonResponse
    {
        try {
            ['workout' => $workout, 'newRecords' => $records, 'created' => $created] = $sync->sync($request->user(), $request->validated());
        } catch (WorkoutOwnedByAnotherUser) {
            return response()->json(['message' => 'Cette séance appartient à un autre compte.'], 409);
        }

        return response()->json([
            'workout_id' => $workout->id,
            'created' => $created,
            'duration_minutes' => $workout->durationMinutes(),
            'volume_kg' => $workout->volume(),
            'sets_count' => $workout->sets->count(),
            'new_records' => array_map(fn (PersonalRecord $r) => [
                'exercise' => $r->exercise->name,
                'weight_kg' => $r->max_weight_kg,
                'reps' => $r->max_weight_reps,
            ], $records),
            'notifications' => $created
                ? [SystemMessage::workoutDone($workout), ...array_map(fn (PersonalRecord $r) => SystemMessage::record($r), $records)]
                : [],
        ], $created ? 201 : 200);
    }
}
