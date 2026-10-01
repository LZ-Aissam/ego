<?php

namespace App\Game;

use App\Models\Program;
use App\Models\ProgramDay;
use App\Models\User;
use App\Support\SystemMessage;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class QuestScheduler
{
    public const QUEST_XP = 200;
    public const PENALTY_XP = 50;
    public const MAX_CATCH_UP_DAYS = 60;

    public function __construct(
        private Titles $titles,
        private StreakCalculator $streaks,
        private PenaltyCircuit $penalties,
    ) {}

    public function rewardFor(int $streak): int
    {
        return (int) round(self::QUEST_XP * (1 + min($streak, 5) * 0.1));
    }

    /** Crée les quêtes dues, constate les échecs, gère la pause. Idempotent. @return list<array> */
    public function sync(User $user, CarbonInterface $today): array
    {
        $today = Carbon::parse($today->toDateString());

        return DB::transaction(function () use ($user, $today) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $user->refresh();
            $notes = [];

            // Période de pause mémorisée AVANT une éventuelle levée automatique : les jours passés
            // en pause doivent rester sans quête ni pénalité pendant le rattrapage.
            [$pausedFrom, $pausedUntil] = [$user->paused_at, $user->paused_until];
            $paused = fn (Carbon $d) => $pausedFrom !== null && $d->gte($pausedFrom) && ($pausedUntil === null || $d->lte($pausedUntil));

            if ($pausedFrom && $pausedUntil && $today->gt($pausedUntil)) {
                $user->forceFill(['paused_at' => null, 'paused_until' => null])->save();
                $notes[] = SystemMessage::pauseOff();
            }

            $program = $user->activeProgram()->with('days')->first();
            if ($program) {
                for ($day = $this->firstDayToProcess($user, $program, $today); $day->lte($today); $day = $day->copy()->addDay()) {
                    if (! $paused($day)) {
                        $this->ensureQuest($user, $program, $day);
                    }
                    if ($day->lt($today)) {
                        array_push($notes, ...$this->expire($user, $day, $paused));
                    }
                }
            }

            $this->streaks->refresh($user, $today);
            foreach ($this->titles->check($user) as $key) {
                $notes[] = SystemMessage::title($key);
            }

            return $notes;
        });
    }

    private function firstDayToProcess(User $user, Program $program, Carbon $today): Carbon
    {
        $last = $user->quests()->max('date');
        $start = $last ? Carbon::parse($last)->addDay() : Carbon::parse($program->created_at->toDateString());

        $pending = $user->quests()->where('status', 'pending')->where('date', '<', $today->toDateString())->min('date');
        if ($pending && Carbon::parse($pending)->lt($start)) {
            $start = Carbon::parse($pending);
        }

        $floor = $today->copy()->subDays(self::MAX_CATCH_UP_DAYS);

        return $start->lt($floor) ? $floor : $start;
    }

    private function ensureQuest(User $user, Program $program, Carbon $day): void
    {
        if ($user->quests()->where('date', $day->toDateString())->whereIn('type', ['training', 'rest'])->exists()) {
            return;
        }

        if (in_array($day->isoWeekday(), $user->training_weekdays ?? [], true)) {
            $programDay = $this->nextProgramDay($user, $program);
            $user->quests()->create([
                'date' => $day->toDateString(), 'type' => 'training', 'program_day_id' => $programDay->id,
                'title' => $programDay->name, 'objectives' => $programDay->objectives(), 'status' => 'pending',
            ]);

            return;
        }

        $user->quests()->create([
            'date' => $day->toDateString(), 'type' => 'rest', 'title' => 'Repos', 'objectives' => [],
            'status' => 'done', 'completed_at' => $day->copy()->endOfDay(),
        ]);
    }

    private function nextProgramDay(User $user, Program $program): ProgramDay
    {
        $days = $program->days;
        $lastDone = $user->quests()->where('type', 'training')->where('status', 'done')
            ->whereIn('program_day_id', $days->pluck('id'))->orderByDesc('date')->first();

        if (! $lastDone) {
            return $days->first();
        }

        $index = $days->search(fn (ProgramDay $d) => $d->id === $lastDone->program_day_id);

        return $days[($index + 1) % $days->count()];
    }

    /** @return list<array> */
    private function expire(User $user, Carbon $day, \Closure $paused): array
    {
        $notes = [];

        foreach ($user->quests()->where('date', $day->toDateString())->where('status', 'pending')->orderBy('id')->get() as $quest) {
            $quest->update(['status' => 'failed']);

            if ($quest->type === 'training') {
                $notes[] = SystemMessage::questFailed($quest);
                $next = $day->copy()->addDay();
                if (! $paused($next)) {
                    $user->quests()->firstOrCreate(
                        ['date' => $next->toDateString(), 'type' => 'penalty'],
                        ['title' => 'Pénalité', 'objectives' => $this->penalties->for($user->level), 'status' => 'pending'],
                    );
                }
            } elseif ($quest->type === 'penalty') {
                $notes[] = SystemMessage::penaltyFailed();
            }
        }

        return $notes;
    }
}
