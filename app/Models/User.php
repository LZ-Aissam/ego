<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    public const AVATAR_COLORS = ['#f97316', '#22c55e', '#3b82f6', '#a855f7', '#ec4899', '#eab308', '#14b8a6', '#ef4444'];

    protected $fillable = [
        'first_name', 'identity_key', 'birth_date', 'avatar_color',
        'sex', 'height_cm', 'weight_kg', 'level', 'goal', 'days_per_week', 'equipment',
    ];

    protected $hidden = ['identity_key', 'remember_token'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'height_cm' => 'integer',
            'weight_kg' => 'float',
            'days_per_week' => 'integer',
            'is_admin' => 'boolean',
            'xp_total' => 'integer',
            'training_weekdays' => 'array',
            'streak_days' => 'integer',
            'best_streak' => 'integer',
            'paused_at' => 'date',
            'paused_until' => 'date',
        ];
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function activeProgram(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'active_program_id');
    }

    public function quests(): HasMany
    {
        return $this->hasMany(Quest::class);
    }

    public function isPaused(?CarbonInterface $day = null): bool
    {
        $day = ($day ?? now())->copy()->startOfDay();

        return $this->paused_at !== null
            && $day->gte($this->paused_at)
            && ($this->paused_until === null || $day->lte($this->paused_until));
    }

    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class);
    }

    public function personalRecords(): HasMany
    {
        return $this->hasMany(PersonalRecord::class);
    }

    public function xpEvents(): HasMany
    {
        return $this->hasMany(XpEvent::class);
    }

    public function titles(): HasMany
    {
        return $this->hasMany(UserTitle::class);
    }

    public function equippedTitleName(): ?string
    {
        return $this->equipped_title ? \App\Game\Titles::name($this->equipped_title) : null;
    }

    public function bmi(): ?float
    {
        if (! $this->height_cm || ! $this->weight_kg) {
            return null;
        }

        $meters = $this->height_cm / 100;

        return round($this->weight_kg / ($meters * $meters), 1);
    }
}
