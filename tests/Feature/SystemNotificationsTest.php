<?php

use App\Models\User;
use App\Support\SystemMessage;
use App\Support\SystemNotifier;

beforeEach(fn () => $this->actingAs($this->user = User::factory()->create()));

it('fabrique une notification normalisée', function () {
    expect(SystemMessage::make('level_up', 'NIVEAU 13', ['Tu as monté de niveau !']))->toBe([
        'type' => 'level_up', 'title' => 'NIVEAU 13', 'lines' => ['Tu as monté de niveau !'], 'variant' => 'default', 'highlight' => null,
    ]);
});

it('affiche une notification stockée une seule fois', function () {
    app(SystemNotifier::class)->push($this->user, SystemMessage::make('info', 'BIENVENUE', ['Le Système est en ligne.']));

    $this->get('/')->assertOk()->assertSee('id="sys-notifications-data"', false)->assertSee('BIENVENUE');
    $this->get('/')->assertOk()->assertDontSee('BIENVENUE');
});

it('n\'affiche pas les notifications d\'un autre joueur', function () {
    app(SystemNotifier::class)->push(User::factory()->create(), SystemMessage::make('info', 'SECRET'));

    $this->get('/')->assertDontSee('SECRET');
});

it('échappe le contenu dynamique dans les données de notification', function () {
    app(SystemNotifier::class)->push($this->user, SystemMessage::make('info', 'T', ['</script><b>x</b>']));

    $this->get('/')->assertDontSee('</script><b>x</b>', false)->assertSee('\u003C\/script\u003E', false);
});

it('rend la notification accessible au clavier et aux lecteurs d\'écran', function () {
    app(SystemNotifier::class)->push($this->user, SystemMessage::make('info', 'BIENVENUE'));

    $this->get('/')
        ->assertSee('@keydown.escape.window="dismiss()"', false)
        ->assertSee('x-ref="ok"', false)
        ->assertSee('aria-live="assertive"', false);
});

it('demande au service worker de ne pas mettre en cache une page qui porte des notifications', function () {
    $this->get('/')->assertDontSee('name="ego-no-cache"', false);

    app(SystemNotifier::class)->push($this->user, SystemMessage::make('info', 'BIENVENUE'));

    $this->get('/')->assertSee('<meta name="ego-no-cache" content="1">', false);
});
