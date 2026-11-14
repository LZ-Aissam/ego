<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Accueil')]
class Home extends Component
{
    /** Pesée de la semaine (virgule acceptée). */
    public $weighKg = '';

    /** Une pesée est demandée quand la dernière date de plus de 7 jours. */
    public const WEIGH_EVERY_DAYS = 7;

    public function weighIn(): void
    {
        $this->weighKg = str_replace(',', '.', (string) $this->weighKg);
        $this->validate(['weighKg' => ['required', 'numeric', 'between:30,300']], ['weighKg.*' => 'Indique ton poids en kg (30 à 300).']);

        $user = auth()->user();
        $kg = round((float) $this->weighKg, 1);
        $user->bodyWeights()->updateOrCreate(['date' => \App\Game\GameDay::today()->toDateString()], ['kg' => $kg]);
        // Le lest prescrit et la force relative partent du poids du profil.
        $user->forceFill(['weight_kg' => $kg])->save();
        $this->weighKg = '';
    }

    public function render(\App\Game\PlayerStats $stats, \App\Game\QuestEvaluator $evaluator, \App\Game\QuestScheduler $scheduler, \App\Crew\Leaderboard $board, \App\Crew\ChallengeBoard $challengeBoard)
    {
        $user = auth()->user();
        $today = \App\Game\GameDay::today();
        $todayQuests = $user->quests()->where('date', $today->toDateString())->get()->keyBy('type');
        $todaySets = \App\Game\GameDay::constrain(\App\Models\WorkoutSet::query()->with('exercise')
            ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
            ->where('workouts.user_id', $user->id), $today)
            ->get(['workout_sets.*']);
        $questProgress = fn ($quest) => $quest ? $evaluator->progress($quest->objectives, $todaySets) : [];
        $lastWeighIn = $user->bodyWeights()->max('date');

        return view('livewire.home', [
            'user' => $user,
            'needsWeighIn' => $user->weight_kg !== null && ($lastWeighIn === null
                || \Illuminate\Support\Carbon::parse($lastWeighIn)->lte($today->copy()->subDays(self::WEIGH_EVERY_DAYS))),
            'player' => \App\Game\Leveling::progress((int) $user->xp_total),
            'stats' => $stats->for($user),
            'quest' => $todayQuests['training'] ?? $todayQuests['calibration'] ?? $todayQuests['rest'] ?? null,
            'questProgress' => $questProgress($todayQuests['training'] ?? $todayQuests['calibration'] ?? null),
            'needsCalibration' => $user->calibrated_at === null && $user->active_program_id !== null
                && ($todayQuests['calibration'] ?? null)?->status !== 'pending',
            'myChallenges' => $user->challenges()->with('crew')->where('status', 'active')->get()
                ->map(fn ($c) => ['challenge' => $c, 'rank' => $challengeBoard->compute($c)->firstWhere('user_id', $user->id)['rank'] ?? null]),
            'weekRank' => $board->rank('xp', 'week')->first(fn ($r) => $r['user']->id === $user->id && $r['value'] > 0)['rank'] ?? null,
            'reward' => $scheduler->rewardFor((int) $user->streak_days),
            'loads' => $user->workingLoads()->pluck('target_kg', 'exercise_id'),
            'objectiveLoads' => collect(($todayQuests['training'] ?? null)?->objectives ?? [])->mapWithKeys(fn ($o, $i) => [$i => ($o['intent'] ?? null) === null
                ? (isset($o['exercise_id']) ? $user->workingLoads()->where('exercise_id', $o['exercise_id'])->value('target_kg') : null)
                : app(\App\Game\LoadPrescriber::class)->prescriptionFor($user, \App\Models\Exercise::find($o['exercise_id']), $o['intent'], $o['reps_max'])]),
            'perDumbbell' => \App\Models\Exercise::all()->filter->isPerDumbbell()->modelKeys(),
            'lastWorkout' => $user->workouts()->with('sets.exercise')->latest('finished_at')->first(),
            'homeBoard' => app(\App\Game\HomeGame::class)->board($user),
            'recentRecords' => $user->personalRecords()->with('exercise')->latest('achieved_at')->limit(3)->get(),
        ]);
    }
}
