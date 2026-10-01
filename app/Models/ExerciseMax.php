<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseMax extends Model
{
    protected $fillable = ['exercise_id', 'e1rm_kg', 'best_reps', 'source', 'measured_at'];

    protected function casts(): array
    {
        return ['exercise_id' => 'integer', 'e1rm_kg' => 'float', 'best_reps' => 'integer', 'measured_at' => 'datetime'];
    }

    /** « 25 répétitions max » au poids du corps, sinon le 1RM estimé. */
    public function label(): string
    {
        return $this->exercise->is_bodyweight && $this->best_reps
            ? $this->best_reps.' répétitions max'
            : \App\Support\Format::kg($this->e1rm_kg);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
