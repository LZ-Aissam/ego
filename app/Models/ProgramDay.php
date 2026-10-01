<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramDay extends Model
{
    protected $fillable = ['position', 'name', 'type'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(ProgramExercise::class)->orderBy('position');
    }

    /** @return list<array{exercise_id: int, name: string, slot: string, sets: int, reps_min: int, reps_max: int, rest_seconds: int}> */
    public function objectives(): array
    {
        return $this->exercises()->with('exercise')->get()->map(fn (ProgramExercise $e) => [
            'exercise_id' => $e->exercise_id,
            'name' => $e->exercise->name,
            'slot' => $e->slot,
            'sets' => $e->sets,
            'reps_min' => $e->reps_min,
            'reps_max' => $e->reps_max,
            'rest_seconds' => $e->rest_seconds,
            'intent' => $e->intent,
        ])->all();
    }
}
