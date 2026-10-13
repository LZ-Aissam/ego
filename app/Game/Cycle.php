<?php

namespace App\Game;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Cycle des programmes à intentions : 3 semaines de montée, puis 1 semaine allégée pour recharger
 * avant de battre ses records. Il part du lundi du programme actif, ou du calibrage s'il est plus récent.
 */
final class Cycle
{
    public const LENGTH = 4;

    /** Charges lourdes de la semaine allégée : −10 %. */
    public const DELOAD_FACTOR = 0.9;

    private const GOALS = ['power', 'pure'];

    /** Semaine du cycle (1 à 4), ou null pour un programme sans cycle. */
    public static function week(User $user, CarbonInterface $day): ?int
    {
        $program = $user->activeProgram()->first();
        if (! $program || ! in_array($program->params['goal'] ?? null, self::GOALS, true)) {
            return null;
        }

        $start = Carbon::parse($program->created_at);
        if ($user->calibrated_at && $user->calibrated_at->gt($start)) {
            $start = Carbon::parse($user->calibrated_at);
        }
        $weeks = (int) floor($start->copy()->startOfWeek()->diffInDays(Carbon::parse($day)->startOfDay(), false) / 7);

        return max(0, $weeks) % self::LENGTH + 1;
    }

    public static function isDeload(User $user, CarbonInterface $day): bool
    {
        return self::week($user, $day) === self::LENGTH;
    }

    /** Séance de la semaine allégée : sans série record, moitié des séries. */
    public static function lighten(array $objectives): array
    {
        return collect($objectives)
            ->reject(fn (array $o) => ($o['intent'] ?? null) === 'top')
            ->map(fn (array $o) => [...$o, 'sets' => (int) max(1, ceil($o['sets'] / 2))])
            ->values()->all();
    }

    public static function label(int $week): string
    {
        return 'Semaine '.$week.'/'.self::LENGTH.' · '.($week === self::LENGTH ? 'allégée' : 'montée');
    }
}
