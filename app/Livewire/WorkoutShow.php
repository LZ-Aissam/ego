<?php

namespace App\Livewire;

use App\Models\Workout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Séance')]
class WorkoutShow extends Component
{
    public Workout $workout;

    public function render()
    {
        $this->workout->load('sets.exercise', 'user');

        return view('livewire.workout-show', [
            'groups' => $this->workout->sets->groupBy('exercise_id'),
        ]);
    }
}
