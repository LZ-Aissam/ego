<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkingLoad extends Model
{
    protected $fillable = ['exercise_id', 'target_kg', 'fail_streak'];

    protected function casts(): array
    {
        return ['exercise_id' => 'integer', 'target_kg' => 'float', 'fail_streak' => 'integer'];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
