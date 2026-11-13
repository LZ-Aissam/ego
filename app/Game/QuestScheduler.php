<?php

namespace App\Game;

use App\Models\Program;
use App\Models\ProgramDay;
use App\Models\Quest;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use App\Support\SystemMessage;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class QuestScheduler
{
    public const QUEST_XP = 200;
    public const MAX_CATCH_UP_DAYS = 60;

    public function __construct(
        private Titles $titles,
        private StreakCalculator $streaks,
        private XpLedger $ledger,
        private QuestEvaluator $evaluator,
        private CalibrationPlan $calibrationPlan,
    ) {}

    public function rewardFor(int $streak): int
    {
        return (int) round(self::QUEST_XP * (1 + min($streak, 5) * 0.1));
    }

    /**
     * Crée les quêtes dues, constate les échecs, gère la pause. Idempotent.
     * $today est un jour de jeu (GameDay::today()), jamais l'instant brut : la journée finit à 4 h du matin.
     *
     * @return list<array>
     */
    public function sync(User $user, CarbonInterface $today): array
    {
        $today = Carbon::parse($today->toDateString());

        return DB::transaction(function () use ($user, $today) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $user->refresh();

            $notes = $this->process($user, $today);

            $this->streaks->refresh($user, $today);
            foreach ($this->titles->check($user) as $key) {
                $notes[] = SystemMessage::title($key);
            }

            return $notes;
        });
    }

    /**
     * Fin de pause manuelle : les jours passés en pause reçoivent une quête « annulée » (neutre pour la série),
     * pour que le rattrapage suivant ne les traite pas comme des jours ratés.
     */
    public function resume(User $user, CarbonInterface $today): void
    {
        $today = Carbon::parse($today->toDateString());

        DB::transaction(function () use ($user, $today) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $user->refresh();

            if ($user->paused_at) {
                for ($day = Carbon::parse($user->paused_at->toDateString()); $day->lt($today); $day = $day->copy()->addDay()) {
                    if (! $user->quests()->where('date', $day->toDateString())->whereIn('type', ['training', 'calibration', 'rest'])->exists()) {
                        $user->quests()->create(['date' => $day->toDateString(), 'type' => 'rest', 'title' => 'Pause', 'objectives' => [], 'status' => 'cancelled']);
                    }
                }
            }

            $user->quests()->where('date', $today->toDateString())->whereIn('type', ['training', 'calibration', 'rest'])->where('status', 'cancelled')->delete();
            $user->forceFill(['paused_at' => null, 'paused_until' => null])->save();
        });
    }

    /**
     * Programme refait ou copié : les séances du jour et à venir encore vierges (et les repos) sont retirées ;
     * la prochaine synchronisation (chaque page) les recrée d'après le nouveau programme. Une séance commencée ou finie est gardée.
     */
    public function programChanged(User $user, CarbonInterface $today): void
    {
        $today = Carbon::parse($today->toDateString());

        DB::transaction(function () use ($user, $today) {
            User::whereKey($user->id)->lockForUpdate()->first();

            $quests = $user->quests()->where('date', '>=', $today->toDateString())->whereIn('type', ['training', 'calibration', 'rest'])->get();
            foreach ($quests as $quest) {
                $untouched = $quest->type === 'rest'
                    ? $quest->status === 'done'
                    : $quest->status === 'pending' && array_sum($this->evaluator->progress($quest->objectives, $this->setsOn($user, Carbon::parse($quest->date->toDateString())))) === 0;
                if ($untouched) {
                    $quest->delete();
                }
            }
        });
    }

    /**
     * Changement de mode d'enchaînement : on efface les séances encore en attente et vierges (aucune série envoyée
     * ce jour-là), pour que le nouveau mode reparte proprement à la rotation après la dernière séance faite. Les
     * séances faites ou commencées sont gardées.
     */
    public function discardPendingSessions(User $user): void
    {
        DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->first();

            foreach ($user->quests()->whereIn('type', ['training', 'calibration', 'rest'])->where('status', 'pending')->get() as $quest) {
                $untouched = $quest->type === 'rest'
                    || array_sum($this->evaluator->progress($quest->objectives, $this->setsOn($user, Carbon::parse($quest->date->toDateString())))) === 0;
                if ($untouched) {
                    $quest->delete();
                }
            }
        });
    }

    /** À appeler sous le verrou du joueur (GameEvents). @return list<array> */
    public function onWorkout(User $user, Workout $workout): array
    {
        $day = GameDay::of($workout->finished_at);

        // La séance peut arriver avant toute page ouverte ce jour-là (hors ligne) : on crée d'abord les quêtes dues.
        $notes = $this->process($user, $day);

        $quests = $user->quests()->where('date', $day->toDateString())->whereIn('type', ['training', 'calibration'])
            ->whereIn('status', ['pending', 'failed'])->orderBy('id')->get();
        $sets = $this->setsOn($user, $day);

        foreach ($quests as $quest) {
            array_push($notes, ...$this->completeIfAccomplished($user, $quest, $sets));
        }

        $this->streaks->refresh($user, GameDay::today());

        return $notes;
    }

    /**
     * Correction ponctuelle au passage à la journée de jeu (fin à 4 h) : une quête ratée dont une séance s'est finie
     * entre minuit et 4 h le lendemain est réévaluée avec les nouvelles bornes, et accomplie si les séries suffisent.
     *
     * @return list<array>
     */
    public function repairFailedBeforeCutoff(User $user, CarbonInterface $today): array
    {
        $today = Carbon::parse($today->toDateString());

        return DB::transaction(function () use ($user, $today) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $user->refresh();

            $notes = [];
            $quests = $user->quests()->whereIn('type', ['training', 'calibration'])->where('status', 'failed')
                ->where('date', '>=', $today->copy()->subDays(self::MAX_CATCH_UP_DAYS)->toDateString())->orderBy('date')->get();

            foreach ($quests as $quest) {
                $day = Carbon::parse($quest->date->toDateString());
                [, $cutoff] = GameDay::bounds($day);                                   // lendemain 4 h
                $afterMidnight = $user->workouts()->where('finished_at', '>=', $day->copy()->addDay())->where('finished_at', '<', $cutoff)->exists();
                if ($afterMidnight) {
                    array_push($notes, ...$this->completeIfAccomplished($user, $quest, $this->setsOn($user, $day)));
                }
            }

            $this->streaks->refresh($user, $today);

            return $notes;
        });
    }

    /** Rattrapage jusqu'à $upTo inclus (sans verrou : l'appelant le tient). @return list<array> */
    private function process(User $user, Carbon $upTo): array
    {
        $notes = [];

        // Période de pause mémorisée AVANT une éventuelle levée automatique : les jours passés
        // en pause doivent rester sans quête pendant le rattrapage.
        [$pausedFrom, $pausedUntil] = [$user->paused_at, $user->paused_until];
        $paused = fn (Carbon $d) => $pausedFrom !== null && $d->gte($pausedFrom) && ($pausedUntil === null || $d->lte($pausedUntil));

        if ($pausedFrom && $pausedUntil && $upTo->gt($pausedUntil)) {
            $user->forceFill(['paused_at' => null, 'paused_until' => null])->save();
            $notes[] = SystemMessage::pauseOff();
        }

        $program = $user->activeProgram()->with('days')->first();
        if (! $program) {
            return $notes;
        }

        if ($program->isSequential()) {
            if (! $paused($upTo)) {
                array_push($notes, ...$this->syncSequential($user, $program, $upTo));
            }

            return $notes;
        }

        // Au-delà de la fenêtre de rattrapage, les quêtes restées en attente sont closes sans bruit.
        $floor = $upTo->copy()->subDays(self::MAX_CATCH_UP_DAYS);
        $user->quests()->where('status', 'pending')->where('date', '<', $floor->toDateString())->update(['status' => 'failed']);

        for ($day = $this->firstDayToProcess($user, $program, $upTo, $floor); $day->lte($upTo); $day = $day->copy()->addDay()) {
            if (! $paused($day)) {
                array_push($notes, ...$this->ensureQuest($user, $program, $day));
            }
            if ($day->lt($upTo)) {
                array_push($notes, ...$this->expire($user, $day));
            }
        }

        // Pas encore calibré : le test passe avant tout entraînement, même si la séance du jour existait déjà.
        if ($user->calibrated_at === null) {
            $user->quests()->where('date', $upTo->toDateString())->where('type', 'training')->where('status', 'pending')
                ->update(['type' => 'calibration', 'title' => 'Calibrage', 'objectives' => json_encode($this->calibrationPlan->objectives($user))]);
        }

        return $notes;
    }

    private function firstDayToProcess(User $user, Program $program, Carbon $upTo, Carbon $floor): Carbon
    {
        $last = $user->quests()->max('date');
        $start = $last ? Carbon::parse($last)->addDay() : Carbon::parse($program->created_at->toDateString());

        $pending = $user->quests()->where('status', 'pending')->where('date', '<', $upTo->toDateString())->min('date');
        if ($pending && Carbon::parse($pending)->lt($start)) {
            $start = Carbon::parse($pending);
        }

        return $start->lt($floor) ? $floor : $start;
    }

    /**
     * Mode séquentiel : une seule séance active (entraînement ou calibrage) à la fois. Elle suit le jour courant
     * tant qu'elle n'est pas faite, ne tombe jamais en échec, et la suivante n'apparaît qu'un autre jour une fois
     * la précédente accomplie. Un jour sans séance est neutre : ni quête de repos, ni échec.
     *
     * @return list<array>
     */
    private function syncSequential(User $user, Program $program, Carbon $today): array
    {
        $pending = $user->quests()->whereIn('type', ['training', 'calibration'])->where('status', 'pending')
            ->orderBy('date')->first();

        if ($pending) {
            // La séance active suit le jour courant, sauf si ce jour porte déjà une séance (une faite plus tôt le même jour).
            $takenToday = $user->quests()->whereIn('type', ['training', 'calibration'])
                ->where('date', $today->toDateString())->where('id', '!=', $pending->id)->exists();
            if ($pending->date->toDateString() !== $today->toDateString() && ! $takenToday) {
                $pending->update(['date' => $today->toDateString()]);
            }

            return [];
        }

        // Pas de séance active : si le jour courant porte déjà une séance (déjà faite aujourd'hui), on attend le lendemain.
        if ($user->quests()->whereIn('type', ['training', 'calibration'])->where('date', $today->toDateString())->exists()) {
            return [];
        }

        $calibrating = $user->calibrated_at === null;
        $programDay = $this->nextProgramDay($user, $program, $today);
        $quest = $user->quests()->create([
            'date' => $today->toDateString(),
            'type' => $calibrating ? 'calibration' : 'training',
            'program_day_id' => $programDay->id,
            'title' => $calibrating ? 'Calibrage' : $programDay->name,
            'objectives' => $calibrating ? $this->calibrationPlan->objectives($user) : $this->objectivesOn($user, $programDay, $today),
            'status' => 'pending',
        ]);

        // Des séances de ce jour ont pu être envoyées avant la création de la quête.
        return $this->completeIfAccomplished($user, $quest, $this->setsOn($user, $today));
    }

    /** @return list<array> */
    private function ensureQuest(User $user, Program $program, Carbon $day): array
    {
        if ($user->quests()->where('date', $day->toDateString())->whereIn('type', ['training', 'calibration', 'rest'])->exists()) {
            return [];
        }

        if (in_array($day->isoWeekday(), $user->training_weekdays ?? [], true)) {
            $programDay = $this->nextProgramDay($user, $program, $day);
            $calibrating = $user->calibrated_at === null;
            $quest = $user->quests()->create([
                'date' => $day->toDateString(),
                'type' => $calibrating ? 'calibration' : 'training',
                'program_day_id' => $programDay->id,
                'title' => $calibrating ? 'Calibrage' : $programDay->name,
                'objectives' => $calibrating ? $this->calibrationPlan->objectives($user) : $this->objectivesOn($user, $programDay, $day),
                'status' => 'pending',
            ]);

            // Des séances de ce jour ont pu être envoyées avant la création de la quête.
            return $this->completeIfAccomplished($user, $quest, $this->setsOn($user, $day));
        }

        $user->quests()->create([
            'date' => $day->toDateString(), 'type' => 'rest', 'title' => 'Repos', 'objectives' => [],
            'status' => 'done', 'completed_at' => $day->copy()->endOfDay(),
        ]);

        return [];
    }

    /** @return list<array> */
    private function completeIfAccomplished(User $user, Quest $quest, Collection $sets): array
    {
        if (! in_array($quest->status, ['pending', 'failed'], true) || ! $this->evaluator->isAccomplished($quest->objectives, $sets)) {
            return [];
        }

        // Seul le vrai test valide le calibrage (Calibration::complete enregistre calibrated_at avant cette étape).
        if ($quest->type === 'calibration' && $user->calibrated_at === null) {
            return [];
        }

        $day = Carbon::parse($quest->date->toDateString());

        $wasFailed = $quest->status === 'failed';
        $xp = $this->rewardFor($this->streaks->compute($user, $day->copy()->subDay()));
        $this->ledger->award($user, 'quest', $quest->id, $xp, at: now());
        $quest->update(['status' => 'done', 'completed_at' => now(), 'xp_awarded' => $xp]);

        if ($wasFailed) {
            // Quête réparée par une séance envoyée en retard : les quêtes suivantes encore vierges
            // passent au jour de programme suivant.
            $this->repointLaterQuests($user, $day);
        }

        return [SystemMessage::questDone($quest, $xp)];
    }

    private function repointLaterQuests(User $user, Carbon $after): void
    {
        $program = $user->activeProgram()->with('days')->first();
        if (! $program) {
            return;
        }

        $later = $user->quests()->where('type', 'training')->where('status', 'pending')
            ->where('date', '>', $after->toDateString())->orderBy('date')->get();

        foreach ($later as $quest) {
            $progress = $this->evaluator->progress($quest->objectives, $this->setsOn($user, Carbon::parse($quest->date->toDateString())));
            if (array_sum($progress) > 0) {
                continue;
            }

            $programDay = $this->nextProgramDay($user, $program, Carbon::parse($quest->date->toDateString()));
            $quest->update(['program_day_id' => $programDay->id, 'title' => $programDay->name, 'objectives' => $this->objectivesOn($user, $programDay, Carbon::parse($quest->date->toDateString()))]);
        }
    }

    /** Séance du jour, allégée la 4e semaine du cycle. */
    public function objectivesOn(User $user, ProgramDay $programDay, Carbon $day): array
    {
        $objectives = Cycle::shape($programDay->objectives(), Cycle::week($user, $day));

        return Cycle::isDeload($user, $day) ? Cycle::lighten($objectives) : $objectives;
    }

    private function setsOn(User $user, Carbon $day): Collection
    {
        $sets = WorkoutSet::query()->with('exercise')
            ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
            ->where('workouts.user_id', $user->id);

        return GameDay::constrain($sets, $day)->get(['workout_sets.*']);
    }

    private function nextProgramDay(User $user, Program $program, Carbon $date): ProgramDay
    {
        $days = $program->days;

        // Programme personnalisé, Force × Vitesse ou Force pure, une journée par jour d'entraînement : chaque journée garde son jour de la semaine.
        // En mode séquentiel, on ignore ce calage : la rotation suit la dernière séance faite.
        $weekdays = collect($user->training_weekdays ?? [])->sort()->values();
        $attached = ! $program->isSequential() && (($program->params['custom'] ?? false) || in_array($program->params['goal'] ?? null, ['power', 'pure'], true));
        if ($attached && $weekdays->count() === $days->count()) {
            $index = $weekdays->search($date->isoWeekday());
            if ($index !== false) {
                return $days[$index];
            }
        }

        $lastDone = $user->quests()->where('type', 'training')->where('status', 'done')
            ->whereIn('program_day_id', $days->pluck('id'))->orderByDesc('date')->first();

        if (! $lastDone) {
            return $days->first();
        }

        $index = $days->search(fn (ProgramDay $d) => $d->id === $lastDone->program_day_id);

        return $days[($index + 1) % $days->count()];
    }

    /** Une quête restée en attente un jour passé est ratée : la série retombe à 0 (sauf séance envoyée en retard). @return list<array> */
    private function expire(User $user, Carbon $day): array
    {
        $notes = [];

        foreach ($user->quests()->where('date', $day->toDateString())->where('status', 'pending')->orderBy('id')->get() as $quest) {
            $quest->update(['status' => 'failed']);

            if (in_array($quest->type, ['training', 'calibration'], true)) {
                $notes[] = SystemMessage::questFailed($quest);
            }
        }

        return $notes;
    }
}
