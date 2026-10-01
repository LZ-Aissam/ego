<?php

namespace App\Models;

use App\Services\RecordCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workout extends Model
{
    protected $fillable = ['client_id', 'started_at', 'finished_at', 'bodyweight_kg'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'bodyweight_kg' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sets(): HasMany
    {
        return $this->hasMany(WorkoutSet::class)->orderBy('position');
    }

    public function durationMinutes(): int
    {
        return (int) round(abs($this->started_at->diffInMinutes($this->finished_at)));
    }

    public function volume(): float
    {
        $calc = app(RecordCalculator::class);

        return round($this->sets->sum(fn (WorkoutSet $set) => $calc->setVolume(
            $set->weight_kg, $set->reps, $set->exercise->is_bodyweight, $this->bodyweight_kg,
        )), 1);
    }
}
