<?php

namespace App\Livewire;

use App\Game\HomeGame;
use App\Support\SystemNotifier;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Jeu maison : son maximum du jour sur les défis choisis, quand on veut. */
#[Title('Jeu maison')]
class HomeGamesPage extends Component
{
    /** Valeur saisie par défi, en attente de validation. */
    public array $values = [];

    public function toggle(string $game): void
    {
        if (! isset(HomeGame::CATALOG[$game])) {
            return;
        }
        $user = auth()->user();
        $games = $user->home_games ?? [];
        $games = in_array($game, $games, true) ? array_values(array_diff($games, [$game])) : [...$games, $game];
        $user->forceFill(['home_games' => $games])->save();
    }

    public function play(string $game, HomeGame $home, SystemNotifier $notifier): void
    {
        $user = auth()->user();
        if (! in_array($game, $user->home_games ?? [], true)) {
            return;
        }
        $this->validate(["values.$game" => ['required', 'integer', 'between:1,1000']], ["values.$game.*" => 'Entre un nombre entre 1 et 1000.']);

        foreach ($home->record($user, $game, (int) $this->values[$game]) as $note) {
            $notifier->push($user, $note);
        }
        unset($this->values[$game]);
    }

    public function render(HomeGame $home)
    {
        $user = auth()->user();

        return view('livewire.home-games-page', [
            'board' => $home->board($user),
            'chosen' => $user->home_games ?? [],
            // Tractions : un vrai max demande des tirages reposés, au moins 4 h après la séance.
            'freshWorkout' => $user->workouts()->where('finished_at', '>=', now()->subHours(4))->exists(),
        ]);
    }
}
