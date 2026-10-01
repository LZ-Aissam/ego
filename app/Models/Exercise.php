<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    public const SLOTS = [
        'horizontal_push', 'vertical_push', 'horizontal_pull', 'vertical_pull',
        'squat', 'hinge', 'lunge',
        'chest_iso', 'shoulder_iso', 'biceps', 'triceps',
        'quads_iso', 'hamstrings_iso', 'calves', 'core',
    ];

    protected $fillable = ['slug', 'name', 'primary_muscles', 'equipment', 'kind', 'slots', 'is_bodyweight', 'start_ratio'];

    protected function casts(): array
    {
        return [
            'primary_muscles' => 'array',
            'equipment' => 'array',
            'slots' => 'array',
            'is_bodyweight' => 'boolean',
            'start_ratio' => 'array',
        ];
    }
}
