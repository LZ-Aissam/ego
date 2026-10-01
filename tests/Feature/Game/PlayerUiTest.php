<?php

use App\Game\Leveling;
use App\Livewire\Profile;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->withMorphology(weightKg: 80.0)->create(['first_name' => 'Élise']);
    $this->user->xpEvents()->create(['source' => 'seed', 'source_id' => 1, 'amount' => Leveling::cumulative(12) + 40]);
    $this->user->forceFill(['xp_total' => Leveling::cumulative(12) + 40, 'equipped_title' => 'awakened'])->save();
    $this->user->titles()->create(['title_key' => 'awakened', 'unlocked_at' => now()]);
    $this->actingAs($this->user);
});

it('affiche la fenêtre STATUT sur l\'accueil', function () {
    $this->get('/')
        ->assertSee('STATUT')
        ->assertSee('Rang D')
        ->assertSee("« L'Éveillé »")
        ->assertSee('data-count-up="12"', false)
        ->assertSeeInOrder(['FORCE', 'ENDURANCE', 'PROGRESSION', 'RÉGULARITÉ'])
        ->assertSee('40 / '.number_format(Leveling::xpToNext(12), 0, ',', ' '), false);
});

it('affiche les titres dans la fiche du joueur, verrouillés compris', function () {
    $this->get('/profile')
        ->assertSee('TITRES')
        ->assertSee("L'Éveillé")
        ->assertSee('Battre 10 records')
        ->assertSee('Monarque');
});

it('équipe un titre débloqué et refuse un titre verrouillé', function () {
    $this->user->titles()->create(['title_key' => 'titan', 'unlocked_at' => now()]);

    Livewire::test(Profile::class)->call('equipTitle', 'titan');
    expect($this->user->fresh()->equipped_title)->toBe('titan');

    Livewire::test(Profile::class)->call('equipTitle', 'monarch');
    expect($this->user->fresh()->equipped_title)->toBe('titan');
});
