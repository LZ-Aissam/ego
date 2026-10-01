<?php

use App\Crew\CrewService;
use App\Crew\Leaderboard;
use App\Livewire\Crew\CrewPage;
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

it('demande confirmation avant de quitter le groupe', function () {
    $this->actingAs($this->friend);

    Livewire::test(GroupPage::class, ['crew' => $this->crew])
        ->assertDontSee('Quitter définitivement')
        ->call('askLeave')->assertSee('Quitter définitivement')
        ->call('leave')->assertRedirect(route('community', ['tab' => 'groupes']));

    expect($this->crew->hasMember($this->friend))->toBeFalse();
});

it('prévient le créateur seul que le groupe sera supprimé', function () {
    app(CrewService::class)->leave($this->friend, $this->crew);
    $this->actingAs($this->owner);

    Livewire::test(GroupPage::class, ['crew' => $this->crew])->call('askLeave')->assertSee('Tu es le dernier membre : le groupe sera supprimé.');
});

it('demande confirmation avant de retirer un membre', function () {
    $this->actingAs($this->owner);

    Livewire::test(GroupPage::class, ['crew' => $this->crew])
        ->call('askKick', $this->friend->id)->assertSee('Retirer Renée du groupe ?')
        ->call('kick', $this->friend->id);

    expect($this->crew->hasMember($this->friend))->toBeFalse();
});

it('cherche un membre sans tenir compte des accents ni des majuscules', function () {
    $this->actingAs($this->owner);

    Livewire::test(CrewPage::class)->set('tab', 'membres')->set('search', 'RENEE')->assertSee('Renée')->assertDontSee('Aïssam');
});

it('classe les ex aequo par prénom sans tenir compte des accents ni des majuscules', function () {
    User::query()->update(['streak_days' => 0]);
    foreach (['Zoé', 'émile', 'bob'] as $name) {
        User::factory()->create(['first_name' => $name]);
    }

    $names = app(Leaderboard::class)->rank('streak')->map(fn ($r) => $r['user']->first_name)->all();

    expect($names)->toBe(['Aïssam', 'bob', 'émile', 'Renée', 'Zoé']);
});

it('reconnaît un code collé avec espace insécable ou tiret Unicode', function () {
    expect(Crew::normalizeCode("ab3\u{00A0}k9p"))->toBe('AB3K9P')
        ->and(Crew::normalizeCode("AB3\u{2011}K9P"))->toBe('AB3K9P');
});

it('signale une copie du lien impossible', function () {
    $this->actingAs($this->friend)->get(route('crew.group', $this->crew))
        ->assertSee('Copie impossible : appuie longuement sur le lien');
});

it('affiche la position en « #1 » et des nombres insécables', function () {
    $this->owner->forceFill(['streak_days' => 9])->save();
    $this->actingAs($this->owner);

    Livewire::withQueryParams(['metric' => 'streak'])->test(CrewPage::class)->assertSee('#1')->assertDontSee('1ᵉʳ');
    expect(Leaderboard::format('xp', 1240))->toBe("1\u{202F}240 XP");
});
