# EGO — Livraison B : progression du joueur — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal :** transformer l'entraînement en progression de jeu. On ajoute :
- l'XP gagnée par séance et par record, avec des plafonds anti-triche ;
- les niveaux et les rangs E à S ;
- les statistiques Force, Endurance et Progression ;
- les titres, dont un que le joueur équipe ;
- les notifications de niveau, de rang et de titre ;
- la fenêtre STATUT sur l'accueil et la fiche du joueur complète ;
- l'attribution rétroactive de l'XP des séances existantes.

**Architecture :**
- Un domaine `App\Game` en PHP pur : `Leveling`, `XpLedger`, `Titles`, `PlayerStats` et `GameEvents`, ce dernier étant le point d'entrée appelé après une synchronisation.
- Un journal `xp_events` idempotent, et un cache `users.xp_total` recalculable.
- Le contrôleur `/workouts/sync` ajoute à sa réponse les notifications de jeu, `xp_gained` et `player`.
- Le rattrapage passe par une commande Artisan `ego:backfill-xp`, lancée par une migration.

**Tech Stack :** Laravel 12, Livewire 3, Pest 4, MariaDB, Tailwind 4 (classes « Système » de la livraison A).

**Spec :** `docs/superpowers/specs/2026-10-01-ego-systeme-design.md` (§5.2 à §5.5, §6, §7, livraison B).

## Global Constraints

- **XP par séance** : `50 + 5 × min(séries, 30)` si la séance compte au moins 3 séries, sinon 0. Seules les **2 premières séances** d'un jour calendaire Europe/Paris (date de `finished_at`) sont éligibles.
- **XP des records** : +50 par record **battu** (un premier record sur un exercice ne compte pas), 3 au maximum par séance.
- **Journal** : `xp_events` unique sur `(user_id, source, source_id)`. `users.xp_total` = somme des `amount`, recalculable.
- **Niveaux** : `xpToNext(n) = round(100 × n^1,3)`, niveau 1 au départ ; `levelForXp(xp)` donne le plus grand niveau dont le cumul est ≤ xp.
- **Rangs** :

| Rang | Niveaux |
|---|---|
| E | 1–9 |
| D | 10–19 |
| C | 20–29 |
| B | 30–44 |
| A | 45–59 |
| S | 60+ |

- **Statistiques** (entiers ≥ 0, sur 28 jours glissants à partir de maintenant) :
  - **FORCE** = round(moyenne des 3 meilleurs `best_e1rm_kg` des exercices `compound` / poids de corps × 50), `null` si le poids est inconnu ;
  - **ENDURANCE** = intdiv(répétitions sur 28 jours, 50) ;
  - **PROGRESSION** = records battus sur 28 jours × 10 ;
  - **RÉGULARITÉ** = `null` (livraison C).
- **Titres livrés** :

| Clé | Nom | Condition |
|---|---|---|
| `awakened` | L'Éveillé | 1 séance |
| `record_breaker` | Briseur de records | 10 records battus |
| `centurion` | Centurion | 100 séances |
| `titan` | Titan | 100 000 kg de volume cumulé |
| `rank_c` | Chasseur de rang C | niveau ≥ 20 |
| `rank_b` | Chasseur de rang B | niveau ≥ 30 |
| `rank_a` | Chasseur de rang A | niveau ≥ 45 |
| `monarch` | Monarque | niveau ≥ 60 |

  Les titres de la livraison C (`steady`, `unstoppable`, `revenant`) figurent au catalogue, verrouillés, mais ne sont jamais débloqués en B.
- **Titre équipé** : par défaut, le premier titre débloqué (dans l'ordre du catalogue). On ne peut équiper qu'un titre débloqué.
- **Notifications de jeu**, dans cet ordre après `SÉANCE TERMINÉE` et `NOUVEAU RECORD` :
  - `level_up` : une seule, avec le niveau final ;
  - `rank_up` ;
  - un `title` par nouveau titre.
- **Rattrapage** : aucune notification, idempotent.
- **Le Système s'appelle « TON EGO »** dans tous les textes visibles.
- **Interface** en français, composants `x-sys.*`, chiffres en `sys-num`. MariaDB (Wamp, port 3307) lancé pour les tests.
- **Commits** terminés par `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **Même séance envoyée deux fois** (réseau instable) : l'XP n'est comptée qu'une fois, ni niveau ni titre en double. Test dans les tâches 3 et 6.
2. **Troisième séance le même jour**, ou séance de 2 séries : 0 XP, pas d'erreur, la séance est enregistrée quand même. Test dans la tâche 3.
3. **Plusieurs niveaux d'un coup** (rattrapage ou grosse séance au niveau 1) : une seule notification `level_up`, avec le niveau final. Test dans la tâche 6.
4. **Utilisateur sans poids renseigné** : FORCE vaut « — », sans division par zéro, et le reste de la fiche s'affiche. Test dans la tâche 5.
5. **Rattrapage lancé deux fois** (migration relancée ou commande exécutée à la main) : même XP totale, mêmes titres. Test dans la tâche 7.

---

## Structure des fichiers

```
app/Game/Leveling.php            courbe, niveaux, rangs, progression
app/Game/XpLedger.php            XP d'une séance, éligibilité du jour, attribution idempotente, recalcul
app/Game/Titles.php              catalogue + déblocage + titre par défaut
app/Game/PlayerStats.php         Force / Endurance / Progression / Régularité
app/Game/GameEvents.php          après une séance (notifications) + rattrapage (sans notification)
app/Models/XpEvent.php, UserTitle.php
app/Console/Commands/BackfillPlayerXp.php     ego:backfill-xp
app/Support/SystemMessage.php    + levelUp, rankUp, title ; workoutDone(+XP)
app/Http/Controllers/WorkoutSyncController.php
app/Livewire/Home.php, Profile.php
resources/views/components/sys/xp-bar.blade.php, stat.blade.php
resources/views/livewire/home.blade.php, profile.blade.php, workout/run.blade.php
resources/css/app.css            + .sys-xp-fill
database/migrations/2026_10_03_000100_add_progression_tables.php
database/migrations/2026_10_03_000200_backfill_player_xp.php
tests/Unit/LevelingTest.php, tests/Feature/Game/*.php
```

---

### Task 0 : le Système s'appelle « TON EGO »

**Files :**
- Modify : `resources/views/components/layouts/guest.blade.php`, `resources/views/livewire/auth/login.blade.php`, `resources/views/livewire/auth/register.blade.php`, `resources/views/livewire/home.blade.php`
- Test : `tests/Feature/HomeTest.php`, `tests/Feature/Auth/RegisterTest.php`

**Interfaces :**
- Produces : partout où l'interface nomme le Système, le nom affiché est **TON EGO**. Les messages des tâches suivantes l'utilisent aussi (`SystemMessage::rankUp`).

- [ ] **Step 1 : tests (échec attendu)**

Dans `tests/Feature/HomeTest.php`, remplacer `->assertSee('SYSTÈME EGO · EN LIGNE')` par `->assertSee('TON EGO · EN LIGNE')`.
Ajouter à `tests/Feature/Auth/RegisterTest.php` :
```php
it('présente le Système sous son nom, TON EGO', function () {
    $this->get('/register')->assertSee('TON EGO a besoin de ton identité')->assertDontSee('Le Système');
});
```
Run : `./vendor/bin/pest tests/Feature/HomeTest.php tests/Feature/Auth/RegisterTest.php`
Attendu : 2 FAIL.

- [ ] **Step 2 : textes**

| Fichier | Avant | Après |
|---|---|---|
| `guest.blade.php` | `Système d'entraînement` | `TON EGO · système d'entraînement` |
| `login.blade.php` | `Déclare-toi au Système.` | `Déclare-toi à TON EGO.` |
| `register.blade.php` | `Le Système a besoin de ton identité pour t'assigner tes quêtes.` | `TON EGO a besoin de ton identité pour t'assigner tes quêtes.` |
| `home.blade.php` | `SYSTÈME EGO · EN LIGNE` | `TON EGO · EN LIGNE` |
| `home.blade.php` | `pour que le Système te suggère` | `pour que TON EGO te suggère` |

- [ ] **Step 3 : vérifier** : `npm run build && ./vendor/bin/pest` → tout PASS.

- [ ] **Step 4 : commit**

```bash
git add -A
git commit -m "copy: le Système s'appelle TON EGO

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 1 : courbe de niveaux et rangs (`Leveling`)

**Files :**
- Create : `app/Game/Leveling.php`
- Test : `tests/Unit/LevelingTest.php`

**Interfaces :**
- Produces :
  - `Leveling::xpToNext(int $level): int`
  - `Leveling::cumulative(int $level): int` (XP totale pour atteindre ce niveau ; `cumulative(1) = 0`)
  - `Leveling::levelForXp(int $xp): int`
  - `Leveling::rankForLevel(int $level): string`
  - `Leveling::progress(int $xp): array{level: int, rank: string, into: int, needed: int}`

- [ ] **Step 1 : test qui échoue**

`tests/Unit/LevelingTest.php` :
```php
<?php

use App\Game\Leveling;

it('suit la courbe 100 × n^1,3', function () {
    expect(Leveling::xpToNext(1))->toBe(100)
        ->and(Leveling::xpToNext(2))->toBe(246)
        ->and(Leveling::xpToNext(10))->toBe((int) round(100 * 10 ** 1.3));
});

it('cumule l\'XP nécessaire pour atteindre un niveau', function () {
    expect(Leveling::cumulative(1))->toBe(0)
        ->and(Leveling::cumulative(2))->toBe(100)
        ->and(Leveling::cumulative(3))->toBe(346)
        ->and(Leveling::cumulative(10))->toBe(array_sum(array_map(fn ($n) => Leveling::xpToNext($n), range(1, 9)))));
});

it('trouve le niveau aux frontières', function () {
    expect(Leveling::levelForXp(0))->toBe(1)
        ->and(Leveling::levelForXp(99))->toBe(1)
        ->and(Leveling::levelForXp(100))->toBe(2)
        ->and(Leveling::levelForXp(345))->toBe(2)
        ->and(Leveling::levelForXp(346))->toBe(3)
        ->and(Leveling::levelForXp(Leveling::cumulative(10) - 1))->toBe(9)
        ->and(Leveling::levelForXp(Leveling::cumulative(10)))->toBe(10);
});

it('attribue les rangs E à S', function (int $level, string $rank) {
    expect(Leveling::rankForLevel($level))->toBe($rank);
})->with([[1, 'E'], [9, 'E'], [10, 'D'], [19, 'D'], [20, 'C'], [29, 'C'], [30, 'B'], [44, 'B'], [45, 'A'], [59, 'A'], [60, 'S'], [99, 'S']]);

it('résume la progression', function () {
    expect(Leveling::progress(150))->toBe(['level' => 2, 'rank' => 'E', 'into' => 50, 'needed' => 246]);
});
```

- [ ] **Step 2 : vérifier l'échec**

Run : `./vendor/bin/pest tests/Unit/LevelingTest.php`
Attendu : FAIL (`Class "App\Game\Leveling" not found`).

- [ ] **Step 3 : implémentation**

`app/Game/Leveling.php` :
```php
<?php

namespace App\Game;

final class Leveling
{
    private const RANKS = [60 => 'S', 45 => 'A', 30 => 'B', 20 => 'C', 10 => 'D', 1 => 'E'];

    public static function xpToNext(int $level): int
    {
        return (int) round(100 * $level ** 1.3);
    }

    public static function cumulative(int $level): int
    {
        $total = 0;
        for ($n = 1; $n < $level; $n++) {
            $total += self::xpToNext($n);
        }

        return $total;
    }

    public static function levelForXp(int $xp): int
    {
        $level = 1;
        $next = self::xpToNext(1);
        while ($xp >= $next) {
            $xp -= $next;
            $level++;
            $next = self::xpToNext($level);
        }

        return $level;
    }

    public static function rankForLevel(int $level): string
    {
        foreach (self::RANKS as $min => $rank) {
            if ($level >= $min) {
                return $rank;
            }
        }

        return 'E';
    }

    /** @return array{level: int, rank: string, into: int, needed: int} */
    public static function progress(int $xp): array
    {
        $level = self::levelForXp($xp);

        return [
            'level' => $level,
            'rank' => self::rankForLevel($level),
            'into' => $xp - self::cumulative($level),
            'needed' => self::xpToNext($level),
        ];
    }
}
```

- [ ] **Step 4 : vérifier**

Run : `./vendor/bin/pest tests/Unit/LevelingTest.php`
Attendu : PASS.

- [ ] **Step 5 : commit**

```bash
git add -A
git commit -m "feat: courbe de niveaux et rangs E à S

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2 : tables de progression

**Files :**
- Create : `database/migrations/2026_10_03_000100_add_progression_tables.php`, `app/Models/XpEvent.php`, `app/Models/UserTitle.php`
- Modify : `app/Models/User.php`
- Test : `tests/Feature/Game/ProgressionTablesTest.php`

**Interfaces :**
- Produces :
  - `users.xp_total` (int, défaut 0, cast `integer`) et `users.equipped_title` (string nullable) ;
  - `XpEvent` : `user_id`, `source`, `source_id`, `amount`, `quantity` (défaut 1), `created_at`, sans `updated_at` ; unique `(user_id, source, source_id)` ;
  - `UserTitle` : `user_id`, `title_key`, `unlocked_at` ; unique `(user_id, title_key)` ;
  - `User::xpEvents(): HasMany`, `User::titles(): HasMany`.

> Écart assumé avec la spec §6 : la colonne `quantity` est ajoutée à `xp_events`. Un même événement `record` (une séance) peut porter jusqu'à 3 records battus, et la stat PROGRESSION ainsi que le titre *Briseur de records* comptent des records, pas des événements.

- [ ] **Step 1 : test qui échoue**

`tests/Feature/Game/ProgressionTablesTest.php` :
```php
<?php

use App\Models\User;
use App\Models\XpEvent;
use Illuminate\Database\UniqueConstraintViolationException;

it('démarre un joueur à 0 XP sans titre équipé', function () {
    $user = User::factory()->create()->fresh();

    expect($user->xp_total)->toBe(0)->and($user->equipped_title)->toBeNull();
});

it('interdit deux événements d\'XP pour la même source', function () {
    $user = User::factory()->create();
    $user->xpEvents()->create(['source' => 'workout', 'source_id' => 1, 'amount' => 65]);

    $user->xpEvents()->create(['source' => 'workout', 'source_id' => 1, 'amount' => 65]);
})->throws(UniqueConstraintViolationException::class);

it('interdit de débloquer deux fois le même titre', function () {
    $user = User::factory()->create();
    $user->titles()->create(['title_key' => 'awakened', 'unlocked_at' => now()]);

    $user->titles()->create(['title_key' => 'awakened', 'unlocked_at' => now()]);
})->throws(UniqueConstraintViolationException::class);

it('enregistre la quantité d\'un événement', function () {
    $event = User::factory()->create()->xpEvents()->create(['source' => 'record', 'source_id' => 3, 'amount' => 100, 'quantity' => 2]);

    expect(XpEvent::find($event->id)->quantity)->toBe(2);
});
```

- [ ] **Step 2 : vérifier l'échec**

Run : `./vendor/bin/pest tests/Feature/Game/ProgressionTablesTest.php`
Attendu : FAIL (colonne `xp_total` inconnue / `xpEvents` indéfinie).

- [ ] **Step 3 : migration et modèles**

`database/migrations/2026_10_03_000100_add_progression_tables.php` :
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('xp_total')->default(0)->after('is_admin');
            $table->string('equipped_title', 30)->nullable()->after('xp_total');
        });

        Schema::create('xp_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20);
            $table->unsignedBigInteger('source_id');
            $table->unsignedInteger('amount');
            $table->unsignedTinyInteger('quantity')->default(1);
            $table->timestamp('created_at')->nullable()->index();
            $table->unique(['user_id', 'source', 'source_id']);
        });

        Schema::create('user_titles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title_key', 30);
            $table->timestamp('unlocked_at');
            $table->unique(['user_id', 'title_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_titles');
        Schema::dropIfExists('xp_events');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['xp_total', 'equipped_title']));
    }
};
```

`app/Models/XpEvent.php` :
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class XpEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['source', 'source_id', 'amount', 'quantity', 'created_at'];

    protected function casts(): array
    {
        return ['source_id' => 'integer', 'amount' => 'integer', 'quantity' => 'integer', 'created_at' => 'datetime'];
    }
}
```

`app/Models/UserTitle.php` :
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTitle extends Model
{
    public $timestamps = false;

    protected $fillable = ['title_key', 'unlocked_at'];

    protected function casts(): array
    {
        return ['unlocked_at' => 'datetime'];
    }
}
```

Dans `app/Models/User.php` :
- dans `casts()`, ajouter `'xp_total' => 'integer',` ;
- ajouter les relations :
```php
    public function xpEvents(): HasMany
    {
        return $this->hasMany(XpEvent::class);
    }

    public function titles(): HasMany
    {
        return $this->hasMany(UserTitle::class);
    }
```
(`xp_total` et `equipped_title` ne sont **pas** ajoutés à `$fillable` : seul le code du jeu les modifie, via `forceFill` ou `increment`.)

- [ ] **Step 4 : vérifier**

Run : `php artisan migrate --no-interaction && ./vendor/bin/pest`
Attendu : migration OK, puis tous les tests PASS.

- [ ] **Step 5 : commit**

```bash
git add -A
git commit -m "feat: tables de progression (journal d'XP, titres, XP totale)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3 : journal d'XP (`XpLedger`)

**Files :**
- Create : `app/Game/XpLedger.php`
- Test : `tests/Feature/Game/XpLedgerTest.php`

**Interfaces :**
- Consumes : `XpEvent`, `Workout` (`sets`, `finished_at`)
- Produces :
  - `XpLedger::workoutXp(int $setsCount): int`
  - `XpLedger::isEligible(User $user, Workout $workout): bool` (moins de 2 séances déjà récompensées le même jour local)
  - `XpLedger::award(User $user, string $source, int $sourceId, int $amount, int $quantity = 1, ?CarbonInterface $at = null): bool` : `true` si l'événement est créé ; met à jour `users.xp_total` et `$user->xp_total` ;
  - `XpLedger::recalculate(User $user): int`

- [ ] **Step 1 : test qui échoue**

`tests/Feature/Game/XpLedgerTest.php` :
```php
<?php

use App\Game\XpLedger;
use App\Models\Exercise;
use App\Models\User;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->create();
    $this->ledger = app(XpLedger::class);
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
});

function workoutAt(User $user, int $exerciseId, string $finishedAt, int $sets = 3)
{
    return app(WorkoutSync::class)->sync($user, [
        'client_id' => (string) Str::uuid(), 'started_at' => $finishedAt, 'finished_at' => $finishedAt,
        'sets' => array_fill(0, $sets, ['exercise_id' => $exerciseId, 'weight_kg' => 40, 'reps' => 10, 'done_at' => $finishedAt]),
    ])['workout'];
}

it('calcule l\'XP d\'une séance : 3 séries minimum, 30 au maximum', function (int $sets, int $xp) {
    expect($this->ledger->workoutXp($sets))->toBe($xp);
})->with([[0, 0], [2, 0], [3, 65], [10, 100], [30, 200], [45, 200]]);

it('attribue une source une seule fois et tient le total à jour', function () {
    expect($this->ledger->award($this->user, 'workout', 7, 65))->toBeTrue()
        ->and($this->ledger->award($this->user, 'workout', 7, 65))->toBeFalse()
        ->and($this->user->xp_total)->toBe(65)
        ->and($this->user->fresh()->xp_total)->toBe(65);
});

it('limite l\'XP à deux séances par jour calendaire de Paris', function () {
    $a = workoutAt($this->user, $this->bench->id, '2026-10-01T06:00:00Z');
    $b = workoutAt($this->user, $this->bench->id, '2026-10-01T12:00:00Z');
    $c = workoutAt($this->user, $this->bench->id, '2026-10-01T20:00:00Z');
    $nextDay = workoutAt($this->user, $this->bench->id, '2026-10-01T22:30:00Z'); // 00:30 le 2 à Paris

    foreach ([$a, $b] as $w) {
        expect($this->ledger->isEligible($this->user, $w))->toBeTrue();
        $this->ledger->award($this->user, 'workout', $w->id, 65, at: $w->finished_at);
    }

    expect($this->ledger->isEligible($this->user, $c))->toBeFalse()
        ->and($this->ledger->isEligible($this->user, $nextDay))->toBeTrue();
});

it('recalcule le total depuis le journal', function () {
    $this->ledger->award($this->user, 'workout', 1, 65);
    $this->ledger->award($this->user, 'record', 1, 100, 2);
    $this->user->forceFill(['xp_total' => 9999])->save();

    expect($this->ledger->recalculate($this->user))->toBe(165)
        ->and($this->user->fresh()->xp_total)->toBe(165);
});
```

- [ ] **Step 2 : vérifier l'échec**

Run : `./vendor/bin/pest tests/Feature/Game/XpLedgerTest.php`
Attendu : FAIL (`Class "App\Game\XpLedger" not found`).

- [ ] **Step 3 : implémentation**

`app/Game/XpLedger.php` :
```php
<?php

namespace App\Game;

use App\Models\User;
use App\Models\Workout;
use App\Models\XpEvent;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;

final class XpLedger
{
    public const DAILY_WORKOUTS = 2;

    public function workoutXp(int $setsCount): int
    {
        return $setsCount < 3 ? 0 : 50 + 5 * min($setsCount, 30);
    }

    public function isEligible(User $user, Workout $workout): bool
    {
        $awardedThatDay = XpEvent::query()
            ->join('workouts', 'workouts.id', '=', 'xp_events.source_id')
            ->where('xp_events.user_id', $user->id)
            ->where('xp_events.source', 'workout')
            ->where('workouts.id', '!=', $workout->id)
            ->whereDate('workouts.finished_at', $workout->finished_at->toDateString())
            ->count();

        return $awardedThatDay < self::DAILY_WORKOUTS;
    }

    public function award(User $user, string $source, int $sourceId, int $amount, int $quantity = 1, ?CarbonInterface $at = null): bool
    {
        try {
            $user->xpEvents()->create([
                'source' => $source,
                'source_id' => $sourceId,
                'amount' => $amount,
                'quantity' => $quantity,
                'created_at' => $at ?? now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        User::whereKey($user->id)->increment('xp_total', $amount);
        $user->xp_total = ($user->xp_total ?? 0) + $amount;

        return true;
    }

    public function recalculate(User $user): int
    {
        $total = (int) $user->xpEvents()->sum('amount');
        $user->forceFill(['xp_total' => $total])->save();

        return $total;
    }
}
```

- [ ] **Step 4 : vérifier**

Run : `./vendor/bin/pest tests/Feature/Game/XpLedgerTest.php`
Attendu : PASS. `finished_at` est stocké en heure de Paris (livraison 1), donc `whereDate` compare bien des jours calendaires de Paris.

- [ ] **Step 5 : commit**

```bash
git add -A
git commit -m "feat: journal d'XP idempotent avec plafond quotidien

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4 : titres (`Titles`)

**Files :**
- Create : `app/Game/Titles.php`
- Modify : `app/Models/User.php` (`equippedTitleName()`)
- Test : `tests/Feature/Game/TitlesTest.php`

**Interfaces :**
- Consumes : `Leveling::levelForXp`, `XpEvent` (records, `quantity`), workouts, sets et exercices
- Produces :
  - `Titles::CATALOG` : `array<string, array{name: string, condition: string}>`, dans l'ordre d'affichage ;
  - `Titles::name(string $key): ?string` ;
  - `Titles::check(User $user): list<string>` : clés nouvellement débloquées ; équipe la première si aucune ne l'est ;
  - `Titles::totalVolume(User $user): float` ;
  - `User::equippedTitleName(): ?string`.

- [ ] **Step 1 : test qui échoue**

`tests/Feature/Game/TitlesTest.php` :
```php
<?php

use App\Game\Leveling;
use App\Game\Titles;
use App\Game\XpLedger;
use App\Models\Exercise;
use App\Models\User;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(weightKg: 80.0)->create();
    $this->titles = app(Titles::class);
});

function syncSets(User $user, string $slug, float $kg, int $reps, int $sets = 1): void
{
    $at = now()->toIso8601String();
    app(WorkoutSync::class)->sync($user, [
        'client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
        'sets' => array_fill(0, $sets, ['exercise_id' => Exercise::firstWhere('slug', $slug)->id, 'weight_kg' => $kg, 'reps' => $reps, 'done_at' => $at]),
    ]);
}

it('ne débloque rien sans séance', function () {
    expect($this->titles->check($this->user))->toBe([])->and($this->user->fresh()->equipped_title)->toBeNull();
});

it('débloque L\'Éveillé à la première séance et l\'équipe par défaut', function () {
    syncSets($this->user, 'bench-press', 40, 10);

    expect($this->titles->check($this->user))->toBe(['awakened'])
        ->and($this->user->fresh()->equipped_title)->toBe('awakened')
        ->and($this->user->fresh()->equippedTitleName())->toBe("L'Éveillé")
        ->and($this->titles->check($this->user))->toBe([]);
});

it('débloque Titan à 100 000 kg cumulés, poids du corps compris', function () {
    syncSets($this->user, 'leg-press', 500, 10, 19);   // 95 000 kg
    expect($this->titles->check($this->user))->not->toContain('titan');

    syncSets($this->user, 'pull-up', 0, 70);           // 80 × 70 = 5 600 kg
    expect($this->titles->totalVolume($this->user))->toBe(100600.0)
        ->and($this->titles->check($this->user))->toContain('titan');
});

it('débloque Briseur de records à 10 records battus', function () {
    app(XpLedger::class)->award($this->user, 'record', 1, 150, 3);
    app(XpLedger::class)->award($this->user, 'record', 2, 150, 3);
    app(XpLedger::class)->award($this->user, 'record', 3, 150, 3);
    expect($this->titles->check($this->user))->not->toContain('record_breaker');

    app(XpLedger::class)->award($this->user, 'record', 4, 50, 1);
    expect($this->titles->check($this->user))->toContain('record_breaker');
});

it('débloque les titres de rang selon le niveau', function () {
    app(XpLedger::class)->award($this->user, 'workout', 1, Leveling::cumulative(30));

    expect($this->titles->check($this->user))->toBe(['rank_c', 'rank_b']);
});

it('expose un catalogue complet avec les titres des quêtes', function () {
    expect(array_keys(Titles::CATALOG))->toBe([
        'awakened', 'record_breaker', 'centurion', 'titan', 'steady', 'unstoppable', 'revenant', 'rank_c', 'rank_b', 'rank_a', 'monarch',
    ]);
});
```

- [ ] **Step 2 : vérifier l'échec**

Run : `./vendor/bin/pest tests/Feature/Game/TitlesTest.php`
Attendu : FAIL (`Class "App\Game\Titles" not found`).

- [ ] **Step 3 : implémentation**

`app/Game/Titles.php` :
```php
<?php

namespace App\Game;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Support\Facades\DB;

final class Titles
{
    public const CATALOG = [
        'awakened' => ['name' => "L'Éveillé", 'condition' => 'Terminer ta première séance'],
        'record_breaker' => ['name' => 'Briseur de records', 'condition' => 'Battre 10 records'],
        'centurion' => ['name' => 'Centurion', 'condition' => 'Terminer 100 séances'],
        'titan' => ['name' => 'Titan', 'condition' => 'Soulever 100 000 kg au total'],
        'steady' => ['name' => 'Assidu', 'condition' => 'Tenir une série de 7 jours'],
        'unstoppable' => ['name' => 'Inarrêtable', 'condition' => 'Tenir une série de 30 jours'],
        'revenant' => ['name' => 'Revenant', 'condition' => 'Réussir une quête de pénalité'],
        'rank_c' => ['name' => 'Chasseur de rang C', 'condition' => 'Atteindre le rang C'],
        'rank_b' => ['name' => 'Chasseur de rang B', 'condition' => 'Atteindre le rang B'],
        'rank_a' => ['name' => 'Chasseur de rang A', 'condition' => 'Atteindre le rang A'],
        'monarch' => ['name' => 'Monarque', 'condition' => 'Atteindre le rang S'],
    ];

    public static function name(string $key): ?string
    {
        return self::CATALOG[$key]['name'] ?? null;
    }

    /** @return list<string> */
    public function check(User $user): array
    {
        $earned = $this->earned($user);
        $owned = $user->titles()->pluck('title_key')->all();
        $new = array_values(array_diff($earned, $owned));

        foreach ($new as $key) {
            $user->titles()->create(['title_key' => $key, 'unlocked_at' => now()]);
        }

        if ($user->equipped_title === null && $earned !== []) {
            $user->forceFill(['equipped_title' => $earned[0]])->save();
        }

        return $new;
    }

    public function totalVolume(User $user): float
    {
        return (float) DB::table('workout_sets')
            ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
            ->join('exercises', 'exercises.id', '=', 'workout_sets.exercise_id')
            ->where('workouts.user_id', $user->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN exercises.is_bodyweight = 1
                THEN (COALESCE(workouts.bodyweight_kg, 0) + workout_sets.weight_kg) * workout_sets.reps
                ELSE workout_sets.weight_kg * workout_sets.reps END), 0) AS volume')
            ->value('volume');
    }

    /** @return list<string> clés méritées, dans l'ordre du catalogue */
    private function earned(User $user): array
    {
        $workouts = Workout::where('user_id', $user->id)->count();
        $level = Leveling::levelForXp((int) $user->xp_total);
        $records = (int) $user->xpEvents()->where('source', 'record')->sum('quantity');

        $conditions = [
            'awakened' => $workouts >= 1,
            'record_breaker' => $records >= 10,
            'centurion' => $workouts >= 100,
            'titan' => fn () => $this->totalVolume($user) >= 100000,
            'rank_c' => $level >= 20,
            'rank_b' => $level >= 30,
            'rank_a' => $level >= 45,
            'monarch' => $level >= 60,
        ];

        $earned = [];
        foreach (array_keys(self::CATALOG) as $key) {
            $condition = $conditions[$key] ?? false;
            if ($condition instanceof \Closure ? $condition() : $condition) {
                $earned[] = $key;
            }
        }

        return $earned;
    }
}
```

Dans `app/Models/User.php`, ajouter :
```php
    public function equippedTitleName(): ?string
    {
        return $this->equipped_title ? \App\Game\Titles::name($this->equipped_title) : null;
    }
```

- [ ] **Step 4 : vérifier**

Run : `./vendor/bin/pest tests/Feature/Game/TitlesTest.php`
Attendu : PASS.

- [ ] **Step 5 : commit**

```bash
git add -A
git commit -m "feat: catalogue et déblocage des titres

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5 : statistiques du joueur (`PlayerStats`)

**Files :**
- Create : `app/Game/PlayerStats.php`
- Test : `tests/Feature/Game/PlayerStatsTest.php`

**Interfaces :**
- Produces : `PlayerStats::for(User $user): array{force: ?int, endurance: int, progression: int, regularite: ?int}`

- [ ] **Step 1 : test qui échoue**

`tests/Feature/Game/PlayerStatsTest.php` :
```php
<?php

use App\Game\PlayerStats;
use App\Game\XpLedger;
use App\Models\Exercise;
use App\Models\PersonalRecord;
use App\Models\User;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(weightKg: 80.0)->create();
});

function recordFor(User $user, string $slug, float $e1rm): void
{
    PersonalRecord::create([
        'user_id' => $user->id, 'exercise_id' => Exercise::firstWhere('slug', $slug)->id,
        'max_weight_kg' => $e1rm * 0.8, 'max_weight_reps' => 5, 'best_e1rm_kg' => $e1rm, 'achieved_at' => now(),
    ]);
}

function repsAt(User $user, int $reps, string $finishedAt): void
{
    app(WorkoutSync::class)->sync($user, [
        'client_id' => (string) Str::uuid(), 'started_at' => $finishedAt, 'finished_at' => $finishedAt,
        'sets' => [['exercise_id' => Exercise::firstWhere('slug', 'push-up')->id, 'weight_kg' => 0, 'reps' => $reps, 'done_at' => $finishedAt]],
    ]);
}

it('arrondit la Force : moyenne 100 kg pour 80 kg de poids → 63', function () {
    recordFor($this->user, 'back-squat', 120);
    recordFor($this->user, 'bench-press', 100);
    recordFor($this->user, 'deadlift', 80);
    recordFor($this->user, 'barbell-row', 60);
    recordFor($this->user, 'leg-extension', 300);

    expect(app(PlayerStats::class)->for($this->user)['force'])->toBe(63);
});

it('ne calcule pas la Force sans poids de corps', function () {
    $user = User::factory()->create();
    recordFor($user, 'back-squat', 120);

    expect(app(PlayerStats::class)->for($user)['force'])->toBeNull();
});

it('compte l\'Endurance et la Progression sur 28 jours glissants', function () {
    repsAt($this->user, 120, now()->subDays(3)->toIso8601String());
    repsAt($this->user, 500, now()->subDays(40)->toIso8601String());
    app(XpLedger::class)->award($this->user, 'record', 1, 100, 2, now()->subDays(2));
    app(XpLedger::class)->award($this->user, 'record', 2, 150, 3, now()->subDays(35));

    $stats = app(PlayerStats::class)->for($this->user);

    expect($stats['endurance'])->toBe(2)
        ->and($stats['progression'])->toBe(20)
        ->and($stats['regularite'])->toBeNull();
});
```

- [ ] **Step 2 : vérifier l'échec**

Run : `./vendor/bin/pest tests/Feature/Game/PlayerStatsTest.php`
Attendu : FAIL (`Class "App\Game\PlayerStats" not found`).

- [ ] **Step 3 : implémentation**

`app/Game/PlayerStats.php` :
```php
<?php

namespace App\Game;

use App\Models\PersonalRecord;
use App\Models\User;
use App\Models\WorkoutSet;

final class PlayerStats
{
    public const WINDOW_DAYS = 28;

    /** @return array{force: ?int, endurance: int, progression: int, regularite: ?int} */
    public function for(User $user): array
    {
        $since = now()->subDays(self::WINDOW_DAYS);

        return [
            'force' => $this->force($user),
            'endurance' => intdiv((int) WorkoutSet::query()
                ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
                ->where('workouts.user_id', $user->id)
                ->where('workouts.finished_at', '>=', $since)
                ->sum('workout_sets.reps'), 50),
            'progression' => 10 * (int) $user->xpEvents()->where('source', 'record')->where('created_at', '>=', $since)->sum('quantity'),
            'regularite' => null,
        ];
    }

    private function force(User $user): ?int
    {
        if (! $user->weight_kg) {
            return null;
        }

        $best = PersonalRecord::query()
            ->join('exercises', 'exercises.id', '=', 'personal_records.exercise_id')
            ->where('personal_records.user_id', $user->id)
            ->where('exercises.kind', 'compound')
            ->orderByDesc('personal_records.best_e1rm_kg')
            ->limit(3)
            ->pluck('personal_records.best_e1rm_kg');

        if ($best->isEmpty()) {
            return 0;
        }

        return (int) round($best->avg() / $user->weight_kg * 50);
    }
}
```

- [ ] **Step 4 : vérifier**

Run : `./vendor/bin/pest tests/Feature/Game/PlayerStatsTest.php`
Attendu : PASS (3 tests).

- [ ] **Step 5 : commit**

```bash
git add -A
git commit -m "feat: statistiques Force, Endurance et Progression

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6 : événements de jeu après une séance + réponse de synchronisation

**Files :**
- Create : `app/Game/GameEvents.php`
- Modify : `app/Support/SystemMessage.php` (`workoutDone` avec XP, `levelUp`, `rankUp`, `title`), `app/Http/Controllers/WorkoutSyncController.php`
- Test : `tests/Feature/Game/GameEventsTest.php`, `tests/Feature/WorkoutSyncEndpointTest.php` (un test ajouté)

**Interfaces :**
- Consumes : `XpLedger`, `Titles`, `Leveling`, `SystemMessage`
- Produces :
  - `GameEvents::afterWorkout(User $user, Workout $workout, array $newRecords): array{xp: int, notifications: list<array>, player: array}` ;
  - `SystemMessage::workoutDone(Workout $w, int $xp = 0)`, `levelUp(array $progress)`, `rankUp(string $rank)`, `title(string $key)` ;
  - réponse de `/workouts/sync` : `xp_gained` (int) et `player` (`Leveling::progress`). Les notifications sont `[workoutDone(xp), ...records, ...game]` si la séance est créée, et `[]` sinon.

- [ ] **Step 1 : tests qui échouent**

`tests/Feature/Game/GameEventsTest.php` :
```php
<?php

use App\Game\GameEvents;
use App\Game\Leveling;
use App\Models\Exercise;
use App\Models\User;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->create();
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
});

function playSession(User $user, int $exerciseId, float $kg, int $sets, string $at = '2026-10-01T16:00:00Z', ?string $clientId = null): array
{
    $sync = app(WorkoutSync::class)->sync($user, [
        'client_id' => $clientId ?? (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
        'sets' => array_fill(0, $sets, ['exercise_id' => $exerciseId, 'weight_kg' => $kg, 'reps' => 10, 'done_at' => $at]),
    ]);

    return app(GameEvents::class)->afterWorkout($user, $sync['workout'], $sync['newRecords']);
}

it('attribue l\'XP de la séance et débloque le premier titre', function () {
    $result = playSession($this->user, $this->bench->id, 40, 4);

    expect($result['xp'])->toBe(70)
        ->and($this->user->fresh()->xp_total)->toBe(70)
        ->and(collect($result['notifications'])->pluck('title')->all())->toBe(['TITRE DÉBLOQUÉ'])
        ->and($result['player'])->toBe(Leveling::progress(70));
});

it('ajoute 50 XP par record battu', function () {
    playSession($this->user, $this->bench->id, 40, 3, '2026-10-01T08:00:00Z');
    $result = playSession($this->user, $this->bench->id, 42.5, 3, '2026-10-02T08:00:00Z');

    expect($result['xp'])->toBe(65 + 50);
});

it('n\'envoie qu\'une notification de niveau avec le niveau final, puis le rang', function () {
    $this->user->forceFill(['xp_total' => Leveling::cumulative(10) - 10])->save();
    $this->user->xpEvents()->create(['source' => 'seed', 'source_id' => 1, 'amount' => Leveling::cumulative(10) - 10]);

    $result = playSession($this->user, $this->bench->id, 40, 30);

    $types = collect($result['notifications'])->pluck('type')->all();
    expect($types)->toBe(['level_up', 'rank_up', 'title'])
        ->and($result['notifications'][0]['title'])->toBe('NIVEAU 10')
        ->and($result['notifications'][1]['title'])->toBe('RANG D');
});

it('ne rapporte rien en dehors des 2 premières séances du jour', function () {
    playSession($this->user, $this->bench->id, 40, 3, '2026-10-01T06:00:00Z');
    playSession($this->user, $this->bench->id, 40, 3, '2026-10-01T07:00:00Z');
    $third = playSession($this->user, $this->bench->id, 40, 3, '2026-10-01T08:00:00Z');

    expect($third['xp'])->toBe(0)->and($this->user->fresh()->xp_total)->toBe(130);
});
```

Ajouter à `tests/Feature/WorkoutSyncEndpointTest.php` :
```php
it('renvoie l\'XP gagnée, la progression et les notifications de jeu', function () {
    $this->body['sets'] = array_fill(0, 4, $this->body['sets'][0]);

    $response = $this->actingAs($this->user)->postJson('/workouts/sync', $this->body);

    $response->assertJsonPath('xp_gained', 70)
        ->assertJsonPath('player.level', 1)
        ->assertJsonPath('notifications.0.lines.2', '+70 XP')
        ->assertJsonPath('notifications.1.title', 'TITRE DÉBLOQUÉ');

    $this->actingAs($this->user)->postJson('/workouts/sync', $this->body)
        ->assertJsonPath('xp_gained', 0)
        ->assertJsonPath('notifications', []);

    expect($this->user->fresh()->xp_total)->toBe(70);
});
```

- [ ] **Step 2 : vérifier l'échec**

Run : `./vendor/bin/pest tests/Feature/Game/GameEventsTest.php tests/Feature/WorkoutSyncEndpointTest.php`
Attendu : FAIL (`Class "App\Game\GameEvents" not found`, clé `xp_gained` absente).

- [ ] **Step 3 : messages système**

Dans `app/Support/SystemMessage.php` :
- ajouter `use App\Game\Leveling;` et `use App\Game\Titles;` ;
- remplacer `workoutDone` et ajouter les fabriques :
```php
    public static function workoutDone(Workout $workout, int $xp = 0): array
    {
        $lines = [
            Format::duration($workout->durationMinutes()).' · '.$workout->sets->count().' séries',
            'Volume : '.Format::kg($workout->volume()),
        ];

        if ($xp > 0) {
            $lines[] = '+'.$xp.' XP';
        }

        return self::make('workout_done', 'SÉANCE TERMINÉE', $lines);
    }

    /** @param array{level: int, rank: string, into: int, needed: int} $progress */
    public static function levelUp(array $progress): array
    {
        return self::make('level_up', 'NIVEAU '.$progress['level'], [
            'Tu as monté de niveau !',
            'Rang '.$progress['rank'].' · '.$progress['into'].' / '.$progress['needed'].' XP',
        ], highlight: 'LEVEL UP');
    }

    public static function rankUp(string $rank): array
    {
        return self::make('rank_up', 'RANG '.$rank, ['TON EGO te reconnaît comme chasseur de rang '.$rank.'.'], highlight: 'RANG '.$rank);
    }

    public static function title(string $key): array
    {
        return self::make('title', 'TITRE DÉBLOQUÉ', [Titles::name($key) ?? $key, Titles::CATALOG[$key]['condition'] ?? '']);
    }
```

- [ ] **Step 4 : `GameEvents`**

`app/Game/GameEvents.php` :
```php
<?php

namespace App\Game;

use App\Models\User;
use App\Models\Workout;
use App\Support\SystemMessage;

final class GameEvents
{
    public const RECORD_XP = 50;
    public const RECORDS_PER_WORKOUT = 3;

    public function __construct(private XpLedger $ledger, private Titles $titles) {}

    /**
     * @param  list<\App\Models\PersonalRecord>  $newRecords  records battus par cette séance
     * @return array{xp: int, notifications: list<array>, player: array}
     */
    public function afterWorkout(User $user, Workout $workout, array $newRecords): array
    {
        $before = Leveling::progress((int) $user->xp_total);
        $xp = $this->awardWorkout($user, $workout, count($newRecords));
        $after = Leveling::progress((int) $user->xp_total);

        $notifications = [];
        if ($after['level'] > $before['level']) {
            $notifications[] = SystemMessage::levelUp($after);
        }
        if ($after['rank'] !== $before['rank']) {
            $notifications[] = SystemMessage::rankUp($after['rank']);
        }
        foreach ($this->titles->check($user) as $key) {
            $notifications[] = SystemMessage::title($key);
        }

        return ['xp' => $xp, 'notifications' => $notifications, 'player' => $after];
    }

    /** XP de la séance et de ses records ; renvoie l'XP réellement gagnée. */
    public function awardWorkout(User $user, Workout $workout, int $recordsBeaten): int
    {
        $xp = 0;
        $amount = $this->ledger->workoutXp($workout->sets->count());

        if ($amount > 0 && $this->ledger->isEligible($user, $workout)
            && $this->ledger->award($user, 'workout', $workout->id, $amount, at: $workout->finished_at)) {
            $xp += $amount;
        }

        $records = min($recordsBeaten, self::RECORDS_PER_WORKOUT);
        if ($records > 0 && $this->ledger->award($user, 'record', $workout->id, $records * self::RECORD_XP, $records, $workout->finished_at)) {
            $xp += $records * self::RECORD_XP;
        }

        return $xp;
    }
}
```

- [ ] **Step 5 : contrôleur**

Dans `app/Http/Controllers/WorkoutSyncController.php` :
- ajouter `use App\Game\GameEvents;` et `use App\Game\Leveling;` ;
- changer la signature en `public function __invoke(SyncWorkoutRequest $request, WorkoutSync $sync, GameEvents $game): JsonResponse` ;
- juste avant `return response()->json([`, ajouter :
```php
        $user = $request->user();
        $play = $created
            ? $game->afterWorkout($user, $workout, $records)
            : ['xp' => 0, 'notifications' => [], 'player' => Leveling::progress((int) $user->xp_total)];
```
- remplacer la clé `'notifications' => …` par :
```php
            'xp_gained' => $play['xp'],
            'player' => $play['player'],
            'notifications' => $created
                ? [
                    SystemMessage::workoutDone($workout, $play['xp']),
                    ...array_map(fn (PersonalRecord $r) => SystemMessage::record($r), $records),
                    ...$play['notifications'],
                ]
                : [],
```

- [ ] **Step 6 : vérifier**

Run : `./vendor/bin/pest`
Attendu : tout PASS. Le test existant « renvoie les notifications système de fin de séance et de record » reste vrai : à la deuxième séance, aucun nouveau titre, donc les indices 0 et 1 sont inchangés.

- [ ] **Step 7 : commit**

```bash
git add -A
git commit -m "feat: XP, niveaux, rangs et titres attribués après chaque séance

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7 : rattrapage des séances existantes

**Files :**
- Create : `app/Console/Commands/BackfillPlayerXp.php`, `database/migrations/2026_10_03_000200_backfill_player_xp.php`
- Modify : `app/Game/GameEvents.php` (`backfill`)
- Test : `tests/Feature/Game/BackfillTest.php`

**Interfaces :**
- Consumes : `GameEvents::awardWorkout`, `RecordCalculator::beats`, `Titles::check`, `XpLedger::recalculate`
- Produces :
  - `GameEvents::backfill(User $user): void` : rejoue les séances dans l'ordre de `finished_at`, recompte les records battus, débloque les titres, recalcule `xp_total`, sans aucune notification ;
  - commande `php artisan ego:backfill-xp`.

- [ ] **Step 1 : test qui échoue**

`tests/Feature/Game/BackfillTest.php` :
```php
<?php

use App\Models\Exercise;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->create();
    $bench = Exercise::firstWhere('slug', 'bench-press')->id;
    // Séances enregistrées « avant » la livraison B : aucune XP attribuée.
    foreach ([['2026-09-01T10:00:00Z', 40], ['2026-09-02T10:00:00Z', 42.5], ['2026-09-03T10:00:00Z', 45]] as [$at, $kg]) {
        app(WorkoutSync::class)->sync($this->user, [
            'client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
            'sets' => array_fill(0, 3, ['exercise_id' => $bench, 'weight_kg' => $kg, 'reps' => 10, 'done_at' => $at]),
        ]);
    }
});

it('attribue rétroactivement l\'XP des séances et des records battus, sans notification', function () {
    $this->artisan('ego:backfill-xp')->assertSuccessful();

    $user = $this->user->fresh();
    expect($user->xp_total)->toBe(3 * 65 + 2 * 50)
        ->and($user->equipped_title)->toBe('awakened')
        ->and(SystemNotification::count())->toBe(0);
});

it('est idempotent', function () {
    $this->artisan('ego:backfill-xp');
    $this->artisan('ego:backfill-xp');

    expect($this->user->fresh()->xp_total)->toBe(295)
        ->and($this->user->xpEvents()->count())->toBe(5)
        ->and($this->user->titles()->count())->toBe(1);
});
```

- [ ] **Step 2 : vérifier l'échec**

Run : `./vendor/bin/pest tests/Feature/Game/BackfillTest.php`
Attendu : FAIL (`The command "ego:backfill-xp" does not exist`).

- [ ] **Step 3 : `GameEvents::backfill`**

Dans `app/Game/GameEvents.php` :
- ajouter `use App\Services\RecordCalculator;` ;
- ajouter `private RecordCalculator $calc` au constructeur : `public function __construct(private XpLedger $ledger, private Titles $titles, private RecordCalculator $calc) {}` ;
- ajouter la méthode :
```php
    /** Rejoue l'historique (séances déjà enregistrées) sans notification. Idempotent. */
    public function backfill(User $user): void
    {
        $best = [];

        $workouts = $user->workouts()->with('sets')->orderBy('finished_at')->orderBy('id')->get();

        foreach ($workouts as $workout) {
            $beaten = 0;
            foreach ($workout->sets->groupBy('exercise_id') as $exerciseId => $sets) {
                $top = $sets->sort(fn ($a, $b) => [$b->weight_kg, $b->reps] <=> [$a->weight_kg, $a->reps])->first();
                if (isset($best[$exerciseId]) && $this->calc->beats($top->weight_kg, $top->reps, ...$best[$exerciseId])) {
                    $beaten++;
                }
                if (! isset($best[$exerciseId]) || $this->calc->beats($top->weight_kg, $top->reps, ...$best[$exerciseId])) {
                    $best[$exerciseId] = [$top->weight_kg, $top->reps];
                }
            }

            $this->awardWorkout($user, $workout, $beaten);
        }

        app(XpLedger::class)->recalculate($user);
        $this->titles->check($user->fresh());
    }
```

- [ ] **Step 4 : commande et migration**

`app/Console/Commands/BackfillPlayerXp.php` :
```php
<?php

namespace App\Console\Commands;

use App\Game\GameEvents;
use App\Models\User;
use Illuminate\Console\Command;

class BackfillPlayerXp extends Command
{
    protected $signature = 'ego:backfill-xp';

    protected $description = "Attribue rétroactivement l'XP, les niveaux et les titres des séances déjà enregistrées (idempotent).";

    public function handle(GameEvents $game): int
    {
        User::query()->orderBy('id')->each(fn (User $user) => $game->backfill($user));
        $this->info('Progression recalculée.');

        return self::SUCCESS;
    }
}
```

`database/migrations/2026_10_03_000200_backfill_player_xp.php` :
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    public function up(): void
    {
        Artisan::call('ego:backfill-xp');
    }

    public function down(): void
    {
        // Données dérivées : rien à annuler (la migration des tables les supprime).
    }
};
```

- [ ] **Step 5 : vérifier**

Run : `./vendor/bin/pest && php artisan migrate --no-interaction`
Attendu : tous les tests PASS ; la migration s'exécute sur la base locale (`ego`), et ta progression réelle est calculée.

- [ ] **Step 6 : commit**

```bash
git add -A
git commit -m "feat: rattrapage idempotent de l'XP des séances existantes

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 8 : interface — fenêtre STATUT, fiche du joueur, titres

**Files :**
- Create : `resources/views/components/sys/xp-bar.blade.php`, `resources/views/components/sys/stat.blade.php`
- Modify : `resources/css/app.css` (`.sys-xp-fill`), `app/Livewire/Home.php`, `resources/views/livewire/home.blade.php`, `app/Livewire/Profile.php`, `resources/views/livewire/profile.blade.php`, `resources/views/workout/run.blade.php` (XP dans « SÉANCE ENREGISTRÉE »)
- Test : `tests/Feature/Game/PlayerUiTest.php`

**Interfaces :**
- Consumes : `Leveling::progress`, `PlayerStats::for`, `Titles::CATALOG`, `User::equippedTitleName()`
- Produces :
  - `<x-sys.xp-bar :progress="…" />` ;
  - `<x-sys.stat label="FORCE" :value="?int" />` ;
  - `Profile::equipTitle(string $key): void`.

- [ ] **Step 1 : test qui échoue**

`tests/Feature/Game/PlayerUiTest.php` :
```php
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
```

- [ ] **Step 2 : vérifier l'échec**

Run : `./vendor/bin/pest tests/Feature/Game/PlayerUiTest.php`
Attendu : FAIL.

- [ ] **Step 3 : CSS et composants**

Ajouter à `resources/css/app.css`, avant `/* ---------- Focus clavier ---------- */` :
```css
/* ---------- Progression ---------- */
.sys-track { height: 0.5rem; background: rgba(255, 255, 255, 0.08); box-shadow: inset 0 0 0 1px rgba(90, 216, 255, 0.25); }
.sys-xp-fill { height: 100%; background: linear-gradient(90deg, var(--color-ice-deep), var(--color-ice)); box-shadow: 0 0 10px var(--color-ice); transition: width 0.8s cubic-bezier(0.2, 0.8, 0.2, 1); }
```

`resources/views/components/sys/xp-bar.blade.php` :
```blade
@props(['progress'])
@php $pct = $progress['needed'] > 0 ? min(100, (int) round($progress['into'] / $progress['needed'] * 100)) : 100; @endphp
<div {{ $attributes }}>
    <div class="sys-track" role="progressbar" aria-label="Expérience" aria-valuemin="0" aria-valuemax="{{ $progress['needed'] }}" aria-valuenow="{{ $progress['into'] }}">
        <div class="sys-xp-fill" style="width: {{ $pct }}%"></div>
    </div>
    <div class="sys-num mt-1 flex justify-between text-xs text-mist">
        <span>XP {{ number_format($progress['into'], 0, ',', ' ') }} / {{ number_format($progress['needed'], 0, ',', ' ') }}</span>
        <span>→ NIV. {{ $progress['level'] + 1 }}</span>
    </div>
</div>
```

`resources/views/components/sys/stat.blade.php` :
```blade
@props(['label', 'value' => null])
<div>
    <div class="sys-num flex justify-between text-sm">
        <span class="text-mist">{{ $label }}</span>
        <span @class(['font-bold', 'text-ice' => $value !== null, 'text-steel' => $value === null])>{{ $value ?? '—' }}</span>
    </div>
    <div class="sys-track mt-1 !h-1.5"><div class="sys-xp-fill" style="width: {{ min(100, $value ?? 0) }}%"></div></div>
</div>
```

- [ ] **Step 4 : accueil**

`app/Livewire/Home.php`, méthode `render()` :
```php
    public function render(\App\Game\PlayerStats $stats)
    {
        $user = auth()->user();

        return view('livewire.home', [
            'user' => $user,
            'player' => \App\Game\Leveling::progress((int) $user->xp_total),
            'stats' => $stats->for($user),
            'lastWorkout' => $user->workouts()->with('sets.exercise')->latest('finished_at')->first(),
            'recentRecords' => $user->personalRecords()->with('exercise')->latest('achieved_at')->limit(3)->get(),
        ]);
    }
```

Dans `resources/views/livewire/home.blade.php`, insérer **entre** la `</header>` et le bouton « Séance libre » :
```blade
    <x-sys.window title="STATUT" :index="0">
        <div class="flex items-end gap-4">
            <div>
                <x-sys.label>Niveau</x-sys.label>
                <p class="sys-num text-5xl font-extrabold leading-none text-frost [text-shadow:0_0_18px_var(--glow-ice)]" data-count-up="{{ $player['level'] }}">{{ $player['level'] }}</p>
            </div>
            <div class="pb-1">
                <p class="text-sm text-mist">Rang {{ $player['rank'] }}@if ($user->equippedTitleName()) · « {{ $user->equippedTitleName() }} »@endif</p>
            </div>
        </div>
        <x-sys.xp-bar :progress="$player" class="mt-3" />
        <div class="mt-4 grid grid-cols-2 gap-x-5 gap-y-3">
            <x-sys.stat label="FORCE" :value="$stats['force']" />
            <x-sys.stat label="ENDURANCE" :value="$stats['endurance']" />
            <x-sys.stat label="PROGRESSION" :value="$stats['progression']" />
            <x-sys.stat label="RÉGULARITÉ" :value="$stats['regularite']" />
        </div>
    </x-sys.window>
```

- [ ] **Step 5 : fiche du joueur**

Dans `app/Livewire/Profile.php` :
- ajouter la méthode :
```php
    public function equipTitle(string $key): void
    {
        $user = auth()->user();

        if (! $user->titles()->where('title_key', $key)->exists()) {
            return;
        }

        $user->forceFill(['equipped_title' => $key])->save();
    }
```
- remplacer `render()` par :
```php
    public function render(\App\Game\PlayerStats $stats)
    {
        $user = auth()->user()->fresh();

        return view('livewire.profile', [
            'user' => $user,
            'player' => \App\Game\Leveling::progress((int) $user->xp_total),
            'stats' => $stats->for($user),
            'unlocked' => $user->titles()->pluck('title_key')->all(),
        ]);
    }
```

Dans `resources/views/livewire/profile.blade.php` :
- dans la fenêtre `FICHE DU JOUEUR`, remplacer la ligne de l'âge (`<p class="sys-num text-sm text-steel">{{ $user->birth_date->age }} ans</p>`) par :
```blade
                <p class="sys-num text-sm text-mist">Niveau {{ $player['level'] }} · Rang {{ $player['rank'] }} · {{ $user->birth_date->age }} ans</p>
                @if ($user->equippedTitleName()) <p class="text-sm text-ice">« {{ $user->equippedTitleName() }} »</p> @endif
```
- juste avant la fermeture `</x-sys.window>` de cette même fenêtre, ajouter :
```blade
        <x-sys.xp-bar :progress="$player" class="mt-4" />
        <div class="mt-4 grid grid-cols-2 gap-x-5 gap-y-3">
            <x-sys.stat label="FORCE" :value="$stats['force']" />
            <x-sys.stat label="ENDURANCE" :value="$stats['endurance']" />
            <x-sys.stat label="PROGRESSION" :value="$stats['progression']" />
            <x-sys.stat label="RÉGULARITÉ" :value="$stats['regularite']" />
        </div>
        @if ($stats['force'] === null)
            <p class="mt-2 text-xs text-mist">Renseigne ton poids ci-dessous pour mesurer ta FORCE.</p>
        @endif
```
- juste **après** la fenêtre `MORPHOLOGIE`, ajouter :
```blade
    <x-sys.window title="TITRES" :index="2">
        @foreach (\App\Game\Titles::CATALOG as $key => $title)
            @if (in_array($key, $unlocked, true))
                <button type="button" wire:click="equipTitle('{{ $key }}')" class="sys-row w-full text-left">
                    <span class="font-semibold">{{ $title['name'] }}</span>
                    <span @class(['sys-label', '!text-ice' => $user->equipped_title === $key])>{{ $user->equipped_title === $key ? 'Équipé' : 'Équiper' }}</span>
                </button>
            @else
                <div class="sys-row opacity-60">
                    <span>🔒 {{ $title['name'] }}</span>
                    <span class="text-right text-xs text-mist">{{ $title['condition'] }}</span>
                </div>
            @endif
        @endforeach
    </x-sys.window>
```

- [ ] **Step 6 : XP sur l'écran de fin de séance**

Dans `resources/views/workout/run.blade.php`, dans la fenêtre `SÉANCE ENREGISTRÉE`, juste après la grille des 3 chiffres, ajouter :
```blade
                        <p class="sys-num mt-3 text-center text-lg font-extrabold text-ice [text-shadow:0_0_14px_var(--glow-ice)]" x-show="summary.xp_gained > 0" x-text="'+' + summary.xp_gained + ' XP'"></p>
```

- [ ] **Step 7 : vérifier**

Run : `npm run build && ./vendor/bin/pest && npm test`
Attendu : tout PASS.

- [ ] **Step 8 : commit**

```bash
git add -A
git commit -m "feat: fenêtre STATUT, fiche du joueur avec statistiques et titres équipables

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 9 : vérification finale

- [ ] **Step 1 : suites** : `npm test && npm run build && ./vendor/bin/pest` → tout PASS.
- [ ] **Step 2 : base locale** : `php artisan migrate --no-interaction` (rattrapage exécuté), puis vérifier dans `php artisan tinker` que `User::first()->xp_total` correspond aux séances déjà faites.
- [ ] **Step 3 : simulation jsdom** de la séance (comme pour la livraison A) avec une réponse qui contient `xp_gained` et une notification `level_up` : l'écran affiche « +XP », et la notification « NIVEAU n » s'enchaîne après « SÉANCE TERMINÉE ».
- [ ] **Step 4 : vérification visuelle** par l'utilisateur : accueil (STATUT), fiche du joueur (statistiques, titres, équiper), séance → notifications.
