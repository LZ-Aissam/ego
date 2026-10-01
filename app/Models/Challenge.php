<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

class Challenge extends Model
{
    public const METRICS = ['xp' => 'XP', 'sessions' => 'Séances', 'volume' => 'Volume', 'strength' => 'Force'];

    public const DURATIONS = ['3d' => '3 jours', '1w' => '1 semaine', '2w' => '2 semaines', '1m' => '1 mois'];

    public const LIFTS = ['bench' => 'Développé couché', 'squat' => 'Squat', 'deadlift' => 'Soulevé de terre'];

    protected $fillable = ['crew_id', 'creator_id', 'metric', 'lift', 'duration', 'starts_at', 'ends_at', 'stake', 'status', 'final_standings', 'started_notified_at', 'finished_notified_at'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'final_standings' => 'array',
            'started_notified_at' => 'datetime', 'finished_notified_at' => 'datetime',
        ];
    }

    /** Départ le lendemain à 00:00, fin exclue. @return array{0: Carbon, 1: Carbon} */
    public static function window(string $duration, Carbon $now): array
    {
        $start = $now->copy()->addDay()->startOfDay();
        $end = match ($duration) {
            '3d' => $start->copy()->addDays(3),
            '1w' => $start->copy()->addWeek(),
            '2w' => $start->copy()->addWeeks(2),
            '1m' => $start->copy()->addMonthNoOverflow(),
        };

        return [$start, $end];
    }

    public function title(): string
    {
        return match ($this->metric) {
            'xp' => 'Défi XP',
            'sessions' => 'Défi séances',
            'volume' => 'Défi volume',
            'strength' => 'Défi force · '.(self::LIFTS[$this->lift] ?? ''),
        };
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['upcoming', 'active'], true);
    }

    public function crew(): BelongsTo
    {
        return $this->belongsTo(Crew::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('joined_at');
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants()->whereKey($user->id)->exists();
    }
}
