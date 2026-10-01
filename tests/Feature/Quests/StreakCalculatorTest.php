<?php

use App\Game\StreakCalculator;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->streaks = app(StreakCalculator::class);
});

function quest(User $user, string $date, string $type, string $status): void
{
    $user->quests()->create(['date' => $date, 'type' => $type, 'title' => $type, 'objectives' => [], 'status' => $status]);
}

it('compte les jours consécutifs accomplis, repos compris', function () {
    quest($this->user, '2026-10-05', 'training', 'done');
    quest($this->user, '2026-10-06', 'rest', 'done');
    quest($this->user, '2026-10-07', 'training', 'done');

    expect($this->streaks->compute($this->user, Carbon::parse('2026-10-07')))->toBe(3);
});

it('ne casse pas la série pour une quête du jour encore en cours', function () {
    quest($this->user, '2026-10-05', 'rest', 'done');
    quest($this->user, '2026-10-06', 'training', 'pending');

    expect($this->streaks->compute($this->user, Carbon::parse('2026-10-06')))->toBe(1);
});

it('sauve la série si la pénalité du lendemain est réussie ou encore en cours, la casse sinon', function () {
    quest($this->user, '2026-10-05', 'rest', 'done');
    quest($this->user, '2026-10-06', 'training', 'failed');
    quest($this->user, '2026-10-07', 'rest', 'done');
    quest($this->user, '2026-10-07', 'penalty', 'done');

    expect($this->streaks->compute($this->user, Carbon::parse('2026-10-07')))->toBe(2);

    $this->user->quests()->where('type', 'penalty')->update(['status' => 'pending']);
    expect($this->streaks->compute($this->user, Carbon::parse('2026-10-07')))->toBe(2);

    $this->user->quests()->where('type', 'penalty')->update(['status' => 'failed']);
    expect($this->streaks->compute($this->user, Carbon::parse('2026-10-07')))->toBe(0);
});

it('ignore les jours sans quête (pause) et les quêtes annulées', function () {
    quest($this->user, '2026-10-05', 'training', 'done');
    quest($this->user, '2026-10-08', 'training', 'cancelled');
    quest($this->user, '2026-10-09', 'rest', 'done');

    expect($this->streaks->compute($this->user, Carbon::parse('2026-10-09')))->toBe(2);
});

it('mémorise la série et la meilleure série', function () {
    quest($this->user, '2026-10-05', 'rest', 'done');
    quest($this->user, '2026-10-06', 'rest', 'done');
    $this->streaks->refresh($this->user, Carbon::parse('2026-10-06'));
    quest($this->user, '2026-10-07', 'training', 'failed');
    quest($this->user, '2026-10-08', 'penalty', 'failed');
    quest($this->user, '2026-10-08', 'rest', 'done');
    $this->streaks->refresh($this->user, Carbon::parse('2026-10-08'));

    expect($this->user->fresh()->streak_days)->toBe(0)->and($this->user->fresh()->best_streak)->toBe(2);
});
