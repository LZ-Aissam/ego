<?php

namespace App\Game;

use App\Models\HomeAttempt;
use App\Models\User;
use App\Support\SystemMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Jeu maison : chaque jour, quand on veut, son maximum sur les défis qu'on a choisis.
 * À battre : le dernier jour joué + 1. Séparé du programme : aucune séance, aucune charge.
 */
final class HomeGame
{
    /** [libellé, unité, tirage (repos conseillé après la séance)] */
    public const CATALOG = [
        'push-ups' => ['Pompes', 'reps', false],
        'pull-ups' => ['Tractions', 'reps', true],
        'dips' => ['Dips', 'reps', false],
        'squats' => ['Squats', 'reps', false],
        'diamond-push-ups' => ['Pompes diamant', 'reps', false],
        'plank' => ['Gainage', 's', false],
    ];

    public const BEAT_XP = 15;

    public const RECORD_XP = 25;

    /** Jours affichés sur la courbe. */
    public const HISTORY_DAYS = 30;

    public function __construct(private XpLedger $ledger) {}

    /** @return list<array> messages du Système */
    public function record(User $user, string $game, int $value): array
    {
        $today = today();
        $previous = $this->dayBests($user, $game)->filter(fn ($best, $date) => $date < $today->toDateString());
        $lastDay = $previous->isEmpty() ? null : $previous->last();
        $recordBefore = (int) $user->homeAttempts()->where('game', $game)->max('value');
        $todayAttempts = $user->homeAttempts()->where('game', $game)->whereDate('played_on', $today)->get();

        $beat = $lastDay !== null && $value > $lastDay;
        $newRecord = $recordBefore > 0 && $value > $recordBefore;
        $attempt = $user->homeAttempts()->create(['game' => $game, 'value' => $value, 'played_on' => $today,
            'beat_previous' => $beat, 'new_record' => $newRecord]);

        $label = self::CATALOG[$game][0];
        $notes = [];
        // Une fois par jour et par défi : refaire des essais ne multiplie pas l'XP.
        if ($beat && ! $todayAttempts->contains('beat_previous', true) && $this->ledger->award($user, 'home_beat', $attempt->id, self::BEAT_XP)) {
            $notes[] = SystemMessage::homeBeat($label, $value, self::BEAT_XP);
        }
        if ($newRecord && ! $todayAttempts->contains('new_record', true) && $this->ledger->award($user, 'home_record', $attempt->id, self::RECORD_XP)) {
            $notes[] = SystemMessage::homeRecord($label, $value, self::RECORD_XP);
        }

        return $notes;
    }

    /** Une carte par défi choisi : aujourd'hui, à battre, record, série et courbe. */
    public function board(User $user): array
    {
        $today = today()->toDateString();

        return collect($user->home_games ?? [])->filter(fn ($g) => isset(self::CATALOG[$g]))->values()->map(function (string $game) use ($user, $today) {
            [$label, $unit, $pull] = self::CATALOG[$game];
            $bests = $this->dayBests($user, $game);
            $before = $bests->filter(fn ($best, $date) => $date < $today);

            return [
                'key' => $game, 'label' => $label, 'unit' => $unit, 'pull' => $pull,
                'today' => $bests[$today] ?? null,
                'target' => $before->isEmpty() ? null : $before->last() + 1,
                'record' => $bests->isEmpty() ? null : $bests->max(),
                'streak' => $this->streak($bests),
                'history' => collect(range(self::HISTORY_DAYS - 1, 0))->mapWithKeys(function ($ago) use ($bests) {
                    $date = today()->subDays($ago)->toDateString();

                    return [$date => $bests[$date] ?? null];
                })->all(),
            ];
        })->all();
    }

    /** Meilleur essai de chaque jour joué, du plus ancien au plus récent. @return Collection<string, int> */
    private function dayBests(User $user, string $game): Collection
    {
        return $user->homeAttempts()->where('game', $game)->orderBy('played_on')->get(['played_on', 'value'])
            ->groupBy(fn (HomeAttempt $a) => $a->played_on->toDateString())
            ->map(fn (Collection $day) => (int) $day->max('value'));
    }

    /** Jours de suite (jusqu'au dernier joué) où l'on a battu ou égalé la veille. */
    private function streak(Collection $bests): int
    {
        $days = $bests->keys()->reverse()->values();
        // Ni hier ni aujourd'hui : la série est retombée.
        if ($days->isEmpty() || $days[0] < today()->subDay()->toDateString()) {
            return 0;
        }
        $streak = 1;
        for ($i = 0; $i < $days->count() - 1; $i++) {
            $day = $days[$i];
            $before = $days[$i + 1];
            if (Carbon::parse($before)->addDay()->toDateString() !== $day || $bests[$day] < $bests[$before]) {
                break;
            }
            $streak++;
        }

        return $streak;
    }
}
