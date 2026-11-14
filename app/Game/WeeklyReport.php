<?php

namespace App\Game;

use App\Models\User;
use App\Support\Format;
use App\Support\SystemMessage;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Récap de TON EGO au premier passage de la semaine : séances faites, records, poids, semaine du cycle à venir.
 * Quand un cycle de 4 semaines vient de finir, il devient le bilan du cycle (max estimé gagné par grand mouvement).
 */
final class WeeklyReport
{
    public function __construct(private StrengthRanks $ranks) {}

    /** @return array|null le message, une seule fois par semaine */
    public function due(User $user, CarbonInterface $today): ?array
    {
        $monday = Carbon::parse($today->toDateString())->startOfWeek(Carbon::MONDAY);
        if ($user->active_program_id === null || ($user->weekly_report_on && $user->weekly_report_on->gte($monday))) {
            return null;
        }
        $user->forceFill(['weekly_report_on' => $monday->toDateString()])->save();

        $from = $monday->copy()->subWeek();
        $to = $monday->copy()->subDay();
        $quests = $user->quests()->where('type', 'training')->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->where('status', '!=', 'cancelled')->get();
        if ($quests->isEmpty()) {
            return null;                                                          // pas encore de semaine à raconter
        }

        $done = $quests->where('status', 'done')->count();
        $lines = [$done.' / '.$quests->count().' séances accomplies'];
        $records = $user->personalRecords()->whereBetween('achieved_at', [$from, $monday])->count();
        if ($records > 0) {
            $lines[] = $records.' record'.($records > 1 ? 's' : '').' battu'.($records > 1 ? 's' : '');
        }
        $weight = $this->weightLine($user, $to);
        if ($weight) {
            $lines[] = $weight;
        }

        $nextWeek = Cycle::week($user, $monday);
        $cycleEnded = $nextWeek === 1 && Cycle::week($user, $to) === Cycle::LENGTH;
        if ($cycleEnded) {
            foreach (StrengthRanks::LIFTS as $key => [$name]) {
                $now = $this->ranks->bestBefore($user, $key, $monday);
                $then = $this->ranks->bestBefore($user, $key, $monday->copy()->subWeeks(Cycle::LENGTH));
                if ($now !== null && $then !== null && abs($now - $then) >= 0.1) {
                    $lines[] = $name.' : '.($now > $then ? '+' : '−').Format::kg(abs(round($now - $then, 1))).' estimés';
                }
            }
        }
        if ($nextWeek !== null) {
            $lines[] = 'Cette semaine : '.Cycle::label($nextWeek);
        }

        return $cycleEnded
            ? SystemMessage::make('weekly_report', 'BILAN DU CYCLE', $lines, highlight: 'CYCLE')
            : SystemMessage::make('weekly_report', 'BILAN DE LA SEMAINE', $lines);
    }

    /** Écart entre la dernière pesée de la semaine et celle d'une semaine plus tôt. */
    private function weightLine(User $user, Carbon $sunday): ?string
    {
        $last = $user->bodyWeights()->where('date', '<=', $sunday->toDateString())->orderByDesc('date')->first();
        $before = $user->bodyWeights()->where('date', '<=', $sunday->copy()->subWeek()->toDateString())->orderByDesc('date')->first();
        if (! $last) {
            return null;
        }
        if (! $before) {
            return 'Poids : '.Format::kg($last->kg);
        }
        $delta = round($last->kg - $before->kg, 1);

        return 'Poids : '.Format::kg($last->kg).' ('.($delta >= 0 ? '+' : '−').Format::kg(abs($delta)).')';
    }
}
