<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Accueil')]
class Home extends Component
{
    public function render(\App\Game\PlayerStats $stats, \App\Game\QuestEvaluator $evaluator, \App\Game\QuestScheduler $scheduler)
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
            'reward' => $scheduler->rewardFor((int) $user->streak_days),
            'loads' => $user->workingLoads()->pluck('target_kg', 'exercise_id'),
            'lastWorkout' => $user->workouts()->with('sets.exercise')->latest('finished_at')->first(),
            'recentRecords' => $user->personalRecords()->with('exercise')->latest('achieved_at')->limit(3)->get(),
        ]);
    }
}
