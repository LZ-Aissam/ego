<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncWorkoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'uuid'],
            'started_at' => ['required', 'date'],
            'finished_at' => ['required', 'date', 'after_or_equal:started_at'],
            'skipped' => ['nullable', 'array', 'max:50'],
            'skipped.*' => ['integer', 'exists:exercises,id'],
            'sets' => ['required', 'array', 'min:1', 'max:500'],
            'sets.*.exercise_id' => ['required', 'integer', 'exists:exercises,id'],
            'sets.*.weight_kg' => ['required', 'numeric', 'min:0', 'max:1000'],
            'sets.*.reps' => ['required', 'integer', 'min:1', 'max:1000'],
            'sets.*.done_at' => ['required', 'date'],
            'sets.*.effort' => ['nullable', 'in:'.implode(',', \App\Game\Effort::KEYS)],
            'readiness' => ['nullable', 'in:'.implode(',', \App\Models\Workout::READINESS)],
            'notes' => ['nullable', 'array', 'max:50'],
            'notes.*.exercise_id' => ['required', 'integer', 'exists:exercises,id'],
            'notes.*.text' => ['nullable', 'string', 'max:280'],
            'notes.*.pain_zone' => ['nullable', 'in:'.implode(',', array_keys(\App\Models\ExerciseNote::PAIN_ZONES))],
        ];
    }
}
