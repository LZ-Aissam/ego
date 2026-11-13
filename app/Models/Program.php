<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    protected $fillable = ['name', 'split', 'params', 'copied_from_id'];

    protected function casts(): array
    {
        return ['params' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(ProgramDay::class)->orderBy('position');
    }

    /** Enchaînement séquentiel : la séance suit l'ordre du programme et attend d'être faite, au lieu de suivre le calendrier. */
    public function isSequential(): bool
    {
        return ($this->params['sequencing'] ?? 'calendar') === 'sequential';
    }
}
