<?php

use App\Game\PlayerStats;
use App\Game\Titles;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->travelTo(Carbon::parse('2026-10-20 12:00'));
});

function q(User $user, string $date, string $type, string $status): void
{
    $user->quests()->create(['date' => $date, 'type' => $type, 'title' => $type, 'objectives' => [], 'status' => $status]);
}

it('calcule la Régularité sur 28 jours, hors annulées et hors aujourd\'hui', function () {
    q($this->user, '2026-10-15', 'training', 'done');
    q($this->user, '2026-10-16', 'rest', 'done');
    q($this->user, '2026-10-17', 'training', 'done');
    q($this->user, '2026-10-18', 'training', 'failed');
    q($this->user, '2026-10-18', 'penalty', 'done');       // les pénalités ne comptent pas
    q($this->user, '2026-10-19', 'training', 'cancelled');
    q($this->user, '2026-10-20', 'training', 'pending');
    q($this->user, '2026-09-01', 'training', 'failed');    // hors fenêtre

    expect(app(PlayerStats::class)->for($this->user)['regularite'])->toBe(75);
});

it('laisse la Régularité vide sans quête échue', function () {
    expect(app(PlayerStats::class)->for($this->user)['regularite'])->toBeNull();
});

it('débloque Assidu, Inarrêtable et Revenant', function () {
    $this->user->forceFill(['best_streak' => 7])->save();
    expect(app(Titles::class)->check($this->user))->toContain('steady')->not->toContain('unstoppable');

    $this->user->forceFill(['best_streak' => 30])->save();
    q($this->user, '2026-10-18', 'penalty', 'done');
    expect(app(Titles::class)->check($this->user))->toBe(['unstoppable', 'revenant']);
});
