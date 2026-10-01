<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Historique')]
class History extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.history', [
            'workouts' => auth()->user()->workouts()->with('sets.exercise')->latest('finished_at')->paginate(20),
        ]);
    }
}
