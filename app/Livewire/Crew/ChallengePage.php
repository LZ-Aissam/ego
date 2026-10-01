<?php

namespace App\Livewire\Crew;

use App\Crew\ChallengeBoard;
use App\Crew\ChallengeService;
use App\Models\Challenge;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Défi')]
class ChallengePage extends Component
{
    #[Locked]
    public int $challengeId;

    public function mount(Challenge $challenge): void
    {
        abort_unless($challenge->crew->hasMember(auth()->user()), 404);
        $this->challengeId = $challenge->id;
    }

    private function challenge(): Challenge
    {
        $challenge = Challenge::with('crew')->find($this->challengeId);
        abort_unless($challenge && $challenge->crew->hasMember(auth()->user()), 404);

        return $challenge;
    }

    public function join(ChallengeService $service): void
    {
        $service->join(auth()->user(), $this->challenge());
    }

    public function leave(ChallengeService $service): void
    {
        $service->leave(auth()->user(), $this->challenge());
    }

    public function cancel(ChallengeService $service)
    {
        $service->cancel(auth()->user(), $this->challenge());

        return $this->redirectRoute('community', ['tab' => 'defis']);
    }

    public function render(ChallengeBoard $board)
    {
        $challenge = $this->challenge();

        return view('livewire.crew.challenge-page', [
            'challenge' => $challenge,
            'standings' => $board->standings($challenge),
            'participating' => $challenge->hasParticipant(auth()->user()),
            'isCreator' => $challenge->creator_id === auth()->id(),
        ]);
    }
}
