<?php

namespace App\Console\Commands;

use App\Game\GameEvents;
use App\Models\User;
use Illuminate\Console\Command;

class BackfillPlayerXp extends Command
{
    protected $signature = 'ego:backfill-xp';

    protected $description = "Attribue rétroactivement l'XP, les niveaux et les titres des séances déjà enregistrées (idempotent).";

    public function handle(GameEvents $game): int
    {
        User::query()->orderBy('id')->each(fn (User $user) => $game->backfill($user));
        $this->info('Progression recalculée.');

        return self::SUCCESS;
    }
}
