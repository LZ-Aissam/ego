<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseMax extends Model
{
    protected $fillable = ['exercise_id', 'e1rm_kg', 'source', 'measured_at'];

    protected function casts(): array
    {
        return ['exercise_id' => 'integer', 'e1rm_kg' => 'float', 'measured_at' => 'datetime'];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
