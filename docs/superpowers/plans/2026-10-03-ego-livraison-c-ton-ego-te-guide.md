# EGO — Livraison C : « TON EGO te guide » — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal :** TON EGO génère un programme personnalisé, assigne une **quête du jour** (séance du programme ou repos), impose une **quête de pénalité** le lendemain d'un échec, tient une **série de jours**, gère un **mode pause**, et fait vivre la stat **RÉGULARITÉ** ainsi que les titres *Assidu*, *Inarrêtable* et *Revenant*.

**Architecture :**
- `App\Services\ProgramGenerator` : fonction pure qui produit un tableau à partir des paramètres et de la bibliothèque. `App\Services\ProgramBuilder` l'enregistre.
- `App\Game` gagne quatre services :
  - `QuestScheduler` : création des quêtes, constat des échecs, pénalités, pause ; et `onWorkout`, l'accomplissement appelé par `GameEvents` ;
  - `QuestEvaluator` : objectifs remplis ou non ;
  - `PenaltyCircuit` : circuit de pénalité selon le niveau ;
  - `StreakCalculator` : série de jours, recalculée à partir des quêtes.
- Un middleware `SyncQuests` appelle `QuestScheduler::sync` à chaque page web authentifiée (requêtes GET) et range les notifications dans `system_notifications`, qui s'affichent sur la page.
- Aucun cron : les échéances sont constatées « au prochain passage ».

**Tech Stack :** Laravel 12, Livewire 3, Alpine.js, Pest 4, Vitest, MariaDB.

**Spec :** `docs/superpowers/specs/2026-10-01-ego-systeme-design.md` (§5.1, §5.4 Régularité, §5.5 titres C, §5.6 à §5.9, §6, §7, §8) et spec de base §8 (générateur).

## Global Constraints

- **Programme**
  - **Questionnaire en 5 étapes** : morphologie (pré-remplie, enregistrée), niveau, objectif, **jours d'entraînement cochés** (2 à 6, ISO 1 = lundi … 7 = dimanche), matériel.
  - **Découpage** selon le nombre de jours, comme dans la spec de base §8 :

| Jours | Découpage | Jours du programme |
|---|---|---|
| 2 | `full_body` | Full body A, Full body B |
| 3 | `full_body` | Full body A, B, C |
| 4 | `upper_lower` | Haut A, Bas A, Haut B, Bas B |
| 5 | `ppl_ul` | Push, Pull, Legs, Haut, Bas |
| 6 | `ppl` | Push A, Pull A, Legs A, Push B, Pull B, Legs B |

  - **Exercices par jour** : 5 (débutant), 6 (intermédiaire), 7 (avancé), pris dans la liste ordonnée des emplacements du type de jour. Un emplacement sans exercice compatible est ignoré.
  - **Choix des exercices** : déterministe, avec une rotation par variante (A, B, C), sans doublon dans un même jour.
  - **Séries et répétitions** (spec de base §8.5) :

| Objectif | Poly-articulaire | Isolation |
|---|---|---|
| strength | 5 × 3–5, 180 s (débutant : 4 séries) | 3 × 8–10, 90 s |
| hypertrophy | 4 × 6–10, 120 s (débutant : 3 séries) | 3 × 10–15, 60 s |
| fitness | 3 × 10–12, 90 s | 3 × 12–15, 60 s (débutant : 2 séries) |

- **Quêtes**
  - Une quête `training` ou `rest` par jour non suspendu, à partir de la date de création du programme actif (Europe/Paris) :
    - jour coché → `training`, sur le **jour de programme suivant** celui de la dernière quête `training` accomplie (le premier jour s'il n'y en a pas) ;
    - jour non coché → `rest`, validée d'office.
  - **Objectifs copiés** dans la quête, sous la forme `{exercise_id, name, slot, sets, reps_min, reps_max, rest_seconds}`.
  - **Accomplissement** : pour chaque objectif, au moins `sets` séries, le **même jour calendaire**, sur l'exercice ou sur un exercice qui partage l'emplacement (`slot`).
  - **XP de quête** : `round(200 × (1 + min(série_jusqu'à_la_veille, 5) × 0,1))`. Pénalité réussie : +50.
- **Échecs et pénalités**
  - Une quête `training` encore `pending` le lendemain devient `failed`, avec la notification `QUÊTE ÉCHOUÉE` et une quête `penalty` le jour suivant, sauf si ce jour est en pause.
  - Une `penalty` encore `pending` le lendemain devient `failed`, avec la notification `PÉNALITÉ ÉCHOUÉE`. Une pénalité ratée n'en déclenche pas d'autre.
- **Pénalité selon `users.level`** (au poids du corps : pompes, squat, crunch) :

| Niveau | Séries × répétitions |
|---|---|
| débutant (ou niveau inconnu) | 3×10, 3×15, 3×15 |
| intermédiaire | 3×20, 3×25, 3×25 |
| avancé | 4×25, 4×30, 4×30 |

- **Série de jours** : nombre de jours consécutifs (en remontant depuis aujourd'hui) dont la quête `training`/`rest` est `done`.
  - Aujourd'hui `pending`, un jour `cancelled` ou un jour sans quête (pause) : neutre.
  - Un `failed` sauvé par une pénalité du lendemain `done` ou encore `pending` : neutre.
  - Tout autre `failed` arrête la série. Un jour portant une pénalité `failed` arrête la série avant même d'être compté (« la série retombe à 0 »).
  - `best_streak` garde le maximum.
- **Pause**
  - `users.paused_at` (date) et `paused_until` (date, nullable).
  - Pendant la pause, aucune quête n'est créée ; les quêtes `pending` à partir d'aujourd'hui sont annulées (`cancelled`).
  - Fin automatique le lendemain de `paused_until`, ou manuelle (« Reprendre »).
  - Notifications `QUÊTES SUSPENDUES` et `RETOUR DE TON EGO`.
- **Séance synchronisée en retard** : elle peut accomplir une quête `failed` de son jour. La quête repasse `done`, la pénalité du lendemain encore `pending` est annulée, et la série est recalculée.
- **RÉGULARITÉ** = round(100 × quêtes `training`+`rest` `done` / quêtes `done`+`failed`), sur les dates des 28 derniers jours **avant aujourd'hui** ; `null` s'il n'y en a aucune.
- **Titres**
  - `steady` : `best_streak` ≥ 7 ;
  - `unstoppable` : `best_streak` ≥ 30 ;
  - `revenant` : au moins une `penalty` `done`.
- **Rattrapage d'absence** limité aux 60 derniers jours.
- **Copie** : le Système s'appelle **TON EGO**, l'interface est en français, avec les composants `x-sys.*`.
- **Commits** terminés par `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. MariaDB (Wamp, port 3307) doit être lancé.

## Review Focus

1. **Absence de plusieurs jours** (l'app n'a pas été ouverte du lundi au vendredi) : chaque jour passé est constaté une seule fois, dans l'ordre, sans doublon de quête ni de notification. Une pénalité ratée ne crée pas d'autre pénalité, et la série retombe à 0. Test dans la tâche 6.
2. **Pages rechargées à la suite** (plusieurs GET le même jour) : aucune quête ni notification en double. Test dans la tâche 6 (idempotence) et la tâche 8.
3. **Séance faite hier hors ligne, envoyée aujourd'hui** alors que la quête d'hier est déjà `failed` : la quête repasse `done`, la pénalité du jour est annulée et la série est intacte. Test dans la tâche 7.
4. **Programme régénéré en cours de semaine** : les quêtes déjà créées gardent leurs objectifs ; la suite part du premier jour du nouveau programme. Test dans la tâche 6.
5. **Une autre personne ouvre l'URL d'une quête qui n'est pas la sienne** (`/workout/quest/{id}`) : erreur 403. Une quête déjà accomplie ou d'un autre jour renvoie à l'accueil. Test dans la tâche 11.

---

## Structure des fichiers

```
database/migrations/2026_10_04_000100_create_programs_and_quests.php
app/Models/Program.php, ProgramDay.php, ProgramExercise.php, Quest.php ; User.php (relations, casts, isPaused)
app/Services/ProgramGenerator.php     génération pure
app/Services/ProgramBuilder.php       enregistrement + programme actif
app/Game/PenaltyCircuit.php, QuestEvaluator.php, StreakCalculator.php, QuestScheduler.php
app/Game/GameEvents.php               + accomplissement des quêtes après une séance
app/Game/PlayerStats.php, Titles.php  + Régularité, titres des quêtes
app/Support/SystemMessage.php         + questDone, questFailed, penaltyDone, penaltyFailed, pauseOn, pauseOff, programForged
app/Http/Middleware/SyncQuests.php
app/Livewire/ProgramPage.php + resources/views/livewire/program-page.blade.php
app/Livewire/Home.php, Profile.php + vues ; WorkoutController::quest ; WorkoutBootData::forQuest
resources/js/workout/store.js, component.js ; resources/views/workout/run.blade.php
routes/web.php
tests/Feature/Quests/*.php, tests/Feature/Program/*.php
```

---

### Task 1 : tables des programmes et des quêtes

**Files :**
- Create : `database/migrations/2026_10_04_000100_create_programs_and_quests.php`, `app/Models/Program.php`, `app/Models/ProgramDay.php`, `app/Models/ProgramExercise.php`, `app/Models/Quest.php`
- Modify : `app/Models/User.php`
- Test : `tests/Feature/Quests/QuestModelsTest.php`

**Interfaces :**
- Produces :
  - `Program` (`user_id`, `name`, `split`, `params` array, `copied_from_id`) avec `days(): HasMany` triée par `position` ;
  - `ProgramDay` (`program_id`, `position`, `name`, `type`) avec `exercises(): HasMany` triée et `objectives(): array` ;
  - `ProgramExercise` (`program_day_id`, `position`, `exercise_id`, `slot`, `sets`, `reps_min`, `reps_max`, `rest_seconds`) avec `exercise(): BelongsTo` ;
  - `Quest` (`user_id`, `date` date, `type` training|rest|penalty, `program_day_id`, `title`, `objectives` array, `status` pending|done|failed|cancelled, `xp_awarded`, `completed_at`) ; unique `(user_id, date, type)` ;
  - `User` :
    - relations `programs()`, `activeProgram()` (belongsTo via `active_program_id`), `quests()` ;
    - casts `training_weekdays` array, `paused_at` et `paused_until` date, `streak_days` et `best_streak` integer ;
    - méthode `isPaused(?CarbonInterface $day = null): bool`.

- [ ] **Step 1 : test qui échoue**

`tests/Feature/Quests/QuestModelsTest.php` :
```php
<?php

use App\Models\Exercise;
use App\Models\Program;
use App\Models\Quest;
use App\Models\User;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(fn () => $this->seed(ExerciseSeeder::class));

it('relie programme, jours et exercices, et produit les objectifs d\'un jour', function () {
    $user = User::factory()->create();
    $program = $user->programs()->create(['name' => 'Test', 'split' => 'full_body', 'params' => ['goal' => 'hypertrophy']]);
    $day = $program->days()->create(['position' => 1, 'name' => 'Full body A', 'type' => 'full_body']);
    $bench = Exercise::firstWhere('slug', 'bench-press');
    $day->exercises()->create(['position' => 1, 'exercise_id' => $bench->id, 'slot' => 'horizontal_push', 'sets' => 3, 'reps_min' => 6, 'reps_max' => 10, 'rest_seconds' => 120]);
    $user->forceFill(['active_program_id' => $program->id])->save();

    expect($user->fresh()->activeProgram->id)->toBe($program->id)
        ->and($program->fresh()->days->first()->objectives())->toBe([[
            'exercise_id' => $bench->id, 'name' => 'Développé couché (barre)', 'slot' => 'horizontal_push',
            'sets' => 3, 'reps_min' => 6, 'reps_max' => 10, 'rest_seconds' => 120,
        ]]);
});

it('interdit deux quêtes du même type le même jour', function () {
    $user = User::factory()->create();
    $user->quests()->create(['date' => '2026-10-05', 'type' => 'training', 'title' => 'A', 'objectives' => [], 'status' => 'pending']);

    $user->quests()->create(['date' => '2026-10-05', 'type' => 'training', 'title' => 'B', 'objectives' => [], 'status' => 'pending']);
})->throws(UniqueConstraintViolationException::class);

it('sait si le joueur est en pause un jour donné', function () {
    $user = User::factory()->create();
    expect($user->isPaused(now()))->toBeFalse();

    $user->forceFill(['paused_at' => '2026-10-05', 'paused_until' => '2026-10-07'])->save();
    $user = $user->fresh();

    expect($user->isPaused(\Illuminate\Support\Carbon::parse('2026-10-04')))->toBeFalse()
        ->and($user->isPaused(\Illuminate\Support\Carbon::parse('2026-10-06')))->toBeTrue()
        ->and($user->isPaused(\Illuminate\Support\Carbon::parse('2026-10-08')))->toBeFalse();

    $user->forceFill(['paused_until' => null])->save();
    expect($user->fresh()->isPaused(\Illuminate\Support\Carbon::parse('2027-01-01')))->toBeTrue();
});

it('caste les champs de quête', function () {
    $quest = User::factory()->create()->quests()->create(['date' => '2026-10-05', 'type' => 'rest', 'title' => 'Repos', 'objectives' => [], 'status' => 'done']);

    expect(Quest::find($quest->id)->date->toDateString())->toBe('2026-10-05')
        ->and(Quest::find($quest->id)->objectives)->toBe([]);
});
```

- [ ] **Step 2 : vérifier l'échec**

Run : `./vendor/bin/pest tests/Feature/Quests/QuestModelsTest.php`
Attendu : FAIL (`Call to undefined method App\Models\User::programs()`).

- [ ] **Step 3 : migration**

`database/migrations/2026_10_04_000100_create_programs_and_quests.php` :
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('split', 12);
            $table->json('params');
            $table->unsignedBigInteger('copied_from_id')->nullable();
            $table->timestamps();
        });

        Schema::create('program_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('name', 30);
            $table->string('type', 12);
            $table->timestamps();
        });

        Schema::create('program_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_day_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->foreignId('exercise_id')->constrained();
            $table->string('slot', 20);
            $table->unsignedTinyInteger('sets');
            $table->unsignedTinyInteger('reps_min');
            $table->unsignedTinyInteger('reps_max');
            $table->unsignedSmallInteger('rest_seconds');
            $table->timestamps();
        });

        Schema::create('quests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 10);
            $table->foreignId('program_day_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 40);
            $table->json('objectives');
            $table->string('status', 10)->default('pending');
            $table->unsignedInteger('xp_awarded')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'date', 'type']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('training_weekdays')->nullable()->after('equipment');
            $table->unsignedInteger('streak_days')->default(0)->after('equipped_title');
            $table->unsignedInteger('best_streak')->default(0)->after('streak_days');
            $table->date('paused_at')->nullable()->after('best_streak');
            $table->date('paused_until')->nullable()->after('paused_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['training_weekdays', 'streak_days', 'best_streak', 'paused_at', 'paused_until']));
        Schema::dropIfExists('quests');
        Schema::dropIfExists('program_exercises');
        Schema::dropIfExists('program_days');
        Schema::dropIfExists('programs');
    }
};
```

- [ ] **Step 4 : modèles**

`app/Models/Program.php` :
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    protected $fillable = ['name', 'split', 'params', 'copied_from_id'];

    protected function casts(): array
    {
        return ['params' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(ProgramDay::class)->orderBy('position');
    }
}
```

`app/Models/ProgramDay.php` :
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramDay extends Model
{
    protected $fillable = ['position', 'name', 'type'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(ProgramExercise::class)->orderBy('position');
    }

    /** @return list<array{exercise_id: int, name: string, slot: string, sets: int, reps_min: int, reps_max: int, rest_seconds: int}> */
    public function objectives(): array
    {
        return $this->exercises()->with('exercise')->get()->map(fn (ProgramExercise $e) => [
            'exercise_id' => $e->exercise_id,
            'name' => $e->exercise->name,
            'slot' => $e->slot,
            'sets' => $e->sets,
            'reps_min' => $e->reps_min,
            'reps_max' => $e->reps_max,
            'rest_seconds' => $e->rest_seconds,
        ])->all();
    }
}
```

`app/Models/ProgramExercise.php` :
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramExercise extends Model
{
    protected $fillable = ['position', 'exercise_id', 'slot', 'sets', 'reps_min', 'reps_max', 'rest_seconds'];

    protected function casts(): array
    {
        return ['exercise_id' => 'integer', 'sets' => 'integer', 'reps_min' => 'integer', 'reps_max' => 'integer', 'rest_seconds' => 'integer'];
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(ProgramDay::class, 'program_day_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
```

`app/Models/Quest.php` :
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quest extends Model
{
    protected $fillable = ['date', 'type', 'program_day_id', 'title', 'objectives', 'status', 'xp_awarded', 'completed_at'];

    protected function casts(): array
    {
        return ['date' => 'date', 'objectives' => 'array', 'xp_awarded' => 'integer', 'completed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

Dans `app/Models/User.php` :
- dans `casts()`, ajouter :
```php
            'training_weekdays' => 'array',
            'streak_days' => 'integer',
            'best_streak' => 'integer',
            'paused_at' => 'date',
            'paused_until' => 'date',
```
- ajouter `use Illuminate\Database\Eloquent\Relations\BelongsTo;` et `use Carbon\CarbonInterface;` ;
- ajouter les méthodes :
```php
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function activeProgram(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'active_program_id');
    }

    public function quests(): HasMany
    {
        return $this->hasMany(Quest::class);
    }

    public function isPaused(?CarbonInterface $day = null): bool
    {
        $day = ($day ?? now())->copy()->startOfDay();

        return $this->paused_at !== null
            && $day->gte($this->paused_at)
            && ($this->paused_until === null || $day->lte($this->paused_until));
    }
```

- [ ] **Step 5 : vérifier**

Run : `php artisan migrate --no-interaction && ./vendor/bin/pest`
Attendu : tout PASS.

- [ ] **Step 6 : commit**

```bash
git add -A
git commit -m "feat: tables des programmes et des quêtes

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2 : générateur de programmes

**Files :**
- Create : `app/Services/ProgramGenerator.php`
- Test : `tests/Feature/Program/ProgramGeneratorTest.php`

**Interfaces :**
- Consumes : `Exercise` (`slots`, `equipment`, `kind`, `id`)
- Produces :
  - `ProgramGenerator::generate(array $params, Collection $exercises): array`, avec `$params = {level, goal, days (2..6), equipment}`. Le résultat est de la forme `{split: string, days: list<{name, type, exercises: list<{exercise_id, slot, sets, reps_min, reps_max, rest_seconds}>}>}` ;
  - `ProgramGenerator::candidates(string $slot, string $equipment, Collection $exercises): Collection` (triés par `id`) ;
  - `ProgramGenerator::alternativesFor(string $slot, string $equipment, Collection $exercises, ?int $exceptId = null): Collection` ;
  - constantes `SPLITS`, `DAY_SLOTS`, `SLOTS_BY_LEVEL`.

- [ ] **Step 1 : test qui échoue**

`tests/Feature/Program/ProgramGeneratorTest.php` :
```php
<?php

use App\Models\Exercise;
use App\Services\ProgramGenerator;
use Database\Seeders\ExerciseSeeder;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->exercises = Exercise::all();
    $this->generator = new ProgramGenerator;
    $this->params = fn (array $over = []) => [...['level' => 'intermediate', 'goal' => 'hypertrophy', 'days' => 3, 'equipment' => 'gym'], ...$over];
});

it('choisit le découpage selon le nombre de jours', function (int $days, string $split, array $names) {
    $program = $this->generator->generate(($this->params)(['days' => $days]), $this->exercises);

    expect($program['split'])->toBe($split)
        ->and(array_column($program['days'], 'name'))->toBe($names);
})->with([
    [2, 'full_body', ['Full body A', 'Full body B']],
    [3, 'full_body', ['Full body A', 'Full body B', 'Full body C']],
    [4, 'upper_lower', ['Haut A', 'Bas A', 'Haut B', 'Bas B']],
    [5, 'ppl_ul', ['Push', 'Pull', 'Legs', 'Haut', 'Bas']],
    [6, 'ppl', ['Push A', 'Pull A', 'Legs A', 'Push B', 'Pull B', 'Legs B']],
]);

it('met 5, 6 ou 7 exercices par jour selon le niveau (salle complète)', function (string $level, int $count) {
    foreach ([2, 4, 5, 6] as $days) {
        foreach ($this->generator->generate(($this->params)(['level' => $level, 'days' => $days]), $this->exercises)['days'] as $day) {
            expect($day['exercises'])->toHaveCount($count);
        }
    }
})->with([['beginner', 5], ['intermediate', 6], ['advanced', 7]]);

it('respecte le matériel et ne répète pas un exercice dans une journée', function (string $equipment) {
    $byId = $this->exercises->keyBy('id');

    foreach ($this->generator->generate(($this->params)(['equipment' => $equipment, 'days' => 6, 'level' => 'advanced']), $this->exercises)['days'] as $day) {
        $ids = array_column($day['exercises'], 'exercise_id');
        expect($ids)->toBe(array_values(array_unique($ids)))->and($ids)->not->toBeEmpty();
        foreach ($ids as $id) {
            expect($byId[$id]->equipment)->toContain($equipment);
        }
    }
})->with(['gym', 'dumbbells', 'bodyweight']);

it('applique séries, répétitions et repos selon l\'objectif et le niveau', function () {
    $day = $this->generator->generate(($this->params)(['goal' => 'hypertrophy', 'level' => 'beginner', 'days' => 4]), $this->exercises)['days'][0];
    $kinds = $this->exercises->keyBy('id');

    foreach ($day['exercises'] as $e) {
        $expected = $kinds[$e['exercise_id']]->kind === 'compound' ? [3, 6, 10, 120] : [3, 10, 15, 60];
        expect([$e['sets'], $e['reps_min'], $e['reps_max'], $e['rest_seconds']])->toBe($expected);
    }

    $strength = $this->generator->generate(($this->params)(['goal' => 'strength', 'level' => 'intermediate']), $this->exercises)['days'][0]['exercises'][0];
    expect([$strength['sets'], $strength['reps_min'], $strength['reps_max'], $strength['rest_seconds']])->toBe([5, 3, 5, 180]);
});

it('fait varier les exercices entre les variantes A et B', function () {
    [$a, $b] = $this->generator->generate(($this->params)(['days' => 2]), $this->exercises)['days'];

    expect($a['exercises'][0]['slot'])->toBe('squat')
        ->and($a['exercises'][0]['exercise_id'])->not->toBe($b['exercises'][0]['exercise_id']);
});

it('est déterministe', function () {
    expect($this->generator->generate(($this->params)(), $this->exercises))->toBe($this->generator->generate(($this->params)(), $this->exercises));
});

it('propose des alternatives du même emplacement et du même matériel', function () {
    $current = Exercise::firstWhere('slug', 'back-squat');
    $alternatives = $this->generator->alternativesFor('squat', 'gym', $this->exercises, $current->id);

    expect($alternatives->pluck('id'))->not->toContain($current->id)
        ->and($alternatives)->not->toBeEmpty()
        ->and($alternatives->every(fn ($e) => in_array('squat', $e->slots) && in_array('gym', $e->equipment)))->toBeTrue();
});
```

- [ ] **Step 2 : vérifier l'échec**

Run : `./vendor/bin/pest tests/Feature/Program/ProgramGeneratorTest.php`
Attendu : FAIL (`Class "App\Services\ProgramGenerator" not found`).

- [ ] **Step 3 : implémentation**

`app/Services/ProgramGenerator.php` :
```php
<?php

namespace App\Services;

use App\Models\Exercise;
use Illuminate\Support\Collection;

final class ProgramGenerator
{
    public const SPLITS = [
        2 => ['full_body', [['Full body A', 'full_body'], ['Full body B', 'full_body']]],
        3 => ['full_body', [['Full body A', 'full_body'], ['Full body B', 'full_body'], ['Full body C', 'full_body']]],
        4 => ['upper_lower', [['Haut A', 'upper'], ['Bas A', 'lower'], ['Haut B', 'upper'], ['Bas B', 'lower']]],
        5 => ['ppl_ul', [['Push', 'push'], ['Pull', 'pull'], ['Legs', 'legs'], ['Haut', 'upper'], ['Bas', 'lower']]],
        6 => ['ppl', [['Push A', 'push'], ['Pull A', 'pull'], ['Legs A', 'legs'], ['Push B', 'push'], ['Pull B', 'pull'], ['Legs B', 'legs']]],
    ];

    /** Emplacements ordonnés par type de jour : poly-articulaires d'abord. */
    public const DAY_SLOTS = [
        'full_body' => ['squat', 'horizontal_push', 'horizontal_pull', 'hinge', 'vertical_push', 'vertical_pull', 'core'],
        'upper' => ['horizontal_push', 'horizontal_pull', 'vertical_push', 'vertical_pull', 'shoulder_iso', 'biceps', 'triceps'],
        'lower' => ['squat', 'hinge', 'lunge', 'quads_iso', 'hamstrings_iso', 'calves', 'core'],
        'push' => ['horizontal_push', 'vertical_push', 'chest_iso', 'shoulder_iso', 'triceps', 'calves', 'core'],
        'pull' => ['vertical_pull', 'horizontal_pull', 'hinge', 'biceps', 'shoulder_iso', 'core', 'calves'],
        'legs' => ['squat', 'hinge', 'lunge', 'quads_iso', 'hamstrings_iso', 'calves', 'core'],
    ];

    public const SLOTS_BY_LEVEL = ['beginner' => 5, 'intermediate' => 6, 'advanced' => 7];

    /** [séries, reps min, reps max, repos s] */
    private const PRESCRIPTION = [
        'strength' => ['compound' => [5, 3, 5, 180], 'isolation' => [3, 8, 10, 90]],
        'hypertrophy' => ['compound' => [4, 6, 10, 120], 'isolation' => [3, 10, 15, 60]],
        'fitness' => ['compound' => [3, 10, 12, 90], 'isolation' => [3, 12, 15, 60]],
    ];

    public function generate(array $params, Collection $exercises): array
    {
        [$split, $days] = self::SPLITS[$params['days']];
        $limit = self::SLOTS_BY_LEVEL[$params['level']] ?? 5;
        $variants = [];
        $out = [];

        foreach ($days as [$name, $type]) {
            $variant = $variants[$type] = ($variants[$type] ?? -1) + 1;
            $used = [];
            $items = [];

            foreach (array_slice(self::DAY_SLOTS[$type], 0, $limit) as $slot) {
                $pick = $this->pick($slot, $params['equipment'], $exercises, $variant, $used);
                if (! $pick) {
                    continue;
                }
                $used[] = $pick->id;
                $items[] = ['exercise_id' => $pick->id, 'slot' => $slot, ...$this->prescription($params['goal'], $params['level'], $pick->kind)];
            }

            $out[] = ['name' => $name, 'type' => $type, 'exercises' => $items];
        }

        return ['split' => $split, 'days' => $out];
    }

    public function candidates(string $slot, string $equipment, Collection $exercises): Collection
    {
        return $exercises
            ->filter(fn (Exercise $e) => in_array($slot, $e->slots, true) && in_array($equipment, $e->equipment, true))
            ->sortBy('id')
            ->values();
    }

    public function alternativesFor(string $slot, string $equipment, Collection $exercises, ?int $exceptId = null): Collection
    {
        return $this->candidates($slot, $equipment, $exercises)->reject(fn (Exercise $e) => $e->id === $exceptId)->values();
    }

    private function pick(string $slot, string $equipment, Collection $exercises, int $variant, array $used): ?Exercise
    {
        $candidates = $this->candidates($slot, $equipment, $exercises)->reject(fn (Exercise $e) => in_array($e->id, $used, true))->values();

        return $candidates->isEmpty() ? null : $candidates[$variant % $candidates->count()];
    }

    /** @return array{sets: int, reps_min: int, reps_max: int, rest_seconds: int} */
    private function prescription(string $goal, string $level, string $kind): array
    {
        [$sets, $min, $max, $rest] = self::PRESCRIPTION[$goal][$kind];

        if ($level === 'beginner') {
            $sets = match (true) {
                $goal === 'strength' && $kind === 'compound' => 4,
                $goal === 'hypertrophy' && $kind === 'compound' => 3,
                $goal === 'fitness' && $kind === 'isolation' => 2,
                default => $sets,
            };
        }

        return ['sets' => $sets, 'reps_min' => $min, 'reps_max' => $max, 'rest_seconds' => $rest];
    }
}
```

- [ ] **Step 4 : vérifier**

Run : `./vendor/bin/pest tests/Feature/Program/ProgramGeneratorTest.php`
Attendu : PASS. Si le test « 5, 6 ou 7 exercices » échoue faute d'exercice de salle pour un emplacement, ajouter l'exercice manquant dans `database/data/exercises.php` (la tâche 5 de l'étape 1 garantit déjà un exercice de salle par emplacement).

- [ ] **Step 5 : commit**

```bash
git add -A
git commit -m "feat: générateur de programmes (découpage, emplacements, séries)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3 : enregistrement du programme et page Programme

**Files :**
- Create : `app/Services/ProgramBuilder.php`, `app/Livewire/ProgramPage.php`, `resources/views/livewire/program-page.blade.php`
- Modify : `routes/web.php`, `app/Support/SystemMessage.php` (`programForged`), `tests/Feature/LayoutTest.php` (la page Programme n'est plus « bientôt »)
- Test : `tests/Feature/Program/ProgramPageTest.php`

**Interfaces :**
- Consumes : `ProgramGenerator`, `SystemNotifier`, `SystemMessage`
- Produces :
  - `ProgramBuilder::build(User $user, array $params): Program`, avec `$params = {level, goal, weekdays: int[], equipment}`. Enregistre le programme et ses jours, et met à jour `level`, `goal`, `days_per_week`, `equipment`, `training_weekdays` (triés, sans doublon) et `active_program_id` ;
  - `ProgramBuilder::GOALS`, `LEVELS`, `EQUIPMENT` (libellés français) ;
  - route `program` → `ProgramPage`, avec les propriétés `step` (1..5), `editing`, `sex`, `heightCm`, `weightKg`, `level`, `goal`, `weekdays`, `equipment`, `replacing` (?int) et les actions `next()`, `back()`, `generate()`, `regenerate()`, `replace(int $programExerciseId, int $exerciseId)`.

- [ ] **Step 1 : test qui échoue**

`tests/Feature/Program/ProgramPageTest.php` :
```php
<?php

use App\Livewire\ProgramPage;
use App\Models\Exercise;
use App\Models\ProgramExercise;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(sex: 'female', heightCm: 165, weightKg: 60.0)->create();
    $this->actingAs($this->user);
});

function answerQuestionnaire($component)
{
    return $component
        ->call('next')                                        // morphologie pré-remplie
        ->set('level', 'beginner')->call('next')
        ->set('goal', 'hypertrophy')->call('next')
        ->set('weekdays', [5, 1, 3])->call('next')
        ->set('equipment', 'gym');
}

it('ouvre le questionnaire quand aucun programme n\'existe', function () {
    $this->get('/program')->assertOk()->assertSee('TON EGO VA FORGER TON PROGRAMME')->assertSee('MORPHOLOGIE');
});

it('génère et active le programme à la fin du questionnaire', function () {
    answerQuestionnaire(Livewire::test(ProgramPage::class))
        ->call('generate')
        ->assertHasNoErrors()
        ->assertRedirect(route('program'));

    $user = $this->user->fresh();
    expect($user->activeProgram)->not->toBeNull()
        ->and($user->activeProgram->days)->toHaveCount(3)
        ->and($user->training_weekdays)->toBe([1, 3, 5])
        ->and($user->level)->toBe('beginner')
        ->and($user->goal)->toBe('hypertrophy')
        ->and($user->equipment)->toBe('gym');

    $this->get('/program')->assertSee('Full body A')->assertSee('Full body C');
});

it('refuse moins de 2 ou plus de 6 jours', function (array $days) {
    Livewire::test(ProgramPage::class)
        ->call('next')->set('level', 'beginner')->call('next')->set('goal', 'fitness')->call('next')
        ->set('weekdays', $days)->call('next')
        ->assertHasErrors(['weekdays'])
        ->assertSet('step', 4);
})->with([[[1]], [[1, 2, 3, 4, 5, 6, 7]]]);

it('remplace un exercice par une alternative du même emplacement uniquement', function () {
    $program = app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3], 'equipment' => 'gym']);
    $line = $program->days->first()->exercises->first();          // emplacement squat
    $frontSquat = Exercise::firstWhere('slug', 'front-squat');
    $curl = Exercise::firstWhere('slug', 'barbell-curl');

    Livewire::test(ProgramPage::class)->call('replace', $line->id, $curl->id);
    expect(ProgramExercise::find($line->id)->exercise_id)->not->toBe($curl->id);

    Livewire::test(ProgramPage::class)->call('replace', $line->id, $frontSquat->id);
    expect(ProgramExercise::find($line->id)->exercise_id)->toBe($frontSquat->id);
});

it('ignore le remplacement dans le programme d\'un autre joueur', function () {
    $other = User::factory()->create();
    $program = app(ProgramBuilder::class)->build($other, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3], 'equipment' => 'gym']);
    $line = $program->days->first()->exercises->first();

    Livewire::test(ProgramPage::class)->call('replace', $line->id, Exercise::firstWhere('slug', 'front-squat')->id);

    expect(ProgramExercise::find($line->id)->exercise_id)->toBe($line->exercise_id);
});

it('rouvre le questionnaire pour régénérer', function () {
    app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3], 'equipment' => 'gym']);

    Livewire::test(ProgramPage::class)->call('regenerate')->assertSet('editing', true)->assertSee('MORPHOLOGIE');
});
```

Dans `tests/Feature/LayoutTest.php`, retirer la ligne `['/program', 'générateur de programmes'],` du dataset « affiche les pages à venir » : la page Programme est maintenant réelle.

- [ ] **Step 2 : vérifier l'échec**

Run : `./vendor/bin/pest tests/Feature/Program/ProgramPageTest.php`
Attendu : FAIL (`Class "App\Livewire\ProgramPage" not found`).

- [ ] **Step 3 : `ProgramBuilder`**

`app/Services/ProgramBuilder.php` :
```php
<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ProgramBuilder
{
    public const GOALS = ['strength' => 'Force', 'hypertrophy' => 'Prise de muscle', 'fitness' => 'Remise en forme'];
    public const LEVELS = ['beginner' => 'Débutant', 'intermediate' => 'Intermédiaire', 'advanced' => 'Avancé'];
    public const EQUIPMENT = ['gym' => 'Salle complète', 'dumbbells' => 'Haltères seuls', 'bodyweight' => 'Poids du corps'];

    public function __construct(private ProgramGenerator $generator) {}

    /** @param array{level: string, goal: string, weekdays: array<int|string>, equipment: string} $params */
    public function build(User $user, array $params): Program
    {
        $weekdays = collect($params['weekdays'])->map(fn ($d) => (int) $d)->unique()->sort()->values()->all();
        $generated = $this->generator->generate([
            'level' => $params['level'], 'goal' => $params['goal'], 'days' => count($weekdays), 'equipment' => $params['equipment'],
        ], Exercise::all());

        return DB::transaction(function () use ($user, $params, $weekdays, $generated) {
            $program = $user->programs()->create([
                'name' => 'Programme '.self::GOALS[$params['goal']].' · '.count($weekdays).' j',
                'split' => $generated['split'],
                'params' => [...$params, 'weekdays' => $weekdays],
            ]);

            foreach ($generated['days'] as $i => $day) {
                $programDay = $program->days()->create(['position' => $i + 1, 'name' => $day['name'], 'type' => $day['type']]);
                foreach ($day['exercises'] as $j => $exercise) {
                    $programDay->exercises()->create([...$exercise, 'position' => $j + 1]);
                }
            }

            $user->forceFill([
                'level' => $params['level'],
                'goal' => $params['goal'],
                'days_per_week' => count($weekdays),
                'equipment' => $params['equipment'],
                'training_weekdays' => $weekdays,
                'active_program_id' => $program->id,
            ])->save();

            return $program->load('days.exercises.exercise');
        });
    }
}
```

- [ ] **Step 4 : message système**

Dans `app/Support/SystemMessage.php`, ajouter :
```php
    public static function programForged(string $name): array
    {
        return self::make('program', 'PROGRAMME FORGÉ', [$name, 'TON EGO t\'assignera ta première quête dès aujourd\'hui.']);
    }
```

- [ ] **Step 5 : composant Livewire**

`app/Livewire/ProgramPage.php` :
```php
<?php

namespace App\Livewire;

use App\Models\Exercise;
use App\Models\ProgramExercise;
use App\Services\ProgramBuilder;
use App\Services\ProgramGenerator;
use App\Support\SystemMessage;
use App\Support\SystemNotifier;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Programme')]
class ProgramPage extends Component
{
    public int $step = 1;

    public bool $editing = false;

    public ?string $sex = null;

    public $heightCm = null;

    public $weightKg = null;

    public ?string $level = null;

    public ?string $goal = null;

    public array $weekdays = [];

    public ?string $equipment = null;

    public ?int $replacing = null;

    public function mount(): void
    {
        $user = auth()->user();
        $this->sex = $user->sex;
        $this->heightCm = $user->height_cm;
        $this->weightKg = $user->weight_kg;
        $this->level = $user->level;
        $this->goal = $user->goal;
        $this->weekdays = $user->training_weekdays ?? [];
        $this->equipment = $user->equipment;
    }

    public function next(): void
    {
        $this->validateStep($this->step);
        $this->step = min(5, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function regenerate(): void
    {
        $this->editing = true;
        $this->step = 1;
    }

    public function generate()
    {
        $builder = app(ProgramBuilder::class);
        $notifier = app(SystemNotifier::class);
        foreach (range(1, 5) as $step) {
            $this->validateStep($step);
        }

        $user = auth()->user();
        $user->forceFill([
            'sex' => $this->sex,
            'height_cm' => (int) $this->heightCm,
            'weight_kg' => (float) str_replace(',', '.', (string) $this->weightKg),
        ])->save();

        $program = $builder->build($user, [
            'level' => $this->level, 'goal' => $this->goal, 'weekdays' => $this->weekdays, 'equipment' => $this->equipment,
        ]);

        $notifier->push($user, SystemMessage::programForged($program->name));

        return $this->redirectRoute('program');
    }

    public function replace(int $programExerciseId, int $exerciseId): void
    {
        $generator = app(ProgramGenerator::class);
        $user = auth()->user();
        $line = ProgramExercise::with('day.program')->find($programExerciseId);

        if (! $line || $line->day->program->id !== $user->active_program_id) {
            return;
        }

        $allowed = $generator->alternativesFor($line->slot, $user->equipment ?? 'gym', Exercise::all(), $line->exercise_id)->pluck('id');
        if ($allowed->contains($exerciseId)) {
            $line->update(['exercise_id' => $exerciseId]);
        }

        $this->replacing = null;
    }

    private function validateStep(int $step): void
    {
        if ($step === 1 && is_string($this->weightKg)) {
            $this->weightKg = str_replace(',', '.', $this->weightKg);
        }

        $rules = match ($step) {
            1 => ['sex' => ['required', 'in:male,female'], 'heightCm' => ['required', 'integer', 'between:100,250'], 'weightKg' => ['required', 'numeric', 'between:30,300']],
            2 => ['level' => ['required', 'in:beginner,intermediate,advanced']],
            3 => ['goal' => ['required', 'in:strength,hypertrophy,fitness']],
            4 => ['weekdays' => ['required', 'array', 'min:2', 'max:6'], 'weekdays.*' => ['integer', 'between:1,7', 'distinct']],
            5 => ['equipment' => ['required', 'in:gym,dumbbells,bodyweight']],
        };

        try {
            $this->validate($rules, [
                'sex.required' => 'Choisis homme ou femme.',
                'heightCm.*' => 'Indique ta taille en cm (100 à 250).',
                'weightKg.*' => 'Indique ton poids en kg (30 à 300).',
                'level.required' => 'Choisis ton niveau.',
                'goal.required' => 'Choisis ton objectif.',
                'weekdays.required' => 'Coche tes jours d\'entraînement.',
                'weekdays.min' => 'Coche au moins 2 jours.',
                'weekdays.max' => 'Coche au plus 6 jours : garde au moins un jour de repos.',
                'equipment.required' => 'Choisis ton matériel.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->step = $step;
            throw $e;
        }
    }

    public function render(ProgramGenerator $generator)
    {
        $user = auth()->user();
        $program = $user->activeProgram()->with('days.exercises.exercise')->first();
        $alternatives = [];

        if ($program && $this->replacing) {
            $line = $program->days->flatMap->exercises->firstWhere('id', $this->replacing);
            if ($line) {
                $alternatives = $generator->alternativesFor($line->slot, $user->equipment ?? 'gym', Exercise::all(), $line->exercise_id);
            }
        }

        return view('livewire.program-page', [
            'program' => $program,
            'showQuestionnaire' => ! $program || $this->editing,
            'alternatives' => $alternatives,
            'days' => ['1' => 'Lun', '2' => 'Mar', '3' => 'Mer', '4' => 'Jeu', '5' => 'Ven', '6' => 'Sam', '7' => 'Dim'],
        ]);
    }
}
```

- [ ] **Step 6 : vue**

`resources/views/livewire/program-page.blade.php` :
```blade
<div class="space-y-5">
    @if ($showQuestionnaire)
        <x-sys.label>Programme</x-sys.label>
        <h1 class="text-2xl font-bold">TON EGO VA FORGER TON PROGRAMME</h1>
        <p class="sys-num text-sm text-mist">Étape {{ $step }} / 5</p>

        <x-sys.window :title="['MORPHOLOGIE', 'NIVEAU', 'OBJECTIF', 'JOURS D\'ENTRAÎNEMENT', 'MATÉRIEL'][$step - 1]">
            @if ($step === 1)
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (['male' => 'Homme', 'female' => 'Femme'] as $value => $label)
                            <label @class(['sys-chip cursor-pointer py-3 text-center text-sm', 'sys-chip--active' => $sex === $value])>
                                <input type="radio" wire:model.live="sex" value="{{ $value }}" class="sr-only">{{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('sex') <p class="sys-error">{{ $message }}</p> @enderror
                    <div class="grid grid-cols-2 gap-3">
                        <x-sys.field name="heightCm" label="Taille (cm)" inputmode="numeric" wire:model="heightCm" />
                        <x-sys.field name="weightKg" label="Poids (kg)" inputmode="decimal" wire:model="weightKg" />
                    </div>
                </div>
            @elseif ($step === 2)
                <div class="grid gap-2">
                    @foreach (\App\Services\ProgramBuilder::LEVELS as $value => $label)
                        <label @class(['sys-chip cursor-pointer py-3 text-center', 'sys-chip--active' => $level === $value])>
                            <input type="radio" wire:model.live="level" value="{{ $value }}" class="sr-only">{{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('level') <p class="sys-error">{{ $message }}</p> @enderror
            @elseif ($step === 3)
                <div class="grid gap-2">
                    @foreach (\App\Services\ProgramBuilder::GOALS as $value => $label)
                        <label @class(['sys-chip cursor-pointer py-3 text-center', 'sys-chip--active' => $goal === $value])>
                            <input type="radio" wire:model.live="goal" value="{{ $value }}" class="sr-only">{{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('goal') <p class="sys-error">{{ $message }}</p> @enderror
            @elseif ($step === 4)
                <p class="mb-3 text-sm text-mist">Coche les jours où tu t'entraînes (2 à 6). Les autres seront des jours de repos.</p>
                <div class="grid grid-cols-7 gap-1">
                    @foreach ($days as $value => $label)
                        <label @class(['sys-chip cursor-pointer px-0 py-3 text-center text-xs', 'sys-chip--active' => in_array((int) $value, array_map('intval', $weekdays), true)])>
                            <input type="checkbox" wire:model.live="weekdays" value="{{ $value }}" class="sr-only">{{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('weekdays') <p class="sys-error">{{ $message }}</p> @enderror
            @else
                <div class="grid gap-2">
                    @foreach (\App\Services\ProgramBuilder::EQUIPMENT as $value => $label)
                        <label @class(['sys-chip cursor-pointer py-3 text-center', 'sys-chip--active' => $equipment === $value])>
                            <input type="radio" wire:model.live="equipment" value="{{ $value }}" class="sr-only">{{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('equipment') <p class="sys-error">{{ $message }}</p> @enderror
            @endif
        </x-sys.window>

        <div class="grid grid-cols-2 gap-3">
            <x-sys.button variant="ghost" wire:click="back" :disabled="$step === 1">Retour</x-sys.button>
            @if ($step < 5)
                <x-sys.button wire:click="next">Suivant</x-sys.button>
            @else
                <x-sys.button :pulse="true" wire:click="generate" wire:loading.attr="disabled">Forger</x-sys.button>
            @endif
        </div>
    @else
        <x-sys.label>Programme</x-sys.label>
        <h1 class="text-2xl font-bold">{{ $program->name }}</h1>
        <p class="text-sm text-mist">
            {{ \App\Services\ProgramBuilder::LEVELS[$program->params['level']] ?? '' }} · {{ \App\Services\ProgramBuilder::EQUIPMENT[$program->params['equipment']] ?? '' }}
            · {{ collect($program->params['weekdays'] ?? [])->map(fn ($d) => $days[(string) $d])->join(', ') }}
        </p>

        @foreach ($program->days as $day)
            <x-sys.window :title="$day->name" :index="$loop->index">
                @foreach ($day->exercises as $line)
                    <div class="sys-row">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $line->exercise->name }}</p>
                            <p class="sys-num text-xs text-mist">{{ $line->sets }} × {{ $line->reps_min }}–{{ $line->reps_max }} · repos {{ $line->rest_seconds }} s</p>
                        </div>
                        <button type="button" class="sys-label shrink-0" wire:click="$set('replacing', {{ $replacing === $line->id ? 'null' : $line->id }})">Remplacer</button>
                    </div>
                    @if ($replacing === $line->id)
                        <ul class="mb-2 border-l border-ice/40 pl-3">
                            @forelse ($alternatives as $alt)
                                <li><button type="button" class="py-1.5 text-left text-sm text-ice" wire:click="replace({{ $line->id }}, {{ $alt->id }})">→ {{ $alt->name }}</button></li>
                            @empty
                                <li class="py-1.5 text-sm text-mist">Aucune alternative avec ton matériel.</li>
                            @endforelse
                        </ul>
                    @endif
                @endforeach
            </x-sys.window>
        @endforeach

        <x-sys.button variant="ghost" wire:click="regenerate">Régénérer le programme</x-sys.button>
    @endif
</div>
```

- [ ] **Step 7 : route**

Dans `routes/web.php`, remplacer le bloc `Route::view('/program', 'pages.soon', [...])->name('program');` par :
```php
    Route::get('/program', ProgramPage::class)->name('program');
```
et ajouter `use App\Livewire\ProgramPage;`.

- [ ] **Step 8 : vérifier**

Run : `npm run build && ./vendor/bin/pest`
Attendu : tout PASS.

- [ ] **Step 9 : commit**

```bash
git add -A
git commit -m "feat: questionnaire en 5 étapes, programme forgé, remplacement d'exercice

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4 : circuit de pénalité et évaluation des objectifs

**Files :**
- Create : `app/Game/PenaltyCircuit.php`, `app/Game/QuestEvaluator.php`
- Test : `tests/Feature/Quests/PenaltyAndEvaluatorTest.php`

**Interfaces :**
- Produces :
  - `PenaltyCircuit::for(?string $level): list<objective>` (pompes, squat au poids du corps, crunch) ;
  - `QuestEvaluator::progress(array $objectives, Collection $sets): list<int>` (séries comptées par objectif ; `$sets` = `WorkoutSet` avec `exercise` chargé) ;
  - `QuestEvaluator::isAccomplished(array $objectives, Collection $sets): bool` (`false` si aucun objectif).

- [ ] **Step 1 : test qui échoue**

`tests/Feature/Quests/PenaltyAndEvaluatorTest.php` :
```php
<?php

use App\Game\PenaltyCircuit;
use App\Game\QuestEvaluator;
use App\Models\Exercise;
use App\Models\WorkoutSet;
use Database\Seeders\ExerciseSeeder;

beforeEach(fn () => $this->seed(ExerciseSeeder::class));

function fakeSets(array $slugCounts)
{
    return collect($slugCounts)->flatMap(function ($count, $slug) {
        $exercise = Exercise::firstWhere('slug', $slug);

        return collect(range(1, $count))->map(fn () => (new WorkoutSet(['exercise_id' => $exercise->id]))->setRelation('exercise', $exercise));
    })->values();
}

it('adapte le circuit de pénalité au niveau', function (?string $level, array $expected) {
    $circuit = app(PenaltyCircuit::class)->for($level);

    expect(array_column($circuit, 'name'))->toBe(['Pompes', 'Squat au poids du corps', 'Crunch'])
        ->and(array_map(fn ($o) => [$o['sets'], $o['reps_min']], $circuit))->toBe($expected);
})->with([
    [null, [[3, 10], [3, 15], [3, 15]]],
    ['beginner', [[3, 10], [3, 15], [3, 15]]],
    ['intermediate', [[3, 20], [3, 25], [3, 25]]],
    ['advanced', [[4, 25], [4, 30], [4, 30]]],
]);

it('valide une quête quand chaque objectif a assez de séries', function () {
    $objectives = [
        ['exercise_id' => Exercise::firstWhere('slug', 'back-squat')->id, 'slot' => 'squat', 'sets' => 3],
        ['exercise_id' => Exercise::firstWhere('slug', 'bench-press')->id, 'slot' => 'horizontal_push', 'sets' => 2],
    ];
    $evaluator = app(QuestEvaluator::class);

    expect($evaluator->isAccomplished($objectives, fakeSets(['back-squat' => 3, 'bench-press' => 1])))->toBeFalse()
        ->and($evaluator->progress($objectives, fakeSets(['back-squat' => 3, 'bench-press' => 1])))->toBe([3, 1])
        ->and($evaluator->isAccomplished($objectives, fakeSets(['back-squat' => 3, 'bench-press' => 2])))->toBeTrue();
});

it('accepte un exercice équivalent du même emplacement', function () {
    $objectives = [['exercise_id' => Exercise::firstWhere('slug', 'back-squat')->id, 'slot' => 'squat', 'sets' => 3]];

    expect(app(QuestEvaluator::class)->isAccomplished($objectives, fakeSets(['leg-press' => 3])))->toBeTrue()
        ->and(app(QuestEvaluator::class)->isAccomplished($objectives, fakeSets(['barbell-curl' => 5])))->toBeFalse()
        ->and(app(QuestEvaluator::class)->isAccomplished([], fakeSets(['leg-press' => 3])))->toBeFalse();
});
```

- [ ] **Step 2 : vérifier l'échec**

Run : `./vendor/bin/pest tests/Feature/Quests/PenaltyAndEvaluatorTest.php`
Attendu : FAIL.

- [ ] **Step 3 : implémentation**

`app/Game/PenaltyCircuit.php` :
```php
<?php

namespace App\Game;

use App\Models\Exercise;

final class PenaltyCircuit
{
    /** slug => [emplacement, [séries, reps] par niveau] */
    private const CIRCUIT = [
        'push-up' => ['horizontal_push', ['beginner' => [3, 10], 'intermediate' => [3, 20], 'advanced' => [4, 25]]],
        'bodyweight-squat' => ['squat', ['beginner' => [3, 15], 'intermediate' => [3, 25], 'advanced' => [4, 30]]],
        'crunch' => ['core', ['beginner' => [3, 15], 'intermediate' => [3, 25], 'advanced' => [4, 30]]],
    ];

    /** @return list<array{exercise_id: int, name: string, slot: string, sets: int, reps_min: int, reps_max: int, rest_seconds: int}> */
    public function for(?string $level): array
    {
        $level = in_array($level, ['beginner', 'intermediate', 'advanced'], true) ? $level : 'beginner';
        $exercises = Exercise::whereIn('slug', array_keys(self::CIRCUIT))->get()->keyBy('slug');

        $objectives = [];
        foreach (self::CIRCUIT as $slug => [$slot, $byLevel]) {
            [$sets, $reps] = $byLevel[$level];
            $objectives[] = [
                'exercise_id' => $exercises[$slug]->id, 'name' => $exercises[$slug]->name, 'slot' => $slot,
                'sets' => $sets, 'reps_min' => $reps, 'reps_max' => $reps, 'rest_seconds' => 60,
            ];
        }

        return $objectives;
    }
}
```

`app/Game/QuestEvaluator.php` :
```php
<?php

namespace App\Game;

use Illuminate\Support\Collection;

final class QuestEvaluator
{
    /** @return list<int> séries comptées pour chaque objectif */
    public function progress(array $objectives, Collection $sets): array
    {
        return array_map(fn (array $objective) => $sets->filter(
            fn ($set) => $set->exercise_id === $objective['exercise_id'] || in_array($objective['slot'], $set->exercise->slots ?? [], true)
        )->count(), $objectives);
    }

    public function isAccomplished(array $objectives, Collection $sets): bool
    {
        if ($objectives === []) {
            return false;
        }

        foreach ($this->progress($objectives, $sets) as $i => $done) {
            if ($done < $objectives[$i]['sets']) {
                return false;
            }
        }

        return true;
    }
}
```

- [ ] **Step 4 : vérifier** : `./vendor/bin/pest tests/Feature/Quests/PenaltyAndEvaluatorTest.php` → PASS.

- [ ] **Step 5 : commit**

```bash
git add -A
git commit -m "feat: circuit de pénalité et évaluation des objectifs de quête

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5 : série de jours

**Files :**
- Create : `app/Game/StreakCalculator.php`
- Test : `tests/Feature/Quests/StreakCalculatorTest.php`

**Interfaces :**
- Produces :
  - `StreakCalculator::compute(User $user, CarbonInterface $asOf): int` ;
  - `StreakCalculator::refresh(User $user, CarbonInterface $today): int` (enregistre `streak_days` et `best_streak`).

- [ ] **Step 1 : test qui échoue**

`tests/Feature/Quests/StreakCalculatorTest.php` :
```php
<?php

use App\Game\StreakCalculator;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->streaks = app(StreakCalculator::class);
});

function quest(User $user, string $date, string $type, string $status): void
{
    $user->quests()->create(['date' => $date, 'type' => $type, 'title' => $type, 'objectives' => [], 'status' => $status]);
}

it('compte les jours consécutifs accomplis, repos compris', function () {
    quest($this->user, '2026-10-05', 'training', 'done');
    quest($this->user, '2026-10-06', 'rest', 'done');
    quest($this->user, '2026-10-07', 'training', 'done');

    expect($this->streaks->compute($this->user, Carbon::parse('2026-10-07')))->toBe(3);
});

it('ne casse pas la série pour une quête du jour encore en cours', function () {
    quest($this->user, '2026-10-05', 'rest', 'done');
    quest($this->user, '2026-10-06', 'training', 'pending');

    expect($this->streaks->compute($this->user, Carbon::parse('2026-10-06')))->toBe(1);
});

it('sauve la série si la pénalité du lendemain est réussie ou encore en cours, la casse sinon', function () {
    quest($this->user, '2026-10-05', 'rest', 'done');
    quest($this->user, '2026-10-06', 'training', 'failed');
    quest($this->user, '2026-10-07', 'rest', 'done');
    quest($this->user, '2026-10-07', 'penalty', 'done');

    expect($this->streaks->compute($this->user, Carbon::parse('2026-10-07')))->toBe(2);

    $this->user->quests()->where('type', 'penalty')->update(['status' => 'pending']);
    expect($this->streaks->compute($this->user, Carbon::parse('2026-10-07')))->toBe(2);

    $this->user->quests()->where('type', 'penalty')->update(['status' => 'failed']);
    expect($this->streaks->compute($this->user, Carbon::parse('2026-10-07')))->toBe(0);
});

it('ignore les jours sans quête (pause) et les quêtes annulées', function () {
    quest($this->user, '2026-10-05', 'training', 'done');
    quest($this->user, '2026-10-08', 'training', 'cancelled');
    quest($this->user, '2026-10-09', 'rest', 'done');

    expect($this->streaks->compute($this->user, Carbon::parse('2026-10-09')))->toBe(2);
});

it('mémorise la série et la meilleure série', function () {
    quest($this->user, '2026-10-05', 'rest', 'done');
    quest($this->user, '2026-10-06', 'rest', 'done');
    $this->streaks->refresh($this->user, Carbon::parse('2026-10-06'));
    quest($this->user, '2026-10-07', 'training', 'failed');
    quest($this->user, '2026-10-08', 'penalty', 'failed');
    quest($this->user, '2026-10-08', 'rest', 'done');
    $this->streaks->refresh($this->user, Carbon::parse('2026-10-08'));

    expect($this->user->fresh()->streak_days)->toBe(0)->and($this->user->fresh()->best_streak)->toBe(2);
});
```

- [ ] **Step 2 : vérifier l'échec** : `./vendor/bin/pest tests/Feature/Quests/StreakCalculatorTest.php` → FAIL.

- [ ] **Step 3 : implémentation**

`app/Game/StreakCalculator.php` :
```php
<?php

namespace App\Game;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class StreakCalculator
{
    public function compute(User $user, CarbonInterface $asOf): int
    {
        $asOf = Carbon::parse($asOf->toDateString());
        $quests = $user->quests()->where('date', '<=', $asOf->toDateString())->get()
            ->groupBy(fn ($quest) => $quest->date->toDateString());

        if ($quests->isEmpty()) {
            return 0;
        }

        $first = Carbon::parse($quests->keys()->min());
        $streak = 0;

        for ($day = $asOf->copy(); $day->gte($first); $day = $day->copy()->subDay()) {
            $quests_of_day = $quests->get($day->toDateString(), collect());
            // Une pénalité ratée ce jour-là remet la série à 0 : ce jour ne compte pas.
            if ($quests_of_day->contains(fn ($q) => $q->type === 'penalty' && $q->status === 'failed')) {
                break;
            }
            $main = $quests_of_day->first(fn ($q) => in_array($q->type, ['training', 'rest'], true));

            if (! $main || in_array($main->status, ['pending', 'cancelled'], true)) {
                continue;
            }
            if ($main->status === 'done') {
                $streak++;
                continue;
            }

            $penalty = $quests->get($day->copy()->addDay()->toDateString(), collect())->firstWhere('type', 'penalty');
            if ($penalty && in_array($penalty->status, ['done', 'pending'], true)) {
                continue;
            }

            break;
        }

        return $streak;
    }

    public function refresh(User $user, CarbonInterface $today): int
    {
        $streak = $this->compute($user, $today);
        $user->forceFill(['streak_days' => $streak, 'best_streak' => max((int) $user->best_streak, $streak)])->save();

        return $streak;
    }
}
```

- [ ] **Step 4 : vérifier** → PASS.

- [ ] **Step 5 : commit**

```bash
git add -A
git commit -m "feat: calcul de la série de jours

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6 : planification des quêtes (`QuestScheduler::sync`)

**Files :**
- Create : `app/Game/QuestScheduler.php`
- Modify : `app/Support/SystemMessage.php` (`questFailed`, `penaltyFailed`, `pauseOn`, `pauseOff`)
- Test : `tests/Feature/Quests/QuestSchedulerTest.php`

**Interfaces :**
- Consumes : `PenaltyCircuit`, `StreakCalculator`, `Titles`, `User::isPaused`, `ProgramDay::objectives`
- Produces :
  - `QuestScheduler::sync(User $user, CarbonInterface $today): list<notification>` ;
  - `QuestScheduler::rewardFor(int $streak): int` ;
  - constantes `QUEST_XP = 200`, `PENALTY_XP = 50`, `MAX_CATCH_UP_DAYS = 60`.

- [ ] **Step 1 : test qui échoue**

`tests/Feature/Quests/QuestSchedulerTest.php` :
```php
<?php

use App\Game\QuestScheduler;
use App\Models\Quest;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->create();
    $this->scheduler = app(QuestScheduler::class);
});

/** Programme lundi / mercredi / vendredi, créé le lundi 5 octobre 2026. */
function programOn(User $user, string $createdAt = '2026-10-05', array $weekdays = [1, 3, 5])
{
    $program = app(ProgramBuilder::class)->build($user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => $weekdays, 'equipment' => 'gym']);
    $program->forceFill(['created_at' => Carbon::parse($createdAt.' 09:00')])->save();

    return $program;
}

function day(string $date): Carbon
{
    return Carbon::parse($date);
}

it('ne fait rien sans programme', function () {
    expect($this->scheduler->sync($this->user, day('2026-10-05')))->toBe([])
        ->and(Quest::count())->toBe(0);
});

it('assigne la séance du jour un jour d\'entraînement', function () {
    programOn($this->user);

    $this->scheduler->sync($this->user, day('2026-10-05'));

    $quest = Quest::sole();
    expect($quest->type)->toBe('training')->and($quest->title)->toBe('Full body A')
        ->and($quest->status)->toBe('pending')->and($quest->objectives)->toHaveCount(5)
        ->and($quest->objectives[0])->toHaveKeys(['exercise_id', 'name', 'slot', 'sets', 'reps_min', 'reps_max', 'rest_seconds']);
});

it('constate l\'échec de la veille, crée la pénalité et le repos du jour, une seule fois', function () {
    programOn($this->user);
    $this->scheduler->sync($this->user, day('2026-10-05'));

    $notes = $this->scheduler->sync($this->user, day('2026-10-06'));
    $again = $this->scheduler->sync($this->user, day('2026-10-06'));

    expect(collect($notes)->pluck('title')->all())->toBe(['QUÊTE ÉCHOUÉE'])
        ->and($again)->toBe([])
        ->and(Quest::where('date', '2026-10-05')->value('status'))->toBe('failed')
        ->and(Quest::where('date', '2026-10-06')->where('type', 'rest')->value('status'))->toBe('done')
        ->and(Quest::where('date', '2026-10-06')->where('type', 'penalty')->first()->objectives)->toHaveCount(3);
});

it('passe au jour suivant du programme après une quête accomplie, répète après un échec', function () {
    programOn($this->user);
    $this->scheduler->sync($this->user, day('2026-10-05'));
    Quest::where('date', '2026-10-05')->update(['status' => 'done']);

    $this->scheduler->sync($this->user, day('2026-10-07'));
    expect(Quest::where('date', '2026-10-07')->value('title'))->toBe('Full body B');

    $this->scheduler->sync($this->user, day('2026-10-09'));     // mercredi raté
    expect(Quest::where('date', '2026-10-09')->value('title'))->toBe('Full body B');
});

it('rattrape une absence de plusieurs jours dans l\'ordre, sans pénalité en cascade', function () {
    programOn($this->user);
    $this->scheduler->sync($this->user, day('2026-10-05'));

    $notes = $this->scheduler->sync($this->user, day('2026-10-09'));

    expect(collect($notes)->pluck('title')->all())->toBe(['QUÊTE ÉCHOUÉE', 'PÉNALITÉ ÉCHOUÉE', 'QUÊTE ÉCHOUÉE', 'PÉNALITÉ ÉCHOUÉE'])
        ->and(Quest::where('type', 'penalty')->count())->toBe(2)
        ->and(Quest::where('date', '2026-10-09')->where('type', 'training')->value('status'))->toBe('pending')
        ->and(Quest::where('date', '2026-10-09')->where('type', 'penalty')->exists())->toBeFalse()
        ->and($this->user->fresh()->streak_days)->toBe(0);
});

it('ne crée ni quête ni pénalité pendant une pause, et la lève automatiquement', function () {
    programOn($this->user);
    $this->scheduler->sync($this->user, day('2026-10-05'));
    $this->user->forceFill(['paused_at' => '2026-10-06', 'paused_until' => '2026-10-07'])->save();

    $notes = $this->scheduler->sync($this->user, day('2026-10-08'));

    expect(collect($notes)->pluck('title')->all())->toBe(['RETOUR DE TON EGO', 'QUÊTE ÉCHOUÉE'])
        ->and(Quest::whereIn('date', ['2026-10-06', '2026-10-07'])->count())->toBe(0)
        ->and(Quest::where('date', '2026-10-08')->value('type'))->toBe('rest')
        ->and($this->user->fresh()->paused_at)->toBeNull();
});

it('garde les objectifs des quêtes existantes quand le programme est régénéré', function () {
    programOn($this->user);
    $this->scheduler->sync($this->user, day('2026-10-05'));
    $before = Quest::sole()->objectives;

    app(ProgramBuilder::class)->build($this->user, ['level' => 'advanced', 'goal' => 'strength', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    $this->scheduler->sync($this->user, day('2026-10-07'));

    expect(Quest::where('date', '2026-10-05')->first()->objectives)->toBe($before)
        ->and(Quest::where('date', '2026-10-07')->first()->objectives)->toHaveCount(7);
});

it('calcule la récompense avec le bonus de série plafonné', function () {
    expect($this->scheduler->rewardFor(0))->toBe(200)
        ->and($this->scheduler->rewardFor(3))->toBe(260)
        ->and($this->scheduler->rewardFor(12))->toBe(300);
});
```

> Notes de lecture :
> - le scénario de pause commence lundi, avec une quête lundi non accomplie : l'échec de lundi est constaté le 8 (« QUÊTE ÉCHOUÉE »), mais le 6 étant en pause, aucune pénalité n'est créée ;
> - pour l'absence de lundi à vendredi :
>   - lundi est raté, ce qui crée une pénalité le mardi ;
>   - mercredi est raté (la quête de mardi est un repos), ce qui crée une pénalité le jeudi ;
>   - les pénalités du mardi et du jeudi sont ratées ;
>   - la quête de vendredi est en cours.

- [ ] **Step 2 : vérifier l'échec** : `./vendor/bin/pest tests/Feature/Quests/QuestSchedulerTest.php` → FAIL.

- [ ] **Step 3 : messages système**

Dans `app/Support/SystemMessage.php`, ajouter `use App\Models\Quest;` et `use Carbon\CarbonInterface;`, puis :
```php
    public static function questDone(Quest $quest, int $xp): array
    {
        return self::make('quest_done', 'QUÊTE ACCOMPLIE', [$quest->title, '+'.$xp.' XP'], highlight: 'QUÊTE');
    }

    public static function questFailed(Quest $quest): array
    {
        return self::make('quest_failed', 'QUÊTE ÉCHOUÉE', [$quest->title.' — '.$quest->date->translatedFormat('l j F'), 'TON EGO t\'impose une quête de pénalité le lendemain.'], 'alert');
    }

    public static function penaltyDone(): array
    {
        return self::make('penalty_done', 'PÉNALITÉ ACCOMPLIE', ['Ta série est sauvée.', '+50 XP']);
    }

    public static function penaltyFailed(): array
    {
        return self::make('penalty_failed', 'PÉNALITÉ ÉCHOUÉE', ['Ta série retombe à 0.', 'TON EGO attend mieux de toi.'], 'alert');
    }

    public static function pauseOn(?CarbonInterface $until): array
    {
        return self::make('pause_on', 'QUÊTES SUSPENDUES', [$until ? 'Jusqu\'au '.$until->translatedFormat('j F').' inclus.' : 'Jusqu\'à ton retour.', 'Ta série est gelée.']);
    }

    public static function pauseOff(): array
    {
        return self::make('pause_off', 'RETOUR DE TON EGO', ['Tes quêtes reprennent.']);
    }
```

- [ ] **Step 4 : `QuestScheduler`**

`app/Game/QuestScheduler.php` :
```php
<?php

namespace App\Game;

use App\Models\Program;
use App\Models\ProgramDay;
use App\Models\User;
use App\Support\SystemMessage;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class QuestScheduler
{
    public const QUEST_XP = 200;
    public const PENALTY_XP = 50;
    public const MAX_CATCH_UP_DAYS = 60;

    public function __construct(
        private Titles $titles,
        private StreakCalculator $streaks,
        private PenaltyCircuit $penalties,
    ) {}

    public function rewardFor(int $streak): int
    {
        return (int) round(self::QUEST_XP * (1 + min($streak, 5) * 0.1));
    }

    /** Crée les quêtes dues, constate les échecs, gère la pause. Idempotent. @return list<array> */
    public function sync(User $user, CarbonInterface $today): array
    {
        $today = Carbon::parse($today->toDateString());

        return DB::transaction(function () use ($user, $today) {
            User::whereKey($user->id)->lockForUpdate()->first();
            $user->refresh();
            $notes = [];

            // Période de pause mémorisée AVANT une éventuelle levée automatique : les jours passés
            // en pause doivent rester sans quête ni pénalité pendant le rattrapage.
            [$pausedFrom, $pausedUntil] = [$user->paused_at, $user->paused_until];
            $paused = fn (Carbon $d) => $pausedFrom !== null && $d->gte($pausedFrom) && ($pausedUntil === null || $d->lte($pausedUntil));

            if ($pausedFrom && $pausedUntil && $today->gt($pausedUntil)) {
                $user->forceFill(['paused_at' => null, 'paused_until' => null])->save();
                $notes[] = SystemMessage::pauseOff();
            }

            $program = $user->activeProgram()->with('days')->first();
            if ($program) {
                for ($day = $this->firstDayToProcess($user, $program, $today); $day->lte($today); $day = $day->copy()->addDay()) {
                    if (! $paused($day)) {
                        $this->ensureQuest($user, $program, $day);
                    }
                    if ($day->lt($today)) {
                        array_push($notes, ...$this->expire($user, $day, $paused));
                    }
                }
            }

            $this->streaks->refresh($user, $today);
            foreach ($this->titles->check($user) as $key) {
                $notes[] = SystemMessage::title($key);
            }

            return $notes;
        });
    }

    private function firstDayToProcess(User $user, Program $program, Carbon $today): Carbon
    {
        $last = $user->quests()->max('date');
        $start = $last ? Carbon::parse($last)->addDay() : Carbon::parse($program->created_at->toDateString());

        $pending = $user->quests()->where('status', 'pending')->where('date', '<', $today->toDateString())->min('date');
        if ($pending && Carbon::parse($pending)->lt($start)) {
            $start = Carbon::parse($pending);
        }

        $floor = $today->copy()->subDays(self::MAX_CATCH_UP_DAYS);

        return $start->lt($floor) ? $floor : $start;
    }

    private function ensureQuest(User $user, Program $program, Carbon $day): void
    {
        if ($user->quests()->where('date', $day->toDateString())->whereIn('type', ['training', 'rest'])->exists()) {
            return;
        }

        if (in_array($day->isoWeekday(), $user->training_weekdays ?? [], true)) {
            $programDay = $this->nextProgramDay($user, $program);
            $user->quests()->create([
                'date' => $day->toDateString(), 'type' => 'training', 'program_day_id' => $programDay->id,
                'title' => $programDay->name, 'objectives' => $programDay->objectives(), 'status' => 'pending',
            ]);

            return;
        }

        $user->quests()->create([
            'date' => $day->toDateString(), 'type' => 'rest', 'title' => 'Repos', 'objectives' => [],
            'status' => 'done', 'completed_at' => $day->copy()->endOfDay(),
        ]);
    }

    private function nextProgramDay(User $user, Program $program): ProgramDay
    {
        $days = $program->days;
        $lastDone = $user->quests()->where('type', 'training')->where('status', 'done')
            ->whereIn('program_day_id', $days->pluck('id'))->orderByDesc('date')->first();

        if (! $lastDone) {
            return $days->first();
        }

        $index = $days->search(fn (ProgramDay $d) => $d->id === $lastDone->program_day_id);

        return $days[($index + 1) % $days->count()];
    }

    /** @return list<array> */
    private function expire(User $user, Carbon $day, \Closure $paused): array
    {
        $notes = [];

        foreach ($user->quests()->where('date', $day->toDateString())->where('status', 'pending')->orderBy('id')->get() as $quest) {
            $quest->update(['status' => 'failed']);

            if ($quest->type === 'training') {
                $notes[] = SystemMessage::questFailed($quest);
                $next = $day->copy()->addDay();
                if (! $paused($next)) {
                    $user->quests()->firstOrCreate(
                        ['date' => $next->toDateString(), 'type' => 'penalty'],
                        ['title' => 'Pénalité', 'objectives' => $this->penalties->for($user->level), 'status' => 'pending'],
                    );
                }
            } elseif ($quest->type === 'penalty') {
                $notes[] = SystemMessage::penaltyFailed();
            }
        }

        return $notes;
    }
}
```

> Ordre de traitement d'un jour passé : la quête du jour est créée, puis les quêtes encore `pending` de ce jour sont constatées échouées ; la pénalité du lendemain est créée à ce moment-là. Une pénalité ratée est constatée le lendemain, sans en créer d'autre.

- [ ] **Step 5 : vérifier** : `./vendor/bin/pest tests/Feature/Quests/QuestSchedulerTest.php` → PASS.

- [ ] **Step 6 : commit**

```bash
git add -A
git commit -m "feat: TON EGO assigne les quêtes, constate les échecs et impose les pénalités

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7 : accomplissement des quêtes après une séance

**Files :**
- Modify : `app/Game/QuestScheduler.php` (`onWorkout`, constructeur avec `XpLedger` et `QuestEvaluator`), `app/Game/GameEvents.php` (appel de `onWorkout` sous le verrou, notifications avant celles de niveau)
- Test : `tests/Feature/Quests/QuestCompletionTest.php`

**Interfaces :**
- Consumes : `QuestEvaluator::isAccomplished`, `XpLedger::award`, `StreakCalculator`
- Produces : `QuestScheduler::onWorkout(User $user, Workout $workout): list<notification>`, appelé par `GameEvents::play()` **sans** transaction propre (le verrou est déjà pris). Ordre des notifications de `afterWorkout` : quêtes, `level_up`, `rank_up`, titres.

- [ ] **Step 1 : test qui échoue**

`tests/Feature/Quests/QuestCompletionTest.php` :
```php
<?php

use App\Game\GameEvents;
use App\Game\QuestScheduler;
use App\Models\Quest;
use App\Models\User;
use App\Models\XpEvent;
use App\Services\ProgramBuilder;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->create();
    $program = app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    $program->forceFill(['created_at' => Carbon::parse('2026-10-05 08:00')])->save();
    $this->travelTo(Carbon::parse('2026-10-05 18:00'));
    app(QuestScheduler::class)->sync($this->user, now());
});

/** Envoie une séance qui remplit les objectifs donnés, terminée à $at (heure de Paris), puis joue les événements de jeu. */
function completeObjectives(User $user, array $objectives, string $at): array
{
    $iso = Carbon::parse($at, 'Europe/Paris')->toIso8601String();
    $sets = [];
    foreach ($objectives as $objective) {
        foreach (range(1, $objective['sets']) as $i) {
            $sets[] = ['exercise_id' => $objective['exercise_id'], 'weight_kg' => 20, 'reps' => $objective['reps_min'], 'done_at' => $iso];
        }
    }
    $sync = app(WorkoutSync::class)->sync($user, ['client_id' => (string) Str::uuid(), 'started_at' => $iso, 'finished_at' => $iso, 'sets' => $sets]);

    return app(GameEvents::class)->afterWorkout($user->fresh(), $sync['workout'], $sync['newRecords']);
}

it('accomplit la quête du jour et attribue son XP', function () {
    $quest = Quest::where('date', '2026-10-05')->sole();

    $result = completeObjectives($this->user, $quest->objectives, '2026-10-05 18:30');

    expect($quest->fresh()->status)->toBe('done')
        ->and($quest->fresh()->xp_awarded)->toBe(200)
        ->and(XpEvent::where('source', 'quest')->value('amount'))->toBe(200)
        ->and($result['notifications'][0]['title'])->toBe('QUÊTE ACCOMPLIE')
        ->and($this->user->fresh()->streak_days)->toBe(1);
});

it('ne valide pas une séance incomplète', function () {
    $quest = Quest::where('date', '2026-10-05')->sole();
    $partial = $quest->objectives;
    $partial[0]['sets'] = 1;

    completeObjectives($this->user, $partial, '2026-10-05 18:30');

    expect($quest->fresh()->status)->toBe('pending');
});

it('répare une quête ratée par une séance d\'hier envoyée aujourd\'hui et annule la pénalité', function () {
    $monday = Quest::where('date', '2026-10-05')->sole();
    $this->travelTo(Carbon::parse('2026-10-06 09:00'));
    app(QuestScheduler::class)->sync($this->user, now());
    expect($monday->fresh()->status)->toBe('failed');

    completeObjectives($this->user, $monday->objectives, '2026-10-05 19:00');

    expect($monday->fresh()->status)->toBe('done')
        ->and(Quest::where('date', '2026-10-06')->where('type', 'penalty')->value('status'))->toBe('cancelled')
        ->and($this->user->fresh()->streak_days)->toBe(2);
});

it('sauve la série avec la pénalité, +50 XP et le titre Revenant', function () {
    $this->travelTo(Carbon::parse('2026-10-06 09:00'));
    app(QuestScheduler::class)->sync($this->user, now());
    $penalty = Quest::where('date', '2026-10-06')->where('type', 'penalty')->sole();

    $result = completeObjectives($this->user, $penalty->objectives, '2026-10-06 12:00');

    $titles = collect($result['notifications'])->pluck('title');
    expect($penalty->fresh()->status)->toBe('done')
        ->and(XpEvent::where('source', 'penalty')->value('amount'))->toBe(50)
        ->and($titles)->toContain('PÉNALITÉ ACCOMPLIE')
        ->and(collect($result['notifications'])->pluck('lines.0'))->toContain('Revenant')
        ->and($this->user->fresh()->streak_days)->toBe(1);
});

it('applique le bonus de série à la récompense', function () {
    foreach (['2026-10-02', '2026-10-03', '2026-10-04'] as $date) {
        $this->user->quests()->create(['date' => $date, 'type' => 'rest', 'title' => 'Repos', 'objectives' => [], 'status' => 'done']);
    }
    $quest = Quest::where('date', '2026-10-05')->sole();

    completeObjectives($this->user, $quest->objectives, '2026-10-05 18:30');

    expect($quest->fresh()->xp_awarded)->toBe(260);
});
```

- [ ] **Step 2 : vérifier l'échec** : `./vendor/bin/pest tests/Feature/Quests/QuestCompletionTest.php` → FAIL (quête restée `pending`).

- [ ] **Step 3 : `onWorkout`**

Dans `app/Game/QuestScheduler.php` :
- ajouter `use App\Models\Workout;` et `use App\Models\WorkoutSet;` ;
- remplacer le constructeur par :
```php
    public function __construct(
        private Titles $titles,
        private StreakCalculator $streaks,
        private PenaltyCircuit $penalties,
        private XpLedger $ledger,
        private QuestEvaluator $evaluator,
    ) {}
```
- ajouter la méthode :
```php
    /** À appeler sous le verrou du joueur (GameEvents). @return list<array> */
    public function onWorkout(User $user, Workout $workout): array
    {
        $date = $workout->finished_at->toDateString();
        $quests = $user->quests()->where('date', $date)->whereIn('type', ['training', 'penalty'])
            ->whereIn('status', ['pending', 'failed'])->orderBy('id')->get();

        if ($quests->isEmpty()) {
            return [];
        }

        $sets = WorkoutSet::query()->with('exercise')
            ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
            ->where('workouts.user_id', $user->id)
            ->whereDate('workouts.finished_at', $date)
            ->get(['workout_sets.*']);

        $notes = [];
        foreach ($quests as $quest) {
            if (! $this->evaluator->isAccomplished($quest->objectives, $sets)) {
                continue;
            }

            if ($quest->type === 'training') {
                $xp = $this->rewardFor($this->streaks->compute($user, Carbon::parse($date)->subDay()));
                $this->ledger->award($user, 'quest', $quest->id, $xp, at: $workout->finished_at);
                if ($quest->status === 'failed') {
                    $user->quests()->where('type', 'penalty')->where('status', 'pending')
                        ->where('date', Carbon::parse($date)->addDay()->toDateString())->update(['status' => 'cancelled']);
                }
                $quest->update(['status' => 'done', 'completed_at' => now(), 'xp_awarded' => $xp]);
                $notes[] = SystemMessage::questDone($quest, $xp);
            } else {
                $this->ledger->award($user, 'penalty', $quest->id, self::PENALTY_XP, at: $workout->finished_at);
                $quest->update(['status' => 'done', 'completed_at' => now(), 'xp_awarded' => self::PENALTY_XP]);
                $notes[] = SystemMessage::penaltyDone();
            }
        }

        $this->streaks->refresh($user, now());

        return $notes;
    }
```

- [ ] **Step 4 : `GameEvents`**

Dans `app/Game/GameEvents.php` :
- ajouter `private QuestScheduler $quests` au constructeur (en dernier paramètre) ;
- remplacer le début de `play()` (jusqu'à `$notifications = [];` inclus) par :
```php
        $startXp = (int) $user->xp_total;
        $before = Leveling::progress($startXp);
        $this->awardWorkout($user, $workout, count($newRecords));
        $notifications = $this->quests->onWorkout($user, $workout);
        $xp = (int) $user->xp_total - $startXp;
        $after = Leveling::progress((int) $user->xp_total);
```

> `$xp` vaut alors tout le gain de la séance (séance, records et quêtes), car `XpLedger::award` met à jour `$user->xp_total` en mémoire. C'est cette valeur qui s'affiche dans « SÉANCE TERMINÉE » et `xp_gained`.

- [ ] **Step 5 : vérifier** : `./vendor/bin/pest` → tout PASS. Les tests existants de `GameEventsTest` restent vrais, puisqu'ils n'ont pas de quêtes.

- [ ] **Step 6 : commit**

```bash
git add -A
git commit -m "feat: quêtes et pénalités accomplies par les séances, réparation des envois tardifs

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 8 : TON EGO passe à chaque page

**Files :**
- Create : `app/Http/Middleware/SyncQuests.php`
- Modify : `routes/web.php`
- Test : `tests/Feature/Quests/SyncQuestsMiddlewareTest.php`

**Interfaces :**
- Consumes : `QuestScheduler::sync`, `SystemNotifier::push`
- Produces : un middleware appliqué au groupe `auth`. Uniquement pour les requêtes GET non JSON : il synchronise les quêtes et range les notifications.

- [ ] **Step 1 : test qui échoue**

`tests/Feature/Quests/SyncQuestsMiddlewareTest.php` :
```php
<?php

use App\Models\Quest;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->create();
    app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'fitness', 'weekdays' => [1, 3, 5], 'equipment' => 'bodyweight'])
        ->forceFill(['created_at' => Carbon::parse('2026-10-05 08:00')])->save();
    $this->actingAs($this->user);
});

it('assigne la quête du jour à l\'ouverture d\'une page, une seule fois', function () {
    $this->travelTo(Carbon::parse('2026-10-05 10:00'));

    $this->get('/')->assertOk();
    $this->get('/history')->assertOk();

    expect(Quest::count())->toBe(1);
});

it('affiche l\'échec de la veille sur la page suivante', function () {
    $this->travelTo(Carbon::parse('2026-10-05 10:00'));
    $this->get('/');
    $this->travelTo(Carbon::parse('2026-10-06 08:00'));

    $this->get('/')->assertSee('QUÊTE ÉCHOUÉE');
    $this->get('/')->assertDontSee('QUÊTE ÉCHOUÉE');
});

it('ne touche pas aux requêtes JSON', function () {
    $this->travelTo(Carbon::parse('2026-10-05 10:00'));

    $this->getJson('/')->assertOk();

    expect(Quest::count())->toBe(0);
});
```

- [ ] **Step 2 : vérifier l'échec** → FAIL (aucune quête créée).

- [ ] **Step 3 : middleware**

`app/Http/Middleware/SyncQuests.php` :
```php
<?php

namespace App\Http\Middleware;

use App\Game\QuestScheduler;
use App\Support\SystemNotifier;
use Closure;
use Illuminate\Http\Request;

class SyncQuests
{
    public function __construct(private QuestScheduler $quests, private SystemNotifier $notifier) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $request->isMethod('GET') && ! $request->expectsJson()) {
            foreach ($this->quests->sync($user, now()) as $notification) {
                $this->notifier->push($user, $notification);
            }
        }

        return $next($request);
    }
}
```

Dans `routes/web.php`, remplacer `Route::middleware('auth')->group(function () {` par `Route::middleware(['auth', SyncQuests::class])->group(function () {`, et ajouter `use App\Http\Middleware\SyncQuests;`.

- [ ] **Step 4 : vérifier** : `./vendor/bin/pest` → tout PASS.

- [ ] **Step 5 : commit**

```bash
git add -A
git commit -m "feat: TON EGO met les quêtes à jour à chaque page

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 9 : Régularité et titres des quêtes

**Files :**
- Modify : `app/Game/PlayerStats.php`, `app/Game/Titles.php`
- Test : `tests/Feature/Quests/RegularityAndTitlesTest.php`

**Interfaces :**
- Produces : `PlayerStats::for()['regularite']` calculée, et les titres `steady`, `unstoppable` et `revenant` débloquables.

- [ ] **Step 1 : test qui échoue**

`tests/Feature/Quests/RegularityAndTitlesTest.php` :
```php
<?php

use App\Game\PlayerStats;
use App\Game\Titles;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->travelTo(Carbon::parse('2026-10-20 12:00'));
});

function q(User $user, string $date, string $type, string $status): void
{
    $user->quests()->create(['date' => $date, 'type' => $type, 'title' => $type, 'objectives' => [], 'status' => $status]);
}

it('calcule la Régularité sur 28 jours, hors annulées et hors aujourd\'hui', function () {
    q($this->user, '2026-10-15', 'training', 'done');
    q($this->user, '2026-10-16', 'rest', 'done');
    q($this->user, '2026-10-17', 'training', 'done');
    q($this->user, '2026-10-18', 'training', 'failed');
    q($this->user, '2026-10-18', 'penalty', 'done');       // les pénalités ne comptent pas
    q($this->user, '2026-10-19', 'training', 'cancelled');
    q($this->user, '2026-10-20', 'training', 'pending');
    q($this->user, '2026-09-01', 'training', 'failed');    // hors fenêtre

    expect(app(PlayerStats::class)->for($this->user)['regularite'])->toBe(75);
});

it('laisse la Régularité vide sans quête échue', function () {
    expect(app(PlayerStats::class)->for($this->user)['regularite'])->toBeNull();
});

it('débloque Assidu, Inarrêtable et Revenant', function () {
    $this->user->forceFill(['best_streak' => 7])->save();
    expect(app(Titles::class)->check($this->user))->toContain('steady')->not->toContain('unstoppable');

    $this->user->forceFill(['best_streak' => 30])->save();
    q($this->user, '2026-10-18', 'penalty', 'done');
    expect(app(Titles::class)->check($this->user))->toBe(['unstoppable', 'revenant']);
});
```

- [ ] **Step 2 : vérifier l'échec** → FAIL.

- [ ] **Step 3 : implémentation**

Dans `app/Game/PlayerStats.php`, remplacer `'regularite' => null,` par `'regularite' => $this->regularity($user),` et ajouter :
```php
    private function regularity(User $user): ?int
    {
        $base = $user->quests()->whereIn('type', ['training', 'rest'])->whereIn('status', ['done', 'failed'])
            ->where('date', '>=', now()->subDays(self::WINDOW_DAYS)->toDateString())
            ->where('date', '<', now()->toDateString());

        $total = (clone $base)->count();

        return $total === 0 ? null : (int) round((clone $base)->where('status', 'done')->count() / $total * 100);
    }
```

Dans `app/Game/Titles.php`, méthode `earned()`, ajouter au tableau `$conditions` :
```php
            'steady' => (int) $user->best_streak >= 7,
            'unstoppable' => (int) $user->best_streak >= 30,
            'revenant' => fn () => $user->quests()->where('type', 'penalty')->where('status', 'done')->exists(),
```

- [ ] **Step 4 : vérifier** : `./vendor/bin/pest` → tout PASS.

- [ ] **Step 5 : commit**

```bash
git add -A
git commit -m "feat: stat Régularité et titres Assidu, Inarrêtable, Revenant

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 10 : mode pause

**Files :**
- Modify : `app/Livewire/Profile.php` (`pauseUntil`, `pause()`, `resume()`), `resources/views/livewire/profile.blade.php`
- Test : `tests/Feature/Quests/PauseTest.php`

**Interfaces :**
- Produces :
  - `Profile::pause()` : met `paused_at` à aujourd'hui et `paused_until` à `pauseUntil` (ou `null`), annule les quêtes `pending` à partir d'aujourd'hui, range `pauseOn`, puis redirige vers `profile` ;
  - `Profile::resume()` : vide la pause, supprime les quêtes `training`/`rest` `cancelled` du jour (pour qu'elles soient recréées), range `pauseOff`, puis redirige.

- [ ] **Step 1 : test qui échoue**

`tests/Feature/Quests/PauseTest.php` :
```php
<?php

use App\Livewire\Profile;
use App\Models\Quest;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-05 10:00'));
    $this->user = User::factory()->create();
    app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'fitness', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    $this->actingAs($this->user);
    $this->get('/');
});

it('suspend les quêtes et annule celle du jour', function () {
    Livewire::test(Profile::class)->set('pauseUntil', '2026-10-12')->call('pause')->assertRedirect(route('profile'));

    $user = $this->user->fresh();
    expect($user->paused_at->toDateString())->toBe('2026-10-05')
        ->and($user->paused_until->toDateString())->toBe('2026-10-12')
        ->and(Quest::where('date', '2026-10-05')->value('status'))->toBe('cancelled')
        ->and(SystemNotification::where('type', 'pause_on')->exists())->toBeTrue();

    $this->get('/profile')->assertSee('QUÊTES SUSPENDUES');
});

it('refuse une date de fin passée', function () {
    Livewire::test(Profile::class)->set('pauseUntil', '2026-10-01')->call('pause')->assertHasErrors(['pauseUntil']);
});

it('reprend les quêtes et réassigne celle du jour', function () {
    Livewire::test(Profile::class)->call('pause');
    Livewire::test(Profile::class)->call('resume')->assertRedirect(route('profile'));

    expect($this->user->fresh()->paused_at)->toBeNull();

    $this->get('/');
    expect(Quest::where('date', '2026-10-05')->where('type', 'training')->value('status'))->toBe('pending');
});
```

- [ ] **Step 2 : vérifier l'échec** → FAIL.

- [ ] **Step 3 : composant**

Dans `app/Livewire/Profile.php`, ajouter la propriété `public ?string $pauseUntil = null;` et les méthodes :
```php
    public function pause()
    {
        $notifier = app(\App\Support\SystemNotifier::class);
        $this->validate(
            ['pauseUntil' => ['nullable', 'date', 'after_or_equal:today']],
            ['pauseUntil.date' => 'Date invalide.', 'pauseUntil.after_or_equal' => 'La date de fin doit être aujourd\'hui ou plus tard.'],
        );

        $user = auth()->user();
        $until = $this->pauseUntil ? \Illuminate\Support\Carbon::parse($this->pauseUntil) : null;
        $user->forceFill(['paused_at' => today(), 'paused_until' => $until])->save();
        $user->quests()->where('status', 'pending')->where('date', '>=', today()->toDateString())->update(['status' => 'cancelled']);
        $notifier->push($user, \App\Support\SystemMessage::pauseOn($until));

        return $this->redirectRoute('profile');
    }

    public function resume()
    {
        $notifier = app(\App\Support\SystemNotifier::class);
        $user = auth()->user();
        $user->forceFill(['paused_at' => null, 'paused_until' => null])->save();
        $user->quests()->where('date', today()->toDateString())->whereIn('type', ['training', 'rest'])->where('status', 'cancelled')->delete();
        $notifier->push($user, \App\Support\SystemMessage::pauseOff());

        return $this->redirectRoute('profile');
    }
```

- [ ] **Step 4 : vue**

Dans `resources/views/livewire/profile.blade.php`, juste **avant** la fenêtre `TITRES`, ajouter :
```blade
    <x-sys.window title="MODE PAUSE" :index="3">
        @if ($user->isPaused())
            <p class="text-sm text-mist">
                QUÊTES SUSPENDUES depuis le {{ $user->paused_at->translatedFormat('j F') }}
                {{ $user->paused_until ? 'jusqu\'au '.$user->paused_until->translatedFormat('j F').' inclus' : 'jusqu\'à ton retour' }}.
                Ta série ({{ $user->streak_days }} j) est gelée.
            </p>
            <x-sys.button class="mt-3" wire:click="resume">Reprendre les quêtes</x-sys.button>
        @else
            <p class="mb-3 text-sm text-mist">Blessure, vacances ? Suspends tes quêtes : aucune pénalité, ta série est gelée.</p>
            <x-sys.field name="pauseUntil" label="Jusqu'au (facultatif)" type="date" wire:model="pauseUntil" />
            <x-sys.button variant="ghost" class="mt-3" wire:click="pause">Suspendre les quêtes</x-sys.button>
        @endif
    </x-sys.window>
```

- [ ] **Step 5 : vérifier** : `npm run build && ./vendor/bin/pest` → tout PASS.

- [ ] **Step 6 : commit**

```bash
git add -A
git commit -m "feat: mode pause des quêtes

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 11 : quête du jour sur l'accueil et séance de quête

**Files :**
- Modify : `app/Livewire/Home.php`, `resources/views/livewire/home.blade.php`, `app/Http/Controllers/WorkoutController.php` (`quest`), `app/Services/WorkoutBootData.php` (`forQuest`), `routes/web.php`, `resources/js/workout/store.js` (`target`), `resources/js/workout/component.js` (plan), `resources/views/workout/run.blade.php` (objectif)
- Test : `tests/Feature/Quests/QuestUiTest.php`, `resources/js/workout/store.test.js`

**Interfaces :**
- Consumes : `QuestEvaluator::progress`, `QuestScheduler::rewardFor`
- Produces :
  - route `workout.quest` (`GET /workout/quest/{quest}`) ;
  - `WorkoutBootData::forQuest(User $user, Quest $quest): array`, qui ajoute `plan` (`{id, name, isBodyweight, suggestedKg, sets, repsMin, repsMax, restSeconds}`) et `questTitle` ;
  - `addExercise(state, ex, { …, target })`, où l'exercice garde `target = {sets, repsMin, repsMax}` ou `null`.

- [ ] **Step 1 : tests qui échouent**

`tests/Feature/Quests/QuestUiTest.php` :
```php
<?php

use App\Models\Quest;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-05 10:00'));          // lundi
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function withProgram(User $user): void
{
    app(ProgramBuilder::class)->build($user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
}

it('invite à forger un programme quand il n\'y en a pas', function () {
    $this->get('/')->assertSee('AUCUN PROGRAMME')->assertSee(route('program'));
});

it('affiche la quête d\'entraînement du jour avec ses objectifs et sa récompense', function () {
    withProgram($this->user);

    $quest = null;
    $response = $this->get('/');
    $quest = Quest::sole();

    $response->assertSee('QUÊTE DU JOUR')
        ->assertSee("TON EGO t'assigne", false)
        ->assertSee('Full body A')
        ->assertSee($quest->objectives[0]['name'])
        ->assertSee('0 / '.$quest->objectives[0]['sets'], false)
        ->assertSee('+200 XP')
        ->assertSee(route('workout.quest', $quest));
});

it('affiche un jour de repos et la pénalité', function () {
    withProgram($this->user);
    $this->get('/');
    $this->travelTo(Carbon::parse('2026-10-06 10:00'));          // mardi : repos + pénalité

    $this->get('/')->assertSee('Repos')->assertSee('PÉNALITÉ')->assertSee('Pompes');
});

it('ouvre la séance de quête préremplie, réservée à son propriétaire et au jour même', function () {
    withProgram($this->user);
    $this->get('/');
    $quest = Quest::sole();

    $this->get(route('workout.quest', $quest))->assertOk()
        ->assertSee('"plan":', false)
        ->assertSee($quest->objectives[0]['name']);

    $other = User::factory()->create();
    $this->actingAs($other)->get(route('workout.quest', $quest))->assertForbidden();

    $quest->update(['status' => 'done']);
    $this->actingAs($this->user)->get(route('workout.quest', $quest))->assertRedirect(route('home'));
});
```

Ajouter à `resources/js/workout/store.test.js`, dans `describe('séance', …)` :
```js
    it('garde l\'objectif de la quête et commence au bas de la plage de répétitions', () => {
        let s = addExercise(newWorkout('id', 't'), bench, { restSeconds: 120, target: { sets: 3, repsMin: 6, repsMax: 10 } });
        expect(s.exercises[0].target).toEqual({ sets: 3, repsMin: 6, repsMax: 10 });
        expect(s.exercises[0].draft.reps).toBe(6);

        s = addExercise(s, bench, { lastTime: [{ weightKg: 60, reps: 8 }], restSeconds: 120, target: { sets: 3, repsMin: 6, repsMax: 10 } });
        expect(s.exercises[1].draft.reps).toBe(8);

        s = addExercise(s, pullUp, { restSeconds: 90 });
        expect(s.exercises[2].target).toBeNull();
    });
```

- [ ] **Step 2 : vérifier l'échec** : `./vendor/bin/pest tests/Feature/Quests/QuestUiTest.php && npm test` → FAIL.

- [ ] **Step 3 : `store.js`**

Remplacer `addExercise` par :
```js
export function addExercise(state, exercise, { lastTime = null, suggestedKg = null, restSeconds, target = null }) {
    const first = lastTime?.[0];
    state.exercises.push({
        exerciseId: exercise.id,
        name: exercise.name,
        isBodyweight: exercise.isBodyweight,
        restSeconds,
        lastTime,
        target,
        draft: { weightKg: first?.weightKg ?? suggestedKg ?? 0, reps: first?.reps ?? target?.repsMin ?? 10 },
        sets: [],
    });
    state.currentIndex = state.exercises.length - 1;
    return state;
}
```

- [ ] **Step 4 : `component.js`**

Dans `init()`, remplacer les deux lignes
```js
            this.state = this.store.loadActive() ?? newWorkout(uuid(), new Date().toISOString());
            this.picking = this.state.exercises.length === 0;
```
par :
```js
            const stored = this.store.loadActive();
            this.state = stored ?? newWorkout(uuid(), new Date().toISOString());
            // Séance de quête : exercices, séries et repos du programme, sauf si une séance est déjà en cours.
            if (!stored && this.boot.plan?.length) {
                for (const p of this.boot.plan) {
                    addExercise(this.state, p, {
                        lastTime: this.boot.lastTime[p.id] ?? null,
                        suggestedKg: p.suggestedKg,
                        restSeconds: p.restSeconds,
                        target: { sets: p.sets, repsMin: p.repsMin, repsMax: p.repsMax },
                    });
                }
                goTo(this.state, 0);
            }
            this.picking = this.state.exercises.length === 0;
```

- [ ] **Step 5 : `run.blade.php`**

Dans la fenêtre de l'exercice courant, juste après le paragraphe « Dernière fois · … », ajouter :
```blade
                            <p class="sys-num mt-1 text-xs uppercase tracking-wider text-ice" x-show="current.target"
                               x-text="current.target ? 'Objectif · ' + current.target.sets + ' × ' + current.target.repsMin + '–' + current.target.repsMax + ' · série ' + Math.min(current.sets.length + 1, current.target.sets) + '/' + current.target.sets : ''"></p>
```

- [ ] **Step 6 : contrôleur, données de démarrage et route**

Dans `app/Services/WorkoutBootData.php`, ajouter :
```php
    public function forQuest(User $user, \App\Models\Quest $quest): array
    {
        $boot = $this->for($user);
        $library = collect($boot['exercises'])->keyBy('id');

        $boot['questTitle'] = $quest->title;
        $boot['plan'] = array_map(fn (array $o) => [
            'id' => $o['exercise_id'],
            'name' => $o['name'],
            'isBodyweight' => $library[$o['exercise_id']]['isBodyweight'] ?? false,
            'suggestedKg' => $library[$o['exercise_id']]['suggestedKg'] ?? null,
            'sets' => $o['sets'],
            'repsMin' => $o['reps_min'],
            'repsMax' => $o['reps_max'],
            'restSeconds' => $o['rest_seconds'],
        ], $quest->objectives);

        return $boot;
    }
```

Dans `app/Http/Controllers/WorkoutController.php`, ajouter :
```php
    public function quest(Request $request, \App\Models\Quest $quest, WorkoutBootData $bootData)
    {
        abort_unless($quest->user_id === $request->user()->id, 403);

        if ($quest->status !== 'pending' || ! $quest->date->isToday()) {
            return redirect()->route('home');
        }

        return view('workout.run', ['boot' => $bootData->forQuest($request->user(), $quest)]);
    }
```

Dans `routes/web.php`, après la route `workout`, ajouter :
```php
    Route::get('/workout/quest/{quest}', [WorkoutController::class, 'quest'])->name('workout.quest');
```

- [ ] **Step 7 : accueil**

Dans `app/Livewire/Home.php`, `render()` reçoit aussi `\App\Game\QuestEvaluator $evaluator` et `\App\Game\QuestScheduler $scheduler`. Ajouter avant le `return` :
```php
        $todayQuests = $user->quests()->where('date', today()->toDateString())->get()->keyBy('type');
        $todaySets = \App\Models\WorkoutSet::query()->with('exercise')
            ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
            ->where('workouts.user_id', $user->id)->whereDate('workouts.finished_at', today())
            ->get(['workout_sets.*']);
        $questProgress = fn ($quest) => $quest ? $evaluator->progress($quest->objectives, $todaySets) : [];
```
et ajouter au tableau de la vue :
```php
            'quest' => $todayQuests['training'] ?? $todayQuests['rest'] ?? null,
            'penalty' => $todayQuests['penalty'] ?? null,
            'questProgress' => $questProgress($todayQuests['training'] ?? null),
            'penaltyProgress' => $questProgress($todayQuests['penalty'] ?? null),
            'reward' => $scheduler->rewardFor((int) $user->streak_days),
```

Dans `resources/views/livewire/home.blade.php`, dans la fenêtre `STATUT`, remplacer la ligne du rang par :
```blade
                <p class="text-sm text-mist">Rang {{ $player['rank'] }} · Série {{ $user->streak_days }} j @if ($user->equippedTitleName()) · « {{ $user->equippedTitleName() }} »@endif</p>
```
puis, juste **après** la fenêtre `STATUT` (et avant le bouton « Séance libre »), ajouter :
```blade
    @if ($penalty && $penalty->status !== 'cancelled')
        <x-sys.window title="PÉNALITÉ" variant="alert" :index="1">
            <p class="mb-2 text-sm text-mist">Quête d'hier non accomplie. Termine ce circuit avant minuit pour sauver ta série.</p>
            @foreach ($penalty->objectives as $i => $objective)
                <div class="sys-row text-sm">
                    <span>{{ $objective['name'] }} · {{ $objective['sets'] }} × {{ $objective['reps_min'] }}</span>
                    <span class="sys-num text-mist">{{ min($penaltyProgress[$i] ?? 0, $objective['sets']) }} / {{ $objective['sets'] }}</span>
                </div>
            @endforeach
            @if ($penalty->status === 'pending')
                <x-sys.button class="mt-3" :href="route('workout.quest', $penalty)">Accepter la pénalité</x-sys.button>
            @else
                <p class="mt-3 text-center text-sm text-ice">PÉNALITÉ ACCOMPLIE ✓</p>
            @endif
        </x-sys.window>
    @endif

    @if ($user->isPaused())
        <x-sys.window title="QUÊTES SUSPENDUES" :index="2">
            <p class="text-sm text-mist">TON EGO attend ton retour. Ta série ({{ $user->streak_days }} j) est gelée.</p>
            <a href="{{ route('profile') }}" class="sys-label mt-2 block text-right">Reprendre</a>
        </x-sys.window>
    @elseif ($quest && $quest->type === 'training')
        <x-sys.window title="QUÊTE DU JOUR" :index="2">
            <p class="mb-2 text-center text-sm text-mist">TON EGO t'assigne : <span class="font-bold text-frost">{{ $quest->title }}</span></p>
            @foreach ($quest->objectives as $i => $objective)
                @php $done = min($questProgress[$i] ?? 0, $objective['sets']); @endphp
                <div @class(['sys-row text-sm', 'line-through opacity-60' => $done >= $objective['sets']])>
                    <span>{{ $objective['name'] }} · {{ $objective['sets'] }} × {{ $objective['reps_min'] }}–{{ $objective['reps_max'] }}</span>
                    <span class="sys-num text-ice">{{ $done }} / {{ $objective['sets'] }}</span>
                </div>
            @endforeach
            <p class="sys-num mt-2 text-center text-xs text-mist">Récompense : +{{ $quest->status === 'done' ? $quest->xp_awarded : $reward }} XP · avant minuit</p>
            @if ($quest->status === 'pending')
                <x-sys.button class="mt-3" :pulse="true" :href="route('workout.quest', $quest)">Accepter la quête</x-sys.button>
            @elseif ($quest->status === 'done')
                <p class="mt-3 text-center font-bold text-ice">QUÊTE ACCOMPLIE ✓</p>
            @endif
        </x-sys.window>
    @elseif ($quest && $quest->type === 'rest')
        <x-sys.window title="QUÊTE DU JOUR" :index="2">
            <p class="text-center text-sm text-mist"><span class="font-bold text-frost">Repos</span> — récupère. TON EGO compte ce jour dans ta série.</p>
        </x-sys.window>
    @elseif (! $user->active_program_id)
        <x-sys.window title="AUCUN PROGRAMME" :index="2">
            <p class="text-sm text-mist">Laisse TON EGO forger ton programme : il t'assignera ensuite une quête chaque jour.</p>
            <x-sys.button class="mt-3" :href="route('program')">Forger mon programme</x-sys.button>
        </x-sys.window>
    @endif
```

- [ ] **Step 8 : vérifier**

Run : `npm run build && npm test && ./vendor/bin/pest`
Attendu : tout PASS. Si le test de la récompense échoue parce que la série vaut déjà 1 (la quête de repos de la veille n'existe pas, la série vaut donc 0), le `+200 XP` attendu est correct.

- [ ] **Step 9 : commit**

```bash
git add -A
git commit -m "feat: quête du jour sur l'accueil et séance de quête préremplie

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 12 : vérification finale

- [ ] **Step 1 : suites** : `npm test && npm run build && ./vendor/bin/pest` → tout PASS.
- [ ] **Step 2 : base locale** : `php artisan migrate --no-interaction`.
- [ ] **Step 3 : simulation jsdom** de la séance de quête : HTML de `/workout/quest/{id}`. Vérifier que les exercices du plan sont préchargés, que l'objectif « Objectif · 3 × 6–10 · série 1/3 » s'affiche, et qu'une réponse simulée avec `QUÊTE ACCOMPLIE` s'affiche en notification.
- [ ] **Step 4 : vérification par l'utilisateur** :
  - Programme : questionnaire, programme forgé, remplacer un exercice ;
  - accueil : QUÊTE DU JOUR, puis « Accepter la quête » ;
  - séance de quête jusqu'à « QUÊTE ACCOMPLIE » ;
  - profil : mode pause.
