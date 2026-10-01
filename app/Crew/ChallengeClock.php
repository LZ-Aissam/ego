<?php

namespace App\Crew;

use App\Models\Challenge;
use App\Models\User;
use App\Support\SystemMessage;
use App\Support\SystemNotifier;
use Illuminate\Support\Facades\DB;

/** Fait avancer les défis du joueur (début, clôture) et prévient chaque participant une seule fois. */
final class ChallengeClock
{
    public function __construct(private ChallengeBoard $board, private SystemNotifier $notifier) {}

    public function tick(User $user): void
    {
        // Tous les défis de ses groupes, même ceux qu'il n'a pas rejoints : un défi avance dès qu'un membre ouvre l'app.
        Challenge::whereIn('crew_id', $user->crews()->pluck('crews.id'))->whereIn('status', ['upcoming', 'active'])
            ->where('starts_at', '<=', now())->pluck('id')
            ->each(fn (int $id) => $this->advance($id));
    }

    private function advance(int $id): void
    {
        DB::transaction(function () use ($id) {
            $challenge = Challenge::with('crew', 'participants')->lockForUpdate()->find($id);
            if (! $challenge || ! $challenge->isOpen()) {
                return;
            }

            if ($challenge->status === 'upcoming' && $challenge->starts_at->lte(now())) {
                $challenge->status = 'active';
                if (! $challenge->started_notified_at) {
                    $challenge->participants->each(fn (User $u) => $this->notifier->push($u, SystemMessage::challengeStarted($challenge)));
                    $challenge->started_notified_at = now();
                }
            }

            if ($challenge->status === 'active' && $challenge->ends_at->lte(now())) {
                $standings = $this->board->compute($challenge)->values();
                $challenge->final_standings = $standings->all();
                $challenge->status = 'finished';
                if (! $challenge->finished_notified_at) {
                    $winners = $standings->where('rank', 1)->pluck('first_name')->join(', ', ' et ') ?: 'personne';
                    foreach ($challenge->participants as $u) {
                        $rank = $standings->firstWhere('user_id', $u->id)['rank'] ?? null;
                        $this->notifier->push($u, SystemMessage::challengeFinished($challenge, $rank, $challenge->participants->count(), $winners));
                    }
                    $challenge->finished_notified_at = now();
                }
            }

            $challenge->save();
        });
    }
}
