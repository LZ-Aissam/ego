<?php

use App\Models\Quest;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-05 10:00'));          // lundi
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function withProgram(User $user): void
{
    app(ProgramBuilder::class)->build($user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    $user->forceFill(['calibrated_at' => now()])->save();
}

it('invite à forger un programme quand il n\'y en a pas', function () {
    $this->get('/')->assertSee('AUCUN PROGRAMME')->assertSee(route('program'));
});

it('affiche la quête d\'entraînement du jour avec ses objectifs et sa récompense', function () {
    withProgram($this->user);

    $quest = null;
    $response = $this->get('/');
    $quest = Quest::sole();

    $response->assertSee('QUÊTE DU JOUR')
        ->assertSee("TON EGO t'assigne", false)
        ->assertSee('Full body A')
        ->assertSee($quest->objectives[0]['name'])
        ->assertSee('0 / '.$quest->objectives[0]['sets'], false)
        ->assertSee('+200 XP')
        ->assertSee(route('workout.quest', $quest));
});

it('affiche un jour de repos et la pénalité', function () {
    withProgram($this->user);
    $this->get('/');
    $this->travelTo(Carbon::parse('2026-10-06 10:00'));          // mardi : repos + pénalité

    $this->get('/')->assertSee('Repos')->assertSee('PÉNALITÉ')->assertSee('Pompes');
});

it('ouvre la séance de quête préremplie, réservée à son propriétaire et au jour même', function () {
    withProgram($this->user);
    $this->get('/');
    $quest = Quest::sole();

    $this->get(route('workout.quest', $quest))->assertOk()
        ->assertSee('"plan":', false)
        ->assertSee($quest->objectives[0]['name']);

    $other = User::factory()->create();
    $this->actingAs($other)->get(route('workout.quest', $quest))->assertForbidden();

    $quest->update(['status' => 'done']);
    $this->actingAs($this->user)->get(route('workout.quest', $quest))->assertRedirect(route('home'));
});
