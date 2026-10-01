<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Accueil')]
class Home extends Component
{
    public function render(\App\Game\PlayerStats $stats, \App\Game\QuestEvaluator $evaluator, \App\Game\QuestScheduler $scheduler, \App\Crew\Leaderboard $board, \App\Crew\ChallengeBoard $challengeBoard)
    {
        $user = auth()->user();
        $todayQuests = $user->quests()->where('date', today()->toDateString())->get()->keyBy('type');
        $todaySets = \App\Models\WorkoutSet::query()->with('exercise')
            ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
            ->where('workouts.user_id', $user->id)->whereDate('workouts.finished_at', today())
            ->get(['workout_sets.*']);
        $questProgress = fn ($quest) => $quest ? $evaluator->progress($quest->objectives, $todaySets) : [];

        return view('livewire.home', [
            'user' => $user,
            'player' => \App\Game\Leveling::progress((int) $user->xp_total),
            'stats' => $stats->for($user),
            'quest' => $todayQuests['training'] ?? $todayQuests['calibration'] ?? $todayQuests['rest'] ?? null,
            'penalty' => $todayQuests['penalty'] ?? null,
            'questProgress' => $questProgress($todayQuests['training'] ?? $todayQuests['calibration'] ?? null),
            'penaltyProgress' => $questProgress($todayQuests['penalty'] ?? null),
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
            'recentRecords' => $user->personalRecords()->with('exercise')->latest('achieved_at')->limit(3)->get(),
        ]);
    }
}
