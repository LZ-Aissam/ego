<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Accueil')]
class Home extends Component
{
    public function render()
    {
        return view('livewire.home', ['user' => auth()->user()]);
    }
}
