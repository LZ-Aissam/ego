# EGO — Livraison A : refonte « Système » — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal :** donner à toute l'app existante l'identité « Système » : Neon Grid, couleur Ice, animations « Vivant », fenêtres holographiques à coins coupés et notifications système. Aucune fonctionnalité ne change, sauf les notifications de fin de séance et de record.

**Architecture :**
- Les jetons de design sont déclarés dans le `@theme` de Tailwind 4 (`resources/css/app.css`), avec des classes CSS `sys-*` pour les matières et les animations.
- Quatre composants Blade anonymes (`resources/views/components/sys/`) couvrent les éléments répétés côté serveur.
- Deux petits modules JS purs, testés avec Vitest, gèrent le compteur animé et la file de notifications.
- Les notifications système passent par deux chemins :
  - côté serveur, une table `system_notifications` lue au rendu du layout ;
  - côté séance, la réponse de `/workouts/sync`.

**Tech Stack :** Laravel 12, Livewire 3, Alpine.js, Tailwind CSS 4, `@fontsource/space-grotesk`, `@fontsource/jetbrains-mono`, Pest 4, Vitest.

**Spec :** `docs/superpowers/specs/2026-10-01-ego-systeme-design.md` (§4, livraison A). Spec de base : `docs/superpowers/specs/2026-10-01-ego-design.md`.

## Global Constraints

- **Jetons exacts** :

| Jeton | Valeur |
|---|---|
| `void` | `#04060A` |
| `abyss` | `#0B1A2C` |
| `ice` | `#5AD8FF` |
| `ice-deep` | `#3D6BFF` |
| `frost` | `#E8F6FF` |
| `mist` | `#9BB7C4` |
| `steel` | `#6F8F9A` |
| `alert` | `#FF4D6D` |
| `amber` | `#FFB547` |
| lueur | `rgba(90,216,255,.55)` |

- **Polices** Space Grotesk (texte) et JetBrains Mono (chiffres et libellés), **auto-hébergées** via @fontsource. Aucun appel à Google Fonts.
- **Coins coupés** : `clip-path` octogonal à 6 px sur les fenêtres et les boutons principaux.
- **Animations « Vivant »** :
  - grille qui défile (8 s) et halo qui respire (4 s) ;
  - bouton principal qui pulse (2 s) ;
  - fenêtres qui s'ouvrent en flash, en cascade de 150 ms ;
  - compteurs animés, appui en `scale(.96)` ;
  - éclat « NEW PR » sur un record.
- **`prefers-reduced-motion: reduce`** coupe toutes les animations et transitions.
- **Aucune nouvelle dépendance JS d'exécution** (les polices @fontsource ne sont que des fichiers CSS et polices).
- **Interface en français.** L'onglet « Communauté » devient **« Crew »**. Les textes déjà vérifiés par les tests restent présents.
- **Tous les chiffres** sont en `font-mono` avec `tabular-nums`.
- **Contraste** : `frost` et `mist` sur `void` ≥ 4,5:1, et la lueur n'est jamais le seul indicateur d'un état.
- **Toutes les vues sont redessinées** : layouts, connexion, inscription, accueil, séance, historique, détail d'une séance, records, progression, profil, pages « bientôt ».
- **MariaDB (WampServer, port 3307) doit être lancé** pour les tests PHP.
- **Commits** terminés par `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **Notification de fin de séance alors que l'envoi a échoué** (hors ligne, 419) : aucune notification serveur ; l'écran affiche le message « enregistrée sur ton téléphone » comme avant. Test JSON dans la tâche 8 (les notifications ne sont renvoyées qu'à l'enregistrement) et chemin `pending` inchangé dans `component.js`.
2. **Deuxième envoi idempotent** (`created:false`) : il ne doit pas réafficher « SÉANCE TERMINÉE » ni les records. Test dans la tâche 8.
3. **Notifications stockées affichées une seule fois**, même si l'utilisateur recharge la page. Test dans la tâche 4 (marquées vues au rendu).
4. **Texte dynamique dans une notification** (nom d'exercice avec `<`, `"` ou `</script>`) : aucune injection HTML. Test dans la tâche 4 (`JSON_HEX_TAG` + `x-text`).
5. **Repos prolongé avec « +30 s »** : l'anneau ne dépasse jamais 100 % et ne devient jamais négatif. Test dans la tâche 3 (`restProgress`).

---

## Structure des fichiers

```
resources/css/app.css                           jetons @theme + classes sys-* + animations
resources/js/app.js                             import des polices + enregistrement de sysNotifications + count-up
resources/js/ui/count-up.js (+ .test.js)        compteur animé
resources/js/ui/notifications.js (+ .test.js)   file de notifications (pur) + composant Alpine
resources/js/workout/store.js                   + restTotalMs, restProgress()
resources/js/workout/component.js               + restPercent, envoi des notifications
resources/views/partials/backdrop.blade.php     fond grille + halo
resources/views/components/sys/window.blade.php
resources/views/components/sys/button.blade.php
resources/views/components/sys/label.blade.php
resources/views/components/sys/field.blade.php
resources/views/components/sys/notifications.blade.php
resources/views/components/layouts/app.blade.php, guest.blade.php
resources/views/livewire/*.blade.php, workout/run.blade.php, pages/soon.blade.php
app/Models/SystemNotification.php
app/Support/SystemNotifier.php                  push / pullUnseen
app/Support/SystemMessage.php                   fabrique des notifications (titre, lignes, variante)
app/Providers/AppServiceProvider.php            composer du layout
app/Http/Controllers/WorkoutSyncController.php  + notifications dans la réponse
database/migrations/2026_10_02_000100_create_system_notifications_table.php
tests/Feature/SysComponentsTest.php, SystemNotificationsTest.php ; tests existants mis à jour
```

---

### Task 1 : polices, jetons et CSS « Système »

> Exception TDD (styles) : pas de test unitaire de CSS. La vérification se fait par la compilation et par la recherche des jetons dans le CSS produit.

**Files :**
- Modify : `package.json` (dépendances), `resources/css/app.css` (réécriture complète), `resources/js/app.js` (imports des polices)

**Interfaces :**
- Produces : utilitaires Tailwind `bg-void`, `text-frost`, `text-mist`, `text-steel`, `text-ice`, `text-alert`, `text-amber`, `font-sans`, `font-mono`, etc. ; classes CSS :
  - structure : `sys-body`, `sys-backdrop`, `sys-grid`, `sys-halo` ;
  - fenêtres : `sys-window` (variable `--i` pour la cascade), `sys-window--alert`, `sys-window-title`, `sys-window-icon` ;
  - boutons : `sys-btn`, `sys-btn--ghost`, `sys-btn--pulse` ;
  - textes et saisie : `sys-label`, `sys-input`, `sys-error`, `sys-num` ;
  - éléments de séance : `sys-chip`, `sys-chip--active`, `sys-step`, `sys-ring` (variable `--p` en %) ;
  - bandeaux et navigation : `sys-banner`, `sys-banner--ice`, `sys-banner--amber`, `sys-nav`, `sys-nav-link`, `sys-nav-link--active`, `sys-logo` ;
  - notifications et records : `sys-overlay`, `sys-pop`, `sys-badge`, `sys-burst` ;
  - liste : `sys-row`.

- [ ] **Step 1 : installer les polices**

```bash
npm install @fontsource/space-grotesk @fontsource/jetbrains-mono
```

- [ ] **Step 2 : réécrire `resources/css/app.css`**

```css
@import 'tailwindcss';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../**/*.blade.php';
@source '../**/*.js';

@theme {
    --color-void: #04060a;
    --color-abyss: #0b1a2c;
    --color-ice: #5ad8ff;
    --color-ice-deep: #3d6bff;
    --color-frost: #e8f6ff;
    --color-mist: #9bb7c4;
    --color-steel: #6f8f9a;
    --color-alert: #ff4d6d;
    --color-amber: #ffb547;
    --font-sans: 'Space Grotesk', ui-sans-serif, system-ui, sans-serif;
    --font-mono: 'JetBrains Mono', ui-monospace, monospace;
}

:root {
    --glow-ice: rgba(90, 216, 255, 0.55);
    --sys-cut: polygon(0 6px, 6px 0, calc(100% - 6px) 0, 100% 6px, 100% calc(100% - 6px), calc(100% - 6px) 100%, 6px 100%, 0 calc(100% - 6px));
}

[x-cloak] { display: none !important; }

/* ---------- Fond ---------- */
.sys-body { background-color: var(--color-void); color: var(--color-frost); font-family: var(--font-sans); }
.sys-backdrop { position: fixed; inset: 0; z-index: -1; overflow: hidden; pointer-events: none; background: radial-gradient(120% 60% at 50% 0%, var(--color-abyss) 0%, var(--color-void) 60%); }
.sys-grid { position: absolute; inset: -44px 0 0 0; background-image: linear-gradient(rgba(90, 216, 255, 0.06) 1px, transparent 1px), linear-gradient(90deg, rgba(90, 216, 255, 0.06) 1px, transparent 1px); background-size: 22px 22px; mask-image: linear-gradient(to bottom, #000 0%, transparent 70%); animation: sys-drift 8s linear infinite; }
.sys-halo { position: absolute; width: 520px; height: 520px; top: -260px; left: -180px; background: radial-gradient(circle, var(--glow-ice), transparent 65%); opacity: 0.45; animation: sys-breathe 4s ease-in-out infinite; }

/* ---------- Fenêtres ---------- */
.sys-window { position: relative; padding: 1rem; background: linear-gradient(180deg, rgba(20, 48, 80, 0.55), rgba(6, 14, 24, 0.78)); border: 1px solid rgba(90, 216, 255, 0.45); box-shadow: inset 0 0 22px rgba(90, 216, 255, 0.12); clip-path: var(--sys-cut); animation: sys-open 0.6s cubic-bezier(0.2, 0.8, 0.2, 1) both; animation-delay: calc(var(--i, 0) * 150ms); }
.sys-window--alert { border-color: rgba(255, 77, 109, 0.6); background: linear-gradient(180deg, rgba(80, 16, 30, 0.55), rgba(24, 6, 10, 0.8)); box-shadow: inset 0 0 22px rgba(255, 77, 109, 0.15); }
.sys-window-title { display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding-bottom: 0.6rem; margin-bottom: 0.75rem; border-bottom: 1px solid rgba(90, 216, 255, 0.3); font-family: var(--font-mono); font-weight: 800; font-size: 0.75rem; letter-spacing: 0.3em; color: #dff6ff; text-shadow: 0 0 10px var(--glow-ice); text-transform: uppercase; }
.sys-window--alert .sys-window-title { border-bottom-color: rgba(255, 77, 109, 0.35); color: #ffd9df; text-shadow: 0 0 10px rgba(255, 77, 109, 0.6); }
.sys-window-icon { display: grid; place-items: center; width: 1rem; height: 1rem; border: 1px solid currentColor; font-size: 0.6rem; letter-spacing: 0; color: var(--color-ice); }
.sys-window--alert .sys-window-icon { color: var(--color-alert); }

/* ---------- Boutons ---------- */
.sys-btn { display: inline-flex; width: 100%; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.95rem 1rem; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #03080b; background: linear-gradient(135deg, var(--color-ice), var(--color-ice-deep)); box-shadow: 0 0 22px var(--glow-ice); clip-path: var(--sys-cut); transition: transform 0.12s ease, filter 0.12s ease; }
.sys-btn:active { transform: scale(0.96); }
.sys-btn:disabled { opacity: 0.4; }
.sys-btn--pulse { animation: sys-pulse 2s ease-in-out infinite; }
.sys-btn--ghost { color: var(--color-frost); background: rgba(90, 216, 255, 0.06); box-shadow: inset 0 0 0 1px rgba(90, 216, 255, 0.45); }

/* ---------- Textes et saisie ---------- */
.sys-label { font-family: var(--font-mono); font-size: 0.65rem; letter-spacing: 0.25em; text-transform: uppercase; color: var(--color-steel); }
.sys-label::before { content: '// '; color: var(--color-ice); }
.sys-num { font-family: var(--font-mono); font-variant-numeric: tabular-nums; }
.sys-input { width: 100%; padding: 0.8rem 1rem; font-size: 1rem; color: var(--color-frost); background: rgba(4, 10, 16, 0.8); border: 1px solid rgba(90, 216, 255, 0.25); clip-path: var(--sys-cut); outline: none; transition: border-color 0.15s, box-shadow 0.15s; }
.sys-input:focus { border-color: var(--color-ice); box-shadow: inset 0 0 12px rgba(90, 216, 255, 0.2); }
.sys-input::placeholder { color: var(--color-steel); }
.sys-error { margin-top: 0.35rem; font-size: 0.85rem; color: var(--color-alert); }
.sys-row { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.5rem 0; border-bottom: 1px solid rgba(90, 216, 255, 0.1); }
.sys-row:last-child { border-bottom: 0; }

/* ---------- Séance ---------- */
.sys-chip { flex-shrink: 0; padding: 0.35rem 0.75rem; font-size: 0.8rem; border-radius: 999px; border: 1px solid rgba(255, 255, 255, 0.12); color: var(--color-steel); white-space: nowrap; }
.sys-chip--active { border-color: var(--color-ice); color: var(--color-ice); box-shadow: 0 0 12px var(--glow-ice), inset 0 0 8px rgba(90, 216, 255, 0.2); }
.sys-step { display: grid; place-items: center; width: 3rem; height: 3rem; flex-shrink: 0; font-size: 1.5rem; color: var(--color-frost); background: rgba(255, 255, 255, 0.06); clip-path: var(--sys-cut); }
.sys-step:active { transform: scale(0.92); background: rgba(90, 216, 255, 0.18); }
.sys-ring { --p: 0%; display: grid; place-items: center; width: 4.5rem; height: 4.5rem; flex-shrink: 0; border-radius: 50%; background: conic-gradient(var(--color-ice) 0 var(--p), rgba(255, 255, 255, 0.08) var(--p) 100%); filter: drop-shadow(0 0 8px var(--glow-ice)); }
.sys-ring > span { display: grid; place-items: center; width: 3.8rem; height: 3.8rem; border-radius: 50%; background: #060a0e; font-family: var(--font-mono); font-weight: 800; color: var(--color-ice); }

/* ---------- Bandeaux et navigation ---------- */
.sys-banner { display: block; padding: 0.7rem 1rem; text-align: center; font-weight: 700; font-size: 0.9rem; clip-path: var(--sys-cut); }
.sys-banner--ice { color: #03080b; background: linear-gradient(135deg, var(--color-ice), var(--color-ice-deep)); box-shadow: 0 0 18px var(--glow-ice); }
.sys-banner--amber { position: sticky; top: 0; z-index: 30; clip-path: none; color: #1a1000; background: var(--color-amber); }
.sys-nav { position: fixed; left: 0.75rem; right: 0.75rem; bottom: max(0.75rem, env(safe-area-inset-bottom)); z-index: 20; border-radius: 1.4rem; background: rgba(6, 12, 18, 0.85); border: 1px solid rgba(90, 216, 255, 0.18); backdrop-filter: blur(16px); }
@media (min-width: 1024px) { .sys-nav { position: sticky; top: 0; left: auto; right: auto; bottom: auto; width: 15rem; height: 100vh; border-radius: 0; border-width: 0 1px 0 0; } }
.sys-nav-link { display: flex; flex-direction: column; align-items: center; gap: 0.25rem; padding: 0.6rem 0; font-size: 0.65rem; letter-spacing: 0.12em; text-transform: uppercase; color: var(--color-steel); }
@media (min-width: 1024px) { .sys-nav-link { flex-direction: row; gap: 0.75rem; padding: 0.75rem 1rem; font-size: 0.8rem; } }
.sys-nav-link--active { color: var(--color-ice); text-shadow: 0 0 10px var(--glow-ice); }
.sys-logo { font-family: var(--font-mono); font-weight: 800; letter-spacing: 0.35em; color: var(--color-ice); text-shadow: 0 0 18px var(--glow-ice); }

/* ---------- Notifications ---------- */
.sys-overlay { position: fixed; inset: 0; z-index: 50; display: grid; place-items: center; padding: 1.5rem; background: rgba(2, 6, 12, 0.72); backdrop-filter: blur(4px); animation: sys-fade 0.25s ease both; }
.sys-pop { width: 100%; max-width: 22rem; text-align: center; animation: sys-pop 0.45s cubic-bezier(0.2, 0.8, 0.2, 1) both; }
.sys-badge { display: inline-block; padding: 0.2rem 0.5rem; font-family: var(--font-mono); font-size: 0.65rem; font-weight: 800; color: #03080b; background: var(--color-ice); animation: sys-badge 0.6s ease both 0.2s; }
.sys-burst { position: relative; display: block; height: 0; }
.sys-burst i { position: absolute; left: 50%; top: -0.6rem; width: 3px; height: 0.9rem; border-radius: 3px; background: var(--color-ice); box-shadow: 0 0 8px var(--color-ice); opacity: 0; transform-origin: 1.5px -0.4rem; animation: sys-spark 0.9s ease-out both 0.15s; }
.sys-burst i:nth-child(1) { --r: 0deg; } .sys-burst i:nth-child(2) { --r: 45deg; } .sys-burst i:nth-child(3) { --r: 90deg; } .sys-burst i:nth-child(4) { --r: 135deg; }
.sys-burst i:nth-child(5) { --r: 180deg; } .sys-burst i:nth-child(6) { --r: 225deg; } .sys-burst i:nth-child(7) { --r: 270deg; } .sys-burst i:nth-child(8) { --r: 315deg; }

/* ---------- Animations ---------- */
@keyframes sys-drift { to { transform: translateY(22px); } }
@keyframes sys-breathe { 0%, 100% { opacity: 0.35; transform: scale(1); } 50% { opacity: 0.6; transform: scale(1.1); } }
@keyframes sys-pulse { 0%, 100% { box-shadow: 0 0 16px var(--glow-ice); } 50% { box-shadow: 0 0 38px var(--glow-ice), 0 0 0 4px rgba(90, 216, 255, 0.15); } }
@keyframes sys-open { 0% { opacity: 0; transform: scaleY(0.05); filter: brightness(3); } 55% { opacity: 1; transform: scaleY(1); filter: brightness(1.6); } 100% { opacity: 1; transform: none; filter: none; } }
@keyframes sys-fade { from { opacity: 0; } to { opacity: 1; } }
@keyframes sys-pop { 0% { opacity: 0; transform: scale(0.6); filter: brightness(2.5); } 70% { opacity: 1; transform: scale(1.04); } 100% { transform: none; filter: none; } }
@keyframes sys-badge { 0% { opacity: 0; transform: scale(0.4); } 60% { opacity: 1; transform: scale(1.25); } 100% { opacity: 1; transform: none; } }
@keyframes sys-spark { 0% { opacity: 0; transform: rotate(var(--r)) translateY(0) scaleY(0.4); } 20% { opacity: 1; } 100% { opacity: 0; transform: rotate(var(--r)) translateY(-2.2rem) scaleY(1); } }

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after { animation: none !important; transition: none !important; }
}
```

- [ ] **Step 3 : importer les polices dans `resources/js/app.js`** (ajouter en tête du fichier, avant les autres imports)

```js
import '@fontsource/space-grotesk/400.css';
import '@fontsource/space-grotesk/500.css';
import '@fontsource/space-grotesk/700.css';
import '@fontsource/jetbrains-mono/500.css';
import '@fontsource/jetbrains-mono/700.css';
import '@fontsource/jetbrains-mono/800.css';
```

- [ ] **Step 4 : compiler et vérifier**

Run : `npm run build && grep -c "sys-window" public/build/assets/*.css && ls public/build/assets | grep -ciE "\.woff2$"`
Attendu : build OK ; au moins 1 occurrence de `sys-window` ; au moins 6 fichiers `.woff2` (les polices sont regroupées localement).

Run : `./vendor/bin/pest && npm test`
Attendu : tout PASS. Rien n'a changé dans les vues, donc rien ne doit casser.

- [ ] **Step 5 : commit**

```bash
git add -A
git commit -m "style: jetons, polices auto-hébergées et classes « Système »

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2 : composants Blade `sys`

**Files :**
- Create : `resources/views/components/sys/window.blade.php`, `button.blade.php`, `label.blade.php`, `field.blade.php`
- Test : `tests/Feature/SysComponentsTest.php`

**Interfaces :**
- Produces :
  - `<x-sys.window title="…" variant="default|alert" :index="0">` : `<section class="sys-window …" style="--i: N">`, avec un `<header class="sys-window-title">` si un titre est fourni ;
  - `<x-sys.button href="…" variant="primary|ghost" :pulse="false" type="button">` : `<a>` si `href`, sinon `<button>` ;
  - `<x-sys.label>` : `<p class="sys-label">` ;
  - `<x-sys.field name="firstName" label="Prénom" type="text">` : `<label class="sys-label" for>` + `<input id class="sys-input" …attributs transmis (wire:model…)>` + `@error($name)`.

- [ ] **Step 1 : écrire le test qui échoue**

`tests/Feature/SysComponentsTest.php` :
```php
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
    $errors = (new ViewErrorBag)->put('default', new MessageBag(['firstName' => ['Indique ton prénom.']]));

    $html = Blade::render(
        '<x-sys.field name="firstName" label="Prénom" wire:model="firstName" autocomplete="given-name" />',
        ['errors' => $errors],
    );

    expect($html)->toContain('<label for="firstName" class="sys-label">Prénom</label>')
        ->toContain('id="firstName"')
        ->toContain('wire:model="firstName"')
        ->toContain('class="sys-input"')
        ->toContain('Indique ton prénom.');
});

it('rend un libellé technique', function () {
    expect(Blade::render('<x-sys.label>VOLUME</x-sys.label>'))->toContain('<p class="sys-label">VOLUME</p>');
});
```

- [ ] **Step 2 : vérifier l'échec**

Run : `./vendor/bin/pest tests/Feature/SysComponentsTest.php`
Attendu : FAIL (`Unable to locate a class or view for component [sys.window]`).

- [ ] **Step 3 : écrire les composants**

`resources/views/components/sys/window.blade.php` :
```blade
@props(['title' => null, 'variant' => 'default', 'index' => 0])
<section {{ $attributes->class(['sys-window', 'sys-window--alert' => $variant === 'alert']) }} style="--i: {{ $index }}">
    @if ($title)
        <header class="sys-window-title"><span class="sys-window-icon" aria-hidden="true">!</span>{{ $title }}</header>
    @endif
    {{ $slot }}
</section>
```

`resources/views/components/sys/button.blade.php` :
```blade
@props(['href' => null, 'variant' => 'primary', 'pulse' => false, 'type' => 'button'])
@php
    $classes = ['sys-btn', 'sys-btn--ghost' => $variant === 'ghost', 'sys-btn--pulse' => $pulse];
@endphp
@if ($href)
<a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
<button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
```

`resources/views/components/sys/label.blade.php` :
```blade
<p {{ $attributes->class('sys-label') }}>{{ $slot }}</p>
```

`resources/views/components/sys/field.blade.php` :
```blade
@props(['name', 'label', 'type' => 'text'])
<div>
    <label for="{{ $name }}" class="sys-label">{{ $label }}</label>
    <input id="{{ $name }}" type="{{ $type }}" {{ $attributes->merge(['class' => 'sys-input']) }}>
    @error($name) <p class="sys-error">{{ $message }}</p> @enderror
</div>
```

- [ ] **Step 4 : vérifier que tout passe**

Run : `./vendor/bin/pest tests/Feature/SysComponentsTest.php`
Attendu : PASS (5 tests). En cas d'écart d'ordre des attributs (par exemple `class` avant `wire:model`), ajuster **l'assertion** sur le fragment exact produit, sans changer le comportement.

- [ ] **Step 5 : commit**

```bash
git add -A
git commit -m "feat: composants Blade « Système » (fenêtre, bouton, libellé, champ)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3 : modules JS — compteur animé, file de notifications, progression du repos

**Files :**
- Create : `resources/js/ui/count-up.js`, `resources/js/ui/count-up.test.js`, `resources/js/ui/notifications.js`, `resources/js/ui/notifications.test.js`
- Modify : `resources/js/workout/store.js` (+ `restTotalMs`, `restProgress`), `resources/js/workout/store.test.js`

**Interfaces :**
- Produces :
  - `count-up.js` :
    - `easeOutCubic(t: number): number` ;
    - `formatNumber(n: number, decimals = 0): string` (fr-FR) ;
    - `countUp(el, { duration = 900, reducedMotion })` (lit `data-count-up`, `data-decimals` et `data-suffix`) ;
    - `initCountUps(root = document)`.
  - `notifications.js` :
    - `normalize(item): Notification | null`, où `Notification = { type, title, lines: string[], variant: 'default'|'alert', highlight: string|null }` ;
    - `enqueue(list, items): Notification[]` ;
    - `dismiss(list): Notification[]` ;
    - `buzz(nav = navigator): boolean` ;
    - `default function sysNotifications()` : composant Alpine qui lit `#sys-notifications-data`, écoute `window` `sys-notify` (`event.detail` = tableau) et expose `current`, `dismiss()`.
  - `store.js` :
    - `logSet` met `restTotalMs = restSeconds × 1000` ;
    - `addRest` ajoute à `restTotalMs` ;
    - `skipRest` remet `restTotalMs` à `null` ;
    - `restProgress(state, nowMs): number`, fraction restante comprise entre 0 et 1.

- [ ] **Step 1 : écrire les tests qui échouent**

`resources/js/ui/count-up.test.js` :
```js
import { describe, expect, it } from 'vitest';
import { countUp, easeOutCubic, formatNumber } from './count-up';

describe('easeOutCubic', () => {
    it('part de 0, arrive à 1 et décélère', () => {
        expect(easeOutCubic(0)).toBe(0);
        expect(easeOutCubic(1)).toBe(1);
        expect(easeOutCubic(0.5)).toBeGreaterThan(0.5);
    });
});

describe('formatNumber', () => {
    it('formate à la française', () => {
        expect(formatNumber(12480).replace(/\s/g, ' ')).toBe('12 480');
        expect(formatNumber(62.5, 1)).toBe('62,5');
    });
});

describe('countUp', () => {
    it('affiche directement la valeur finale si les animations sont réduites', () => {
        const el = { dataset: { countUp: '4250', suffix: ' kg' }, textContent: '' };
        countUp(el, { reducedMotion: true });
        expect(el.textContent.replace(/\s/g, ' ')).toBe('4 250 kg');
    });
});
```

`resources/js/ui/notifications.test.js` :
```js
import { describe, expect, it } from 'vitest';
import { buzz, dismiss, enqueue, normalize } from './notifications';

const rec = { type: 'record', title: 'NOUVEAU RECORD', lines: ['Squat', '90 kg × 3'], highlight: 'NEW PR' };

describe('normalize', () => {
    it('complète les champs facultatifs', () => {
        expect(normalize({ type: 'x', title: 'T' })).toEqual({ type: 'x', title: 'T', lines: [], variant: 'default', highlight: null });
    });

    it('rejette un élément sans titre', () => {
        expect(normalize({ type: 'x' })).toBeNull();
        expect(normalize(null)).toBeNull();
    });
});

describe('file', () => {
    it('ajoute à la fin en ignorant les éléments invalides puis retire le premier', () => {
        let list = enqueue([], [rec, { nope: true }, { type: 'workout_done', title: 'SÉANCE TERMINÉE' }]);
        expect(list.map((n) => n.title)).toEqual(['NOUVEAU RECORD', 'SÉANCE TERMINÉE']);
        list = dismiss(list);
        expect(list.map((n) => n.title)).toEqual(['SÉANCE TERMINÉE']);
        expect(dismiss(dismiss(list))).toEqual([]);
    });

    it('accepte une entrée qui n\'est pas un tableau sans planter', () => {
        expect(enqueue([], undefined)).toEqual([]);
    });
});

describe('buzz', () => {
    it('fait vibrer si possible', () => {
        let pattern = null;
        expect(buzz({ vibrate: (p) => { pattern = p; return true; } })).toBe(true);
        expect(pattern).toEqual([60, 40, 60]);
        expect(buzz({})).toBe(false);
    });
});
```

Ajouter à `resources/js/workout/store.test.js` : compléter l'import avec `restProgress` (`import { …, restProgress } from './store';`) et ajouter ce `describe` :
```js
describe('restProgress', () => {
    it('donne la fraction de repos restante, bornée entre 0 et 1, même après +30 s', () => {
        let s = addExercise(newWorkout('id', 't'), bench, { restSeconds: 90 });
        expect(restProgress(s, 0)).toBe(0);
        s = logSet(s, 0);
        expect(restProgress(s, 0)).toBe(1);
        expect(restProgress(s, 45_000)).toBe(0.5);
        s = addRest(s, 30, 45_000);
        expect(restProgress(s, 45_000)).toBeCloseTo(75 / 120);
        expect(restProgress(s, 999_999)).toBe(0);
        s = skipRest(s);
        expect(restProgress(s, 0)).toBe(0);
    });
});
```

- [ ] **Step 2 : vérifier l'échec**

Run : `npm test`
Attendu : FAIL (`Failed to resolve import "./count-up"`, `"./notifications"`, et `restProgress is not a function`).

- [ ] **Step 3 : implémenter `count-up.js`**

`resources/js/ui/count-up.js` :
```js
export function easeOutCubic(t) {
    return 1 - Math.pow(1 - t, 3);
}

export function formatNumber(n, decimals = 0) {
    return n.toLocaleString('fr-FR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

export function countUp(el, { duration = 900, reducedMotion = false } = {}) {
    const target = parseFloat(el.dataset.countUp);
    if (!Number.isFinite(target)) return;
    const decimals = parseInt(el.dataset.decimals ?? '0', 10);
    const suffix = el.dataset.suffix ?? '';
    const render = (value) => { el.textContent = formatNumber(value, decimals) + suffix; };

    if (reducedMotion || typeof requestAnimationFrame !== 'function') {
        render(target);
        return;
    }

    const start = performance.now();
    const frame = (now) => {
        const t = Math.min(1, (now - start) / duration);
        render(target * easeOutCubic(t));
        if (t < 1) requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
}

export function initCountUps(root = document) {
    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
    root.querySelectorAll('[data-count-up]').forEach((el) => countUp(el, { reducedMotion }));
}
```

- [ ] **Step 4 : implémenter `notifications.js`**

`resources/js/ui/notifications.js` :
```js
export function normalize(item) {
    if (!item || typeof item.title !== 'string' || item.title === '') return null;
    return {
        type: item.type ?? 'info',
        title: item.title,
        lines: Array.isArray(item.lines) ? item.lines.map(String) : [],
        variant: item.variant === 'alert' ? 'alert' : 'default',
        highlight: item.highlight ?? null,
    };
}

export function enqueue(list, items) {
    if (!Array.isArray(items)) return list;
    return [...list, ...items.map(normalize).filter(Boolean)];
}

export function dismiss(list) {
    return list.slice(1);
}

export function buzz(nav = globalThis.navigator) {
    if (typeof nav?.vibrate !== 'function') return false;
    nav.vibrate([60, 40, 60]);
    return true;
}

export default function sysNotifications() {
    return {
        items: [],

        init() {
            const data = document.getElementById('sys-notifications-data');
            this.push(data ? JSON.parse(data.textContent) : []);
            window.addEventListener('sys-notify', (event) => this.push(event.detail));
        },

        get current() {
            return this.items[0] ?? null;
        },

        push(items) {
            const wasEmpty = this.items.length === 0;
            this.items = enqueue(this.items, items);
            if (wasEmpty && this.items.length) buzz();
        },

        dismiss() {
            this.items = dismiss(this.items);
            if (this.items.length) buzz();
        },
    };
}
```

- [ ] **Step 5 : `restProgress` dans `store.js`**

Dans `newWorkout`, ajouter `restTotalMs: null` à l'objet renvoyé :
```js
export function newWorkout(clientId, startedAtIso) {
    return { clientId, startedAt: startedAtIso, currentIndex: 0, restEndsAt: null, restTotalMs: null, exercises: [] };
}
```
Dans `logSet`, juste après `state.restEndsAt = nowMs + ex.restSeconds * 1000;`, ajouter :
```js
    state.restTotalMs = ex.restSeconds * 1000;
```
Remplacer `skipRest` et `addRest` par :
```js
export function skipRest(state) {
    state.restEndsAt = null;
    state.restTotalMs = null;
    return state;
}

export function addRest(state, seconds, nowMs) {
    state.restEndsAt = Math.max(state.restEndsAt ?? nowMs, nowMs) + seconds * 1000;
    state.restTotalMs = (state.restTotalMs ?? 0) + seconds * 1000;
    return state;
}

export function restProgress(state, nowMs) {
    if (!state.restEndsAt || !state.restTotalMs) return 0;
    return Math.min(1, Math.max(0, (state.restEndsAt - nowMs) / state.restTotalMs));
}
```

- [ ] **Step 6 : vérifier que tout passe**

Run : `npm test`
Attendu : PASS (les tests existants et les nouveaux).

- [ ] **Step 7 : commit**

```bash
git add -A
git commit -m "feat: compteur animé, file de notifications système et progression du repos

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4 : notifications système côté serveur

**Files :**
- Create : `database/migrations/2026_10_02_000100_create_system_notifications_table.php`, `app/Models/SystemNotification.php`, `app/Support/SystemNotifier.php`, `app/Support/SystemMessage.php`, `resources/views/components/sys/notifications.blade.php`
- Modify : `app/Providers/AppServiceProvider.php` (composer), `resources/views/components/layouts/app.blade.php` (inclusion du conteneur), `resources/js/app.js` (enregistrement Alpine)
- Test : `tests/Feature/SystemNotificationsTest.php`

**Interfaces :**
- Consumes : `Format::kg`, `Format::duration`, `Workout::volume()`, `Workout::durationMinutes()`
- Produces :
  - `SystemMessage::make(string $type, string $title, array $lines = [], string $variant = 'default', ?string $highlight = null): array` ;
  - `SystemMessage::workoutDone(Workout $w): array` (titre `SÉANCE TERMINÉE`) ;
  - `SystemMessage::record(PersonalRecord $r): array` (titre `NOUVEAU RECORD`, `highlight` `NEW PR`) ;
  - `SystemNotifier::push(User $user, array $notification): void` ;
  - `SystemNotifier::pullUnseen(User $user): list<array>` (les marque vues) ;
  - variable de vue `$systemNotifications` pour `components.layouts.app` ;
  - composant `<x-sys.notifications :items="…">`.

- [ ] **Step 1 : écrire le test qui échoue**

`tests/Feature/SystemNotificationsTest.php` :
```php
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

    $this->get('/')->assertDontSee('</script><b>x</b>', false)->assertSee('<\/script>', false);
});
```

- [ ] **Step 2 : vérifier l'échec**

Run : `./vendor/bin/pest tests/Feature/SystemNotificationsTest.php`
Attendu : FAIL (`Class "App\Support\SystemMessage" not found`).

- [ ] **Step 3 : migration et modèle**

`database/migrations/2026_10_02_000100_create_system_notifications_table.php` :
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->json('payload');
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_notifications');
    }
};
```

`app/Models/SystemNotification.php` :
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemNotification extends Model
{
    protected $fillable = ['user_id', 'type', 'payload', 'seen_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'seen_at' => 'datetime'];
    }
}
```

- [ ] **Step 4 : fabrique et service**

`app/Support/SystemMessage.php` :
```php
<?php

namespace App\Support;

use App\Models\PersonalRecord;
use App\Models\Workout;

final class SystemMessage
{
    /** @return array{type: string, title: string, lines: list<string>, variant: string, highlight: ?string} */
    public static function make(string $type, string $title, array $lines = [], string $variant = 'default', ?string $highlight = null): array
    {
        return ['type' => $type, 'title' => $title, 'lines' => array_values($lines), 'variant' => $variant, 'highlight' => $highlight];
    }

    public static function workoutDone(Workout $workout): array
    {
        return self::make('workout_done', 'SÉANCE TERMINÉE', [
            Format::duration($workout->durationMinutes()).' · '.$workout->sets->count().' séries',
            'Volume : '.Format::kg($workout->volume()),
        ]);
    }

    public static function record(PersonalRecord $record): array
    {
        return self::make('record', 'NOUVEAU RECORD', [
            $record->exercise->name,
            Format::kg($record->max_weight_kg).' × '.$record->max_weight_reps,
        ], highlight: 'NEW PR');
    }
}
```

`app/Support/SystemNotifier.php` :
```php
<?php

namespace App\Support;

use App\Models\SystemNotification;
use App\Models\User;

final class SystemNotifier
{
    public function push(User $user, array $notification): void
    {
        SystemNotification::create(['user_id' => $user->id, 'type' => $notification['type'], 'payload' => $notification]);
    }

    /** @return list<array> */
    public function pullUnseen(User $user): array
    {
        $rows = SystemNotification::where('user_id', $user->id)->whereNull('seen_at')->orderBy('id')->get();

        if ($rows->isEmpty()) {
            return [];
        }

        SystemNotification::whereIn('id', $rows->pluck('id'))->update(['seen_at' => now()]);

        return $rows->pluck('payload')->all();
    }
}
```

- [ ] **Step 5 : composer du layout**

Dans `app/Providers/AppServiceProvider.php`, compléter `boot()` :
```php
    public function boot(): void
    {
        \Illuminate\Support\Carbon::setLocale(config('app.locale'));

        \Illuminate\Support\Facades\View::composer('components.layouts.app', function ($view) {
            $view->with('systemNotifications', auth()->check()
                ? app(\App\Support\SystemNotifier::class)->pullUnseen(auth()->user())
                : []);
        });
    }
```

- [ ] **Step 6 : composant conteneur**

`resources/views/components/sys/notifications.blade.php` :
```blade
@props(['items' => []])
<div x-data="sysNotifications">
    <script type="application/json" id="sys-notifications-data">{!! json_encode($items, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_THROW_ON_ERROR) !!}</script>
    <template x-if="current">
        <div class="sys-overlay" role="dialog" aria-modal="true" :aria-label="current.title" @click="dismiss()">
            <div class="sys-window sys-pop" :class="current.variant === 'alert' && 'sys-window--alert'">
                <header class="sys-window-title"><span class="sys-window-icon" aria-hidden="true">!</span><span x-text="current.title"></span></header>
                <template x-if="current.highlight">
                    <div class="mb-3">
                        <span class="sys-burst" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>
                        <span class="sys-badge" x-text="current.highlight"></span>
                    </div>
                </template>
                <template x-for="(line, i) in current.lines" :key="i">
                    <p :class="i === 0 ? 'text-lg font-bold text-frost' : 'mt-1 text-mist sys-num'" x-text="line"></p>
                </template>
                <p class="sys-label mt-5">Touche pour continuer</p>
            </div>
        </div>
    </template>
</div>
```

Dans `resources/views/components/layouts/app.blade.php`, juste **avant** `@livewireScriptConfig`, ajouter :
```blade
    <x-sys.notifications :items="$systemNotifications ?? []" />
```

Dans `resources/js/app.js`, ajouter l'import et l'enregistrement (à côté de `Alpine.data('workout', workout);`) :
```js
import sysNotifications from './ui/notifications';
// …
Alpine.data('sysNotifications', sysNotifications);
```

- [ ] **Step 7 : vérifier que tout passe**

Run : `php artisan migrate --no-interaction && npm run build && ./vendor/bin/pest`
Attendu : migration OK, puis tous les tests PASS.

- [ ] **Step 8 : commit**

```bash
git add -A
git commit -m "feat: notifications système stockées et affichées une seule fois

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5 : layouts, fond holographique et navigation « Crew »

**Files :**
- Create : `resources/views/partials/backdrop.blade.php`
- Modify : `resources/views/components/layouts/app.blade.php` (réécriture), `resources/views/components/layouts/guest.blade.php` (réécriture), `resources/js/app.js` (`initCountUps`)
- Test : `tests/Feature/LayoutTest.php` (mise à jour)

**Interfaces :**
- Consumes : classes `sys-*` (tâche 1), `<x-sys.notifications>` (tâche 4), `initCountUps` (tâche 3)
- Produces : layouts au nouveau style ; onglet `community` libellé **Crew** ; `#resume-banner` et `#offline-banner` conservés (mêmes `id`) ; `body` avec `data-user-id`.

- [ ] **Step 1 : mettre à jour le test (échec attendu)**

Dans `tests/Feature/LayoutTest.php`, remplacer le premier test par :
```php
it('affiche l\'accueil avec la navigation « Système » à 4 onglets', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Salut Élise')
        ->assertSeeInOrder(['Accueil', 'Programme', 'Crew', 'Profil'])
        ->assertSee('class="sys-backdrop"', false)
        ->assertSee('class="sys-nav"', false)
        ->assertSee('/manifest.json', false)
        ->assertSee('id="resume-banner"', false);
});
```
et dans le dataset des pages à venir, remplacer `['/community', 'classement']` par `['/community', 'Crew']`.

Run : `./vendor/bin/pest tests/Feature/LayoutTest.php`
Attendu : FAIL (pas de `Crew`, pas de `sys-backdrop`).

- [ ] **Step 2 : fond**

`resources/views/partials/backdrop.blade.php` :
```blade
<div class="sys-backdrop" aria-hidden="true"><div class="sys-grid"></div><div class="sys-halo"></div></div>
```

- [ ] **Step 3 : layout de l'application**

`resources/views/components/layouts/app.blade.php` :
```blade
@props(['title' => null, 'bare' => false])
@php
    $tabs = [
        ['home', 'Accueil', 'home', ['home']],
        ['program', 'Programme', 'program', ['program*']],
        ['community', 'Crew', 'community', ['community*']],
        ['profile', 'Profil', 'profile', ['profile*', 'history*', 'records*', 'progress*']],
    ];
@endphp
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="sys-body min-h-full antialiased" data-user-id="{{ auth()->id() }}">
    @include('partials.backdrop')

    <div id="offline-banner" hidden class="sys-banner sys-banner--amber">Hors ligne — ta séance sera synchronisée</div>

    @if ($bare)
        {{ $slot }}
    @else
        <div class="lg:flex">
            <nav class="sys-nav">
                <p class="sys-logo hidden px-6 py-8 text-2xl lg:block">EGO</p>
                <ul class="grid grid-cols-4 lg:grid-cols-1 lg:gap-1 lg:px-3">
                    @foreach ($tabs as [$route, $label, $icon, $patterns])
                        @php $active = request()->routeIs(...$patterns); @endphp
                        <li>
                            <a href="{{ route($route) }}" @class(['sys-nav-link', 'sys-nav-link--active' => $active]) @if ($active) aria-current="page" @endif>
                                @include('partials.nav-icon', ['name' => $icon])
                                <span>{{ $label }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <main class="relative mx-auto w-full max-w-[720px] px-4 pb-32 pt-[max(1.5rem,env(safe-area-inset-top))] lg:pb-12">
                <a id="resume-banner" hidden href="{{ route('workout') }}" class="sys-banner sys-banner--ice mb-5">Séance en cours — Reprendre</a>
                {{ $slot }}
            </main>
        </div>
    @endif

    <x-sys.notifications :items="$systemNotifications ?? []" />
    @livewireScriptConfig
</body>
</html>
```

- [ ] **Step 4 : layout invité**

`resources/views/components/layouts/guest.blade.php` :
```blade
@props(['title' => null])
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="sys-body flex min-h-full items-center justify-center px-4 antialiased">
    @include('partials.backdrop')
    <main class="w-full max-w-sm py-10">
        <p class="sys-logo mb-2 text-center text-5xl">EGO</p>
        <p class="sys-label mb-8 text-center">Système d'entraînement</p>
        {{ $slot }}
    </main>
    @livewireScriptConfig
</body>
</html>
```

- [ ] **Step 5 : compteurs au chargement**

Dans `resources/js/app.js` :
- ajouter l'import `import { initCountUps } from './ui/count-up';` ;
- dans le gestionnaire `DOMContentLoaded`, ajouter `initCountUps();` comme première instruction ;
- après `Livewire.start();`, ajouter :
```js
document.addEventListener('livewire:navigated', () => initCountUps());
```

- [ ] **Step 6 : vérifier**

Run : `npm run build && ./vendor/bin/pest && npm test`
Attendu : tout PASS.

- [ ] **Step 7 : commit**

```bash
git add -A
git commit -m "style: layouts « Système », fond holographique et navigation Crew

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6 : écrans de connexion et d'inscription

**Files :**
- Modify : `resources/views/livewire/auth/register.blade.php`, `resources/views/livewire/auth/login.blade.php`
- Test : `tests/Feature/Auth/RegisterTest.php`, `tests/Feature/Auth/LoginTest.php` (un test ajouté chacun)

**Interfaces :**
- Consumes : `<x-sys.window>`, `<x-sys.field>`, `<x-sys.button>`

- [ ] **Step 1 : tests (échec attendu)**

Ajouter à `tests/Feature/Auth/RegisterTest.php` :
```php
it('présente l\'inscription comme une fenêtre système ÉVEIL', function () {
    $this->get('/register')->assertSee('ÉVEIL')->assertSee('class="sys-window"', false)->assertSee('class="sys-input"', false);
});
```
Ajouter à `tests/Feature/Auth/LoginTest.php` :
```php
it('présente la connexion comme une fenêtre système IDENTIFICATION', function () {
    $this->get('/login')->assertSee('IDENTIFICATION')->assertSee('class="sys-window"', false);
});
```
Run : `./vendor/bin/pest tests/Feature/Auth`
Attendu : 2 FAIL.

- [ ] **Step 2 : vues**

`resources/views/livewire/auth/register.blade.php` :
```blade
<div>
    <x-sys.window title="ÉVEIL">
        <h1 class="mb-1 text-xl font-bold">Créer mon compte</h1>
        <p class="mb-5 text-sm text-mist">Le Système a besoin de ton identité pour t'assigner tes quêtes.</p>
        <form wire:submit="register" class="space-y-4" novalidate>
            <x-sys.field name="firstName" label="Prénom" wire:model="firstName" autocomplete="given-name" autocapitalize="words" />
            <x-sys.field name="birthDate" label="Date de naissance" type="date" wire:model="birthDate" autocomplete="bday" />
            <x-sys.button type="submit" :pulse="true" wire:loading.attr="disabled">C'est parti</x-sys.button>
        </form>
    </x-sys.window>
    <p class="mt-6 text-center text-sm text-mist">
        Déjà inscrit ? <a href="{{ route('login') }}" class="font-semibold text-ice">Se connecter</a>
    </p>
</div>
```

`resources/views/livewire/auth/login.blade.php` :
```blade
<div>
    <x-sys.window title="IDENTIFICATION">
        <h1 class="mb-1 text-xl font-bold">Connexion</h1>
        <p class="mb-5 text-sm text-mist">Déclare-toi au Système.</p>
        <form wire:submit="login" class="space-y-4" novalidate>
            <x-sys.field name="firstName" label="Prénom" wire:model="firstName" autocomplete="given-name" autocapitalize="words" />
            <x-sys.field name="birthDate" label="Date de naissance" type="date" wire:model="birthDate" autocomplete="bday" />
            <x-sys.button type="submit" :pulse="true" wire:loading.attr="disabled">Se connecter</x-sys.button>
        </form>
    </x-sys.window>
    <p class="mt-6 text-center text-sm text-mist">
        Pas encore de compte ? <a href="{{ route('register') }}" class="font-semibold text-ice">Créer un compte</a>
    </p>
</div>
```

- [ ] **Step 3 : vérifier**

Run : `npm run build && ./vendor/bin/pest tests/Feature/Auth`
Attendu : PASS (tous les tests d'authentification, anciens et nouveaux).

- [ ] **Step 4 : commit**

```bash
git add -A
git commit -m "style: connexion et inscription en fenêtres système

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7 : accueil, la « console du joueur »

**Files :**
- Modify : `resources/views/livewire/home.blade.php`
- Test : `tests/Feature/HomeTest.php` (un test ajouté)

**Interfaces :**
- Consumes : `$user`, `$lastWorkout`, `$recentRecords` (inchangés), `<x-sys.*>`, `data-count-up`

- [ ] **Step 1 : test (échec attendu)**

Ajouter à `tests/Feature/HomeTest.php` :
```php
it('affiche la console du joueur en fenêtres système avec un volume animé', function () {
    $bench = Exercise::firstWhere('slug', 'bench-press');
    app(WorkoutSync::class)->sync($this->user, [
        'client_id' => (string) Str::uuid(), 'started_at' => '2026-09-27T10:00:00Z', 'finished_at' => '2026-09-27T10:40:00Z',
        'sets' => [['exercise_id' => $bench->id, 'weight_kg' => 60, 'reps' => 8, 'done_at' => '2026-09-27T10:10:00Z']],
    ]);

    $this->get('/')
        ->assertSee('SYSTÈME EGO · EN LIGNE')
        ->assertSee('DERNIÈRE SÉANCE')
        ->assertSee('DERNIERS RECORDS')
        ->assertSee('data-count-up="480"', false);
});
```
Run : `./vendor/bin/pest tests/Feature/HomeTest.php`
Attendu : FAIL.

- [ ] **Step 2 : vue**

`resources/views/livewire/home.blade.php` :
```blade
<div class="space-y-5">
    <header>
        <x-sys.label>SYSTÈME EGO · EN LIGNE</x-sys.label>
        {{-- « Salut Prénom » reste d'un seul tenant (vérifié par LayoutTest) --}}
        <h1 class="mt-2 text-3xl font-bold leading-tight"><span class="text-ice [text-shadow:0_0_16px_var(--glow-ice)]">Salut {{ $user->first_name }}</span>.<br>Prêt à forger ?</h1>
    </header>

    <x-sys.button :href="route('workout')" :pulse="true" class="py-5 text-lg">Séance libre <span aria-hidden="true">→</span></x-sys.button>

    @if (! $user->sex || ! $user->weight_kg)
        <a href="{{ route('profile') }}" class="block">
            <x-sys.window title="CALIBRAGE REQUIS" :index="1">
                <p class="text-sm text-mist">Complète ton profil (sexe, taille, poids) pour que le Système te suggère tes charges de départ. <span class="text-ice">→</span></p>
            </x-sys.window>
        </a>
    @endif

    @if ($lastWorkout)
        <a href="{{ route('history.show', $lastWorkout) }}" class="block">
            <x-sys.window title="DERNIÈRE SÉANCE" :index="2">
                <p class="font-semibold">{{ ucfirst($lastWorkout->finished_at->translatedFormat('l j F')) }}</p>
                <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                    <div><x-sys.label>Durée</x-sys.label><p class="sys-num mt-1 text-lg font-bold">{{ \App\Support\Format::duration($lastWorkout->durationMinutes()) }}</p></div>
                    <div><x-sys.label>Séries</x-sys.label><p class="sys-num mt-1 text-lg font-bold">{{ $lastWorkout->sets->count() }}</p></div>
                    <div><x-sys.label>Volume</x-sys.label><p class="sys-num mt-1 text-lg font-bold text-ice" data-count-up="{{ $lastWorkout->volume() }}" data-suffix=" kg">{{ \App\Support\Format::kg($lastWorkout->volume()) }}</p></div>
                </div>
            </x-sys.window>
        </a>
    @endif

    <x-sys.window title="DERNIERS RECORDS" :index="3">
        @forelse ($recentRecords as $record)
            <div class="sys-row">
                <span>{{ $record->exercise->name }}</span>
                <span class="sys-num font-bold text-ice">{{ \App\Support\Format::kg($record->max_weight_kg) }} × {{ $record->max_weight_reps }}</span>
            </div>
        @empty
            <p class="text-sm text-mist">Tes records apparaîtront ici après ta première séance.</p>
        @endforelse
        <a href="{{ route('records') }}" class="sys-label mt-3 block text-right">Tout voir</a>
    </x-sys.window>
</div>
```

- [ ] **Step 3 : vérifier**

Run : `npm run build && ./vendor/bin/pest tests/Feature/HomeTest.php tests/Feature/LayoutTest.php`
Attendu : PASS. Les anciens tests vérifient encore « Séance libre », « Complète ton profil », « Dernière séance », « 40 min » et « 60 kg × 8 ».

> Note : « Dernière séance » est vérifié par l'ancien test de `HomeTest`, et la vue affiche maintenant `DERNIÈRE SÉANCE`, en majuscules. `assertSee` est sensible à la casse. Remplacer `->assertSee('Dernière séance')` par `->assertSee('DERNIÈRE SÉANCE')` dans le test « affiche la dernière séance et les derniers records ». C'est le seul changement de texte assumé, et il est voulu par la spec (§4.5).

- [ ] **Step 4 : commit**

```bash
git add -A
git commit -m "style: accueil en console du joueur

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 8 : écran de séance et notifications de fin de séance

**Files :**
- Modify : `app/Http/Controllers/WorkoutSyncController.php`, `resources/views/workout/run.blade.php` (réécriture), `resources/js/workout/component.js`
- Test : `tests/Feature/WorkoutSyncEndpointTest.php` (2 tests ajoutés), `tests/Feature/WorkoutPageTest.php` (1 test ajouté)

**Interfaces :**
- Consumes : `SystemMessage::workoutDone`, `SystemMessage::record`, `restProgress`
- Produces : la réponse de `/workouts/sync` contient en plus `notifications: list<Notification>`, `[workoutDone, ...records]` si `created`, et `[]` sinon. `component.js` expose `restPercent` (0 à 100) et déclenche `window` `sys-notify` avec `body.notifications`.

- [ ] **Step 1 : tests (échec attendu)**

Ajouter à `tests/Feature/WorkoutSyncEndpointTest.php` :
```php
it('renvoie les notifications système de fin de séance et de record', function () {
    $this->actingAs($this->user)->postJson('/workouts/sync', $this->body);
    $this->body['client_id'] = (string) Str::uuid();
    $this->body['sets'][0]['weight_kg'] = 62.5;

    $this->actingAs($this->user)->postJson('/workouts/sync', $this->body)
        ->assertJsonPath('notifications.0.title', 'SÉANCE TERMINÉE')
        ->assertJsonPath('notifications.1.title', 'NOUVEAU RECORD')
        ->assertJsonPath('notifications.1.highlight', 'NEW PR')
        ->assertJsonPath('notifications.1.lines.0', 'Développé couché (barre)');
});

it('ne renvoie aucune notification pour un envoi déjà enregistré', function () {
    $this->actingAs($this->user)->postJson('/workouts/sync', $this->body);

    $this->actingAs($this->user)->postJson('/workouts/sync', $this->body)->assertJsonPath('notifications', []);
});
```
Ajouter à `tests/Feature/WorkoutPageTest.php` :
```php
it('affiche l\'écran de séance au style Système', function () {
    $this->actingAs($this->user)->get('/workout')
        ->assertSee('class="sys-ring"', false)
        ->assertSee('VALIDER LA SÉRIE')
        ->assertSee('id="sys-notifications-data"', false);
});
```
Run : `./vendor/bin/pest tests/Feature/WorkoutSyncEndpointTest.php tests/Feature/WorkoutPageTest.php`
Attendu : 3 FAIL.

- [ ] **Step 2 : contrôleur**

Dans `app/Http/Controllers/WorkoutSyncController.php` :
- ajouter `use App\Support\SystemMessage;` ;
- dans le tableau JSON renvoyé, après la clé `'new_records' => …`, ajouter :
```php
            'notifications' => $created
                ? [SystemMessage::workoutDone($workout), ...array_map(fn (PersonalRecord $r) => SystemMessage::record($r), $records)]
                : [],
```

- [ ] **Step 3 : composant de séance**

Dans `resources/js/workout/component.js` :
- ajouter `restProgress` à l'import depuis `./store` ;
- ajouter le getter, juste après `get restRemaining()` :
```js
        get restPercent() {
            return Math.round(restProgress(this.state, this.now) * 100);
        },
```
- dans `finish()`, juste après l'affectation de `this.summary = …`, ajouter :
```js
            if (body?.notifications?.length) {
                window.dispatchEvent(new CustomEvent('sys-notify', { detail: body.notifications }));
            }
```

- [ ] **Step 4 : vue de séance**

`resources/views/workout/run.blade.php` :
```blade
<x-layouts.app title="Séance" :bare="true">
    {{-- JSON_UNESCAPED_UNICODE : noms lisibles (« Développé ») ; JSON_HEX_TAG : impossible de fermer la balise script --}}
    <script type="application/json" id="workout-boot">{!! json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_THROW_ON_ERROR) !!}</script>

    <div x-data="workout" x-cloak class="mx-auto flex min-h-screen max-w-xl flex-col px-4 pb-36 pt-[max(1rem,env(safe-area-inset-top))]">
        <header class="flex items-center justify-between gap-3 py-2">
            <a href="{{ route('home') }}" class="sys-label">Base</a>
            <span class="sys-num text-sm text-ice [text-shadow:0_0_10px_var(--glow-ice)]">● <span x-text="elapsed"></span></span>
            <button type="button" class="sys-btn !w-auto !px-4 !py-2 text-xs" x-show="!summary" :disabled="!canFinish || finishing" @click="finish()">
                <span x-text="finishing ? 'Envoi…' : 'Terminer'"></span>
            </button>
        </header>

        {{-- Fin de séance --}}
        <template x-if="summary">
            <section class="mt-6 space-y-4">
                <template x-if="!summary.pending">
                    <div class="sys-window">
                        <header class="sys-window-title"><span class="sys-window-icon" aria-hidden="true">!</span>SÉANCE ENREGISTRÉE</header>
                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div><p class="sys-label">Durée</p><p class="sys-num mt-1 text-lg font-bold" x-text="summary.duration_minutes + ' min'"></p></div>
                            <div><p class="sys-label">Séries</p><p class="sys-num mt-1 text-lg font-bold" x-text="summary.sets_count"></p></div>
                            <div><p class="sys-label">Volume</p><p class="sys-num mt-1 text-lg font-bold text-ice" x-text="formatKg(summary.volume_kg)"></p></div>
                        </div>
                    </div>
                </template>
                <template x-if="summary.pending">
                    <div class="sys-window" :class="summary.invalid && 'sys-window--alert'">
                        <header class="sys-window-title"><span class="sys-window-icon" aria-hidden="true">!</span>EN ATTENTE</header>
                        <p class="text-sm text-mist" x-show="!summary.invalid">Séance enregistrée sur ton téléphone. Elle sera envoyée dès que la connexion revient.</p>
                        <p class="text-sm text-mist" x-show="summary.invalid">Le serveur a refusé cette séance. Elle reste enregistrée sur ton téléphone.</p>
                    </div>
                </template>
                <a href="{{ route('home') }}" class="sys-btn">Retour à la base</a>
            </section>
        </template>

        {{-- Séance --}}
        <template x-if="!summary">
            <section class="mt-2 flex flex-1 flex-col gap-4">
                <nav class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1" x-show="state.exercises.length">
                    <template x-for="(ex, i) in state.exercises" :key="i">
                        <button type="button" @click="go(i)" class="sys-chip" :class="i === state.currentIndex && 'sys-chip--active'">
                            <span x-text="ex.name"></span><span class="ml-1" x-text="ex.sets.length ? '(' + ex.sets.length + ')' : ''"></span>
                        </button>
                    </template>
                </nav>

                <template x-if="current && !picking">
                    <div class="sys-window space-y-4">
                        <div>
                            <h1 class="text-2xl font-bold leading-tight" x-text="current.name"></h1>
                            <p class="sys-num mt-1 text-xs uppercase tracking-wider text-steel" x-show="lastTimeText(current)">
                                Dernière fois · <span x-text="lastTimeText(current)"></span>
                            </p>
                            <p class="mt-1 text-sm text-mist" x-show="current.isBodyweight">Poids du corps — indique la charge ajoutée (0 si aucune).</p>
                        </div>

                        <ol class="space-y-1.5" x-show="current.sets.length">
                            <template x-for="(set, i) in current.sets" :key="i">
                                <li class="sys-num flex justify-between bg-white/[.04] px-3 py-2 text-sm [clip-path:var(--sys-cut)]">
                                    <span class="text-steel" x-text="'SÉRIE ' + String(i + 1).padStart(2, '0')"></span>
                                    <span class="font-bold text-ice" x-text="formatKg(set.weightKg) + ' × ' + set.reps"></span>
                                </li>
                            </template>
                        </ol>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="sys-label mb-1 block text-center" for="draft-weight">Charge kg</label>
                                <div class="flex items-center gap-1">
                                    <button type="button" class="sys-step" @click="adjust('weightKg', -2.5)" aria-label="Moins 2,5 kg">−</button>
                                    <input id="draft-weight" type="text" inputmode="decimal" class="sys-input sys-num min-w-0 px-1 text-center text-xl font-extrabold" x-model="current.draft.weightKg">
                                    <button type="button" class="sys-step" @click="adjust('weightKg', 2.5)" aria-label="Plus 2,5 kg">+</button>
                                </div>
                            </div>
                            <div>
                                <label class="sys-label mb-1 block text-center" for="draft-reps">Reps</label>
                                <div class="flex items-center gap-1">
                                    <button type="button" class="sys-step" @click="adjust('reps', -1)" aria-label="Une répétition de moins">−</button>
                                    <input id="draft-reps" type="text" inputmode="numeric" class="sys-input sys-num min-w-0 px-1 text-center text-xl font-extrabold" x-model="current.draft.reps">
                                    <button type="button" class="sys-step" @click="adjust('reps', 1)" aria-label="Une répétition de plus">+</button>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="sys-btn sys-btn--pulse py-4 text-lg" @click="validateSet()">VALIDER LA SÉRIE</button>
                        <button type="button" class="sys-label w-full text-center" x-show="current.sets.length" @click="undoSet()">Annuler la dernière série</button>
                    </div>
                </template>

                <template x-if="picking">
                    <div class="sys-window space-y-3">
                        <header class="sys-window-title"><span class="sys-window-icon" aria-hidden="true">!</span>CHOISIR UN EXERCICE</header>
                        <div class="flex justify-end" x-show="state.exercises.length">
                            <button type="button" class="sys-label" @click="picking = false">Fermer</button>
                        </div>
                        <input type="search" class="sys-input" placeholder="Rechercher (ex. squat)" x-model="search">
                        <ul class="max-h-[50vh] overflow-y-auto">
                            <template x-for="ex in filtered" :key="ex.id">
                                <li class="sys-row">
                                    <button type="button" class="flex w-full items-center justify-between py-1 text-left" @click="add(ex)">
                                        <span x-text="ex.name"></span>
                                        <span class="sys-label" x-show="boot.lastTime[ex.id]">déjà fait</span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>

                <button type="button" class="sys-btn sys-btn--ghost" x-show="!picking" @click="picking = true">+ Ajouter un exercice</button>

                <div class="mt-auto pt-6 text-center">
                    <button type="button" class="text-sm text-alert" @click="discard()"
                            x-text="confirmDiscard ? 'Confirmer l\'abandon (la séance sera perdue)' : 'Abandonner la séance'"></button>
                </div>
            </section>
        </template>

        {{-- Minuteur de repos --}}
        <div x-show="!summary && restRemaining > 0" x-transition.opacity
             class="fixed inset-x-3 bottom-[max(0.75rem,env(safe-area-inset-bottom))] z-30 mx-auto max-w-xl">
            <div class="sys-window flex items-center gap-4 !p-3">
                <div class="sys-ring" :style="`--p: ${restPercent}%`"><span x-text="clock(restRemaining)"></span></div>
                <div class="flex-1">
                    <p class="sys-label">Repos</p>
                    <p class="mt-0.5 text-sm text-mist">Prochaine série en préparation</p>
                    <div class="mt-2 flex gap-2">
                        <button type="button" class="sys-btn sys-btn--ghost !w-auto !px-3 !py-2 text-xs" @click="moreRest()">+30 s</button>
                        <button type="button" class="sys-btn !w-auto !px-3 !py-2 text-xs" @click="skipRest()">Passer</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
```

- [ ] **Step 5 : vérifier**

Run : `npm run build && npm test && ./vendor/bin/pest`
Attendu : tout PASS.

- [ ] **Step 6 : commit**

```bash
git add -A
git commit -m "feat: écran de séance « Système » et notifications de fin de séance et de record

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 9 : historique, détail d'une séance, records et progression

**Files :**
- Modify : `resources/views/livewire/history.blade.php`, `workout-show.blade.php`, `records.blade.php`, `progress.blade.php`
- Test : `tests/Feature/LogbookPagesTest.php` (un test ajouté)

**Interfaces :**
- Consumes : données des composants (inchangées), `Sparkline::points`

- [ ] **Step 1 : test (échec attendu)**

Ajouter à `tests/Feature/LogbookPagesTest.php` :
```php
it('présente le carnet en fenêtres système avec un graphique néon', function () {
    $this->get('/history')->assertSee('JOURNAL DES SÉANCES');
    $this->get('/records')->assertSee('TABLEAU DES RECORDS');
    $this->get("/progress/{$this->bench->id}")->assertSee('filter="url(#neon)"', false)->assertSee('<circle', false);
});
```
Run : `./vendor/bin/pest tests/Feature/LogbookPagesTest.php`
Attendu : FAIL.

- [ ] **Step 2 : vues**

`resources/views/livewire/history.blade.php` :
```blade
<div class="space-y-4">
    <x-sys.label>Historique</x-sys.label>
    <x-sys.window title="JOURNAL DES SÉANCES">
        @forelse ($workouts as $workout)
            <a href="{{ route('history.show', $workout) }}" class="sys-row block">
                <div class="min-w-0">
                    <p class="font-semibold">{{ ucfirst($workout->finished_at->translatedFormat('l j F')) }}</p>
                    <p class="truncate text-sm text-steel">{{ $workout->sets->pluck('exercise.name')->unique()->join(', ') }}</p>
                </div>
                <div class="shrink-0 text-right">
                    <p class="sys-num font-bold text-ice">{{ \App\Support\Format::kg($workout->volume()) }}</p>
                    <p class="sys-num text-xs text-steel">{{ \App\Support\Format::duration($workout->durationMinutes()) }} · {{ $workout->sets->count() }} séries</p>
                </div>
            </a>
        @empty
            <p class="text-mist">Aucune séance pour l'instant.</p>
        @endforelse
    </x-sys.window>
    {{ $workouts->links() }}
</div>
```

`resources/views/livewire/workout-show.blade.php` :
```blade
<div class="space-y-4">
    <a href="{{ route('history') }}" class="sys-label">Historique</a>
    <h1 class="text-2xl font-bold">{{ ucfirst($workout->finished_at->translatedFormat('l j F Y')) }}</h1>
    <div class="grid grid-cols-2 gap-3">
        <x-sys.window :index="0"><x-sys.label>Durée</x-sys.label><p class="sys-num mt-1 text-xl font-bold">{{ \App\Support\Format::duration($workout->durationMinutes()) }}</p></x-sys.window>
        <x-sys.window :index="1"><x-sys.label>Volume</x-sys.label><p class="sys-num mt-1 text-xl font-bold text-ice" data-count-up="{{ $workout->volume() }}" data-suffix=" kg">{{ \App\Support\Format::kg($workout->volume()) }}</p></x-sys.window>
    </div>
    @foreach ($groups as $sets)
        <x-sys.window :index="$loop->index + 2">
            <a href="{{ route('progress', $sets->first()->exercise) }}" class="font-semibold">{{ $sets->first()->exercise->name }} <span class="text-ice">→</span></a>
            <ol class="mt-2">
                @foreach ($sets as $set)
                    <li class="sys-row sys-num text-sm">
                        <span class="text-steel">SÉRIE {{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <span class="font-bold">{{ \App\Support\Format::kg($set->weight_kg) }} × {{ $set->reps }}</span>
                    </li>
                @endforeach
            </ol>
        </x-sys.window>
    @endforeach
</div>
```

`resources/views/livewire/records.blade.php` :
```blade
<div class="space-y-4">
    <x-sys.label>Records</x-sys.label>
    <x-sys.window title="TABLEAU DES RECORDS">
        @forelse ($records as ['record' => $record, 'ratio' => $ratio])
            <a href="{{ route('progress', $record->exercise) }}" class="sys-row">
                <div>
                    <p class="font-semibold">{{ $record->exercise->name }}</p>
                    <p class="sys-num text-xs text-steel">1RM estimé : {{ \App\Support\Format::kg($record->best_e1rm_kg) }}</p>
                </div>
                <div class="text-right">
                    <p class="sys-num font-bold text-ice">{{ \App\Support\Format::kg($record->max_weight_kg) }} × {{ $record->max_weight_reps }}</p>
                    @if ($ratio)
                        <p class="sys-num text-xs text-steel">{{ number_format($ratio, 2, ',', ' ') }} × PDC</p>
                    @endif
                </div>
            </a>
        @empty
            <p class="text-mist">Termine une séance pour voir tes records.</p>
        @endforelse
    </x-sys.window>
</div>
```

`resources/views/livewire/progress.blade.php` :
```blade
<div class="space-y-4">
    <a href="{{ route('records') }}" class="sys-label">Records</a>
    <h1 class="text-2xl font-bold">{{ $exercise->name }}</h1>
    @if ($rows->isEmpty())
        <x-sys.window><p class="text-mist">Pas encore de données pour cet exercice.</p></x-sys.window>
    @else
        <x-sys.window title="CHARGE MAX. PAR SÉANCE">
            <svg viewBox="0 0 320 140" class="h-44 w-full overflow-visible" role="img" aria-label="Progression de la charge maximale">
                <defs>
                    <filter id="neon" x="-20%" y="-20%" width="140%" height="140%"><feGaussianBlur stdDeviation="3" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
                </defs>
                <polyline points="{{ $points }}" fill="none" stroke="#5ad8ff" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" filter="url(#neon)"/>
                @foreach (explode(' ', $points) as $point)
                    @php [$cx, $cy] = explode(',', $point); @endphp
                    <circle cx="{{ $cx }}" cy="{{ $cy }}" r="3.5" fill="#04060a" stroke="#5ad8ff" stroke-width="2"/>
                @endforeach
            </svg>
            <div class="sys-num mt-2 flex justify-between text-xs text-steel">
                <span>{{ $rows->first()['date']->translatedFormat('j M') }}</span>
                <span>{{ $rows->last()['date']->translatedFormat('j M') }}</span>
            </div>
        </x-sys.window>
        <x-sys.window :index="1">
            @foreach ($rows->reverse() as $row)
                <div class="sys-row text-sm">
                    <span class="text-steel">{{ $row['date']->translatedFormat('D j M') }}</span>
                    <span class="sys-num font-bold">{{ \App\Support\Format::kg($row['kg']) }}</span>
                </div>
            @endforeach
        </x-sys.window>
    @endif
</div>
```

- [ ] **Step 3 : vérifier**

Run : `npm run build && ./vendor/bin/pest tests/Feature/LogbookPagesTest.php`
Attendu : PASS. Les anciens tests vérifient encore « Dimanche 27 septembre », « 325 kg », « 65 kg × 5 », « 0,81 × PDC », `<polyline` et « Pas encore de données ».

- [ ] **Step 4 : commit**

```bash
git add -A
git commit -m "style: carnet (historique, détail, records, progression) en fenêtres système

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 10 : fiche du joueur (profil) et pages « bientôt »

**Files :**
- Modify : `resources/views/livewire/profile.blade.php`, `resources/views/pages/soon.blade.php`, `routes/web.php` (message de la page Crew)
- Test : `tests/Feature/ProfileTest.php` (un test ajouté)

**Interfaces :**
- Consumes : `Profile` (propriétés inchangées : `$sex`, `$heightCm`, `$weightKg`, `$saved`)

- [ ] **Step 1 : test (échec attendu)**

Ajouter à `tests/Feature/ProfileTest.php` :
```php
it('présente le profil comme une fiche du joueur', function () {
    $this->get('/profile')->assertSee('FICHE DU JOUEUR')->assertSee('MORPHOLOGIE')->assertSee('class="sys-input"', false);
});
```
Run : `./vendor/bin/pest tests/Feature/ProfileTest.php`
Attendu : FAIL.

- [ ] **Step 2 : vues**

`resources/views/livewire/profile.blade.php` :
```blade
<div class="space-y-5">
    <x-sys.window title="FICHE DU JOUEUR">
        <div class="flex items-center gap-4">
            <span class="grid h-16 w-16 place-items-center text-3xl font-black text-void [clip-path:var(--sys-cut)]" style="background: {{ $user->avatar_color }}; box-shadow: 0 0 24px {{ $user->avatar_color }}">
                {{ mb_strtoupper(mb_substr($user->first_name, 0, 1)) }}
            </span>
            <div>
                <h1 class="text-2xl font-bold">{{ $user->first_name }}</h1>
                <p class="sys-num text-sm text-steel">{{ $user->birth_date->age }} ans</p>
            </div>
        </div>
    </x-sys.window>

    <div class="grid grid-cols-2 gap-3">
        <x-sys.button :href="route('history')" variant="ghost">Historique</x-sys.button>
        <x-sys.button :href="route('records')" variant="ghost">Records</x-sys.button>
    </div>

    <x-sys.window title="MORPHOLOGIE" :index="1">
        <form wire:submit="save" class="space-y-4" novalidate>
            <div>
                <p class="sys-label mb-1">Sexe</p>
                <div class="grid grid-cols-2 gap-2">
                    @foreach (['male' => 'Homme', 'female' => 'Femme'] as $value => $label)
                        <label @class(['sys-chip cursor-pointer py-3 text-center text-sm', 'sys-chip--active' => $sex === $value])>
                            <input type="radio" wire:model.live="sex" value="{{ $value }}" class="sr-only">{{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('sex') <p class="sys-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <x-sys.field name="heightCm" label="Taille (cm)" inputmode="numeric" wire:model="heightCm" />
                <x-sys.field name="weightKg" label="Poids (kg)" inputmode="decimal" wire:model="weightKg" />
            </div>
            @if ($user->bmi())
                <p class="sys-num text-sm text-mist">IMC indicatif : {{ number_format($user->bmi(), 1, ',', ' ') }}</p>
            @endif
            <x-sys.button type="submit">Enregistrer</x-sys.button>
            @if ($saved) <p class="text-center text-sm text-ice">Profil enregistré ✓</p> @endif
        </form>
    </x-sys.window>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <x-sys.button type="submit" variant="ghost">Se déconnecter</x-sys.button>
    </form>
</div>
```

`resources/views/pages/soon.blade.php` :
```blade
<x-layouts.app :title="$title">
    <x-sys.label>{{ $title }}</x-sys.label>
    <x-sys.window title="ACCÈS VERROUILLÉ" class="mt-3">
        <p class="text-mist">{{ $message }}</p>
    </x-sys.window>
</x-layouts.app>
```

Dans `routes/web.php`, pour la route `community`, remplacer `'title' => 'Communauté'` par `'title' => 'Crew'` et le message par `'Le Crew débarque bientôt : records des amis, classement, défis et groupes.'`.

> Le message contient « classement », donc l'ancien test reste vrai, et « Crew », ce qui satisfait le test mis à jour à la tâche 5.

- [ ] **Step 3 : vérifier**

Run : `npm run build && ./vendor/bin/pest`
Attendu : tout PASS.

- [ ] **Step 4 : commit**

```bash
git add -A
git commit -m "style: fiche du joueur et pages verrouillées

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 11 : vérification finale

- [ ] **Step 1 : suites et compilation**

Run : `npm test && npm run build && ./vendor/bin/pest`
Attendu : tout PASS.

- [ ] **Step 2 : simulation de séance (jsdom, jetable, dans le dossier temporaire)**

Rejouer la simulation de l'étape 1 sur le nouveau bundle et le HTML de `/workout` :
- ajout d'un exercice, +2,5, validation d'une série ;
- l'anneau de repos a un `--p` compris entre 0 et 100 ;
- « Terminer » avec un faux `fetch` qui renvoie `notifications` à 2 éléments.

Attendu : aucune erreur JS, puis un `sys-overlay` affiché avec « SÉANCE TERMINÉE » ; un clic dessus affiche « NOUVEAU RECORD », un second clic le ferme.

- [ ] **Step 3 : vérification visuelle**

`php artisan serve`, puis vérifier dans le navigateur, en vue mobile 360 px et en vue PC :
- connexion, accueil, séance, notifications, historique, records, progression, profil, Crew ;
- avec « réduire les animations » (DevTools → Rendering → `prefers-reduced-motion`) : plus aucune animation, tout reste lisible.

Si l'extension navigateur n'est pas disponible, demander à l'utilisateur de faire cette vérification.
