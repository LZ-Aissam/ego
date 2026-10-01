<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalRecord extends Model
{
    protected $fillable = ['user_id', 'exercise_id', 'max_weight_kg', 'max_weight_reps', 'best_e1rm_kg', 'achieved_at'];

    protected function casts(): array
    {
        return [
            'max_weight_kg' => 'float',
            'max_weight_reps' => 'integer',
            'best_e1rm_kg' => 'float',
            'achieved_at' => 'datetime',
        ];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
