<?php

namespace App\Livewire\Crew;

use App\Crew\CrewService;
use App\Models\Crew;
use App\Models\User;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Groupe')]
class GroupPage extends Component
{
    #[Locked]
    public int $crewId;

    public string $name = '';

    public string $color = 'ice';

    public bool $confirmingDelete = false;

    public function mount(Crew $crew): void
    {
        abort_unless($crew->hasMember(auth()->user()), 404);
        $this->crewId = $crew->id;
        $this->name = $crew->name;
        $this->color = $crew->color;
    }

    private function crew(): Crew
    {
        $crew = Crew::find($this->crewId);
        abort_unless($crew && $crew->hasMember(auth()->user()), 404);

        return $crew;
    }

    public function save(CrewService $crews): void
    {
        $crews->update(auth()->user(), $this->crew(), $this->name, $this->color);
    }

    public function regenerate(CrewService $crews): void
    {
        $crews->regenerateCode(auth()->user(), $this->crew());
    }

    public function kick(int $userId, CrewService $crews): void
    {
        $crew = $this->crew();
        abort_unless($crew->isOwnedBy(auth()->user()), 403);     // droits d'abord : un membre ne sonde pas les identifiants
        $member = User::find($userId);
        abort_unless($member !== null, 404);
        $crews->kick(auth()->user(), $crew, $member);
    }

    public function askDelete(): void
    {
        abort_unless($this->crew()->isOwnedBy(auth()->user()), 403);
        $this->confirmingDelete = true;
    }

    public function delete(CrewService $crews)
    {
        $crews->delete(auth()->user(), $this->crew());

        return $this->redirectRoute('community', ['tab' => 'groupes']);
    }

    public function leave(CrewService $crews)
    {
        $crews->leave(auth()->user(), $this->crew());

        return $this->redirectRoute('community', ['tab' => 'groupes']);
    }

    public function render()
    {
        $crew = $this->crew();

        return view('livewire.crew.group-page', [
            'crew' => $crew,
            'members' => $crew->members()->orderBy('crew_user.joined_at')->orderBy('users.id')->get(),
            'isOwner' => $crew->isOwnedBy(auth()->user()),
            'inviteUrl' => route('crew.join', $crew->code),
        ]);
    }
}
