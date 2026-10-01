<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quest extends Model
{
    protected $fillable = ['date', 'type', 'program_day_id', 'title', 'objectives', 'status', 'xp_awarded', 'completed_at'];

    protected function casts(): array
    {
        return ['date' => 'date', 'objectives' => 'array', 'xp_awarded' => 'integer', 'completed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
