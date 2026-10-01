<?php

namespace App\Livewire\Crew;

use App\Models\User;
use Livewire\Component;

class MemberPage extends Component
{
    public function mount(User $member): void {}

    public function render()
    {
        return '<div></div>';
    }
}
