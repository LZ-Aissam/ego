<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramExercise extends Model
{
    protected $fillable = ['position', 'exercise_id', 'slot', 'sets', 'reps_min', 'reps_max', 'rest_seconds'];

    protected function casts(): array
    {
        return ['exercise_id' => 'integer', 'sets' => 'integer', 'reps_min' => 'integer', 'reps_max' => 'integer', 'rest_seconds' => 'integer'];
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(ProgramDay::class, 'program_day_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
