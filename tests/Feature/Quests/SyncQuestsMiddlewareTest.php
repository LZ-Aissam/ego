<?php

use App\Models\Quest;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->create();
    app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'fitness', 'weekdays' => [1, 3, 5], 'equipment' => 'bodyweight'])
        ->forceFill(['created_at' => Carbon::parse('2026-10-05 08:00')])->save();
    $this->user->forceFill(['calibrated_at' => now()])->save();
    $this->actingAs($this->user);
});

it('assigne la quête du jour à l\'ouverture d\'une page, une seule fois', function () {
    $this->travelTo(Carbon::parse('2026-10-05 10:00'));

    $this->get('/')->assertOk();
    $this->get('/history')->assertOk();

    expect(Quest::count())->toBe(1);
});

it('affiche l\'échec de la veille sur la page suivante', function () {
    $this->travelTo(Carbon::parse('2026-10-05 10:00'));
    $this->get('/');
    $this->travelTo(Carbon::parse('2026-10-06 08:00'));

    $this->get('/')->assertSee('QUÊTE ÉCHOUÉE');
    $this->get('/')->assertDontSee('QUÊTE ÉCHOUÉE');
});

it('ne touche pas aux requêtes JSON', function () {
    $this->travelTo(Carbon::parse('2026-10-05 10:00'));

    $this->getJson('/')->assertOk();

    expect(Quest::count())->toBe(0);
});
