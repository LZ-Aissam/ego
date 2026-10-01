<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutSet extends Model
{
    protected $fillable = ['exercise_id', 'position', 'weight_kg', 'reps', 'done_at'];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'float',
            'reps' => 'integer',
            'position' => 'integer',
            'done_at' => 'datetime',
        ];
    }

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
