<?php

namespace App\Game;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * Journée de jeu : elle finit à 4 h du matin, pas à minuit. Une séance terminée à 0 h 30 compte pour la veille,
 * et la quête de la veille ne tombe en échec qu'à 4 h. Tout ce qui parle de « jour » pour les quêtes passe par ici :
 * le planificateur reçoit des jours de jeu (today(), of()), jamais l'instant brut.
 */
final class GameDay
{
    public const CUTOFF_HOUR = 4;

    /** Jour de jeu d'un instant, à minuit dans le fuseau de l'appli. */
    public static function of(CarbonInterface $at): Carbon
    {
        $local = Carbon::instance($at)->setTimezone(config('app.timezone'));
        if ($local->hour < self::CUTOFF_HOUR) {
            $local->subDay();
        }

        return Carbon::parse($local->toDateString());
    }

    public static function today(): Carbon
    {
        return self::of(now());
    }

    /** Début (inclus) et fin (exclue) des séances qui comptent pour ce jour. @return array{Carbon, Carbon} */
    public static function bounds(CarbonInterface $day): array
    {
        $from = Carbon::parse($day->toDateString())->addHours(self::CUTOFF_HOUR);

        return [$from, $from->copy()->addDay()];
    }

    /** Restreint une requête aux séances de ce jour de jeu (colonne horodatée, par défaut la fin de séance). */
    public static function constrain(EloquentBuilder|QueryBuilder|Relation $query, CarbonInterface $day, string $column = 'workouts.finished_at'): EloquentBuilder|QueryBuilder|Relation
    {
        [$from, $to] = self::bounds($day);

        return $query->where($column, '>=', $from)->where($column, '<', $to);
    }
}
