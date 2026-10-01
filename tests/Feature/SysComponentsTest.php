<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

it('rend une fenêtre système avec titre, variante et rang de cascade', function () {
    $html = Blade::render('<x-sys.window title="STATUT" variant="alert" :index="2">Contenu</x-sys.window>');

    expect($html)->toContain('class="sys-window sys-window--alert"')
        ->toContain('style="--i: 2"')
        ->toContain('sys-window-title')
        ->toContain('STATUT')
        ->toContain('Contenu');
});

it('rend une fenêtre sans titre', function () {
    expect(Blade::render('<x-sys.window>Corps</x-sys.window>'))->not->toContain('sys-window-title');
});

it('rend un bouton lien ou un bouton', function () {
    expect(Blade::render('<x-sys.button href="/workout" :pulse="true">Go</x-sys.button>'))
        ->toContain('<a href="/workout"')->toContain('sys-btn sys-btn--pulse');

    expect(Blade::render('<x-sys.button variant="ghost" type="submit">Ok</x-sys.button>'))
        ->toContain('<button type="submit"')->toContain('sys-btn sys-btn--ghost');
});

it('rend un champ avec son libellé, ses attributs et son erreur', function () {
    // Comme le middleware ShareErrorsFromSession, on partage $errors avec toutes les vues.
    view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag(['firstName' => ['Indique ton prénom.']])));

    $html = Blade::render('<x-sys.field name="firstName" label="Prénom" wire:model="firstName" autocomplete="given-name" />');

    expect($html)->toContain('<label for="firstName" class="sys-label">Prénom</label>')
        ->toContain('id="firstName"')
        ->toContain('wire:model="firstName"')
        ->toContain('class="sys-input"')
        ->toContain('Indique ton prénom.');
});

it('rend un libellé technique', function () {
    expect(Blade::render('<x-sys.label>VOLUME</x-sys.label>'))->toContain('<p class="sys-label">VOLUME</p>');
});
