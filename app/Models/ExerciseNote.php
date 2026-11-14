<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseNote extends Model
{
    /** Zones de douleur qu'on peut signaler pendant la séance. */
    public const PAIN_ZONES = ['shoulder' => 'Épaule', 'elbow' => 'Coude', 'wrist' => 'Poignet', 'back' => 'Dos', 'chest' => 'Pec'];

    /** Une douleur reste signalée à la séance suivante pendant ce nombre de jours. */
    public const PAIN_DAYS = 14;

    protected $fillable = ['user_id', 'workout_id', 'exercise_id', 'text', 'pain_zone'];

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
