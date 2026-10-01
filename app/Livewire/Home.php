<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Accueil')]
class Home extends Component
{
    public function render()
    {
        $user = auth()->user();

        return view('livewire.home', [
            'user' => $user,
            'lastWorkout' => $user->workouts()->with('sets.exercise')->latest('finished_at')->first(),
            'recentRecords' => $user->personalRecords()->with('exercise')->latest('achieved_at')->limit(3)->get(),
        ]);
    }
}
