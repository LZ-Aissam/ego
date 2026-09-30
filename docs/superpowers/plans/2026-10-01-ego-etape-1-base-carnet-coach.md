# EGO — Étape 1 : base, carnet et séance guidée — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal :** livrer une première version utilisable d'EGO. On s'inscrit avec son prénom et sa date de naissance, on fait une séance libre guidée (minuteur, charges de la dernière fois, charges suggérées) qui fonctionne hors ligne, puis on consulte son historique, ses records et sa progression.

**Architecture :** application Laravel 12 monolithique. Les pages sont des composants Livewire 3 ; l'écran de séance est un composant Alpine.js autonome qui garde l'état dans `localStorage` et envoie la séance terminée à une route JSON idempotente. La logique métier vit dans des services PHP purs (`app/Services`, `app/Support`) testés avec Pest.

**Tech Stack :** PHP 8.4, Laravel 12, Livewire 3, Alpine.js (fourni par Livewire), Tailwind CSS 4 + Vite, SQLite (local et tests) / MySQL (production), Pest 3, Vitest.

**Spec :** `docs/superpowers/specs/2026-10-01-ego-design.md`

## Global Constraints

- Interface entièrement en français ; charges en kg ; fuseau horaire `Europe/Paris`.
- PHP 8.4, Laravel `^12.0`, Livewire `^3.6` (versions épinglées, ne pas monter en version majeure).
- Connexion par prénom + date de naissance uniquement : la table `users` n'a ni email ni mot de passe.
- Clé d'identité = prénom normalisé (minuscules, sans accents, espaces fusionnés) + `|` + date `Y-m-d`.
- Limite de connexion : 5 essais par minute et par IP.
- Aucun worker de file d'attente ni cron obligatoire (compatibilité hébergement mutualisé).
- Les assets sont compilés en local (`npm run build`) ; le serveur n'a pas besoin de Node.
- Mobile-first ; à partir de 1024 px, barre latérale et contenu centré (max. ~720 px).
- Pas de 2,5 kg pour les charges, de 1 pour les répétitions ; charge ≥ 0, répétitions ≥ 1 ; taille 100–250 cm, poids 30–300 kg.
- 1RM estimé (Epley) : `charge × (1 + reps / 30)`, et `charge` si reps = 1.
- Exercice au poids du corps : charge saisie = charge additionnelle ; volume = (poids de corps de la séance + charge) × reps, avec un poids de corps inconnu compté comme 0.
- Commits terminés par la ligne `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **Test sur téléphone via l'IP locale en `http://`** : `crypto.randomUUID` n'existe pas hors contexte sécurisé. La séance doit quand même démarrer (repli `uuid()` avec `getRandomValues`) — test dans la tâche 9.
2. **Double envoi d'une séance** (double appui sur « Terminer », renvoi après une coupure) : une seule séance en base — test d'idempotence dans les tâches 7 et 8.
3. **Session expirée pendant une longue séance (419/401)** : la séance reste en attente et repart au chargement de page suivant — test dans la tâche 9 (`sendWorkout` → `retry`).
4. **Virgule décimale** (« 62,5 ») saisie sur un clavier français : elle est interprétée comme 62.5 — test dans la tâche 9 (`toNumber`).
5. **Même prénom écrit différemment** (« Élise » / « elise » / « ELISE ») : c'est la même personne ; l'inscription en double est refusée et la connexion fonctionne — tests dans les tâches 2 et 3.

---

## Structure des fichiers

```
app/
  Http/Controllers/WorkoutController.php       page de séance (données de démarrage)
  Http/Controllers/WorkoutSyncController.php   POST /workouts/sync
  Http/Controllers/LogoutController.php
  Http/Requests/SyncWorkoutRequest.php
  Livewire/Auth/ValidatesIdentity.php          règles et messages communs
  Livewire/Auth/Register.php, Login.php
  Livewire/Home.php, History.php, WorkoutShow.php, Records.php, Progress.php, Profile.php
  Models/User.php, Exercise.php, Workout.php, WorkoutSet.php, PersonalRecord.php
  Services/RecordCalculator.php                1RM, volume, ratio, comparaison de records
  Services/StartingLoad.php                    charges de départ suggérées
  Services/WorkoutSync.php                     enregistrement idempotent + records
  Services/WorkoutBootData.php                 données JSON de l'écran de séance
  Support/IdentityKey.php                      normalisation prénom + date
  Support/Format.php                           affichage des kg et durées
  Support/Sparkline.php                        points SVG du graphique de progression
database/
  data/exercises.php                           bibliothèque (~60 exercices)
  migrations/…                                 users, exercises, workouts, workout_sets, personal_records
  seeders/ExerciseSeeder.php, DatabaseSeeder.php
  factories/UserFactory.php
resources/
  css/app.css
  js/app.js                                    Livewire + Alpine, synchro globale, bandeaux
  js/workout/store.js                          état de séance (fonctions pures) + stockage
  js/workout/sync.js                           envoi et file d'attente
  js/workout/component.js                      composant Alpine « workout »
  views/components/layouts/app.blade.php, guest.blade.php
  views/partials/head.blade.php, nav-icon.blade.php
  views/livewire/…                             vues des composants
  views/workout/run.blade.php
  views/pages/soon.blade.php
public/manifest.json, public/sw.js, public/icons/…
scripts/make-icons.php
tests/Unit/…, tests/Feature/…, resources/js/workout/*.test.js
```

---

### Task 1 : Environnement et squelette Laravel

**Files :**
- Modify : `C:\Users\theai\php\php.ini` (extensions)
- Create : tout le squelette Laravel à la racine de `C:\Users\theai\Documents\EGO`
- Modify : `config/app.php`, `.env`, `.env.example`, `phpunit.xml`, `bootstrap/app.php`, `routes/web.php`, `config/livewire.php`
- Delete : `resources/views/welcome.blade.php`
- Test : `tests/Feature/SmokeTest.php`

**Interfaces :**
- Produces : projet Laravel 12 avec Pest, Livewire 3 (assets intégrés manuellement), locale `fr`, fuseau `Europe/Paris`, SQLite.

- [ ] **Step 1 : Activer les extensions PHP SQLite et MySQL**

```bash
sed -i 's/^;extension=pdo_sqlite/extension=pdo_sqlite/; s/^;extension=sqlite3/extension=sqlite3/; s/^;extension=pdo_mysql/extension=pdo_mysql/' /c/Users/theai/php/php.ini
php -m | grep -iE "pdo_sqlite|pdo_mysql|sqlite3"
```
Attendu : les trois lignes `pdo_mysql`, `pdo_sqlite`, `sqlite3`. Si une ligne manque, ouvrir `php.ini`, chercher le nom de l'extension et retirer le `;` en début de ligne.

- [ ] **Step 2 : Créer le squelette Laravel dans un dossier temporaire puis le remonter à la racine** (le dossier contient déjà `docs/` et `.git/`, et `create-project` exige un dossier vide)

```bash
cd /c/Users/theai/Documents/EGO
composer create-project "laravel/laravel:^12.0" _scaffold --no-interaction
cp -a _scaffold/. .
rm -rf _scaffold
composer dump-autoload
php artisan --version
```
Attendu : `Laravel Framework 12.x.x`.

- [ ] **Step 3 : Installer Pest, Livewire et Vitest**

```bash
composer remove phpunit/phpunit --dev --no-interaction
composer require pestphp/pest pestphp/pest-plugin-laravel --dev -W --no-interaction
./vendor/bin/pest --init
composer require "livewire/livewire:^3.6" --no-interaction
php artisan livewire:publish --config
npm install
npm install -D vitest
```

- [ ] **Step 4 : Configurer la locale, le fuseau, la base de test et Livewire**

Dans `config/app.php`, remplacer `'timezone' => 'UTC',` par :
```php
    'timezone' => env('APP_TIMEZONE', 'Europe/Paris'),
```

Dans `.env` **et** `.env.example`, fixer ces valeurs (remplacer les lignes existantes) :
```dotenv
APP_NAME=EGO
APP_LOCALE=fr
APP_FALLBACK_LOCALE=fr
APP_FAKER_LOCALE=fr_FR
APP_TIMEZONE=Europe/Paris
```

Dans `phpunit.xml`, vérifier que ces deux lignes existent **sans commentaire** dans `<php>` (les décommenter si besoin) :
```xml
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
```

Dans `config/livewire.php`, mettre :
```php
    'inject_assets' => false,
```

Dans `package.json`, ajouter dans `"scripts"` :
```json
        "test": "vitest run"
```

Créer `vitest.config.js` (limite les tests à nos fichiers, sans parcourir `vendor/`) :
```js
import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: { include: ['resources/js/**/*.test.js'] },
});
```

- [ ] **Step 5 : Configurer les redirections d'authentification**

Dans `bootstrap/app.php`, remplacer le bloc `->withMiddleware(...)` par :
```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home'));
    })
```

- [ ] **Step 6 : Configurer Pest pour les tests fonctionnels**

Remplacer le contenu de `tests/Pest.php` par :
```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
```
Supprimer les exemples : `rm -f tests/Feature/ExampleTest.php tests/Unit/ExampleTest.php`.

- [ ] **Step 7 : Écrire le test de fumée**

`tests/Feature/SmokeTest.php` :
```php
<?php

it('répond sur la route de santé', function () {
    $this->get('/up')->assertOk();
});

it('utilise le français et le fuseau de Paris', function () {
    expect(app()->getLocale())->toBe('fr')
        ->and(config('app.timezone'))->toBe('Europe/Paris');
});
```

- [ ] **Step 8 : Vider les routes par défaut**

`routes/web.php` :
```php
<?php

use Illuminate\Support\Facades\Route;
```
`rm resources/views/welcome.blade.php`

- [ ] **Step 9 : Lancer les tests**

Run : `./vendor/bin/pest`
Attendu : 2 tests PASS.

- [ ] **Step 10 : Commit**

```bash
git add -A
git commit -m "chore: squelette Laravel 12 + Pest + Livewire 3 + Vitest

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2 : Utilisateur et clé d'identité

**Files :**
- Create : `app/Support/IdentityKey.php`
- Modify : `database/migrations/0001_01_01_000000_create_users_table.php`, `app/Models/User.php`, `database/factories/UserFactory.php`, `app/Providers/AppServiceProvider.php`
- Test : `tests/Unit/IdentityKeyTest.php`, `tests/Feature/UserModelTest.php`

**Interfaces :**
- Produces :
  - `App\Support\IdentityKey::normalizeFirstName(string $firstName): string`
  - `App\Support\IdentityKey::displayFirstName(string $firstName): string`
  - `App\Support\IdentityKey::make(string $firstName, string $birthDate): string` → `"elise|2001-04-12"`
  - `App\Models\User` : colonnes du §6 de la spec ; `User::AVATAR_COLORS` (list<string>) ; `bmi(): ?float` ; relations `workouts()`, `personalRecords()` (ajoutées à la tâche 7)
  - `UserFactory` avec l'état `withMorphology(string $sex = 'male', int $heightCm = 180, float $weightKg = 80.0, string $level = 'beginner')`

- [ ] **Step 1 : Écrire le test unitaire qui échoue**

`tests/Unit/IdentityKeyTest.php` :
```php
<?php

use App\Support\IdentityKey;

it('normalise le prénom : minuscules, sans accents, espaces fusionnés', function (string $input, string $expected) {
    expect(IdentityKey::normalizeFirstName($input))->toBe($expected);
})->with([
    ['Élise', 'elise'],
    ['ELISE', 'elise'],
    ['  élise  ', 'elise'],
    ['Jean   Marc', 'jean marc'],
    ['Zoë', 'zoe'],
    ['José', 'jose'],
]);

it('construit la clé prénom|date', function () {
    expect(IdentityKey::make('Élise', '2001-04-12'))->toBe('elise|2001-04-12');
});

it('donne la même clé quelle que soit la casse ou les accents', function () {
    expect(IdentityKey::make('ELISE ', '2001-04-12'))
        ->toBe(IdentityKey::make('élise', '2001-04-12'));
});

it('garde le prénom affiché tel que saisi, espaces nettoyés', function () {
    expect(IdentityKey::displayFirstName('  Jean   Marc '))->toBe('Jean Marc');
});
```

- [ ] **Step 2 : Vérifier l'échec**

Run : `./vendor/bin/pest tests/Unit/IdentityKeyTest.php`
Attendu : FAIL, `Class "App\Support\IdentityKey" not found`.

- [ ] **Step 3 : Implémenter `IdentityKey`**

`app/Support/IdentityKey.php` :
```php
<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class IdentityKey
{
    public static function normalizeFirstName(string $firstName): string
    {
        return Str::lower(Str::squish(Str::ascii($firstName)));
    }

    public static function displayFirstName(string $firstName): string
    {
        return Str::squish($firstName);
    }

    public static function make(string $firstName, string $birthDate): string
    {
        return self::normalizeFirstName($firstName).'|'.Carbon::parse($birthDate)->format('Y-m-d');
    }
}
```

- [ ] **Step 4 : Vérifier que le test passe**

Run : `./vendor/bin/pest tests/Unit/IdentityKeyTest.php`
Attendu : PASS (9 tests).

- [ ] **Step 5 : Réécrire la migration `users`**

Remplacer tout le contenu de `database/migrations/0001_01_01_000000_create_users_table.php` par :
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 50);
            $table->string('identity_key')->unique();
            $table->date('birth_date');
            $table->string('avatar_color', 7);
            $table->string('sex', 6)->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->decimal('weight_kg', 5, 1)->nullable();
            $table->string('level', 12)->nullable();
            $table->string('goal', 12)->nullable();
            $table->unsignedTinyInteger('days_per_week')->nullable();
            $table->string('equipment', 10)->nullable();
            $table->unsignedBigInteger('active_program_id')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
```

- [ ] **Step 6 : Écrire le test du modèle qui échoue**

`tests/Feature/UserModelTest.php` :
```php
<?php

use App\Models\User;

it('crée un utilisateur via la factory avec une clé cohérente', function () {
    $user = User::factory()->create(['first_name' => 'Élise', 'birth_date' => '2001-04-12']);

    expect($user->identity_key)->toBe('elise|2001-04-12')
        ->and($user->avatar_color)->toBeIn(User::AVATAR_COLORS);
});

it('calcule l\'IMC quand taille et poids sont connus', function () {
    $user = User::factory()->withMorphology(heightCm: 180, weightKg: 81.0)->make();

    expect($user->bmi())->toBe(25.0);
});

it('renvoie null pour l\'IMC sans morphologie', function () {
    expect(User::factory()->make()->bmi())->toBeNull();
});

it('ne sérialise pas la clé d\'identité', function () {
    expect(User::factory()->make()->toArray())->not->toHaveKey('identity_key');
});
```

- [ ] **Step 7 : Vérifier l'échec**

Run : `./vendor/bin/pest tests/Feature/UserModelTest.php`
Attendu : FAIL (colonne `name` / `password` attendue par l'ancienne factory, ou `withMorphology` inexistante).

- [ ] **Step 8 : Réécrire le modèle `User`**

`app/Models/User.php` :
```php
<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    public const AVATAR_COLORS = ['#f97316', '#22c55e', '#3b82f6', '#a855f7', '#ec4899', '#eab308', '#14b8a6', '#ef4444'];

    protected $fillable = [
        'first_name', 'identity_key', 'birth_date', 'avatar_color',
        'sex', 'height_cm', 'weight_kg', 'level', 'goal', 'days_per_week', 'equipment',
    ];

    protected $hidden = ['identity_key', 'remember_token'];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'height_cm' => 'integer',
            'weight_kg' => 'float',
            'days_per_week' => 'integer',
            'is_admin' => 'boolean',
        ];
    }

    public function bmi(): ?float
    {
        if (! $this->height_cm || ! $this->weight_kg) {
            return null;
        }

        $meters = $this->height_cm / 100;

        return round($this->weight_kg / ($meters * $meters), 1);
    }
}
```

- [ ] **Step 9 : Réécrire la factory**

`database/factories/UserFactory.php` :
```php
<?php

namespace Database\Factories;

use App\Models\User;
use App\Support\IdentityKey;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'birth_date' => fake()->unique()->dateTimeBetween('-45 years', '-16 years')->format('Y-m-d'),
            'avatar_color' => fake()->randomElement(User::AVATAR_COLORS),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (User $user) {
            $user->identity_key ??= IdentityKey::make($user->first_name, $user->birth_date->format('Y-m-d'));
        });
    }

    public function withMorphology(string $sex = 'male', int $heightCm = 180, float $weightKg = 80.0, string $level = 'beginner'): static
    {
        return $this->state(fn () => [
            'sex' => $sex,
            'height_cm' => $heightCm,
            'weight_kg' => $weightKg,
            'level' => $level,
        ]);
    }
}
```

- [ ] **Step 10 : Mettre Carbon en français**

Dans `app/Providers/AppServiceProvider.php`, méthode `boot()` :
```php
    public function boot(): void
    {
        \Illuminate\Support\Carbon::setLocale(config('app.locale'));
    }
```

- [ ] **Step 11 : Vérifier que tout passe**

Run : `./vendor/bin/pest`
Attendu : PASS (tous les tests).

- [ ] **Step 12 : Commit**

```bash
git add -A
git commit -m "feat: utilisateur identifié par prénom + date de naissance

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3 : Inscription, connexion, déconnexion

**Files :**
- Create : `app/Livewire/Auth/ValidatesIdentity.php`, `app/Livewire/Auth/Register.php`, `app/Livewire/Auth/Login.php`, `app/Http/Controllers/LogoutController.php`
- Create : `resources/views/components/layouts/guest.blade.php`, `resources/views/partials/head.blade.php`, `resources/views/livewire/auth/register.blade.php`, `resources/views/livewire/auth/login.blade.php`
- Create : `resources/css/app.css` (remplacement), `resources/js/app.js` (remplacement, version minimale)
- Modify : `routes/web.php`
- Test : `tests/Feature/Auth/RegisterTest.php`, `tests/Feature/Auth/LoginTest.php`

**Interfaces :**
- Consumes : `IdentityKey::make`, `IdentityKey::displayFirstName`, `User::AVATAR_COLORS`
- Produces : routes nommées `register`, `login`, `logout` ; layout `<x-layouts.guest>` ; partial `partials.head` (prend `$title`) ; classes CSS `.input`, `.btn-primary`, `.btn-secondary`, `.card`. La route `home` est déclarée ici (vue provisoire) et remplacée à la tâche 4.

- [ ] **Step 1 : Écrire les tests qui échouent**

`tests/Feature/Auth/RegisterTest.php` :
```php
<?php

use App\Livewire\Auth\Register;
use App\Models\User;
use Livewire\Livewire;

it('affiche la page d\'inscription', function () {
    $this->get('/register')->assertOk()->assertSee('Créer mon compte');
});

it('inscrit et connecte un nouvel utilisateur', function () {
    Livewire::test(Register::class)
        ->set('firstName', '  Élise ')
        ->set('birthDate', '2001-04-12')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $user = User::sole();
    expect($user->first_name)->toBe('Élise')
        ->and($user->identity_key)->toBe('elise|2001-04-12');
    $this->assertAuthenticatedAs($user);
});

it('refuse un doublon même avec une autre casse ou sans accent', function () {
    User::factory()->create(['first_name' => 'Élise', 'birth_date' => '2001-04-12']);

    Livewire::test(Register::class)
        ->set('firstName', 'ELISE')
        ->set('birthDate', '2001-04-12')
        ->call('register')
        ->assertHasErrors(['firstName'])
        ->assertSee('déjà utilisés');

    expect(User::count())->toBe(1);
});

it('valide les champs', function (string $first, string $date, string $field) {
    Livewire::test(Register::class)
        ->set('firstName', $first)
        ->set('birthDate', $date)
        ->call('register')
        ->assertHasErrors([$field]);
})->with([
    'prénom vide' => ['   ', '2001-04-12', 'firstName'],
    'date vide' => ['Élise', '', 'birthDate'],
    'date future' => ['Élise', '2999-01-01', 'birthDate'],
    'date invalide' => ['Élise', 'pas-une-date', 'birthDate'],
]);
```

`tests/Feature/Auth/LoginTest.php` :
```php
<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Livewire\Livewire;

it('connecte un utilisateur existant, casse et accents ignorés', function () {
    $user = User::factory()->create(['first_name' => 'Élise', 'birth_date' => '2001-04-12']);

    Livewire::test(Login::class)
        ->set('firstName', 'ELISE ')
        ->set('birthDate', '2001-04-12')
        ->call('login')
        ->assertHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertAuthenticatedAs($user);
});

it('refuse un couple inconnu', function () {
    Livewire::test(Login::class)
        ->set('firstName', 'Inconnu')
        ->set('birthDate', '2000-01-01')
        ->call('login')
        ->assertHasErrors(['firstName'])
        ->assertSee('Aucun compte');

    $this->assertGuest();
});

it('bloque après 5 tentatives échouées', function () {
    $user = User::factory()->create(['first_name' => 'Élise', 'birth_date' => '2001-04-12']);

    foreach (range(1, 5) as $i) {
        Livewire::test(Login::class)->set('firstName', 'Faux')->set('birthDate', '2000-01-01')->call('login');
    }

    Livewire::test(Login::class)
        ->set('firstName', 'Élise')
        ->set('birthDate', '2001-04-12')
        ->call('login')
        ->assertHasErrors(['firstName'])
        ->assertSee('Trop de tentatives');

    $this->assertGuest();
});

it('déconnecte', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('redirige un invité vers la connexion', function () {
    $this->get('/')->assertRedirect(route('login'));
});
```

- [ ] **Step 2 : Vérifier l'échec**

Run : `./vendor/bin/pest tests/Feature/Auth`
Attendu : FAIL (`Class "App\Livewire\Auth\Register" not found`).

- [ ] **Step 3 : Règles communes**

`app/Livewire/Auth/ValidatesIdentity.php` :
```php
<?php

namespace App\Livewire\Auth;

trait ValidatesIdentity
{
    public string $firstName = '';

    public string $birthDate = '';

    protected function validateIdentity(): void
    {
        $this->validate([
            'firstName' => ['required', 'string', 'max:50'],
            'birthDate' => ['required', 'date', 'before:today', 'after:1900-01-01'],
        ], [
            'firstName.required' => 'Indique ton prénom.',
            'firstName.max' => 'Ton prénom est trop long (50 caractères max.).',
            'birthDate.required' => 'Indique ta date de naissance.',
            'birthDate.date' => 'Date de naissance invalide.',
            'birthDate.before' => 'La date de naissance doit être dans le passé.',
            'birthDate.after' => 'Date de naissance invalide.',
        ]);
    }
}
```

- [ ] **Step 4 : Composant d'inscription**

`app/Livewire/Auth/Register.php` :
```php
<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Support\IdentityKey;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Inscription')]
class Register extends Component
{
    use ValidatesIdentity;

    public function register()
    {
        $this->validateIdentity();

        $key = IdentityKey::make($this->firstName, $this->birthDate);

        if (User::where('identity_key', $key)->exists()) {
            return $this->duplicate();
        }

        try {
            $user = User::create([
                'first_name' => IdentityKey::displayFirstName($this->firstName),
                'identity_key' => $key,
                'birth_date' => $this->birthDate,
                'avatar_color' => collect(User::AVATAR_COLORS)->random(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->duplicate();
        }

        Auth::login($user, remember: true);
        session()->regenerate();

        return $this->redirectRoute('home');
    }

    private function duplicate(): void
    {
        $this->addError('firstName', 'Ce prénom et cette date de naissance sont déjà utilisés — connecte-toi.');
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
```

- [ ] **Step 5 : Composant de connexion**

`app/Livewire/Auth/Login.php` :
```php
<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Support\IdentityKey;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Connexion')]
class Login extends Component
{
    use ValidatesIdentity;

    public function login()
    {
        $this->validateIdentity();

        $throttleKey = 'login:'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('firstName', "Trop de tentatives. Réessaie dans {$seconds} secondes.");

            return;
        }

        $user = User::where('identity_key', IdentityKey::make($this->firstName, $this->birthDate))->first();

        if (! $user) {
            RateLimiter::hit($throttleKey, 60);
            $this->addError('firstName', 'Aucun compte ne correspond à ce prénom et cette date de naissance.');

            return;
        }

        RateLimiter::clear($throttleKey);
        Auth::login($user, remember: true);
        session()->regenerate();

        return $this->redirectRoute('home');
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
```

- [ ] **Step 6 : Déconnexion**

`app/Http/Controllers/LogoutController.php` :
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LogoutController extends Controller
{
    public function __invoke(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
```

- [ ] **Step 7 : Routes**

`routes/web.php` :
```php
<?php

use App\Http\Controllers\LogoutController;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/register', Register::class)->name('register');
    Route::get('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => 'EGO')->name('home'); // remplacé à la tâche 4
    Route::post('/logout', LogoutController::class)->name('logout');
});
```

- [ ] **Step 8 : Styles de base**

`resources/css/app.css` (garder les lignes `@source` générées par Laravel si elles existent, et remplacer le reste) :
```css
@import 'tailwindcss';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../**/*.blade.php';
@source '../**/*.js';

[x-cloak] { display: none !important; }

.input {
    @apply w-full rounded-xl border border-zinc-800 bg-zinc-900 px-4 py-3 text-base text-zinc-100 placeholder:text-zinc-500 focus:border-lime-400 focus:outline-none;
}
.btn-primary {
    @apply inline-flex w-full items-center justify-center rounded-xl bg-lime-400 px-4 py-3 text-base font-bold text-zinc-950 active:bg-lime-300 disabled:opacity-40;
}
.btn-secondary {
    @apply inline-flex w-full items-center justify-center rounded-xl border border-zinc-700 px-4 py-3 text-base font-semibold text-zinc-100 active:bg-zinc-800 disabled:opacity-40;
}
.card {
    @apply rounded-2xl border border-zinc-800 bg-zinc-900/60 p-4;
}
```

- [ ] **Step 9 : JavaScript minimal (Livewire + Alpine intégrés à Vite)**

`resources/js/app.js` :
```js
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

window.Alpine = Alpine;
Livewire.start();
```

- [ ] **Step 10 : Partial `<head>` et layout invité**

`resources/views/partials/head.blade.php` :
```blade
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#09090b">
<link rel="manifest" href="/manifest.json">
<link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/icons/icon-192.png">
<title>{{ $title ? $title.' · EGO' : 'EGO' }}</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
```

`resources/views/components/layouts/guest.blade.php` :
```blade
@props(['title' => null])
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="flex min-h-full items-center justify-center bg-zinc-950 px-4 text-zinc-100 antialiased">
    <main class="w-full max-w-sm py-10">
        <p class="mb-8 text-center text-5xl font-black tracking-[0.3em] text-lime-400">EGO</p>
        {{ $slot }}
    </main>
    @livewireScriptConfig
</body>
</html>
```

- [ ] **Step 11 : Vues des formulaires**

`resources/views/livewire/auth/register.blade.php` :
```blade
<div>
    <h1 class="mb-6 text-xl font-bold">Créer mon compte</h1>
    <form wire:submit="register" class="space-y-4" novalidate>
        <div>
            <label for="firstName" class="mb-1 block text-sm text-zinc-400">Prénom</label>
            <input id="firstName" type="text" wire:model="firstName" autocomplete="given-name" autocapitalize="words" class="input">
            @error('firstName') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="birthDate" class="mb-1 block text-sm text-zinc-400">Date de naissance</label>
            <input id="birthDate" type="date" wire:model="birthDate" autocomplete="bday" class="input">
            @error('birthDate') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn-primary" wire:loading.attr="disabled">C'est parti</button>
    </form>
    <p class="mt-6 text-center text-sm text-zinc-400">
        Déjà inscrit ? <a href="{{ route('login') }}" class="font-semibold text-lime-400">Se connecter</a>
    </p>
</div>
```

`resources/views/livewire/auth/login.blade.php` :
```blade
<div>
    <h1 class="mb-6 text-xl font-bold">Connexion</h1>
    <form wire:submit="login" class="space-y-4" novalidate>
        <div>
            <label for="firstName" class="mb-1 block text-sm text-zinc-400">Prénom</label>
            <input id="firstName" type="text" wire:model="firstName" autocomplete="given-name" autocapitalize="words" class="input">
            @error('firstName') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="birthDate" class="mb-1 block text-sm text-zinc-400">Date de naissance</label>
            <input id="birthDate" type="date" wire:model="birthDate" autocomplete="bday" class="input">
            @error('birthDate') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="btn-primary" wire:loading.attr="disabled">Se connecter</button>
    </form>
    <p class="mt-6 text-center text-sm text-zinc-400">
        Pas encore de compte ? <a href="{{ route('register') }}" class="font-semibold text-lime-400">Créer un compte</a>
    </p>
</div>
```

- [ ] **Step 12 : Compiler les assets et lancer les tests**

Run : `npm run build && ./vendor/bin/pest`
Attendu : build OK ; tous les tests PASS. (Les tests qui rendent des vues ont besoin de `public/build/manifest.json`. Relancer `npm run build` après chaque modification de CSS/JS avant `pest`, ou garder `npm run dev` ouvert.)

- [ ] **Step 13 : Commit**

```bash
git add -A
git commit -m "feat: inscription, connexion et déconnexion par prénom + date de naissance

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4 : Layout de l'app, navigation et PWA

**Files :**
- Create : `resources/views/components/layouts/app.blade.php`, `resources/views/partials/nav-icon.blade.php`, `resources/views/pages/soon.blade.php`
- Create : `app/Livewire/Home.php`, `resources/views/livewire/home.blade.php` (version provisoire, complétée à la tâche 12)
- Create : `public/manifest.json`, `public/sw.js`, `public/icons/icon.svg`, `scripts/make-icons.php` → `public/icons/icon-192.png`, `public/icons/icon-512.png`
- Modify : `routes/web.php`, `resources/js/app.js`
- Test : `tests/Feature/LayoutTest.php`

**Interfaces :**
- Consumes : `partials.head`, route `logout`
- Produces : layout `<x-layouts.app :title="…" :bare="false">` (bare = plein écran sans navigation) ; `body[data-auth]` ; éléments `#resume-banner` (lien vers `route('workout')`) et `#offline-banner` masqués par défaut ; routes `home`, `program`, `community`. Les routes `workout`, `history`, `records`, `profile` sont déclarées ici en vues provisoires (`pages.soon`) et remplacées aux tâches 10–12, pour que la navigation fonctionne dès maintenant.

- [ ] **Step 1 : Écrire le test qui échoue**

`tests/Feature/LayoutTest.php` :
```php
<?php

use App\Models\User;

beforeEach(fn () => $this->actingAs(User::factory()->create(['first_name' => 'Élise'])));

it('affiche l\'accueil avec la navigation à 4 onglets', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Salut Élise')
        ->assertSeeInOrder(['Accueil', 'Programme', 'Communauté', 'Profil'])
        ->assertSee('/manifest.json', false)
        ->assertSee('id="resume-banner"', false);
});

it('affiche les pages à venir', function (string $url, string $text) {
    $this->get($url)->assertOk()->assertSee($text);
})->with([
    ['/program', 'générateur de programmes'],
    ['/community', 'classement'],
]);
```

- [ ] **Step 2 : Vérifier l'échec**

Run : `./vendor/bin/pest tests/Feature/LayoutTest.php`
Attendu : FAIL (la route `/` renvoie « EGO » sans « Salut Élise »).

- [ ] **Step 3 : Icônes de navigation**

`resources/views/partials/nav-icon.blade.php` :
```blade
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-6 w-6" aria-hidden="true">
    @switch($name)
        @case('home')
            <path d="M3 11l9-8 9 8"/><path d="M5 10v10h5v-6h4v6h5V10"/>
            @break
        @case('program')
            <rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/>
            @break
        @case('community')
            <circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M15 14.5c.6-.3 1.3-.5 2-.5 2.8 0 4 2.2 4 5"/>
            @break
        @case('profile')
            <circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/>
            @break
    @endswitch
</svg>
```

- [ ] **Step 4 : Layout de l'application**

`resources/views/components/layouts/app.blade.php` :
```blade
@props(['title' => null, 'bare' => false])
@php
    $tabs = [
        ['home', 'Accueil', 'home', ['home']],
        ['program', 'Programme', 'program', ['program*']],
        ['community', 'Communauté', 'community', ['community*']],
        ['profile', 'Profil', 'profile', ['profile*', 'history*', 'records*', 'progress*']],
    ];
@endphp
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    @include('partials.head', ['title' => $title])
</head>
<body class="min-h-full bg-zinc-950 text-zinc-100 antialiased" data-auth="1">
    <div id="offline-banner" hidden class="sticky top-0 z-30 bg-amber-500 px-4 py-2 text-center text-sm font-semibold text-zinc-950">
        Hors ligne — ta séance sera synchronisée
    </div>

    @if ($bare)
        {{ $slot }}
    @else
        <div class="lg:flex">
            <nav class="fixed inset-x-0 bottom-0 z-20 border-t border-zinc-800 bg-zinc-950/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:sticky lg:top-0 lg:h-screen lg:w-56 lg:border-r lg:border-t-0 lg:pb-0">
                <p class="hidden px-6 py-8 text-2xl font-black tracking-[0.3em] text-lime-400 lg:block">EGO</p>
                <ul class="grid grid-cols-4 lg:grid-cols-1 lg:gap-1 lg:px-3">
                    @foreach ($tabs as [$route, $label, $icon, $patterns])
                        @php $active = request()->routeIs(...$patterns); @endphp
                        <li>
                            <a href="{{ route($route) }}"
                               @class([
                                   'flex flex-col items-center gap-1 py-2 text-xs lg:flex-row lg:gap-3 lg:rounded-xl lg:px-3 lg:py-3 lg:text-sm',
                                   'text-lime-400 lg:bg-zinc-900' => $active,
                                   'text-zinc-400 hover:text-zinc-100' => ! $active,
                               ])
                               @if ($active) aria-current="page" @endif>
                                @include('partials.nav-icon', ['name' => $icon])
                                <span>{{ $label }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <main class="mx-auto w-full max-w-[720px] px-4 pb-28 pt-[max(1.5rem,env(safe-area-inset-top))] lg:pb-10">
                <a id="resume-banner" hidden href="{{ route('workout') }}"
                   class="mb-4 block rounded-xl bg-lime-400 px-4 py-3 text-center font-bold text-zinc-950">
                    Séance en cours — Reprendre
                </a>
                {{ $slot }}
            </main>
        </div>
    @endif

    @livewireScriptConfig
</body>
</html>
```

- [ ] **Step 5 : Page « bientôt » et accueil provisoire**

`resources/views/pages/soon.blade.php` :
```blade
<x-layouts.app :title="$title">
    <h1 class="mb-4 text-2xl font-bold">{{ $title }}</h1>
    <div class="card text-zinc-300">{{ $message }}</div>
</x-layouts.app>
```

`app/Livewire/Home.php` :
```php
<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Accueil')]
class Home extends Component
{
    public function render()
    {
        return view('livewire.home', ['user' => auth()->user()]);
    }
}
```

`resources/views/livewire/home.blade.php` :
```blade
<div class="space-y-6">
    <h1 class="text-2xl font-bold">Salut {{ $user->first_name }} 👋</h1>
</div>
```

- [ ] **Step 6 : Routes**

Remplacer le groupe `auth` de `routes/web.php` par :
```php
Route::middleware('auth')->group(function () {
    Route::get('/', Home::class)->name('home');
    Route::view('/program', 'pages.soon', [
        'title' => 'Programme',
        'message' => 'Le générateur de programmes arrive à la prochaine étape.',
    ])->name('program');
    Route::view('/community', 'pages.soon', [
        'title' => 'Communauté',
        'message' => 'Records des amis, classement, défis et groupes arrivent bientôt.',
    ])->name('community');
    // Provisoires, remplacées aux tâches 10 à 12
    Route::view('/workout', 'pages.soon', ['title' => 'Séance', 'message' => 'Bientôt.'])->name('workout');
    Route::view('/history', 'pages.soon', ['title' => 'Historique', 'message' => 'Bientôt.'])->name('history');
    Route::view('/records', 'pages.soon', ['title' => 'Records', 'message' => 'Bientôt.'])->name('records');
    Route::view('/profile', 'pages.soon', ['title' => 'Profil', 'message' => 'Bientôt.'])->name('profile');
    Route::post('/logout', LogoutController::class)->name('logout');
});
```
et ajouter `use App\Livewire\Home;` en haut.

- [ ] **Step 7 : Manifest, service worker et icônes**

`public/manifest.json` :
```json
{
    "name": "EGO",
    "short_name": "EGO",
    "description": "La musculation entre amis",
    "lang": "fr",
    "start_url": "/",
    "scope": "/",
    "display": "standalone",
    "orientation": "portrait",
    "background_color": "#09090b",
    "theme_color": "#09090b",
    "icons": [
        { "src": "/icons/icon-192.png", "sizes": "192x192", "type": "image/png" },
        { "src": "/icons/icon-512.png", "sizes": "512x512", "type": "image/png" },
        { "src": "/icons/icon.svg", "sizes": "any", "type": "image/svg+xml" }
    ]
}
```

`public/sw.js` :
```js
const CACHE = 'ego-v1';

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        for (const key of await caches.keys()) {
            if (key !== CACHE) await caches.delete(key);
        }
        await self.clients.claim();
    })());
});

function putInCache(request, response) {
    const copy = response.clone();
    caches.open(CACHE).then((cache) => cache.put(request, copy));
}

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;

    if (url.pathname.startsWith('/build/') || url.pathname.startsWith('/icons/')) {
        event.respondWith(caches.match(request).then((hit) => hit || fetch(request).then((response) => {
            if (response.ok) putInCache(request, response);
            return response;
        })));
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).then((response) => {
            if (response.ok) putInCache(request, response);
            return response;
        }).catch(() => caches.match(request).then((hit) => hit || caches.match('/workout'))));
    }
});
```

`public/icons/icon.svg` :
```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><rect width="512" height="512" rx="96" fill="#09090b"/><text x="256" y="310" font-family="Arial Black, Arial, sans-serif" font-weight="900" font-size="170" text-anchor="middle" letter-spacing="8" fill="#a3e635">EGO</text></svg>
```

`scripts/make-icons.php` :
```php
<?php
// Génère public/icons/icon-192.png et icon-512.png (texte « EGO » pixelisé) avec GD.

foreach ([192, 512] as $size) {
    $small = imagecreatetruecolor(33, 21);
    imagefill($small, 0, 0, imagecolorallocate($small, 9, 9, 11));
    imagestring($small, 5, 3, 3, 'EGO', imagecolorallocate($small, 163, 230, 53));

    $icon = imagecreatetruecolor($size, $size);
    imagefill($icon, 0, 0, imagecolorallocate($icon, 9, 9, 11));
    $width = (int) round($size * 0.8);
    $height = (int) round($width * 21 / 33);
    imagecopyresized($icon, $small, (int) (($size - $width) / 2), (int) (($size - $height) / 2), 0, 0, $width, $height, 33, 21);

    imagepng($icon, __DIR__."/../public/icons/icon-{$size}.png");
}

echo "Icônes générées.\n";
```
Run : `php scripts/make-icons.php`
Attendu : « Icônes générées. » et deux fichiers PNG dans `public/icons/`.

- [ ] **Step 8 : Enregistrer le service worker et gérer le bandeau hors ligne**

`resources/js/app.js` :
```js
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

window.Alpine = Alpine;
Livewire.start();

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
}

function updateOfflineBanner() {
    const banner = document.getElementById('offline-banner');
    if (banner) banner.hidden = navigator.onLine;
}

window.addEventListener('online', updateOfflineBanner);
window.addEventListener('offline', updateOfflineBanner);
document.addEventListener('DOMContentLoaded', updateOfflineBanner);
```

- [ ] **Step 9 : Compiler et tester**

Run : `npm run build && ./vendor/bin/pest`
Attendu : tous les tests PASS.

- [ ] **Step 10 : Commit**

```bash
git add -A
git commit -m "feat: layout mobile-first, navigation 4 onglets et PWA

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5 : Bibliothèque d'exercices

**Files :**
- Create : `database/migrations/2026_10_01_000100_create_exercises_table.php`, `app/Models/Exercise.php`, `database/data/exercises.php`, `database/seeders/ExerciseSeeder.php`
- Modify : `database/seeders/DatabaseSeeder.php`
- Test : `tests/Feature/ExerciseSeederTest.php`

**Interfaces :**
- Produces :
  - `App\Models\Exercise` : `slug`, `name`, `primary_muscles` (array), `equipment` (array), `kind` (`compound`|`isolation`), `slots` (array), `is_bodyweight` (bool), `start_ratio` (array|null : `['male'|'female' => ['beginner'|'intermediate'|'advanced' => float]]`)
  - `Exercise::SLOTS` : liste des 15 emplacements
  - `ExerciseSeeder::ratios(float $maleBeginner): array` (intermédiaire ×1,5, avancé ×2, femme ×0,6)

- [ ] **Step 1 : Écrire le test qui échoue**

`tests/Feature/ExerciseSeederTest.php` :
```php
<?php

use App\Models\Exercise;
use Database\Seeders\ExerciseSeeder;

beforeEach(fn () => $this->seed(ExerciseSeeder::class));

it('charge au moins 55 exercices avec des slugs uniques', function () {
    expect(Exercise::count())->toBeGreaterThanOrEqual(55)
        ->and(Exercise::distinct()->count('slug'))->toBe(Exercise::count());
});

it('est idempotent', function () {
    $count = Exercise::count();
    $this->seed(ExerciseSeeder::class);
    expect(Exercise::count())->toBe($count);
});

it('couvre chaque emplacement avec un exercice de salle', function (string $slot) {
    $found = Exercise::all()->contains(fn ($e) => in_array($slot, $e->slots) && in_array('gym', $e->equipment));
    expect($found)->toBeTrue();
})->with(Exercise::SLOTS);

it('couvre les mouvements de base avec haltères et au poids du corps', function (string $slot, string $equipment) {
    $found = Exercise::all()->contains(fn ($e) => in_array($slot, $e->slots) && in_array($equipment, $e->equipment));
    expect($found)->toBeTrue();
})->with(function () {
    foreach (['horizontal_push', 'vertical_push', 'horizontal_pull', 'vertical_pull', 'squat', 'hinge', 'lunge', 'core'] as $slot) {
        foreach (['dumbbells', 'bodyweight'] as $equipment) {
            yield "$slot / $equipment" => [$slot, $equipment];
        }
    }
});

it('calcule les ratios de départ', function () {
    $bench = Exercise::firstWhere('slug', 'bench-press');

    expect($bench->is_bodyweight)->toBeFalse()
        ->and($bench->start_ratio['male']['beginner'])->toBe(0.5)
        ->and($bench->start_ratio['male']['advanced'])->toBe(1.0)
        ->and($bench->start_ratio['female']['beginner'])->toBe(0.3);

    $pullUp = Exercise::firstWhere('slug', 'pull-up');
    expect($pullUp->is_bodyweight)->toBeTrue()->and($pullUp->start_ratio)->toBeNull();
});
```

- [ ] **Step 2 : Vérifier l'échec**

Run : `./vendor/bin/pest tests/Feature/ExerciseSeederTest.php`
Attendu : FAIL (`Class "App\Models\Exercise" not found`).

- [ ] **Step 3 : Migration**

`database/migrations/2026_10_01_000100_create_exercises_table.php` :
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->json('primary_muscles');
            $table->json('equipment');
            $table->string('kind', 10);
            $table->json('slots');
            $table->boolean('is_bodyweight')->default(false);
            $table->json('start_ratio')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
```

- [ ] **Step 4 : Modèle**

`app/Models/Exercise.php` :
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    public const SLOTS = [
        'horizontal_push', 'vertical_push', 'horizontal_pull', 'vertical_pull',
        'squat', 'hinge', 'lunge',
        'chest_iso', 'shoulder_iso', 'biceps', 'triceps',
        'quads_iso', 'hamstrings_iso', 'calves', 'core',
    ];

    protected $fillable = ['slug', 'name', 'primary_muscles', 'equipment', 'kind', 'slots', 'is_bodyweight', 'start_ratio'];

    protected function casts(): array
    {
        return [
            'primary_muscles' => 'array',
            'equipment' => 'array',
            'slots' => 'array',
            'is_bodyweight' => 'boolean',
            'start_ratio' => 'array',
        ];
    }
}
```

- [ ] **Step 5 : Données** — chaque ligne : `[slug, nom, muscles, matériel, type, emplacements, ratio homme débutant | null = poids du corps]`

`database/data/exercises.php` :
```php
<?php

return [
    // Poussée horizontale
    ['bench-press', 'Développé couché (barre)', ['pectoraux', 'triceps', 'épaules'], ['gym'], 'compound', ['horizontal_push'], 0.5],
    ['dumbbell-bench-press', 'Développé couché haltères', ['pectoraux', 'triceps', 'épaules'], ['gym', 'dumbbells'], 'compound', ['horizontal_push'], 0.2],
    ['incline-bench-press', 'Développé incliné (barre)', ['pectoraux', 'épaules', 'triceps'], ['gym'], 'compound', ['horizontal_push'], 0.4],
    ['incline-dumbbell-press', 'Développé incliné haltères', ['pectoraux', 'épaules', 'triceps'], ['gym', 'dumbbells'], 'compound', ['horizontal_push'], 0.17],
    ['machine-chest-press', 'Presse à pectoraux (machine)', ['pectoraux', 'triceps'], ['gym'], 'compound', ['horizontal_push'], 0.4],
    ['push-up', 'Pompes', ['pectoraux', 'triceps', 'épaules'], ['bodyweight', 'dumbbells', 'gym'], 'compound', ['horizontal_push'], null],
    ['dips', 'Dips', ['pectoraux', 'triceps'], ['gym', 'bodyweight'], 'compound', ['horizontal_push', 'triceps'], null],
    // Poussée verticale
    ['overhead-press', 'Développé militaire (barre)', ['épaules', 'triceps'], ['gym'], 'compound', ['vertical_push'], 0.35],
    ['dumbbell-shoulder-press', 'Développé épaules haltères', ['épaules', 'triceps'], ['gym', 'dumbbells'], 'compound', ['vertical_push'], 0.13],
    ['arnold-press', 'Développé Arnold', ['épaules', 'triceps'], ['gym', 'dumbbells'], 'compound', ['vertical_push'], 0.11],
    ['machine-shoulder-press', 'Développé épaules (machine)', ['épaules', 'triceps'], ['gym'], 'compound', ['vertical_push'], 0.3],
    ['pike-push-up', 'Pompes piquées', ['épaules', 'triceps'], ['bodyweight'], 'compound', ['vertical_push'], null],
    // Tirage horizontal
    ['barbell-row', 'Rowing barre', ['dos', 'biceps'], ['gym'], 'compound', ['horizontal_pull'], 0.4],
    ['dumbbell-row', 'Rowing haltère unilatéral', ['dos', 'biceps'], ['gym', 'dumbbells'], 'compound', ['horizontal_pull'], 0.2],
    ['chest-supported-row', 'Rowing buste appuyé haltères', ['dos', 'biceps'], ['gym', 'dumbbells'], 'compound', ['horizontal_pull'], 0.15],
    ['seated-cable-row', 'Tirage horizontal à la poulie', ['dos', 'biceps'], ['gym'], 'compound', ['horizontal_pull'], 0.4],
    ['inverted-row', 'Rowing inversé', ['dos', 'biceps'], ['bodyweight', 'gym'], 'compound', ['horizontal_pull'], null],
    // Tirage vertical
    ['pull-up', 'Tractions pronation', ['dos', 'biceps'], ['gym', 'bodyweight'], 'compound', ['vertical_pull'], null],
    ['chin-up', 'Tractions supination', ['dos', 'biceps'], ['gym', 'bodyweight'], 'compound', ['vertical_pull'], null],
    ['lat-pulldown', 'Tirage vertical à la poulie', ['dos', 'biceps'], ['gym'], 'compound', ['vertical_pull'], 0.45],
    ['dumbbell-pullover', 'Pull-over haltère', ['dos', 'pectoraux'], ['gym', 'dumbbells'], 'isolation', ['vertical_pull'], 0.12],
    // Squat
    ['back-squat', 'Squat (barre)', ['quadriceps', 'fessiers'], ['gym'], 'compound', ['squat'], 0.6],
    ['front-squat', 'Squat avant', ['quadriceps', 'fessiers'], ['gym'], 'compound', ['squat'], 0.5],
    ['leg-press', 'Presse à cuisses', ['quadriceps', 'fessiers'], ['gym'], 'compound', ['squat'], 1.0],
    ['goblet-squat', 'Squat goblet', ['quadriceps', 'fessiers'], ['gym', 'dumbbells'], 'compound', ['squat'], 0.2],
    ['bodyweight-squat', 'Squat au poids du corps', ['quadriceps', 'fessiers'], ['bodyweight'], 'compound', ['squat'], null],
    // Charnière de hanche
    ['deadlift', 'Soulevé de terre', ['ischio-jambiers', 'fessiers', 'dos'], ['gym'], 'compound', ['hinge'], 0.75],
    ['romanian-deadlift', 'Soulevé de terre roumain (barre)', ['ischio-jambiers', 'fessiers'], ['gym'], 'compound', ['hinge'], 0.5],
    ['dumbbell-romanian-deadlift', 'Soulevé de terre roumain haltères', ['ischio-jambiers', 'fessiers'], ['gym', 'dumbbells'], 'compound', ['hinge'], 0.2],
    ['hip-thrust', 'Hip thrust', ['fessiers', 'ischio-jambiers'], ['gym'], 'compound', ['hinge'], 0.6],
    ['glute-bridge', 'Pont fessier', ['fessiers', 'ischio-jambiers'], ['bodyweight', 'dumbbells'], 'compound', ['hinge'], null],
    // Fentes
    ['walking-lunge', 'Fentes marchées haltères', ['quadriceps', 'fessiers'], ['gym', 'dumbbells'], 'compound', ['lunge'], 0.12],
    ['bulgarian-split-squat', 'Squat bulgare haltères', ['quadriceps', 'fessiers'], ['gym', 'dumbbells'], 'compound', ['lunge'], 0.1],
    ['step-up', 'Montées sur banc haltères', ['quadriceps', 'fessiers'], ['gym', 'dumbbells'], 'compound', ['lunge'], 0.1],
    ['bodyweight-lunge', 'Fentes au poids du corps', ['quadriceps', 'fessiers'], ['bodyweight'], 'compound', ['lunge'], null],
    // Pectoraux (isolation)
    ['dumbbell-fly', 'Écarté couché haltères', ['pectoraux'], ['gym', 'dumbbells'], 'isolation', ['chest_iso'], 0.08],
    ['cable-fly', 'Écarté à la poulie', ['pectoraux'], ['gym'], 'isolation', ['chest_iso'], 0.1],
    ['pec-deck', 'Pec deck', ['pectoraux'], ['gym'], 'isolation', ['chest_iso'], 0.3],
    // Épaules (isolation)
    ['lateral-raise', 'Élévations latérales', ['épaules'], ['gym', 'dumbbells'], 'isolation', ['shoulder_iso'], 0.05],
    ['rear-delt-fly', 'Oiseau haltères', ['épaules', 'dos'], ['gym', 'dumbbells'], 'isolation', ['shoulder_iso'], 0.05],
    ['face-pull', 'Face pull', ['épaules', 'dos'], ['gym'], 'isolation', ['shoulder_iso'], 0.2],
    ['cable-lateral-raise', 'Élévations latérales à la poulie', ['épaules'], ['gym'], 'isolation', ['shoulder_iso'], 0.06],
    // Biceps
    ['barbell-curl', 'Curl barre', ['biceps'], ['gym'], 'isolation', ['biceps'], 0.2],
    ['dumbbell-curl', 'Curl haltères', ['biceps'], ['gym', 'dumbbells'], 'isolation', ['biceps'], 0.08],
    ['hammer-curl', 'Curl marteau', ['biceps', 'avant-bras'], ['gym', 'dumbbells'], 'isolation', ['biceps'], 0.08],
    ['cable-curl', 'Curl à la poulie', ['biceps'], ['gym'], 'isolation', ['biceps'], 0.15],
    // Triceps
    ['triceps-pushdown', 'Extension triceps à la poulie', ['triceps'], ['gym'], 'isolation', ['triceps'], 0.2],
    ['overhead-dumbbell-extension', 'Extension triceps haltère au-dessus de la tête', ['triceps'], ['gym', 'dumbbells'], 'isolation', ['triceps'], 0.1],
    ['skull-crusher', 'Barre au front', ['triceps'], ['gym'], 'isolation', ['triceps'], 0.15],
    ['diamond-push-up', 'Pompes diamant', ['triceps', 'pectoraux'], ['bodyweight'], 'isolation', ['triceps'], null],
    ['bench-dips', 'Dips sur banc', ['triceps'], ['bodyweight', 'dumbbells'], 'isolation', ['triceps'], null],
    // Quadriceps (isolation)
    ['leg-extension', 'Leg extension', ['quadriceps'], ['gym'], 'isolation', ['quads_iso'], 0.3],
    ['sissy-squat', 'Sissy squat', ['quadriceps'], ['bodyweight'], 'isolation', ['quads_iso'], null],
    // Ischio-jambiers (isolation)
    ['leg-curl', 'Leg curl', ['ischio-jambiers'], ['gym'], 'isolation', ['hamstrings_iso'], 0.25],
    ['nordic-curl', 'Nordic curl', ['ischio-jambiers'], ['bodyweight'], 'isolation', ['hamstrings_iso'], null],
    // Mollets
    ['standing-calf-raise', 'Mollets debout (machine)', ['mollets'], ['gym'], 'isolation', ['calves'], 0.5],
    ['dumbbell-calf-raise', 'Mollets debout haltères', ['mollets'], ['gym', 'dumbbells'], 'isolation', ['calves'], 0.1],
    ['bodyweight-calf-raise', 'Mollets au poids du corps', ['mollets'], ['bodyweight'], 'isolation', ['calves'], null],
    // Abdominaux
    ['crunch', 'Crunch', ['abdominaux'], ['bodyweight', 'dumbbells', 'gym'], 'isolation', ['core'], null],
    ['hanging-leg-raise', 'Relevés de jambes suspendu', ['abdominaux'], ['gym', 'bodyweight'], 'isolation', ['core'], null],
    ['cable-crunch', 'Crunch à la poulie', ['abdominaux'], ['gym'], 'isolation', ['core'], 0.3],
];
```

- [ ] **Step 6 : Seeder**

`database/seeders/ExerciseSeeder.php` :
```php
<?php

namespace Database\Seeders;

use App\Models\Exercise;
use Illuminate\Database\Seeder;

class ExerciseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (require database_path('data/exercises.php') as [$slug, $name, $muscles, $equipment, $kind, $slots, $ratio]) {
            Exercise::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'primary_muscles' => $muscles,
                'equipment' => $equipment,
                'kind' => $kind,
                'slots' => $slots,
                'is_bodyweight' => $ratio === null,
                'start_ratio' => $ratio === null ? null : self::ratios($ratio),
            ]);
        }
    }

    /** @return array{male: array<string, float>, female: array<string, float>} */
    public static function ratios(float $maleBeginner): array
    {
        $levels = fn (float $base) => [
            'beginner' => round($base, 3),
            'intermediate' => round($base * 1.5, 3),
            'advanced' => round($base * 2, 3),
        ];

        return ['male' => $levels($maleBeginner), 'female' => $levels($maleBeginner * 0.6)];
    }
}
```

`database/seeders/DatabaseSeeder.php` :
```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ExerciseSeeder::class);
    }
}
```

- [ ] **Step 7 : Vérifier que les tests passent**

Run : `./vendor/bin/pest tests/Feature/ExerciseSeederTest.php`
Attendu : PASS. Si un test de couverture d'emplacement échoue, ajouter dans `database/data/exercises.php` un exercice qui couvre l'emplacement et le matériel indiqués.

- [ ] **Step 8 : Charger la base locale**

Run : `php artisan migrate:fresh --seed`
Attendu : migrations OK, `Database\Seeders\ExerciseSeeder ... DONE`.

- [ ] **Step 9 : Commit**

```bash
git add -A
git commit -m "feat: bibliothèque de ~60 exercices avec emplacements et ratios de départ

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6 : Calculs de records et charges de départ

**Files :**
- Create : `app/Services/RecordCalculator.php`, `app/Services/StartingLoad.php`, `app/Support/Format.php`
- Test : `tests/Unit/RecordCalculatorTest.php`, `tests/Unit/StartingLoadTest.php`, `tests/Unit/FormatTest.php`

**Interfaces :**
- Consumes : `Exercise` (`is_bodyweight`, `start_ratio`), `User` (`sex`, `level`, `weight_kg`)
- Produces :
  - `RecordCalculator::estimatedOneRepMax(float $load, int $reps): float`
  - `RecordCalculator::effectiveLoad(float $weightKg, bool $isBodyweight, ?float $bodyweightKg): float`
  - `RecordCalculator::setVolume(float $weightKg, int $reps, bool $isBodyweight, ?float $bodyweightKg): float`
  - `RecordCalculator::bodyweightRatio(float $loadKg, ?float $bodyweightKg): ?float`
  - `RecordCalculator::beats(float $weightKg, int $reps, float $recordWeightKg, int $recordReps): bool`
  - `StartingLoad::suggest(Exercise $exercise, User $user): ?float`
  - `Format::kg(float $kg): string` (« 62,5 kg », « 1 250 kg ») ; `Format::duration(int $minutes): string` (« 52 min », « 1 h 05 »)

- [ ] **Step 1 : Écrire les tests qui échouent**

`tests/Unit/RecordCalculatorTest.php` :
```php
<?php

use App\Services\RecordCalculator;

beforeEach(fn () => $this->calc = new RecordCalculator);

it('estime le 1RM avec Epley', function () {
    expect($this->calc->estimatedOneRepMax(100, 5))->toBe(116.7)
        ->and($this->calc->estimatedOneRepMax(60, 10))->toBe(80.0);
});

it('garde la charge telle quelle pour 1 répétition', function () {
    expect($this->calc->estimatedOneRepMax(140, 1))->toBe(140.0);
});

it('ajoute le poids de corps pour un exercice au poids du corps', function () {
    expect($this->calc->effectiveLoad(10, true, 80))->toBe(90.0)
        ->and($this->calc->effectiveLoad(10, true, null))->toBe(10.0)
        ->and($this->calc->effectiveLoad(60, false, 80))->toBe(60.0);
});

it('calcule le volume d\'une série', function () {
    expect($this->calc->setVolume(60, 8, false, 80))->toBe(480.0)
        ->and($this->calc->setVolume(0, 10, true, 75))->toBe(750.0)
        ->and($this->calc->setVolume(0, 10, true, null))->toBe(0.0);
});

it('calcule le ratio au poids de corps', function () {
    expect($this->calc->bodyweightRatio(100, 80))->toBe(1.25)
        ->and($this->calc->bodyweightRatio(100, null))->toBeNull()
        ->and($this->calc->bodyweightRatio(100, 0))->toBeNull();
});

it('compare les performances : charge d\'abord, puis répétitions', function () {
    expect($this->calc->beats(62.5, 5, 60, 8))->toBeTrue()
        ->and($this->calc->beats(60, 9, 60, 8))->toBeTrue()
        ->and($this->calc->beats(60, 8, 60, 8))->toBeFalse()
        ->and($this->calc->beats(57.5, 12, 60, 8))->toBeFalse();
});
```

`tests/Unit/StartingLoadTest.php` :
```php
<?php

use App\Models\Exercise;
use App\Models\User;
use App\Services\StartingLoad;
use Database\Seeders\ExerciseSeeder;

function weighted(float $maleBeginner): Exercise
{
    return new Exercise(['is_bodyweight' => false, 'start_ratio' => ExerciseSeeder::ratios($maleBeginner)]);
}

function lifter(?string $sex, ?float $weight, ?string $level = 'beginner'): User
{
    return new User(['sex' => $sex, 'weight_kg' => $weight, 'level' => $level]);
}

beforeEach(fn () => $this->load = new StartingLoad);

it('suggère poids de corps × ratio, arrondi à 2,5 kg', function () {
    expect($this->load->suggest(weighted(0.5), lifter('male', 80)))->toBe(40.0)
        ->and($this->load->suggest(weighted(0.5), lifter('female', 60)))->toBe(17.5)
        ->and($this->load->suggest(weighted(0.5), lifter('male', 80, 'advanced')))->toBe(80.0);
});

it('considère un niveau inconnu comme débutant', function () {
    expect($this->load->suggest(weighted(0.5), lifter('male', 80, null)))->toBe(40.0);
});

it('ne descend jamais sous 2,5 kg', function () {
    expect($this->load->suggest(weighted(0.05), lifter('female', 50)))->toBe(2.5);
});

it('ne suggère rien sans morphologie ou pour le poids du corps', function () {
    expect($this->load->suggest(weighted(0.5), lifter(null, 80)))->toBeNull()
        ->and($this->load->suggest(weighted(0.5), lifter('male', null)))->toBeNull()
        ->and($this->load->suggest(new Exercise(['is_bodyweight' => true, 'start_ratio' => null]), lifter('male', 80)))->toBeNull();
});
```

`tests/Unit/FormatTest.php` :
```php
<?php

use App\Support\Format;

it('formate les kilos à la française', function () {
    expect(Format::kg(62.5))->toBe('62,5 kg')
        ->and(Format::kg(60))->toBe('60 kg')
        ->and(Format::kg(1250))->toBe('1 250 kg');
});

it('formate les durées', function () {
    expect(Format::duration(52))->toBe('52 min')
        ->and(Format::duration(65))->toBe('1 h 05');
});
```

- [ ] **Step 2 : Vérifier l'échec**

Run : `./vendor/bin/pest tests/Unit`
Attendu : FAIL (`Class "App\Services\RecordCalculator" not found`).

- [ ] **Step 3 : Implémenter `RecordCalculator`**

`app/Services/RecordCalculator.php` :
```php
<?php

namespace App\Services;

final class RecordCalculator
{
    public function estimatedOneRepMax(float $load, int $reps): float
    {
        if ($reps <= 1) {
            return round($load, 1);
        }

        return round($load * (1 + $reps / 30), 1);
    }

    public function effectiveLoad(float $weightKg, bool $isBodyweight, ?float $bodyweightKg): float
    {
        return $isBodyweight ? ($bodyweightKg ?? 0.0) + $weightKg : $weightKg;
    }

    public function setVolume(float $weightKg, int $reps, bool $isBodyweight, ?float $bodyweightKg): float
    {
        return round($this->effectiveLoad($weightKg, $isBodyweight, $bodyweightKg) * $reps, 1);
    }

    public function bodyweightRatio(float $loadKg, ?float $bodyweightKg): ?float
    {
        if (! $bodyweightKg) {
            return null;
        }

        return round($loadKg / $bodyweightKg, 2);
    }

    public function beats(float $weightKg, int $reps, float $recordWeightKg, int $recordReps): bool
    {
        if (abs($weightKg - $recordWeightKg) < 0.001) {
            return $reps > $recordReps;
        }

        return $weightKg > $recordWeightKg;
    }
}
```

- [ ] **Step 4 : Implémenter `StartingLoad`**

`app/Services/StartingLoad.php` :
```php
<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\User;

final class StartingLoad
{
    public function suggest(Exercise $exercise, User $user): ?float
    {
        if ($exercise->is_bodyweight || ! $exercise->start_ratio || ! $user->sex || ! $user->weight_kg) {
            return null;
        }

        $ratio = $exercise->start_ratio[$user->sex][$user->level ?? 'beginner'] ?? null;

        if ($ratio === null) {
            return null;
        }

        return max(2.5, round($user->weight_kg * $ratio / 2.5) * 2.5);
    }
}
```

- [ ] **Step 5 : Implémenter `Format`**

`app/Support/Format.php` :
```php
<?php

namespace App\Support;

final class Format
{
    public static function kg(float $kg): string
    {
        $decimals = fmod($kg, 1.0) === 0.0 ? 0 : 1;

        return number_format($kg, $decimals, ',', ' ').' kg';
    }

    public static function duration(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.' min';
        }

        return intdiv($minutes, 60).' h '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }
}
```

- [ ] **Step 6 : Vérifier que tout passe**

Run : `./vendor/bin/pest tests/Unit`
Attendu : PASS.

- [ ] **Step 7 : Commit**

```bash
git add -A
git commit -m "feat: calculs de records, volume, ratio et charges de départ

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7 : Séances, séries, records et synchronisation idempotente

**Files :**
- Create : `database/migrations/2026_10_01_000200_create_workouts_tables.php`, `app/Models/Workout.php`, `app/Models/WorkoutSet.php`, `app/Models/PersonalRecord.php`, `app/Services/WorkoutSync.php`
- Modify : `app/Models/User.php` (relations)
- Test : `tests/Feature/WorkoutSyncTest.php`

**Interfaces :**
- Consumes : `RecordCalculator`, `Exercise`, `User`
- Produces :
  - `Workout` : `user_id`, `client_id`, `started_at`, `finished_at` (datetime), `bodyweight_kg` (?float) ; `sets(): HasMany` (triées par `position`), `user(): BelongsTo`, `durationMinutes(): int`, `volume(): float` (exige `sets.exercise` chargé)
  - `WorkoutSet` : `workout_id`, `exercise_id`, `position`, `weight_kg` (float), `reps` (int), `done_at` ; `exercise(): BelongsTo`, `workout(): BelongsTo`
  - `PersonalRecord` : `user_id`, `exercise_id`, `max_weight_kg`, `max_weight_reps`, `best_e1rm_kg`, `achieved_at` ; `exercise(): BelongsTo`
  - `User::workouts(): HasMany`, `User::personalRecords(): HasMany`
  - `WorkoutSync::sync(User $user, array $data): array{workout: Workout, newRecords: list<PersonalRecord>, created: bool}` où `$data` = `['client_id' => string, 'started_at' => string ISO, 'finished_at' => string ISO, 'sets' => list<['exercise_id' => int, 'weight_kg' => float, 'reps' => int, 'done_at' => string ISO]>]`
  - Règle : un **nouveau record** n'est signalé que s'il bat un record existant. La première fois qu'on fait un exercice, un record est créé sans être signalé.

- [ ] **Step 1 : Écrire le test qui échoue**

`tests/Feature/WorkoutSyncTest.php` :
```php
<?php

use App\Models\Exercise;
use App\Models\PersonalRecord;
use App\Models\User;
use App\Models\Workout;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(weightKg: 80.0)->create();
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
    $this->pullUp = Exercise::firstWhere('slug', 'pull-up');
    $this->sync = app(WorkoutSync::class);
});

function payload(array $sets, ?string $clientId = null): array
{
    return [
        'client_id' => $clientId ?? (string) Str::uuid(),
        'started_at' => '2026-10-01T16:00:00.000Z',
        'finished_at' => '2026-10-01T17:05:00.000Z',
        'sets' => array_map(fn ($s) => [
            'exercise_id' => $s[0], 'weight_kg' => $s[1], 'reps' => $s[2], 'done_at' => '2026-10-01T16:10:00.000Z',
        ], $sets),
    ];
}

it('enregistre la séance, ses séries et le poids de corps', function () {
    $result = $this->sync->sync($this->user, payload([[$this->bench->id, 60, 8], [$this->bench->id, 60, 7]]));

    $workout = $result['workout'];
    expect($result['created'])->toBeTrue()
        ->and($workout->sets)->toHaveCount(2)
        ->and($workout->sets->pluck('position')->all())->toBe([1, 2])
        ->and($workout->bodyweight_kg)->toBe(80.0)
        ->and($workout->durationMinutes())->toBe(65)
        ->and($workout->volume())->toBe(900.0);
});

it('convertit les heures en heure de Paris', function () {
    $workout = $this->sync->sync($this->user, payload([[$this->bench->id, 60, 8]]))['workout'];

    expect($workout->fresh()->started_at->format('H:i'))->toBe('18:00');
});

it('est idempotent sur client_id', function () {
    $data = payload([[$this->bench->id, 60, 8]]);
    $this->sync->sync($this->user, $data);
    $second = $this->sync->sync($this->user, $data);

    expect($second['created'])->toBeFalse()
        ->and($second['newRecords'])->toBe([])
        ->and(Workout::count())->toBe(1);
});

it('crée un record à la première séance sans le signaler', function () {
    $result = $this->sync->sync($this->user, payload([[$this->bench->id, 60, 8], [$this->bench->id, 62.5, 5]]));

    $record = PersonalRecord::sole();
    expect($result['newRecords'])->toBe([])
        ->and($record->max_weight_kg)->toBe(62.5)
        ->and($record->max_weight_reps)->toBe(5)
        ->and($record->best_e1rm_kg)->toBe(76.0);
});

it('signale un record battu', function () {
    $this->sync->sync($this->user, payload([[$this->bench->id, 60, 8]]));
    $result = $this->sync->sync($this->user, payload([[$this->bench->id, 60, 9]]));

    expect($result['newRecords'])->toHaveCount(1)
        ->and($result['newRecords'][0]->max_weight_reps)->toBe(9);
});

it('ne signale rien si le record n\'est pas battu', function () {
    $this->sync->sync($this->user, payload([[$this->bench->id, 60, 8]]));
    $result = $this->sync->sync($this->user, payload([[$this->bench->id, 55, 12]]));

    expect($result['newRecords'])->toBe([])
        ->and(PersonalRecord::sole()->max_weight_kg)->toBe(60.0);
});

it('compte le poids de corps dans le volume et le 1RM des exercices au poids du corps', function () {
    $workout = $this->sync->sync($this->user, payload([[$this->pullUp->id, 0, 10]]))['workout'];

    expect($workout->volume())->toBe(800.0)
        ->and(PersonalRecord::sole()->best_e1rm_kg)->toBe(106.7);
});
```

- [ ] **Step 2 : Vérifier l'échec**

Run : `./vendor/bin/pest tests/Feature/WorkoutSyncTest.php`
Attendu : FAIL (`Class "App\Services\WorkoutSync" not found`).

- [ ] **Step 3 : Migration**

`database/migrations/2026_10_01_000200_create_workouts_tables.php` :
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_id')->unique();
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->index();
            $table->decimal('bodyweight_kg', 5, 1)->nullable();
            $table->timestamps();
        });

        Schema::create('workout_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained();
            $table->unsignedSmallInteger('position');
            $table->decimal('weight_kg', 6, 2);
            $table->unsignedSmallInteger('reps');
            $table->dateTime('done_at');
            $table->timestamps();
        });

        Schema::create('personal_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained();
            $table->decimal('max_weight_kg', 6, 2);
            $table->unsignedSmallInteger('max_weight_reps');
            $table->decimal('best_e1rm_kg', 6, 1);
            $table->dateTime('achieved_at');
            $table->timestamps();
            $table->unique(['user_id', 'exercise_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_records');
        Schema::dropIfExists('workout_sets');
        Schema::dropIfExists('workouts');
    }
};
```

- [ ] **Step 4 : Modèles**

`app/Models/Workout.php` :
```php
<?php

namespace App\Models;

use App\Services\RecordCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workout extends Model
{
    protected $fillable = ['client_id', 'started_at', 'finished_at', 'bodyweight_kg'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'bodyweight_kg' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sets(): HasMany
    {
        return $this->hasMany(WorkoutSet::class)->orderBy('position');
    }

    public function durationMinutes(): int
    {
        return (int) round(abs($this->started_at->diffInMinutes($this->finished_at)));
    }

    public function volume(): float
    {
        $calc = app(RecordCalculator::class);

        return round($this->sets->sum(fn (WorkoutSet $set) => $calc->setVolume(
            $set->weight_kg, $set->reps, $set->exercise->is_bodyweight, $this->bodyweight_kg,
        )), 1);
    }
}
```

`app/Models/WorkoutSet.php` :
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutSet extends Model
{
    protected $fillable = ['exercise_id', 'position', 'weight_kg', 'reps', 'done_at'];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'float',
            'reps' => 'integer',
            'position' => 'integer',
            'done_at' => 'datetime',
        ];
    }

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
```

`app/Models/PersonalRecord.php` :
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalRecord extends Model
{
    protected $fillable = ['user_id', 'exercise_id', 'max_weight_kg', 'max_weight_reps', 'best_e1rm_kg', 'achieved_at'];

    protected function casts(): array
    {
        return [
            'max_weight_kg' => 'float',
            'max_weight_reps' => 'integer',
            'best_e1rm_kg' => 'float',
            'achieved_at' => 'datetime',
        ];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
```

Dans `app/Models/User.php`, ajouter les imports et relations :
```php
use Illuminate\Database\Eloquent\Relations\HasMany;

    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class);
    }

    public function personalRecords(): HasMany
    {
        return $this->hasMany(PersonalRecord::class);
    }
```

- [ ] **Step 5 : Implémenter `WorkoutSync`**

`app/Services/WorkoutSync.php` :
```php
<?php

namespace App\Services;

use App\Models\PersonalRecord;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class WorkoutSync
{
    public function __construct(private RecordCalculator $calc) {}

    /**
     * @param  array{client_id: string, started_at: string, finished_at: string, sets: list<array{exercise_id: int, weight_kg: float|int|string, reps: int|string, done_at: string}>}  $data
     * @return array{workout: Workout, newRecords: list<PersonalRecord>, created: bool}
     */
    public function sync(User $user, array $data): array
    {
        $existing = $user->workouts()->where('client_id', $data['client_id'])->first();

        if ($existing) {
            return ['workout' => $existing->load('sets.exercise'), 'newRecords' => [], 'created' => false];
        }

        return DB::transaction(function () use ($user, $data) {
            $workout = $user->workouts()->create([
                'client_id' => $data['client_id'],
                'started_at' => $this->localTime($data['started_at']),
                'finished_at' => $this->localTime($data['finished_at']),
                'bodyweight_kg' => $user->weight_kg,
            ]);

            foreach (array_values($data['sets']) as $index => $set) {
                $workout->sets()->create([
                    'exercise_id' => (int) $set['exercise_id'],
                    'position' => $index + 1,
                    'weight_kg' => (float) $set['weight_kg'],
                    'reps' => (int) $set['reps'],
                    'done_at' => $this->localTime($set['done_at']),
                ]);
            }

            $workout->load('sets.exercise');

            return ['workout' => $workout, 'newRecords' => $this->updateRecords($user, $workout), 'created' => true];
        });
    }

    /** @return list<PersonalRecord> */
    private function updateRecords(User $user, Workout $workout): array
    {
        $improved = [];

        foreach ($workout->sets->groupBy('exercise_id') as $exerciseId => $sets) {
            $isBodyweight = $sets->first()->exercise->is_bodyweight;

            $best = $sets->sort(fn (WorkoutSet $a, WorkoutSet $b) => [$b->weight_kg, $b->reps] <=> [$a->weight_kg, $a->reps])->first();

            $bestE1rm = $sets->max(fn (WorkoutSet $set) => $this->calc->estimatedOneRepMax(
                $this->calc->effectiveLoad($set->weight_kg, $isBodyweight, $workout->bodyweight_kg),
                $set->reps,
            ));

            $record = PersonalRecord::firstWhere(['user_id' => $user->id, 'exercise_id' => $exerciseId]);

            if (! $record) {
                PersonalRecord::create([
                    'user_id' => $user->id,
                    'exercise_id' => $exerciseId,
                    'max_weight_kg' => $best->weight_kg,
                    'max_weight_reps' => $best->reps,
                    'best_e1rm_kg' => $bestE1rm,
                    'achieved_at' => $workout->finished_at,
                ]);

                continue;
            }

            if ($this->calc->beats($best->weight_kg, $best->reps, $record->max_weight_kg, $record->max_weight_reps)) {
                $record->fill([
                    'max_weight_kg' => $best->weight_kg,
                    'max_weight_reps' => $best->reps,
                    'achieved_at' => $workout->finished_at,
                ]);
                $improved[] = $record;
            }

            $record->best_e1rm_kg = max($record->best_e1rm_kg, $bestE1rm);
            $record->save();
        }

        foreach ($improved as $record) {
            $record->load('exercise');
        }

        return $improved;
    }

    private function localTime(string $iso): Carbon
    {
        return Carbon::parse($iso)->setTimezone(config('app.timezone'));
    }
}
```

- [ ] **Step 6 : Vérifier que tout passe**

Run : `./vendor/bin/pest`
Attendu : PASS.

- [ ] **Step 7 : Commit**

```bash
git add -A
git commit -m "feat: séances, séries et records avec synchronisation idempotente

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 8 : Route de synchronisation `POST /workouts/sync`

**Files :**
- Create : `app/Http/Requests/SyncWorkoutRequest.php`, `app/Http/Controllers/WorkoutSyncController.php`
- Modify : `routes/web.php`
- Test : `tests/Feature/WorkoutSyncEndpointTest.php`

**Interfaces :**
- Consumes : `WorkoutSync::sync`, `Format` (non), `Workout::durationMinutes()`, `Workout::volume()`
- Produces : `POST /workouts/sync` (nommée `workouts.sync`). Elle répond en JSON `{ workout_id: int, created: bool, duration_minutes: int, volume_kg: float, sets_count: int, new_records: [{ exercise: string, weight_kg: float, reps: int }] }`, avec le statut 201 si la séance est créée, 200 si elle existait déjà, 422 si la validation échoue et 401 pour un invité.

- [ ] **Step 1 : Écrire le test qui échoue**

`tests/Feature/WorkoutSyncEndpointTest.php` :
```php
<?php

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(weightKg: 80.0)->create();
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
    $this->body = [
        'client_id' => (string) Str::uuid(),
        'started_at' => '2026-10-01T16:00:00.000Z',
        'finished_at' => '2026-10-01T16:45:00.000Z',
        'sets' => [
            ['exercise_id' => $this->bench->id, 'weight_kg' => 60, 'reps' => 8, 'done_at' => '2026-10-01T16:10:00.000Z'],
        ],
    ];
});

it('enregistre une séance et renvoie le résumé', function () {
    $this->actingAs($this->user)
        ->postJson('/workouts/sync', $this->body)
        ->assertCreated()
        ->assertJson([
            'created' => true,
            'duration_minutes' => 45,
            'volume_kg' => 480,
            'sets_count' => 1,
            'new_records' => [],
        ]);
});

it('renvoie 200 sans doublon au deuxième envoi', function () {
    $this->actingAs($this->user)->postJson('/workouts/sync', $this->body)->assertCreated();
    $this->actingAs($this->user)->postJson('/workouts/sync', $this->body)->assertOk()->assertJson(['created' => false]);

    expect(Workout::count())->toBe(1);
});

it('renvoie les records battus', function () {
    $this->actingAs($this->user)->postJson('/workouts/sync', $this->body);

    $this->body['client_id'] = (string) Str::uuid();
    $this->body['sets'][0]['weight_kg'] = 62.5;

    $this->actingAs($this->user)
        ->postJson('/workouts/sync', $this->body)
        ->assertJsonPath('new_records.0.exercise', 'Développé couché (barre)')
        ->assertJsonPath('new_records.0.weight_kg', 62.5);
});

it('refuse un contenu invalide', function (string $case, string $field) {
    // Les modifications sont définies ici et non dans le dataset : Pest exécute lui-même les closures d'un dataset.
    $mutations = [
        'client_id absent' => fn (array $b) => array_diff_key($b, ['client_id' => 1]),
        'client_id pas uuid' => fn (array $b) => [...$b, 'client_id' => 'abc'],
        'aucune série' => fn (array $b) => [...$b, 'sets' => []],
        'exercice inconnu' => function (array $b) { $b['sets'][0]['exercise_id'] = 999999; return $b; },
        'charge négative' => function (array $b) { $b['sets'][0]['weight_kg'] = -5; return $b; },
        'zéro répétition' => function (array $b) { $b['sets'][0]['reps'] = 0; return $b; },
        'fin avant début' => fn (array $b) => [...$b, 'finished_at' => '2026-10-01T15:00:00.000Z'],
    ];

    $this->actingAs($this->user)->postJson('/workouts/sync', $mutations[$case]($this->body))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with([
    ['client_id absent', 'client_id'],
    ['client_id pas uuid', 'client_id'],
    ['aucune série', 'sets'],
    ['exercice inconnu', 'sets.0.exercise_id'],
    ['charge négative', 'sets.0.weight_kg'],
    ['zéro répétition', 'sets.0.reps'],
    ['fin avant début', 'finished_at'],
]);

it('refuse un invité', function () {
    $this->postJson('/workouts/sync', $this->body)->assertUnauthorized();
});
```

- [ ] **Step 2 : Vérifier l'échec**

Run : `./vendor/bin/pest tests/Feature/WorkoutSyncEndpointTest.php`
Attendu : FAIL (404 sur `/workouts/sync`).

- [ ] **Step 3 : Requête de validation**

`app/Http/Requests/SyncWorkoutRequest.php` :
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SyncWorkoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'uuid'],
            'started_at' => ['required', 'date'],
            'finished_at' => ['required', 'date', 'after_or_equal:started_at'],
            'sets' => ['required', 'array', 'min:1', 'max:500'],
            'sets.*.exercise_id' => ['required', 'integer', 'exists:exercises,id'],
            'sets.*.weight_kg' => ['required', 'numeric', 'min:0', 'max:1000'],
            'sets.*.reps' => ['required', 'integer', 'min:1', 'max:1000'],
            'sets.*.done_at' => ['required', 'date'],
        ];
    }
}
```

- [ ] **Step 4 : Contrôleur**

`app/Http/Controllers/WorkoutSyncController.php` :
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncWorkoutRequest;
use App\Models\PersonalRecord;
use App\Services\WorkoutSync;
use Illuminate\Http\JsonResponse;

class WorkoutSyncController extends Controller
{
    public function __invoke(SyncWorkoutRequest $request, WorkoutSync $sync): JsonResponse
    {
        ['workout' => $workout, 'newRecords' => $records, 'created' => $created] = $sync->sync($request->user(), $request->validated());

        return response()->json([
            'workout_id' => $workout->id,
            'created' => $created,
            'duration_minutes' => $workout->durationMinutes(),
            'volume_kg' => $workout->volume(),
            'sets_count' => $workout->sets->count(),
            'new_records' => array_map(fn (PersonalRecord $r) => [
                'exercise' => $r->exercise->name,
                'weight_kg' => $r->max_weight_kg,
                'reps' => $r->max_weight_reps,
            ], $records),
        ], $created ? 201 : 200);
    }
}
```

- [ ] **Step 5 : Route**

Dans le groupe `auth` de `routes/web.php`, ajouter :
```php
    Route::post('/workouts/sync', WorkoutSyncController::class)->name('workouts.sync');
```
et l'import `use App\Http\Controllers\WorkoutSyncController;`.

- [ ] **Step 6 : Vérifier que tout passe**

Run : `./vendor/bin/pest`
Attendu : PASS.

- [ ] **Step 7 : Commit**

```bash
git add -A
git commit -m "feat: route JSON de synchronisation des séances

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 9 : État de séance et file d'attente côté navigateur (JS pur)

**Files :**
- Create : `resources/js/workout/store.js`, `resources/js/workout/sync.js`
- Test : `resources/js/workout/store.test.js`, `resources/js/workout/sync.test.js`

**Interfaces :**
- Produces (`store.js`) :
  - `uuid(): string` (uuid v4, fonctionne sans `crypto.randomUUID`)
  - `toNumber(value): number` (accepte « 62,5 »)
  - `newWorkout(clientId: string, startedAtIso: string): State`
  - `addExercise(state, { id, name, isBodyweight }, { lastTime?: {weightKg, reps}[] | null, suggestedKg?: number | null, restSeconds: number }): State`
  - `adjustDraft(state, field: 'weightKg' | 'reps', delta: number): State`
  - `logSet(state, nowMs: number): State` (démarre le repos)
  - `removeLastSet(state): State`, `skipRest(state): State`, `addRest(state, seconds, nowMs): State`, `goTo(state, index): State`
  - `hasSets(state): boolean`, `toPayload(state, finishedAtIso): Payload`
  - `createStore(storage)` → `{ loadActive(), saveActive(state), clearActive(), pending(), queuePending(payload), removePending(clientId) }`
  - `finishWorkout(store, state, finishedAtIso): Payload` (met la séance en file d'attente et efface la séance active)
  - Forme de `State` : `{ clientId, startedAt, currentIndex, restEndsAt: number|null, exercises: [{ exerciseId, name, isBodyweight, restSeconds, lastTime, draft: { weightKg, reps }, sets: [{ weightKg, reps, doneAt }] }] }`
- Produces (`sync.js`) :
  - `sendWorkout(payload, { token, fetchImpl?, url? }): Promise<{status: 'ok'|'invalid'|'retry', body?}>`
  - `flushPending(store, send): Promise<{ sent: Record<clientId, body>, invalid: string[], remaining: number }>`

- [ ] **Step 1 : Écrire les tests qui échouent**

`resources/js/workout/store.test.js` :
```js
import { describe, expect, it, vi } from 'vitest';
import {
    addExercise, addRest, adjustDraft, createStore, finishWorkout, goTo, hasSets,
    logSet, newWorkout, removeLastSet, skipRest, toNumber, toPayload, uuid,
} from './store';

function memoryStorage() {
    const data = new Map();
    return {
        getItem: (k) => (data.has(k) ? data.get(k) : null),
        setItem: (k, v) => data.set(k, String(v)),
        removeItem: (k) => data.delete(k),
    };
}

const bench = { id: 1, name: 'Développé couché', isBodyweight: false };
const pullUp = { id: 2, name: 'Tractions', isBodyweight: true };

describe('uuid', () => {
    it('génère un uuid v4 même sans crypto.randomUUID (http sur IP locale)', () => {
        const real = globalThis.crypto;
        vi.stubGlobal('crypto', { getRandomValues: (a) => real.getRandomValues(a) });
        const id = uuid();
        vi.unstubAllGlobals();
        expect(id).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
    });
});

describe('toNumber', () => {
    it('accepte la virgule décimale', () => {
        expect(toNumber('62,5')).toBe(62.5);
        expect(toNumber(60)).toBe(60);
        expect(toNumber('')).toBe(0);
        expect(toNumber('abc')).toBe(0);
    });
});

describe('séance', () => {
    it('pré-remplit avec la dernière fois, sinon la charge suggérée, sinon 0 × 10', () => {
        let s = newWorkout('id-1', '2026-10-01T16:00:00.000Z');
        s = addExercise(s, bench, { lastTime: [{ weightKg: 60, reps: 8 }], suggestedKg: 40, restSeconds: 90 });
        expect(s.exercises[0].draft).toEqual({ weightKg: 60, reps: 8 });

        s = addExercise(s, bench, { lastTime: null, suggestedKg: 40, restSeconds: 90 });
        expect(s.exercises[1].draft).toEqual({ weightKg: 40, reps: 10 });

        s = addExercise(s, pullUp, { restSeconds: 90 });
        expect(s.exercises[2].draft).toEqual({ weightKg: 0, reps: 10 });
        expect(s.currentIndex).toBe(2);
    });

    it('ajuste le brouillon par pas de 2,5 kg et 1 rep sans passer sous les minimums', () => {
        let s = addExercise(newWorkout('id', 't'), bench, { suggestedKg: 2.5, restSeconds: 90 });
        s = adjustDraft(s, 'weightKg', -2.5);
        s = adjustDraft(s, 'weightKg', -2.5);
        expect(s.exercises[0].draft.weightKg).toBe(0);
        s.exercises[0].draft.weightKg = '62,5';
        s = adjustDraft(s, 'weightKg', 2.5);
        expect(s.exercises[0].draft.weightKg).toBe(65);
        s.exercises[0].draft.reps = 1;
        s = adjustDraft(s, 'reps', -1);
        expect(s.exercises[0].draft.reps).toBe(1);
    });

    it('valide une série et démarre le repos', () => {
        let s = addExercise(newWorkout('id', 't'), bench, { lastTime: [{ weightKg: 60, reps: 8 }], restSeconds: 90 });
        s = logSet(s, 1_000_000);
        expect(s.exercises[0].sets).toHaveLength(1);
        expect(s.exercises[0].sets[0]).toMatchObject({ weightKg: 60, reps: 8 });
        expect(s.restEndsAt).toBe(1_000_000 + 90_000);
        s = addRest(s, 30, 1_000_000);
        expect(s.restEndsAt).toBe(1_000_000 + 120_000);
        s = skipRest(s);
        expect(s.restEndsAt).toBeNull();
        s = removeLastSet(s);
        expect(s.exercises[0].sets).toHaveLength(0);
    });

    it('ne fait rien sans exercice courant', () => {
        const s = logSet(newWorkout('id', 't'), 0);
        expect(s.restEndsAt).toBeNull();
        expect(hasSets(s)).toBe(false);
    });

    it('produit le contenu envoyé au serveur en ignorant les exercices sans série', () => {
        let s = newWorkout('abc', '2026-10-01T16:00:00.000Z');
        s = addExercise(s, bench, { lastTime: [{ weightKg: 60, reps: 8 }], restSeconds: 90 });
        s = logSet(s, 0);
        s = addExercise(s, pullUp, { restSeconds: 90 });
        s = goTo(s, 0);
        expect(s.currentIndex).toBe(0);
        const p = toPayload(s, '2026-10-01T17:00:00.000Z');
        expect(p).toMatchObject({ client_id: 'abc', started_at: '2026-10-01T16:00:00.000Z', finished_at: '2026-10-01T17:00:00.000Z' });
        expect(p.sets).toEqual([{ exercise_id: 1, weight_kg: 60, reps: 8, done_at: expect.any(String) }]);
    });
});

describe('stockage', () => {
    it('sauvegarde et reprend une séance active', () => {
        const store = createStore(memoryStorage());
        expect(store.loadActive()).toBeNull();
        const s = addExercise(newWorkout('id', 't'), bench, { restSeconds: 90 });
        store.saveActive(s);
        expect(store.loadActive()).toEqual(s);
        store.clearActive();
        expect(store.loadActive()).toBeNull();
    });

    it('ignore un contenu corrompu', () => {
        const storage = memoryStorage();
        storage.setItem('ego.activeWorkout', '{pas du json');
        expect(createStore(storage).loadActive()).toBeNull();
    });

    it('met en file d\'attente sans doublon et efface la séance active à la fin', () => {
        const store = createStore(memoryStorage());
        let s = addExercise(newWorkout('abc', 't'), bench, { lastTime: [{ weightKg: 60, reps: 8 }], restSeconds: 90 });
        s = logSet(s, 0);
        store.saveActive(s);
        finishWorkout(store, s, 'fin');
        finishWorkout(store, s, 'fin');
        expect(store.pending()).toHaveLength(1);
        expect(store.loadActive()).toBeNull();
        store.removePending('abc');
        expect(store.pending()).toEqual([]);
    });
});
```

`resources/js/workout/sync.test.js` :
```js
import { describe, expect, it } from 'vitest';
import { createStore } from './store';
import { flushPending, sendWorkout } from './sync';

function memoryStorage() {
    const data = new Map();
    return {
        getItem: (k) => (data.has(k) ? data.get(k) : null),
        setItem: (k, v) => data.set(k, String(v)),
        removeItem: (k) => data.delete(k),
    };
}

const fakeFetch = (status, body = {}) => async () => ({ ok: status >= 200 && status < 300, status, json: async () => body });

describe('sendWorkout', () => {
    it('renvoie ok avec le corps en cas de succès', async () => {
        const r = await sendWorkout({ client_id: 'a' }, { token: 't', fetchImpl: fakeFetch(201, { workout_id: 1 }) });
        expect(r).toEqual({ status: 'ok', body: { workout_id: 1 } });
    });

    it('renvoie invalid sur 422', async () => {
        const r = await sendWorkout({}, { token: 't', fetchImpl: fakeFetch(422, { errors: {} }) });
        expect(r.status).toBe('invalid');
    });

    it('renvoie retry sur session expirée (419/401), erreur serveur ou coupure réseau', async () => {
        for (const status of [419, 401, 500]) {
            expect((await sendWorkout({}, { token: 't', fetchImpl: fakeFetch(status) })).status).toBe('retry');
        }
        const offline = async () => { throw new TypeError('Failed to fetch'); };
        expect((await sendWorkout({}, { token: 't', fetchImpl: offline })).status).toBe('retry');
    });

    it('envoie le jeton CSRF et du JSON', async () => {
        let seen;
        const spy = async (url, init) => { seen = { url, init }; return { ok: true, status: 201, json: async () => ({}) }; };
        await sendWorkout({ client_id: 'a' }, { token: 'tok', fetchImpl: spy });
        expect(seen.url).toBe('/workouts/sync');
        expect(seen.init.headers['X-CSRF-TOKEN']).toBe('tok');
        expect(JSON.parse(seen.init.body)).toEqual({ client_id: 'a' });
    });
});

describe('flushPending', () => {
    it('retire les séances envoyées et garde les autres', async () => {
        const store = createStore(memoryStorage());
        store.queuePending({ client_id: 'ok' });
        store.queuePending({ client_id: 'bad' });
        store.queuePending({ client_id: 'later' });
        const send = async (p) => ({ ok: { status: 'ok', body: { id: 1 } }, bad: { status: 'invalid' }, later: { status: 'retry' } }[p.client_id]);

        const result = await flushPending(store, send);

        expect(result.sent).toEqual({ ok: { id: 1 } });
        expect(result.invalid).toEqual(['bad']);
        expect(result.remaining).toBe(2);
        expect(store.pending().map((p) => p.client_id)).toEqual(['bad', 'later']);
    });
});
```

- [ ] **Step 2 : Vérifier l'échec**

Run : `npm test`
Attendu : FAIL (`Failed to resolve import "./store"`).

- [ ] **Step 3 : Implémenter `store.js`**

`resources/js/workout/store.js` :
```js
const ACTIVE_KEY = 'ego.activeWorkout';
const PENDING_KEY = 'ego.pendingWorkouts';
const WEIGHT_STEP = 2.5;

export function uuid() {
    if (typeof globalThis.crypto?.randomUUID === 'function') {
        return globalThis.crypto.randomUUID();
    }
    const bytes = globalThis.crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export function toNumber(value) {
    const n = parseFloat(String(value).replace(',', '.'));
    return Number.isFinite(n) ? n : 0;
}

const round2 = (n) => Math.round(n * 100) / 100;

export function newWorkout(clientId, startedAtIso) {
    return { clientId, startedAt: startedAtIso, currentIndex: 0, restEndsAt: null, exercises: [] };
}

export function addExercise(state, exercise, { lastTime = null, suggestedKg = null, restSeconds }) {
    const first = lastTime?.[0];
    state.exercises.push({
        exerciseId: exercise.id,
        name: exercise.name,
        isBodyweight: exercise.isBodyweight,
        restSeconds,
        lastTime,
        draft: { weightKg: first?.weightKg ?? suggestedKg ?? 0, reps: first?.reps ?? 10 },
        sets: [],
    });
    state.currentIndex = state.exercises.length - 1;
    return state;
}

function current(state) {
    return state.exercises[state.currentIndex] ?? null;
}

export function adjustDraft(state, field, delta) {
    const ex = current(state);
    if (!ex) return state;
    if (field === 'weightKg') {
        ex.draft.weightKg = Math.max(0, round2(toNumber(ex.draft.weightKg) + delta));
    } else {
        ex.draft.reps = Math.max(1, Math.round(toNumber(ex.draft.reps) + delta));
    }
    return state;
}

export function logSet(state, nowMs) {
    const ex = current(state);
    if (!ex) return state;
    ex.sets.push({
        weightKg: Math.max(0, round2(toNumber(ex.draft.weightKg))),
        reps: Math.max(1, Math.round(toNumber(ex.draft.reps))),
        doneAt: new Date(nowMs).toISOString(),
    });
    state.restEndsAt = nowMs + ex.restSeconds * 1000;
    return state;
}

export function removeLastSet(state) {
    current(state)?.sets.pop();
    return state;
}

export function skipRest(state) {
    state.restEndsAt = null;
    return state;
}

export function addRest(state, seconds, nowMs) {
    state.restEndsAt = Math.max(state.restEndsAt ?? nowMs, nowMs) + seconds * 1000;
    return state;
}

export function goTo(state, index) {
    if (index >= 0 && index < state.exercises.length) state.currentIndex = index;
    return state;
}

export function hasSets(state) {
    return state.exercises.some((ex) => ex.sets.length > 0);
}

export function toPayload(state, finishedAtIso) {
    return {
        client_id: state.clientId,
        started_at: state.startedAt,
        finished_at: finishedAtIso,
        sets: state.exercises.flatMap((ex) => ex.sets.map((set) => ({
            exercise_id: ex.exerciseId,
            weight_kg: set.weightKg,
            reps: set.reps,
            done_at: set.doneAt,
        }))),
    };
}

function readJson(storage, key, fallback) {
    try {
        const raw = storage.getItem(key);
        return raw === null ? fallback : JSON.parse(raw);
    } catch {
        return fallback;
    }
}

export function createStore(storage) {
    return {
        loadActive: () => readJson(storage, ACTIVE_KEY, null),
        saveActive: (state) => storage.setItem(ACTIVE_KEY, JSON.stringify(state)),
        clearActive: () => storage.removeItem(ACTIVE_KEY),
        pending: () => readJson(storage, PENDING_KEY, []),
        queuePending(payload) {
            const list = this.pending().filter((p) => p.client_id !== payload.client_id);
            list.push(payload);
            storage.setItem(PENDING_KEY, JSON.stringify(list));
        },
        removePending(clientId) {
            storage.setItem(PENDING_KEY, JSON.stringify(this.pending().filter((p) => p.client_id !== clientId)));
        },
    };
}

export function finishWorkout(store, state, finishedAtIso) {
    const payload = toPayload(state, finishedAtIso);
    store.queuePending(payload);
    store.clearActive();
    return payload;
}
```

- [ ] **Step 4 : Implémenter `sync.js`**

`resources/js/workout/sync.js` :
```js
export async function sendWorkout(payload, { token, fetchImpl = (...args) => globalThis.fetch(...args), url = '/workouts/sync' }) {
    try {
        const response = await fetchImpl(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });
        if (response.ok) return { status: 'ok', body: await response.json() };
        if (response.status === 422) return { status: 'invalid', body: await response.json() };
        return { status: 'retry' };
    } catch {
        return { status: 'retry' };
    }
}

export async function flushPending(store, send) {
    const result = { sent: {}, invalid: [], remaining: 0 };
    for (const payload of store.pending()) {
        const response = await send(payload);
        if (response.status === 'ok') {
            store.removePending(payload.client_id);
            result.sent[payload.client_id] = response.body;
        } else if (response.status === 'invalid') {
            result.invalid.push(payload.client_id);
        }
    }
    result.remaining = store.pending().length;
    return result;
}
```

- [ ] **Step 5 : Vérifier que tout passe**

Run : `npm test`
Attendu : PASS (tous les tests de `store.test.js` et `sync.test.js`).

- [ ] **Step 6 : Commit**

```bash
git add -A
git commit -m "feat: état de séance local et file d'attente de synchronisation

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 10 : Écran « Séance en cours »

**Files :**
- Create : `app/Services/WorkoutBootData.php`, `app/Http/Controllers/WorkoutController.php`, `resources/views/workout/run.blade.php`, `resources/js/workout/component.js`
- Modify : `routes/web.php` (remplace la route provisoire `workout`), `resources/js/app.js`
- Test : `tests/Feature/WorkoutPageTest.php`

**Interfaces :**
- Consumes : `StartingLoad::suggest`, `Exercise`, `WorkoutSet`, fonctions de `store.js` et `sync.js`
- Produces :
  - `WorkoutBootData::for(User $user): array{restSeconds: int, exercises: list<array{id:int,name:string,isBodyweight:bool,suggestedKg:?float}>, lastTime: array<int, list<array{weightKg: float, reps: int}>>}`
  - `GET /workout` (nommée `workout`) ; composant Alpine `workout`

- [ ] **Step 1 : Écrire le test qui échoue**

`tests/Feature/WorkoutPageTest.php` :
```php
<?php

use App\Models\Exercise;
use App\Models\User;
use App\Services\WorkoutBootData;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(sex: 'male', weightKg: 80.0)->create();
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
});

function doWorkout(User $user, array $sets, string $finishedAt): void
{
    app(WorkoutSync::class)->sync($user, [
        'client_id' => (string) Str::uuid(),
        'started_at' => $finishedAt,
        'finished_at' => $finishedAt,
        'sets' => array_map(fn ($s) => ['exercise_id' => $s[0], 'weight_kg' => $s[1], 'reps' => $s[2], 'done_at' => $finishedAt], $sets),
    ]);
}

it('fournit la bibliothèque avec les charges suggérées', function () {
    $data = app(WorkoutBootData::class)->for($this->user);
    $bench = collect($data['exercises'])->firstWhere('id', $this->bench->id);

    expect($data['restSeconds'])->toBe(90)
        ->and($bench['suggestedKg'])->toBe(40.0)
        ->and($bench['isBodyweight'])->toBeFalse();
});

it('fournit les séries de la dernière séance de chaque exercice', function () {
    doWorkout($this->user, [[$this->bench->id, 50, 10]], '2026-09-20T10:00:00Z');
    doWorkout($this->user, [[$this->bench->id, 60, 8], [$this->bench->id, 57.5, 8]], '2026-09-27T10:00:00Z');

    $last = app(WorkoutBootData::class)->for($this->user)['lastTime'][$this->bench->id];

    expect($last)->toBe([['weightKg' => 60.0, 'reps' => 8], ['weightKg' => 57.5, 'reps' => 8]]);
});

it('n\'utilise pas les séances des autres', function () {
    doWorkout(User::factory()->create(), [[$this->bench->id, 100, 5]], '2026-09-27T10:00:00Z');

    expect(app(WorkoutBootData::class)->for($this->user)['lastTime'])->toBe([]);
});

it('affiche l\'écran de séance avec les données de démarrage', function () {
    $this->actingAs($this->user)
        ->get('/workout')
        ->assertOk()
        ->assertSee('id="workout-boot"', false)
        ->assertSee('x-data="workout"', false)
        ->assertSee('Développé couché (barre)');
});
```

- [ ] **Step 2 : Vérifier l'échec**

Run : `./vendor/bin/pest tests/Feature/WorkoutPageTest.php`
Attendu : FAIL (`Class "App\Services\WorkoutBootData" not found`).

- [ ] **Step 3 : Service de données de démarrage**

`app/Services/WorkoutBootData.php` :
```php
<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSet;

final class WorkoutBootData
{
    public const REST_SECONDS = 90;

    public function __construct(private StartingLoad $startingLoad) {}

    public function for(User $user): array
    {
        return [
            'restSeconds' => self::REST_SECONDS,
            'exercises' => Exercise::orderBy('name')->get()->map(fn (Exercise $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'isBodyweight' => $e->is_bodyweight,
                'suggestedKg' => $this->startingLoad->suggest($e, $user),
            ])->values()->all(),
            'lastTime' => $this->lastTime($user),
        ];
    }

    /** @return array<int, list<array{weightKg: float, reps: int}>> */
    private function lastTime(User $user): array
    {
        $sets = WorkoutSet::query()
            ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
            ->where('workouts.user_id', $user->id)
            ->orderByDesc('workouts.finished_at')
            ->orderByDesc('workouts.id')
            ->orderBy('workout_sets.position')
            ->get(['workout_sets.exercise_id', 'workout_sets.workout_id', 'workout_sets.weight_kg', 'workout_sets.reps']);

        $result = [];
        $latestWorkout = [];

        foreach ($sets as $set) {
            $latestWorkout[$set->exercise_id] ??= $set->workout_id;

            if ($latestWorkout[$set->exercise_id] === $set->workout_id) {
                $result[$set->exercise_id][] = ['weightKg' => $set->weight_kg, 'reps' => $set->reps];
            }
        }

        return $result;
    }
}
```

- [ ] **Step 4 : Contrôleur et route**

`app/Http/Controllers/WorkoutController.php` :
```php
<?php

namespace App\Http\Controllers;

use App\Services\WorkoutBootData;
use Illuminate\Http\Request;

class WorkoutController extends Controller
{
    public function create(Request $request, WorkoutBootData $bootData)
    {
        return view('workout.run', ['boot' => $bootData->for($request->user())]);
    }
}
```

Dans `routes/web.php`, remplacer la ligne provisoire `Route::view('/workout', …)` par :
```php
    Route::get('/workout', [WorkoutController::class, 'create'])->name('workout');
```
et ajouter `use App\Http\Controllers\WorkoutController;`.

- [ ] **Step 5 : Composant Alpine**

`resources/js/workout/component.js` :
```js
import {
    addExercise, addRest, adjustDraft, createStore, finishWorkout, goTo, hasSets,
    logSet, newWorkout, removeLastSet, skipRest, uuid,
} from './store';
import { flushPending, sendWorkout } from './sync';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export function formatKg(kg) {
    return `${String(Math.round(kg * 100) / 100).replace('.', ',')} kg`;
}

export default function workout() {
    return {
        boot: { restSeconds: 90, exercises: [], lastTime: {} },
        state: null,
        store: null,
        now: Date.now(),
        picking: false,
        search: '',
        finishing: false,
        confirmDiscard: false,
        done: false,
        summary: null,
        audio: null,

        init() {
            this.boot = JSON.parse(document.getElementById('workout-boot').textContent);
            this.store = createStore(window.localStorage);
            this.state = this.store.loadActive() ?? newWorkout(uuid(), new Date().toISOString());
            this.picking = this.state.exercises.length === 0;
            this.persist();
            this.$watch('state', () => this.persist());
            setInterval(() => this.tick(), 250);
            navigator.wakeLock?.request('screen').catch(() => {});
        },

        persist() {
            if (!this.done) this.store.saveActive(this.state);
        },

        get current() {
            return this.state.exercises[this.state.currentIndex] ?? null;
        },

        get restRemaining() {
            if (!this.state.restEndsAt) return 0;
            return Math.max(0, Math.ceil((this.state.restEndsAt - this.now) / 1000));
        },

        get elapsed() {
            const total = Math.max(0, Math.floor((this.now - Date.parse(this.state.startedAt)) / 1000));
            const h = Math.floor(total / 3600);
            const m = String(Math.floor((total % 3600) / 60)).padStart(2, '0');
            const s = String(total % 60).padStart(2, '0');
            return h > 0 ? `${h}:${m}:${s}` : `${m}:${s}`;
        },

        get filtered() {
            const q = this.search.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            return this.boot.exercises
                .filter((e) => !q || e.name.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').includes(q))
                .slice(0, 50);
        },

        get canFinish() {
            return hasSets(this.state);
        },

        clock(seconds) {
            return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
        },

        formatKg,

        lastTimeText(ex) {
            if (!ex.lastTime?.length) return null;
            return ex.lastTime.map((s) => `${formatKg(s.weightKg)} × ${s.reps}`).join(' · ');
        },

        tick() {
            this.now = Date.now();
            if (this.state.restEndsAt && this.now >= this.state.restEndsAt) {
                skipRest(this.state);
                this.alarm();
            }
        },

        alarm() {
            navigator.vibrate?.([300, 100, 300]);
            if (!this.audio) return;
            [0, 0.35].forEach((offset) => {
                const osc = this.audio.createOscillator();
                const gain = this.audio.createGain();
                osc.frequency.value = 880;
                osc.connect(gain).connect(this.audio.destination);
                gain.gain.setValueAtTime(0.25, this.audio.currentTime + offset);
                osc.start(this.audio.currentTime + offset);
                osc.stop(this.audio.currentTime + offset + 0.2);
            });
        },

        add(exercise) {
            addExercise(this.state, exercise, {
                lastTime: this.boot.lastTime[exercise.id] ?? null,
                suggestedKg: exercise.suggestedKg,
                restSeconds: this.boot.restSeconds,
            });
            this.picking = false;
            this.search = '';
        },

        adjust(field, delta) { adjustDraft(this.state, field, delta); },
        go(index) { goTo(this.state, index); },
        undoSet() { removeLastSet(this.state); },
        skipRest() { skipRest(this.state); },
        moreRest() { addRest(this.state, 30, Date.now()); },

        validateSet() {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!this.audio && Ctx) this.audio = new Ctx();
            logSet(this.state, Date.now());
        },

        async finish() {
            if (!this.canFinish || this.finishing) return;
            this.finishing = true;
            this.done = true;
            const payload = finishWorkout(this.store, this.state, new Date().toISOString());
            const result = await flushPending(this.store, (p) => sendWorkout(p, { token: csrfToken() }));
            const body = result.sent[payload.client_id];
            this.summary = body
                ? { pending: false, ...body }
                : { pending: true, invalid: result.invalid.includes(payload.client_id) };
            this.finishing = false;
        },

        discard() {
            if (!this.confirmDiscard) {
                this.confirmDiscard = true;
                return;
            }
            this.done = true;
            this.store.clearActive();
            window.location.href = '/';
        },
    };
}
```

- [ ] **Step 6 : Enregistrer le composant, reprise et synchronisation globale**

`resources/js/app.js` (remplacement complet) :
```js
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import workout from './workout/component';
import { createStore } from './workout/store';
import { flushPending, sendWorkout } from './workout/sync';

window.Alpine = Alpine;
Alpine.data('workout', workout);
Livewire.start();

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
}

function updateOfflineBanner() {
    const banner = document.getElementById('offline-banner');
    if (banner) banner.hidden = navigator.onLine;
}

function syncPending() {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!token || !document.body.dataset.auth || !navigator.onLine) return;
    flushPending(createStore(window.localStorage), (p) => sendWorkout(p, { token }));
}

function showResumeBanner() {
    const banner = document.getElementById('resume-banner');
    if (banner && createStore(window.localStorage).loadActive()) banner.hidden = false;
}

window.addEventListener('online', () => { updateOfflineBanner(); syncPending(); });
window.addEventListener('offline', updateOfflineBanner);
document.addEventListener('DOMContentLoaded', () => {
    updateOfflineBanner();
    showResumeBanner();
    if (!document.getElementById('workout-boot')) syncPending();
});
```
(Sur l'écran de séance, c'est `finish()` qui déclenche l'envoi. On évite ainsi deux envois simultanés de la même file d'attente.)

- [ ] **Step 7 : Vue de l'écran de séance**

`resources/views/workout/run.blade.php` :
```blade
<x-layouts.app title="Séance" :bare="true">
    {{-- JSON_UNESCAPED_UNICODE : noms lisibles (« Développé ») ; JSON_HEX_TAG : impossible de fermer la balise script --}}
    <script type="application/json" id="workout-boot">{!! json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_THROW_ON_ERROR) !!}</script>

    <div x-data="workout" x-cloak class="mx-auto flex min-h-screen max-w-xl flex-col px-4 pb-10 pt-[max(1rem,env(safe-area-inset-top))]">
        <header class="flex items-center justify-between gap-3 py-2">
            <a href="{{ route('home') }}" class="text-sm text-zinc-400">← Accueil</a>
            <span class="font-mono text-sm tabular-nums text-zinc-400" x-text="elapsed"></span>
            <button type="button" class="rounded-lg bg-lime-400 px-4 py-2 text-sm font-bold text-zinc-950 disabled:opacity-40"
                    x-show="!summary" :disabled="!canFinish || finishing" @click="finish()">
                <span x-text="finishing ? 'Envoi…' : 'Terminer'"></span>
            </button>
        </header>

        {{-- Résumé --}}
        <template x-if="summary">
            <section class="mt-6 space-y-4">
                <h1 class="text-2xl font-bold">Séance terminée 💪</h1>
                <template x-if="!summary.pending">
                    <div class="space-y-3">
                        <div class="grid grid-cols-3 gap-2 text-center">
                            <div class="card"><p class="text-xs text-zinc-400">Durée</p><p class="text-lg font-bold" x-text="summary.duration_minutes + ' min'"></p></div>
                            <div class="card"><p class="text-xs text-zinc-400">Séries</p><p class="text-lg font-bold" x-text="summary.sets_count"></p></div>
                            <div class="card"><p class="text-xs text-zinc-400">Volume</p><p class="text-lg font-bold" x-text="formatKg(summary.volume_kg)"></p></div>
                        </div>
                        <template x-if="summary.new_records.length">
                            <div class="card border-lime-400/50">
                                <p class="mb-2 font-bold text-lime-400">Nouveaux records 🏆</p>
                                <template x-for="r in summary.new_records" :key="r.exercise">
                                    <p class="text-sm"><span x-text="r.exercise"></span> — <span x-text="formatKg(r.weight_kg) + ' × ' + r.reps"></span></p>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="summary.pending">
                    <div class="card text-sm text-zinc-300">
                        <span x-show="!summary.invalid">Séance enregistrée sur ton téléphone. Elle sera envoyée dès que la connexion revient.</span>
                        <span x-show="summary.invalid">Le serveur a refusé cette séance. Elle reste enregistrée sur ton téléphone.</span>
                    </div>
                </template>
                <a href="{{ route('home') }}" class="btn-primary">Retour à l'accueil</a>
            </section>
        </template>

        {{-- Séance --}}
        <template x-if="!summary">
            <section class="mt-2 flex flex-1 flex-col gap-4">
                <nav class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1" x-show="state.exercises.length">
                    <template x-for="(ex, i) in state.exercises" :key="i">
                        <button type="button" @click="go(i)"
                                class="shrink-0 rounded-full border px-3 py-1.5 text-sm"
                                :class="i === state.currentIndex ? 'border-lime-400 bg-lime-400/10 text-lime-300' : 'border-zinc-800 text-zinc-400'">
                            <span x-text="ex.name"></span>
                            <span class="ml-1 text-xs" x-text="ex.sets.length ? '(' + ex.sets.length + ')' : ''"></span>
                        </button>
                    </template>
                </nav>

                <template x-if="current && !picking">
                    <div class="card space-y-4">
                        <div>
                            <h1 class="text-2xl font-bold" x-text="current.name"></h1>
                            <p class="mt-1 text-sm text-zinc-400" x-show="lastTimeText(current)">
                                Dernière fois : <span x-text="lastTimeText(current)"></span>
                            </p>
                            <p class="mt-1 text-sm text-zinc-500" x-show="current.isBodyweight">Poids du corps — indique la charge ajoutée (0 si aucune).</p>
                        </div>

                        <ol class="space-y-1" x-show="current.sets.length">
                            <template x-for="(set, i) in current.sets" :key="i">
                                <li class="flex justify-between rounded-lg bg-zinc-800/60 px-3 py-2 text-sm">
                                    <span class="text-zinc-400" x-text="'Série ' + (i + 1)"></span>
                                    <span class="font-semibold" x-text="formatKg(set.weightKg) + ' × ' + set.reps"></span>
                                </li>
                            </template>
                        </ol>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-center text-xs text-zinc-400" for="draft-weight">Charge (kg)</label>
                                <div class="flex items-center gap-1">
                                    <button type="button" class="h-12 w-12 rounded-xl bg-zinc-800 text-2xl" @click="adjust('weightKg', -2.5)" aria-label="Moins 2,5 kg">−</button>
                                    <input id="draft-weight" type="text" inputmode="decimal" class="input h-12 px-1 text-center text-lg font-bold" x-model="current.draft.weightKg">
                                    <button type="button" class="h-12 w-12 rounded-xl bg-zinc-800 text-2xl" @click="adjust('weightKg', 2.5)" aria-label="Plus 2,5 kg">+</button>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1 block text-center text-xs text-zinc-400" for="draft-reps">Répétitions</label>
                                <div class="flex items-center gap-1">
                                    <button type="button" class="h-12 w-12 rounded-xl bg-zinc-800 text-2xl" @click="adjust('reps', -1)" aria-label="Une répétition de moins">−</button>
                                    <input id="draft-reps" type="text" inputmode="numeric" class="input h-12 px-1 text-center text-lg font-bold" x-model="current.draft.reps">
                                    <button type="button" class="h-12 w-12 rounded-xl bg-zinc-800 text-2xl" @click="adjust('reps', 1)" aria-label="Une répétition de plus">+</button>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn-primary py-4 text-lg" @click="validateSet()">Valider la série</button>
                        <button type="button" class="w-full text-sm text-zinc-500" x-show="current.sets.length" @click="undoSet()">Annuler la dernière série</button>
                    </div>
                </template>

                {{-- Choix d'un exercice --}}
                <template x-if="picking">
                    <div class="card space-y-3">
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-bold">Ajouter un exercice</h2>
                            <button type="button" class="text-sm text-zinc-400" x-show="state.exercises.length" @click="picking = false">Fermer</button>
                        </div>
                        <input type="search" class="input" placeholder="Rechercher (ex. squat)" x-model="search">
                        <ul class="max-h-[50vh] divide-y divide-zinc-800 overflow-y-auto">
                            <template x-for="ex in filtered" :key="ex.id">
                                <li>
                                    <button type="button" class="flex w-full items-center justify-between py-3 text-left" @click="add(ex)">
                                        <span x-text="ex.name"></span>
                                        <span class="text-xs text-zinc-500" x-text="boot.lastTime[ex.id] ? 'déjà fait' : ''"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>

                <button type="button" class="btn-secondary" x-show="!picking" @click="picking = true">+ Ajouter un exercice</button>

                <div class="mt-auto pt-6 text-center">
                    <button type="button" class="text-sm text-red-400" @click="discard()"
                            x-text="confirmDiscard ? 'Confirmer l\'abandon (la séance sera perdue)' : 'Abandonner la séance'"></button>
                </div>
            </section>
        </template>

        {{-- Minuteur de repos --}}
        <div x-show="!summary && restRemaining > 0" x-transition.opacity
             class="fixed inset-x-0 bottom-0 z-30 border-t border-zinc-800 bg-zinc-950/95 px-4 pb-[max(1rem,env(safe-area-inset-bottom))] pt-4 backdrop-blur">
            <div class="mx-auto flex max-w-xl items-center justify-between gap-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-zinc-400">Repos</p>
                    <p class="font-mono text-4xl font-black tabular-nums text-lime-400" x-text="clock(restRemaining)"></p>
                </div>
                <div class="flex gap-2">
                    <button type="button" class="rounded-xl bg-zinc-800 px-4 py-3 font-semibold" @click="moreRest()">+30 s</button>
                    <button type="button" class="rounded-xl bg-lime-400 px-4 py-3 font-bold text-zinc-950" @click="skipRest()">Passer</button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
```

- [ ] **Step 8 : Compiler et vérifier**

Run : `npm run build && npm test && ./vendor/bin/pest`
Attendu : build OK ; tous les tests PASS.

- [ ] **Step 9 : Vérification manuelle dans le navigateur (vue mobile)**

Run : `php artisan serve` (et `npm run dev` dans un second terminal).
Parcours : s'inscrire → « Séance libre » (ou aller sur `/workout`) → ajouter « Développé couché (barre) » → valider 2 séries → vérifier que le minuteur démarre et que « Passer » et « +30 s » fonctionnent → recharger la page (la séance est toujours là) → aller à l'accueil (le bandeau « Reprendre » s'affiche) → revenir → « Terminer » (résumé avec la durée, les séries et le volume). Refaire une séance à 62,5 kg : le résumé doit afficher « Nouveaux records 🏆 ».
Hors ligne : dans les outils de développement, passer le réseau en « Offline », terminer une séance (message « enregistrée sur ton téléphone »), repasser en ligne, recharger une page : la séance apparaît dans l'historique (après la tâche 11).

- [ ] **Step 10 : Commit**

```bash
git add -A
git commit -m "feat: écran de séance guidée hors ligne avec minuteur de repos

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 11 : Historique, détail de séance, records et progression

**Files :**
- Create : `app/Livewire/History.php`, `app/Livewire/WorkoutShow.php`, `app/Livewire/Records.php`, `app/Livewire/Progress.php`, `app/Support/Sparkline.php`
- Create : `resources/views/livewire/history.blade.php`, `resources/views/livewire/workout-show.blade.php`, `resources/views/livewire/records.blade.php`, `resources/views/livewire/progress.blade.php`
- Modify : `routes/web.php`
- Test : `tests/Unit/SparklineTest.php`, `tests/Feature/LogbookPagesTest.php`

**Interfaces :**
- Consumes : `Workout::volume()`, `Workout::durationMinutes()`, `RecordCalculator::bodyweightRatio`, `Format::kg`, `Format::duration`
- Produces : routes `history`, `history.show` (`/history/{workout}`), `records`, `progress` (`/progress/{exercise}`) ; `Sparkline::points(list<float> $values, int $width, int $height, int $padding = 8): string`

- [ ] **Step 1 : Écrire les tests qui échouent**

`tests/Unit/SparklineTest.php` :
```php
<?php

use App\Support\Sparkline;

it('répartit les points sur la largeur et inverse l\'axe vertical', function () {
    expect(Sparkline::points([10, 20, 30], 100, 50, 0))->toBe('0,50 50,25 100,0');
});

it('centre une série constante', function () {
    expect(Sparkline::points([60, 60], 100, 50, 0))->toBe('0,25 100,25');
});

it('renvoie une chaîne vide sans point', function () {
    expect(Sparkline::points([], 100, 50))->toBe('');
});
```

`tests/Feature/LogbookPagesTest.php` :
```php
<?php

use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(weightKg: 80.0)->create();
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
    foreach ([['2026-09-20T10:00:00Z', 60], ['2026-09-27T10:00:00Z', 65]] as [$at, $kg]) {
        app(WorkoutSync::class)->sync($this->user, [
            'client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
            'sets' => [['exercise_id' => $this->bench->id, 'weight_kg' => $kg, 'reps' => 5, 'done_at' => $at]],
        ]);
    }
    $this->actingAs($this->user);
});

it('liste l\'historique du plus récent au plus ancien', function () {
    $this->get('/history')->assertOk()->assertSeeInOrder(['Dimanche 27 septembre', 'Dimanche 20 septembre'])->assertSee('325 kg');
});

it('affiche le détail d\'une séance', function () {
    $workout = Workout::latest('finished_at')->first();
    $this->get("/history/{$workout->id}")->assertOk()->assertSee('Développé couché (barre)')->assertSee('65 kg × 5');
});

it('affiche les records avec le ratio au poids de corps', function () {
    $this->get('/records')->assertOk()->assertSee('Développé couché (barre)')->assertSee('65 kg × 5')->assertSee('0,81 × PDC');
});

it('affiche la progression d\'un exercice', function () {
    $this->get("/progress/{$this->bench->id}")->assertOk()->assertSee('<polyline', false)->assertSee('65 kg');
});

it('indique qu\'il n\'y a pas encore de données', function () {
    $squat = Exercise::firstWhere('slug', 'back-squat');
    $this->get("/progress/{$squat->id}")->assertOk()->assertSee('Pas encore de données');
});
```

- [ ] **Step 2 : Vérifier l'échec**

Run : `./vendor/bin/pest tests/Unit/SparklineTest.php tests/Feature/LogbookPagesTest.php`
Attendu : FAIL.

- [ ] **Step 3 : `Sparkline`**

`app/Support/Sparkline.php` :
```php
<?php

namespace App\Support;

final class Sparkline
{
    /** @param list<float|int> $values */
    public static function points(array $values, int $width, int $height, int $padding = 8): string
    {
        if ($values === []) {
            return '';
        }

        $min = min($values);
        $max = max($values);
        $count = count($values);
        $innerW = $width - 2 * $padding;
        $innerH = $height - 2 * $padding;

        $points = [];
        foreach (array_values($values) as $i => $value) {
            $x = $padding + ($count === 1 ? $innerW / 2 : $innerW * $i / ($count - 1));
            $y = $max == $min ? $padding + $innerH / 2 : $padding + $innerH * (1 - ($value - $min) / ($max - $min));
            $points[] = round($x, 1).','.round($y, 1);
        }

        return implode(' ', $points);
    }
}
```

- [ ] **Step 4 : Composants**

`app/Livewire/History.php` :
```php
<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Historique')]
class History extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.history', [
            'workouts' => auth()->user()->workouts()->with('sets.exercise')->latest('finished_at')->paginate(20),
        ]);
    }
}
```

`app/Livewire/WorkoutShow.php` :
```php
<?php

namespace App\Livewire;

use App\Models\Workout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Séance')]
class WorkoutShow extends Component
{
    public Workout $workout;

    public function render()
    {
        $this->workout->load('sets.exercise', 'user');

        return view('livewire.workout-show', [
            'groups' => $this->workout->sets->groupBy('exercise_id'),
        ]);
    }
}
```

`app/Livewire/Records.php` :
```php
<?php

namespace App\Livewire;

use App\Services\RecordCalculator;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Records')]
class Records extends Component
{
    public function render(RecordCalculator $calc)
    {
        $user = auth()->user();

        $records = $user->personalRecords()->with('exercise')->get()
            ->sortBy(fn ($r) => $r->exercise->name)
            ->map(fn ($r) => [
                'record' => $r,
                'ratio' => $r->exercise->is_bodyweight ? null : $calc->bodyweightRatio($r->max_weight_kg, $user->weight_kg),
            ]);

        return view('livewire.records', ['records' => $records]);
    }
}
```

`app/Livewire/Progress.php` :
```php
<?php

namespace App\Livewire;

use App\Models\Exercise;
use App\Models\WorkoutSet;
use App\Support\Sparkline;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Progression')]
class Progress extends Component
{
    public Exercise $exercise;

    public function render()
    {
        $rows = WorkoutSet::query()
            ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
            ->where('workouts.user_id', auth()->id())
            ->where('workout_sets.exercise_id', $this->exercise->id)
            ->groupBy('workouts.id', 'workouts.finished_at')
            ->orderByDesc('workouts.finished_at')
            ->limit(30)
            ->selectRaw('workouts.finished_at as finished_at, MAX(workout_sets.weight_kg) as max_kg')
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($row) => ['date' => Carbon::parse($row->finished_at), 'kg' => (float) $row->max_kg]);

        return view('livewire.progress', [
            'rows' => $rows,
            'points' => Sparkline::points($rows->pluck('kg')->all(), 320, 140),
        ]);
    }
}
```

- [ ] **Step 5 : Vues**

`resources/views/livewire/history.blade.php` :
```blade
<div class="space-y-4">
    <h1 class="text-2xl font-bold">Historique</h1>
    @forelse ($workouts as $workout)
        <a href="{{ route('history.show', $workout) }}" class="card block active:bg-zinc-800">
            <p class="font-semibold">{{ ucfirst($workout->finished_at->translatedFormat('l j F')) }}</p>
            <p class="mt-1 text-sm text-zinc-400">
                {{ \App\Support\Format::duration($workout->durationMinutes()) }}
                · {{ $workout->sets->count() }} séries
                · {{ \App\Support\Format::kg($workout->volume()) }}
            </p>
            <p class="mt-1 truncate text-sm text-zinc-500">{{ $workout->sets->pluck('exercise.name')->unique()->join(', ') }}</p>
        </a>
    @empty
        <div class="card text-zinc-400">Aucune séance pour l'instant.</div>
    @endforelse
    {{ $workouts->links() }}
</div>
```

`resources/views/livewire/workout-show.blade.php` :
```blade
<div class="space-y-4">
    <a href="{{ route('history') }}" class="text-sm text-zinc-400">← Historique</a>
    <h1 class="text-2xl font-bold">{{ ucfirst($workout->finished_at->translatedFormat('l j F Y')) }}</h1>
    <p class="text-sm text-zinc-400">
        {{ \App\Support\Format::duration($workout->durationMinutes()) }} · {{ \App\Support\Format::kg($workout->volume()) }}
    </p>
    @foreach ($groups as $sets)
        <div class="card">
            <a href="{{ route('progress', $sets->first()->exercise) }}" class="font-semibold">{{ $sets->first()->exercise->name }}</a>
            <ol class="mt-2 space-y-1 text-sm">
                @foreach ($sets as $i => $set)
                    <li class="flex justify-between">
                        <span class="text-zinc-400">Série {{ $loop->iteration }}</span>
                        <span>{{ \App\Support\Format::kg($set->weight_kg) }} × {{ $set->reps }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    @endforeach
</div>
```

`resources/views/livewire/records.blade.php` :
```blade
<div class="space-y-4">
    <h1 class="text-2xl font-bold">Records</h1>
    @forelse ($records as ['record' => $record, 'ratio' => $ratio])
        <a href="{{ route('progress', $record->exercise) }}" class="card flex items-center justify-between active:bg-zinc-800">
            <div>
                <p class="font-semibold">{{ $record->exercise->name }}</p>
                <p class="text-sm text-zinc-400">1RM estimé : {{ \App\Support\Format::kg($record->best_e1rm_kg) }}</p>
            </div>
            <div class="text-right">
                <p class="font-bold text-lime-400">{{ \App\Support\Format::kg($record->max_weight_kg) }} × {{ $record->max_weight_reps }}</p>
                @if ($ratio)
                    <p class="text-xs text-zinc-400">{{ number_format($ratio, 2, ',', ' ') }} × PDC</p>
                @endif
            </div>
        </a>
    @empty
        <div class="card text-zinc-400">Termine une séance pour voir tes records.</div>
    @endforelse
</div>
```

`resources/views/livewire/progress.blade.php` :
```blade
<div class="space-y-4">
    <a href="{{ route('records') }}" class="text-sm text-zinc-400">← Records</a>
    <h1 class="text-2xl font-bold">{{ $exercise->name }}</h1>
    @if ($rows->isEmpty())
        <div class="card text-zinc-400">Pas encore de données pour cet exercice.</div>
    @else
        <div class="card">
            <p class="mb-2 text-sm text-zinc-400">Charge max. par séance</p>
            <svg viewBox="0 0 320 140" class="h-40 w-full" role="img" aria-label="Progression de la charge maximale">
                <polyline points="{{ $points }}" fill="none" stroke="#a3e635" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
            </svg>
            <div class="mt-2 flex justify-between text-xs text-zinc-500">
                <span>{{ $rows->first()['date']->translatedFormat('j M') }}</span>
                <span>{{ $rows->last()['date']->translatedFormat('j M') }}</span>
            </div>
        </div>
        <ul class="card divide-y divide-zinc-800 text-sm">
            @foreach ($rows->reverse() as $row)
                <li class="flex justify-between py-2">
                    <span class="text-zinc-400">{{ $row['date']->translatedFormat('D j M') }}</span>
                    <span class="font-semibold">{{ \App\Support\Format::kg($row['kg']) }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
```

- [ ] **Step 6 : Routes**

Dans `routes/web.php`, remplacer les lignes provisoires `history` et `records` par :
```php
    Route::get('/history', History::class)->name('history');
    Route::get('/history/{workout}', WorkoutShow::class)->name('history.show');
    Route::get('/records', Records::class)->name('records');
    Route::get('/progress/{exercise}', Progress::class)->name('progress');
```
et ajouter les imports `App\Livewire\History`, `App\Livewire\WorkoutShow`, `App\Livewire\Records`, `App\Livewire\Progress`.

- [ ] **Step 7 : Vérifier que tout passe**

Run : `npm run build && ./vendor/bin/pest`
Attendu : PASS. (Le 27 et le 20 septembre 2026 sont des dimanches ; `translatedFormat('l j F')` + `ucfirst` affiche « Dimanche 27 septembre ».)

- [ ] **Step 8 : Commit**

```bash
git add -A
git commit -m "feat: historique, détail de séance, records et progression

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 12 : Profil (morphologie) et accueil complet

**Files :**
- Create : `app/Livewire/Profile.php`, `resources/views/livewire/profile.blade.php`
- Modify : `app/Livewire/Home.php`, `resources/views/livewire/home.blade.php`, `routes/web.php`
- Test : `tests/Feature/ProfileTest.php`, `tests/Feature/HomeTest.php`

**Interfaces :**
- Consumes : `User::bmi()`, `Workout::volume()`, `Format`
- Produces : route `profile` (Livewire) ; propriétés `Profile::$sex` (?string), `$heightCm`, `$weightKg` ; méthode `save()`

- [ ] **Step 1 : Écrire les tests qui échouent**

`tests/Feature/ProfileTest.php` :
```php
<?php

use App\Livewire\Profile;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs($this->user = User::factory()->create(['first_name' => 'Élise'])));

it('affiche le profil', function () {
    $this->get('/profile')->assertOk()->assertSee('Élise')->assertSee('Historique')->assertSee('Records')->assertSee('Se déconnecter');
});

it('enregistre la morphologie et affiche l\'IMC', function () {
    Livewire::test(Profile::class)
        ->set('sex', 'female')
        ->set('heightCm', '165')
        ->set('weightKg', '58,5')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Profil enregistré')
        ->assertSee('21,5');

    $this->user->refresh();
    expect($this->user->sex)->toBe('female')
        ->and($this->user->height_cm)->toBe(165)
        ->and($this->user->weight_kg)->toBe(58.5);
});

it('vide un champ laissé blanc', function () {
    $this->user->update(['height_cm' => 180]);

    Livewire::test(Profile::class)->set('heightCm', '')->call('save')->assertHasNoErrors();

    expect($this->user->fresh()->height_cm)->toBeNull();
});

it('refuse des valeurs hors limites', function (string $field, string $value) {
    Livewire::test(Profile::class)->set($field, $value)->call('save')->assertHasErrors([$field]);
})->with([
    ['heightCm', '90'],
    ['heightCm', '260'],
    ['weightKg', '25'],
    ['weightKg', '350'],
    ['sex', 'autre'],
]);
```

`tests/Feature/HomeTest.php` :
```php
<?php

use App\Models\Exercise;
use App\Models\User;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->actingAs($this->user = User::factory()->create());
});

it('propose une séance libre et invite à compléter la morphologie', function () {
    $this->get('/')->assertOk()->assertSee('Séance libre')->assertSee(route('workout'))->assertSee('Complète ton profil');
});

it('affiche la dernière séance et les derniers records', function () {
    $bench = Exercise::firstWhere('slug', 'bench-press');
    app(WorkoutSync::class)->sync($this->user, [
        'client_id' => (string) Str::uuid(), 'started_at' => '2026-09-27T10:00:00Z', 'finished_at' => '2026-09-27T10:40:00Z',
        'sets' => [['exercise_id' => $bench->id, 'weight_kg' => 60, 'reps' => 8, 'done_at' => '2026-09-27T10:10:00Z']],
    ]);

    $this->get('/')->assertSee('Dernière séance')->assertSee('40 min')->assertSee('Développé couché (barre)')->assertSee('60 kg × 8');
});
```

- [ ] **Step 2 : Vérifier l'échec**

Run : `./vendor/bin/pest tests/Feature/ProfileTest.php tests/Feature/HomeTest.php`
Attendu : FAIL.

- [ ] **Step 3 : Composant Profil**

`app/Livewire/Profile.php` :
```php
<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Profil')]
class Profile extends Component
{
    public ?string $sex = null;

    public $heightCm = null;

    public $weightKg = null;

    public bool $saved = false;

    public function mount(): void
    {
        $user = auth()->user();
        $this->sex = $user->sex;
        $this->heightCm = $user->height_cm;
        $this->weightKg = $user->weight_kg;
    }

    public function save(): void
    {
        $this->sex = $this->sex ?: null;
        $this->heightCm = $this->heightCm === '' ? null : $this->heightCm;
        $this->weightKg = in_array($this->weightKg, ['', null], true) ? null : str_replace(',', '.', (string) $this->weightKg);

        $data = $this->validate([
            'sex' => ['nullable', 'in:male,female'],
            'heightCm' => ['nullable', 'integer', 'between:100,250'],
            'weightKg' => ['nullable', 'numeric', 'between:30,300'],
        ], [
            'sex.in' => 'Choisis homme ou femme.',
            'heightCm.integer' => 'La taille doit être un nombre entier (en cm).',
            'heightCm.between' => 'La taille doit être comprise entre 100 et 250 cm.',
            'weightKg.numeric' => 'Le poids doit être un nombre.',
            'weightKg.between' => 'Le poids doit être compris entre 30 et 300 kg.',
        ]);

        auth()->user()->update([
            'sex' => $data['sex'],
            'height_cm' => $data['heightCm'],
            'weight_kg' => $data['weightKg'],
        ]);

        $this->saved = true;
    }

    public function render()
    {
        return view('livewire.profile', ['user' => auth()->user()->fresh()]);
    }
}
```

`resources/views/livewire/profile.blade.php` :
```blade
<div class="space-y-6">
    <div class="flex items-center gap-4">
        <span class="flex h-14 w-14 items-center justify-center rounded-full text-2xl font-black text-zinc-950" style="background: {{ $user->avatar_color }}">
            {{ mb_strtoupper(mb_substr($user->first_name, 0, 1)) }}
        </span>
        <div>
            <h1 class="text-2xl font-bold">{{ $user->first_name }}</h1>
            <p class="text-sm text-zinc-400">{{ $user->birth_date->age }} ans</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <a href="{{ route('history') }}" class="card text-center font-semibold active:bg-zinc-800">Historique</a>
        <a href="{{ route('records') }}" class="card text-center font-semibold active:bg-zinc-800">Records</a>
    </div>

    <form wire:submit="save" class="card space-y-4" novalidate>
        <h2 class="text-lg font-bold">Morphologie</h2>
        <div>
            <span class="mb-1 block text-sm text-zinc-400">Sexe</span>
            <div class="grid grid-cols-2 gap-2">
                @foreach (['male' => 'Homme', 'female' => 'Femme'] as $value => $label)
                    <label class="cursor-pointer rounded-xl border px-4 py-3 text-center {{ $sex === $value ? 'border-lime-400 text-lime-300' : 'border-zinc-800 text-zinc-300' }}">
                        <input type="radio" wire:model.live="sex" value="{{ $value }}" class="sr-only">{{ $label }}
                    </label>
                @endforeach
            </div>
            @error('sex') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="heightCm" class="mb-1 block text-sm text-zinc-400">Taille (cm)</label>
                <input id="heightCm" type="text" inputmode="numeric" wire:model="heightCm" class="input">
                @error('heightCm') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="weightKg" class="mb-1 block text-sm text-zinc-400">Poids (kg)</label>
                <input id="weightKg" type="text" inputmode="decimal" wire:model="weightKg" class="input">
                @error('weightKg') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>
        @if ($user->bmi())
            <p class="text-sm text-zinc-400">IMC indicatif : {{ number_format($user->bmi(), 1, ',', ' ') }}</p>
        @endif
        <button type="submit" class="btn-primary">Enregistrer</button>
        @if ($saved) <p class="text-center text-sm text-lime-400">Profil enregistré ✓</p> @endif
    </form>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit" class="btn-secondary">Se déconnecter</button>
    </form>
</div>
```

- [ ] **Step 4 : Accueil complet**

`app/Livewire/Home.php` :
```php
<?php

namespace App\Livewire;

use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Accueil')]
class Home extends Component
{
    public function render()
    {
        $user = auth()->user();

        return view('livewire.home', [
            'user' => $user,
            'lastWorkout' => $user->workouts()->with('sets.exercise')->latest('finished_at')->first(),
            'recentRecords' => $user->personalRecords()->with('exercise')->latest('achieved_at')->limit(3)->get(),
        ]);
    }
}
```

`resources/views/livewire/home.blade.php` :
```blade
<div class="space-y-6">
    <h1 class="text-2xl font-bold">Salut {{ $user->first_name }} 👋</h1>

    <a href="{{ route('workout') }}" class="btn-primary py-5 text-lg">Séance libre</a>

    @if (! $user->sex || ! $user->weight_kg)
        <a href="{{ route('profile') }}" class="card block border-amber-500/40 text-sm text-amber-300">
            Complète ton profil (sexe, taille, poids) pour obtenir des charges de départ suggérées →
        </a>
    @endif

    @if ($lastWorkout)
        <a href="{{ route('history.show', $lastWorkout) }}" class="card block active:bg-zinc-800">
            <p class="text-xs uppercase tracking-wide text-zinc-400">Dernière séance</p>
            <p class="mt-1 font-semibold">{{ ucfirst($lastWorkout->finished_at->translatedFormat('l j F')) }}</p>
            <p class="text-sm text-zinc-400">
                {{ \App\Support\Format::duration($lastWorkout->durationMinutes()) }}
                · {{ $lastWorkout->sets->count() }} séries
                · {{ \App\Support\Format::kg($lastWorkout->volume()) }}
            </p>
        </a>
    @endif

    <section>
        <div class="mb-2 flex items-center justify-between">
            <h2 class="font-bold">Derniers records</h2>
            <a href="{{ route('records') }}" class="text-sm text-lime-400">Tout voir</a>
        </div>
        @forelse ($recentRecords as $record)
            <div class="card mb-2 flex justify-between">
                <span>{{ $record->exercise->name }}</span>
                <span class="font-bold text-lime-400">{{ \App\Support\Format::kg($record->max_weight_kg) }} × {{ $record->max_weight_reps }}</span>
            </div>
        @empty
            <p class="text-sm text-zinc-400">Tes records apparaîtront ici après ta première séance.</p>
        @endforelse
    </section>
</div>
```

- [ ] **Step 5 : Route**

Dans `routes/web.php`, remplacer la ligne provisoire `profile` par :
```php
    Route::get('/profile', Profile::class)->name('profile');
```
et ajouter `use App\Livewire\Profile;`. Vérifier qu'il ne reste plus aucune route provisoire sauf `program` et `community`.

- [ ] **Step 6 : Vérifier que tout passe**

Run : `npm run build && ./vendor/bin/pest`
Attendu : PASS.

- [ ] **Step 7 : Commit**

```bash
git add -A
git commit -m "feat: profil avec morphologie et accueil complet

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 13 : Vérification finale et guide de démarrage

**Files :**
- Create : `README.md` (remplace celui de Laravel)

- [ ] **Step 1 : Suite complète**

Run : `npm test && npm run build && ./vendor/bin/pest`
Attendu : tout PASS, build sans erreur.

- [ ] **Step 2 : Vérification manuelle complète en vue mobile** (outils de développement, appareil « iPhone 12 Pro » ou équivalent)

Run : `php artisan migrate:fresh --seed && php artisan serve`
Vérifier dans l'ordre :
1. `/register` : inscription « Élise » + date → accueil « Salut Élise ».
2. Déconnexion depuis Profil, puis connexion avec « ELISE » : ça fonctionne.
3. Profil : homme/femme, 175 cm, 70 kg → « Profil enregistré », IMC affiché.
4. Séance libre : ajouter le développé couché ; la charge suggérée est pré-remplie ; valider 3 séries ; minuteur, vibration/son, « +30 s », « Passer ».
5. Recharger au milieu de la séance : l'état est intact. Aller à l'accueil : le bandeau « Reprendre » apparaît.
6. Terminer : le résumé s'affiche ; historique, détail, records et progression sont cohérents.
7. Hors ligne (onglet Réseau → Offline) : le bandeau orange s'affiche ; terminer une séance → message « enregistrée sur ton téléphone » ; revenir en ligne → la séance arrive dans l'historique.
8. Vue PC (≥ 1024 px) : barre latérale à gauche, contenu centré.
9. Onglet Application → Manifest : nom EGO, icônes présentes, installable.

- [ ] **Step 3 : README**

`README.md` :
````markdown
# EGO

La musculation entre amis : séances guidées, carnet, records (et bientôt programmes générés et classement).

## Prérequis

- PHP 8.4 avec les extensions `pdo_sqlite` (local) et `pdo_mysql` (production)
- Composer 2
- Node 20+ (uniquement pour compiler les assets)

## Démarrer en local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run dev          # dans un terminal
php artisan serve    # dans un autre
```

Ouvrir http://127.0.0.1:8000.

## Tests

```bash
./vendor/bin/pest    # PHP
npm test             # JavaScript
```

## Mise en production (VPS ou mutualisé)

1. En local : `npm run build` (génère `public/build`).
2. Envoyer le projet sur le serveur (sans `node_modules`). Sur un mutualisé sans SSH, envoyer aussi `vendor/` après `composer install --no-dev --optimize-autoloader` en local.
3. Faire pointer le domaine sur le dossier `public/`.
4. `.env` : `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, `DB_CONNECTION=mysql` + identifiants.
5. `php artisan migrate --force && php artisan db:seed --force && php artisan config:cache && php artisan route:cache && php artisan view:cache`.
6. HTTPS obligatoire (service worker et installation de la PWA).

Aucun cron ni worker n'est nécessaire.
````

- [ ] **Step 4 : Commit**

```bash
git add -A
git commit -m "docs: guide de démarrage et de mise en production

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```
