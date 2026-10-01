<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Accueil')]
class Home extends Component
{
    /** « Plus tard » : la quête de calibrage du jour devient la séance du programme ; TON EGO redemande dans une semaine. */
    public function postponeCalibration(): void
    {
        $user = auth()->user();
        $quest = $user->quests()->where('date', today()->toDateString())->where('type', 'calibration')->where('status', 'pending')->first();
        $programDay = $quest ? \App\Models\ProgramDay::find($quest->program_day_id) : null;
        if (! $programDay) {
            return;
        }

        $quest->update(['type' => 'training', 'title' => $programDay->name, 'objectives' => $programDay->objectives()]);
        $user->forceFill(['calibration_snoozed_until' => today()->addDays(6)])->save();
    }

    public function render(\App\Game\PlayerStats $stats, \App\Game\QuestEvaluator $evaluator, \App\Game\QuestScheduler $scheduler, \App\Crew\Leaderboard $board)
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
            'weekRank' => $board->rank('xp', 'week')->first(fn ($r) => $r['user']->id === $user->id && $r['value'] > 0)['rank'] ?? null,
            'reward' => $scheduler->rewardFor((int) $user->streak_days),
            'loads' => $user->workingLoads()->pluck('target_kg', 'exercise_id'),
            'perDumbbell' => \App\Models\Exercise::all()->filter->isPerDumbbell()->modelKeys(),
            'lastWorkout' => $user->workouts()->with('sets.exercise')->latest('finished_at')->first(),
            'recentRecords' => $user->personalRecords()->with('exercise')->latest('achieved_at')->limit(3)->get(),
        ]);
    }
}
