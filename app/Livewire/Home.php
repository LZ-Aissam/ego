<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Accueil')]
class Home extends Component
{
    public function render(\App\Game\PlayerStats $stats)
    {
        $user = auth()->user();

        return view('livewire.home', [
            'user' => $user,
            'player' => \App\Game\Leveling::progress((int) $user->xp_total),
            'stats' => $stats->for($user),
            'lastWorkout' => $user->workouts()->with('sets.exercise')->latest('finished_at')->first(),
            'recentRecords' => $user->personalRecords()->with('exercise')->latest('achieved_at')->limit(3)->get(),
        ]);
    }
}
