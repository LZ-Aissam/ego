<?php

namespace App\Game;

use App\Models\User;
use App\Models\Workout;
use App\Services\RecordCalculator;
use App\Support\SystemMessage;
use Illuminate\Support\Facades\DB;

final class GameEvents
{
    public const RECORD_XP = 50;
    public const RECORDS_PER_WORKOUT = 3;

    public function __construct(private XpLedger $ledger, private Titles $titles, private RecordCalculator $calc, private QuestScheduler $quests, private LoadPrescriber $loads) {}

    /**
     * @param  list<\App\Models\PersonalRecord>  $newRecords  records battus par cette séance
     * @return array{xp: int, notifications: list<array>, player: array}
     */
    public function afterWorkout(User $user, Workout $workout, array $newRecords, bool $progressLoads = true): array
    {
        // Verrou par joueur : deux envois simultanés passent l'un après l'autre (plafond du jour,
        // titres et montée de niveau calculés sur l'XP réellement en base).
        return DB::transaction(function () use ($user, $workout, $newRecords, $progressLoads) {
            $user->xp_total = (int) User::whereKey($user->id)->lockForUpdate()->value('xp_total');

            return $this->play($user, $workout, $newRecords, $progressLoads);
        });
    }

    private function play(User $user, Workout $workout, array $newRecords, bool $progressLoads): array
    {
        $startXp = (int) $user->xp_total;
        $before = Leveling::progress($startXp);
        $this->awardWorkout($user, $workout, count($newRecords));
        $notifications = $this->quests->onWorkout($user, $workout);
        if ($progressLoads) {
            array_push($notifications, ...$this->loads->progress($user, $workout));
        }
        $xp = (int) $user->xp_total - $startXp;
        $after = Leveling::progress((int) $user->xp_total);

        if ($after['level'] > $before['level']) {
            $notifications[] = SystemMessage::levelUp($after);
        }
        if ($after['rank'] !== $before['rank']) {
            $notifications[] = SystemMessage::rankUp($after['rank']);
        }
        foreach ($this->titles->check($user) as $key) {
            $notifications[] = SystemMessage::title($key);
        }

        return ['xp' => $xp, 'notifications' => $notifications, 'player' => $after];
    }

    /** XP de la séance et de ses records ; renvoie l'XP réellement gagnée. */
    public function awardWorkout(User $user, Workout $workout, int $recordsBeaten): int
    {
        $xp = 0;
        $amount = $this->ledger->workoutXp($workout->sets->count());

        if ($amount > 0 && $this->ledger->isEligible($user, $workout)
            && $this->ledger->award($user, 'workout', $workout->id, $amount, at: $workout->finished_at)) {
            $xp += $amount;
        }

        $records = min($recordsBeaten, self::RECORDS_PER_WORKOUT);
        if ($records > 0 && $this->ledger->award($user, 'record', $workout->id, $records * self::RECORD_XP, $records, $workout->finished_at)) {
            $xp += $records * self::RECORD_XP;
        }

        return $xp;
    }

    /** Rejoue l'historique (séances déjà enregistrées) sans notification. Idempotent. */
    public function backfill(User $user): void
    {
        $best = [];

        $workouts = $user->workouts()->with('sets')->orderBy('finished_at')->orderBy('id')->get();

        foreach ($workouts as $workout) {
            $beaten = 0;
            foreach ($workout->sets->groupBy('exercise_id') as $exerciseId => $sets) {
                $top = $sets->sort(fn ($a, $b) => [$b->weight_kg, $b->reps] <=> [$a->weight_kg, $a->reps])->first();
                $improves = ! isset($best[$exerciseId]) || $this->calc->beats($top->weight_kg, $top->reps, ...$best[$exerciseId]);

                if ($improves && isset($best[$exerciseId])) {
                    $beaten++;
                }
                if ($improves) {
                    $best[$exerciseId] = [$top->weight_kg, $top->reps];
                }
            }

            $this->awardWorkout($user, $workout, $beaten);
        }

        $this->ledger->recalculate($user);
        $this->titles->check($user->fresh());
    }
}
