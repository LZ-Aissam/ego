<?php

use App\Crew\CrewService;
use App\Livewire\Crew\CrewPage;
use App\Models\Crew;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->me = User::factory()->create(['first_name' => 'Aïssam']);
    $this->me->forceFill(['streak_days' => 3])->save();
    $this->actingAs($this->me);
});

it('redirige /community vers /crew', function () {
    $this->get('/community')->assertRedirect('/crew');
});

it('affiche le classement par défaut, l\'onglet Crew actif', function () {
    $this->get('/crew')->assertOk()->assertSee('CLASSEMENT')->assertSee('Aïssam')->assertSee('aria-current="page"', false);
});

it('affiche le classement de la série avec les rangs E–S et la valeur formatée', function () {
    $rival = User::factory()->create(['first_name' => 'Renée']);
    $rival->forceFill(['streak_days' => 12])->save();

    Livewire::withQueryParams(['metric' => 'streak'])->test(CrewPage::class)
        ->assertSeeInOrder(['Renée', '12 j', 'Aïssam', '3 j']);
});

it('annonce un classement vide', function () {
    Livewire::withQueryParams(['metric' => 'force'])->test(CrewPage::class)->assertSee('Aucun chasseur classé pour l\'instant.', false);
});

it('ignore les paramètres d\'URL inconnus et les groupes dont je ne suis pas membre', function () {
    $stranger = User::factory()->create(['first_name' => 'Intrus']);
    $foreign = app(CrewService::class)->create($stranger, 'Secret', 'red');

    Livewire::withQueryParams(['metric' => 'foo', 'period' => 'bar', 'lift' => 'baz', 'groupe' => $foreign->id, 'tab' => 'qux'])
        ->test(CrewPage::class)
        ->assertSet('metric', 'xp')->assertSet('period', 'week')->assertSet('lift', 'bench')
        ->assertSet('group', null)->assertSet('tab', 'classement')
        ->assertDontSee('Secret');
});

it('filtre le classement sur un de mes groupes', function () {
    $friend = User::factory()->create(['first_name' => 'Renée']);
    User::factory()->create(['first_name' => 'Horsgroupe']);
    $crew = app(CrewService::class)->create($this->me, 'Les Ombres', 'violet');
    app(CrewService::class)->join($friend, $crew->code);

    Livewire::test(CrewPage::class)->set('group', $crew->id)
        ->assertSee('Renée')->assertDontSee('Horsgroupe');
});

it('épingle ma ligne quand je suis hors du top 50', function () {
    User::factory()->count(50)->create()->each(fn ($u) => $u->forceFill(['streak_days' => 10])->save());

    Livewire::test(CrewPage::class)->set('metric', 'streak')->assertSee('#51');
});

it('liste les membres avec recherche par prénom', function () {
    User::factory()->create(['first_name' => 'Renée']);

    Livewire::test(CrewPage::class)->set('tab', 'membres')->assertSee('Renée')
        ->set('search', 'aïs')->assertSee('Aïssam')->assertDontSee('Renée');
});

it('crée un groupe et y mène', function () {
    Livewire::test(CrewPage::class)->set('tab', 'groupes')
        ->set('newName', 'Les Ombres')->set('newColor', 'violet')->call('createCrew')
        ->assertRedirect(route('crew.group', Crew::sole()));
});

it('rejoint un groupe avec un code, ou affiche l\'erreur', function () {
    $crew = app(CrewService::class)->create(User::factory()->create(), 'Les Ombres', 'ice');

    Livewire::test(CrewPage::class)->set('tab', 'groupes')
        ->set('joinCode', 'ZZZZZZ')->call('joinCrew')->assertSee('Code inconnu.')
        ->set('joinCode', strtolower($crew->code))->call('joinCrew')->assertRedirect(route('crew.group', $crew));
});

it('liste mes groupes avec le nombre de membres', function () {
    app(CrewService::class)->create($this->me, 'Les Ombres', 'violet');

    Livewire::test(CrewPage::class)->set('tab', 'groupes')->assertSee('Les Ombres')->assertSee('1 membre');
});

it('ne plante pas sur des paramètres d\'URL de mauvais type', function (array $query) {
    $this->get('/crew?'.http_build_query($query))->assertOk()->assertSee('CLASSEMENT');
})->with([
    [['groupe' => 'abc']],
    [['metric' => ['x']]],
    [['tab' => ['a'], 'period' => ['b'], 'lift' => ['c']]],
]);
