<?php

use App\Crew\CrewService;
use App\Livewire\Auth\Login;
use App\Livewire\Crew\GroupPage;
use App\Models\Crew;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->create(['first_name' => 'Aïssam']);
    $this->friend = User::factory()->create(['first_name' => 'Renée']);
    $this->crew = app(CrewService::class)->create($this->owner, 'Les Ombres', 'violet');
    app(CrewService::class)->join($this->friend, $this->crew->code);
});

it('montre le groupe, son code, son lien d\'invitation et ses membres à un membre', function () {
    $this->actingAs($this->friend)->get(route('crew.group', $this->crew))->assertOk()
        ->assertSee('Les Ombres')->assertSee($this->crew->code)
        ->assertSee(route('crew.join', $this->crew->code))
        ->assertSee('Aïssam')->assertSee('Renée')->assertSee('Créateur')
        ->assertDontSee('Supprimer le groupe');
});

it('répond 404 à un non-membre', function () {
    $this->actingAs(User::factory()->create())->get(route('crew.group', $this->crew))->assertNotFound();
});

it('montre les réglages au créateur et applique ses actions', function () {
    $this->actingAs($this->owner);
    $old = $this->crew->code;

    Livewire::test(GroupPage::class, ['crew' => $this->crew])
        ->assertSee('Supprimer le groupe')
        ->set('name', 'Les Lames')->set('color', 'gold')->call('save')
        ->call('regenerate')
        ->call('kick', $this->friend->id);

    $this->crew->refresh();
    expect($this->crew->name)->toBe('Les Lames')->and($this->crew->color)->toBe('gold')
        ->and($this->crew->code)->not->toBe($old)
        ->and($this->crew->hasMember($this->friend))->toBeFalse();
});

it('demande une confirmation intégrée avant de supprimer', function () {
    $this->actingAs($this->owner);

    Livewire::test(GroupPage::class, ['crew' => $this->crew])
        ->call('askDelete')->assertSee('Supprimer définitivement')
        ->call('delete')->assertRedirect(route('community', ['tab' => 'groupes']));

    expect(Crew::find($this->crew->id))->toBeNull();
});

it('refuse les actions de créateur forgées par un membre', function (string $method, array $args) {
    $this->actingAs($this->friend);

    Livewire::test(GroupPage::class, ['crew' => $this->crew])->call($method, ...$args)->assertForbidden();
})->with([['save', []], ['regenerate', []], ['kick', [1]], ['delete', []]]);

it('verrouille l\'identifiant du groupe affiché', function () {
    $this->actingAs($this->friend);

    Livewire::test(GroupPage::class, ['crew' => $this->crew])->set('crewId', 999);
})->throws(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

it('laisse un membre quitter le groupe', function () {
    $this->actingAs($this->friend);

    Livewire::test(GroupPage::class, ['crew' => $this->crew])->call('leave')->assertRedirect(route('community', ['tab' => 'groupes']));

    expect($this->crew->hasMember($this->friend))->toBeFalse();
});

it('rejoint par le lien d\'invitation', function () {
    $newcomer = User::factory()->create();

    $this->actingAs($newcomer)->get(route('crew.join', strtolower($this->crew->code)))->assertRedirect(route('crew.group', $this->crew));

    expect($this->crew->hasMember($newcomer))->toBeTrue();
});

it('signale un lien d\'invitation périmé', function () {
    $this->actingAs(User::factory()->create())->get(route('crew.join', 'ZZZZZZ'))
        ->assertRedirect(route('community', ['tab' => 'groupes']))
        ->assertSessionHas('crew_error', 'Code inconnu.');
});

it('ramène au lien d\'invitation après la connexion', function () {
    $newcomer = User::factory()->create(['first_name' => 'Nina', 'birth_date' => '1995-04-12']);

    $this->get(route('crew.join', $this->crew->code))->assertRedirect(route('login'));

    Livewire::test(Login::class)->set('firstName', 'Nina')->set('birthDate', '1995-04-12')->call('login')
        ->assertRedirect(route('crew.join', $this->crew->code));
});

it('ramène au lien d\'invitation après une inscription', function () {
    $this->get(route('crew.join', $this->crew->code))->assertRedirect(route('login'));

    Livewire::test(\App\Livewire\Auth\Register::class)->set('firstName', 'Nina')->set('birthDate', '1995-04-12')->call('register')
        ->assertRedirect(route('crew.join', $this->crew->code));
});
