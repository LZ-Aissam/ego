<?php

use App\Livewire\Home;
use App\Livewire\Records;
use App\Models\User;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-11-14 10:00'));
    $this->user = User::factory()->withMorphology(weightKg: 70.0)->create();
    $this->actingAs($this->user);
});

it('demande la pesée de la semaine sur l\'accueil et met le poids du profil à jour', function () {
    Livewire::test(Home::class)
        ->assertSee('PESÉE DE LA SEMAINE')
        ->set('weighKg', '71,5')->call('weighIn')
        ->assertHasNoErrors()
        ->assertDontSee('PESÉE DE LA SEMAINE');

    expect($this->user->fresh()->weight_kg)->toBe(71.5)
        ->and($this->user->bodyWeights()->first()->only(['kg']))->toBe(['kg' => 71.5]);
});

it('une seule pesée par jour : la dernière remplace la précédente', function () {
    Livewire::test(Home::class)->set('weighKg', '71')->call('weighIn');
    Livewire::test(Home::class)->set('weighKg', '70.4')->call('weighIn');

    expect($this->user->bodyWeights()->count())->toBe(1)
        ->and($this->user->fresh()->weight_kg)->toBe(70.4);
});

it('redemande la pesée au bout de 7 jours', function () {
    $this->user->bodyWeights()->create(['date' => '2026-11-10', 'kg' => 70]);
    Livewire::test(Home::class)->assertDontSee('PESÉE DE LA SEMAINE');

    $this->travelTo(Carbon::parse('2026-11-17 10:00'));
    Livewire::test(Home::class)->assertSee('PESÉE DE LA SEMAINE');
});

it('refuse un poids impossible', function () {
    Livewire::test(Home::class)->set('weighKg', '12')->call('weighIn')->assertHasErrors('weighKg');
});

it('montre la force relative, les rangs et l\'évolution du poids sur la page des records', function () {
    $this->user->bodyWeights()->create(['date' => '2026-10-20', 'kg' => 69]);
    $this->user->bodyWeights()->create(['date' => '2026-11-13', 'kg' => 70]);

    Livewire::test(Records::class)
        ->assertSee('FORCE RELATIVE')
        ->assertSee('Développé couché')
        ->assertSee('Tractions lestées')
        ->assertSee('POIDS DU CORPS')
        ->assertSee('+1 kg');
});
