<?php

namespace App\Livewire\Crew;

use App\Game\PlayerStats;
use App\Models\User;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Profil public')]
class MemberPage extends Component
{
    #[Locked]
    public int $memberId;

    public function mount(User $member): void
    {
        $this->memberId = $member->id;
    }

    public function render(PlayerStats $stats)
    {
        $member = User::findOrFail($this->memberId);
        $myCrewIds = auth()->user()->crews()->pluck('crews.id');

        return view('livewire.crew.member-page', [
            'member' => $member,
            'player' => $member->player(),
            'stats' => $stats->for($member),
            'records' => $member->personalRecords()->with('exercise')->get()->sortBy(fn ($r) => $r->exercise->name)->values(),
            'maxes' => $member->exerciseMaxes()->with('exercise')->orderByDesc('e1rm_kg')->get(),
            'program' => $member->activeProgram()->with('days.exercises.exercise')->first(),
            'commonCrews' => $member->crews()->whereIn('crews.id', $myCrewIds)->orderBy('name')->get(),
        ]);
    }
}
