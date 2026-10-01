<?php

use App\Livewire\Profile;
use App\Models\Quest;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-05 10:00'));
    $this->user = User::factory()->create();
    app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'fitness', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    $this->actingAs($this->user);
    $this->get('/');
});

it('suspend les quêtes et annule celle du jour', function () {
    Livewire::test(Profile::class)->set('pauseUntil', '2026-10-12')->call('pause')->assertRedirect(route('profile'));

    $user = $this->user->fresh();
    expect($user->paused_at->toDateString())->toBe('2026-10-05')
        ->and($user->paused_until->toDateString())->toBe('2026-10-12')
        ->and(Quest::where('date', '2026-10-05')->value('status'))->toBe('cancelled')
        ->and(SystemNotification::where('type', 'pause_on')->exists())->toBeTrue();

    $this->get('/profile')->assertSee('QUÊTES SUSPENDUES');
});

it('refuse une date de fin passée', function () {
    Livewire::test(Profile::class)->set('pauseUntil', '2026-10-01')->call('pause')->assertHasErrors(['pauseUntil']);
});

it('reprend les quêtes et réassigne celle du jour', function () {
    Livewire::test(Profile::class)->call('pause');
    Livewire::test(Profile::class)->call('resume')->assertRedirect(route('profile'));

    expect($this->user->fresh()->paused_at)->toBeNull();

    $this->get('/');
    expect(Quest::where('date', '2026-10-05')->where('type', 'training')->value('status'))->toBe('pending');
});
