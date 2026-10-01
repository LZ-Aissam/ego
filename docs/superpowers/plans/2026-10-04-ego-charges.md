# EGO — Charges : calibrage et charge prescrite — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal :** TON EGO mesure la force réelle du joueur (séance de calibrage, test de répétitions max), lui prescrit une charge pour chaque exercice chargé du programme, et la fait évoluer par double progression.

**Architecture :**
- Deux tables : `exercise_maxes` (profil de force) et `working_loads` (charge prescrite).
- `App\Game\CalibrationPlan` : mouvements testés, plan d'échauffement, objectifs de la quête `calibration`. Il n'a aucune dépendance au jeu, pour éviter un cycle avec `QuestScheduler`.
- `App\Game\Calibration::complete` : enregistre la séance via `WorkoutSync`, met à jour le profil de force et le facteur de puissance, prescrit les charges, puis appelle `GameEvents` **sans progression**.
- `App\Game\LoadPrescriber` : charges de départ et double progression. Il est branché dans `GameEvents` après les quêtes.
- `QuestScheduler` crée une quête `calibration` à la place des quêtes `training` tant que le joueur n'est pas calibré, et la traite comme `training` partout.
- Écran Livewire `CalibrationPage`. La charge prescrite s'affiche dans la quête, la séance (pré-remplissage), le programme et la fiche du joueur.

**Tech Stack :** Laravel 12, Livewire 3, Alpine.js, Pest 4, Vitest, MariaDB.

**Spec :** `docs/superpowers/specs/2026-10-04-ego-charges-design.md`.

## Global Constraints

- **Arrondis**
  - `arrondi2,5(x) = round(x / 2,5) × 2,5` ;
  - `arrondi2,5_inférieur(x) = floor(x / 2,5) × 2,5` ;
  - toute charge prescrite est ≥ 2,5 kg.
- **Charge de départ d'un exercice testé** : `max(2,5, arrondi2,5_inférieur(1RM / (1 + (reps_max + 2) / 30)))`. Avec un 1RM de 100 kg : 5 répétitions → 80 kg, 10 répétitions → 70 kg.
- **Charge de départ d'un exercice non testé** : `max(2,5, arrondi2,5(StartingLoad::suggest × F))`, où `F` (`users.power_factor`) est borné entre 0,5 et 2. Pas de charge si la suggestion est nulle, ou si l'exercice est au poids du corps.
- **Facteur de puissance** `F` = moyenne, sur les mouvements testés et chargés, de `charge de départ / E`, où `E` = suggestion ou 20 kg par défaut. Borné entre 0,5 et 2 ; 1 s'il n'y a aucun mouvement chargé.
- **Double progression**
  - Ne compte que les séries **≥ charge prescrite** (`n` séries) ; `S` = séries prévues.
  - **Échec** si une série retenue a `reps < reps_min` : `fail_streak + 1` ; à 2, **décharge** de −10 % (`arrondi2,5`, minimum 2,5) et `fail_streak = 0`.
  - **Réussite** si `n ≥ S` et que toutes les séries retenues ont `reps ≥ reps_max` : **+5 kg** pour les emplacements `squat`, `hinge`, `lunge`, `quads_iso`, `hamstrings_iso`, `calves`, **+2,5 kg** sinon.
  - **Consolidation** sinon : `fail_streak = 0`.
  - **Aucune série retenue** : rien ne change.
  - **Poids du corps** : notification « LESTE-TOI » si `n ≥ S` et toutes les séries ont `reps ≥ reps_max + 5`.
- **Calibrage**
  - **Mouvements testés**, chacun pris dans le programme en suivant l'ordre des jours puis des positions, à défaut le premier candidat du générateur : `horizontal_push`, puis `horizontal_pull` (sinon `vertical_pull`), puis `squat` (sinon `lunge`), puis `hinge` (uniquement pour les niveaux `intermediate` et `advanced` avec le matériel `gym` ou `dumbbells`).
  - **Échauffements** : `arrondi2,5(E × 0,5)` × 8, puis `arrondi2,5(E × 0,7)` × 4. Au poids du corps : 0 × 5 puis 0 × 3.
  - **Série test** : charge ≥ 0 (> 0 pour un exercice chargé), de 1 à 30 répétitions.
- **Quête `calibration`**
  - Créée **à la place de chaque quête `training`** tant que `calibrated_at` est vide, avec le titre « Calibrage » et 3 séries par mouvement testé (`reps_min` 1, `reps_max` 30, repos 120 s).
  - Elle ne fait pas avancer la rotation des jours de programme.
  - Elle suit les règles de `training` : XP, série, échec et pénalité, Régularité.
- **La séance de calibrage ne déclenche pas la double progression.**
- **Ordre des notifications** après une séance : `SÉANCE TERMINÉE`, records, quêtes, **charges**, niveau, rang, titres.
- **Copie** : le Système s'appelle TON EGO, l'interface est en français, avec les composants `x-sys.*`.
- **Commits** terminés par `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. MariaDB (Wamp, port 3307) doit être lancé.

## Review Focus

1. **Charge saisie plus légère que la charge prescrite** (le joueur allège de lui-même) : ce n'est ni une réussite ni un échec, et la charge prescrite ne change pas. Test dans la tâche 3.
2. **Même exercice deux fois dans une séance** (séance libre) : évalué une seule fois, sans double hausse. Test dans la tâche 3.
3. **Calibrage d'un joueur sans poids ni sexe renseignés** : il reste possible, avec `E` = 20 kg. Les exercices non testés n'ont pas de charge prescrite, et aucune erreur ne survient. Test dans la tâche 4.
4. **Recalibrage** : les charges des exercices testés sont recalculées et leur `fail_streak` remis à 0. Aucune hausse automatique n'a lieu sur la séance de calibrage elle-même. Test dans la tâche 4.
5. **Quête `calibration` ratée** : la pénalité tombe comme pour un entraînement, et la quête suivante est encore un calibrage. Test dans la tâche 6.

---

## Structure des fichiers

```
database/migrations/2026_10_05_000100_create_strength_tables.php
app/Models/ExerciseMax.php, WorkingLoad.php ; User.php (relations, casts)
app/Game/LoadPrescriber.php       charges de départ + double progression
app/Game/CalibrationPlan.php      mouvements testés, plan, objectifs
app/Game/Calibration.php          fin du calibrage (séance, profil, facteur, charges, notifications)
app/Game/GameEvents.php           + progression (désactivable)
app/Game/QuestScheduler.php, StreakCalculator.php, PlayerStats.php   + type calibration
app/Support/SystemMessage.php     + loadUp, deload, addWeight, powerMeasured
app/Services/ProgramBuilder.php   + charges après (re)génération
app/Livewire/CalibrationPage.php + resources/views/livewire/calibration-page.blade.php
app/Livewire/Home.php, ProgramPage.php, Profile.php + vues ; WorkoutController ; WorkoutBootData
resources/js/workout/store.js, component.js ; resources/views/workout/run.blade.php
routes/web.php
tests/Feature/Strength/*.php ; resources/js/workout/store.test.js
```

---

### Task 1 : tables du profil de force et des charges

**Files :**
- Create : `database/migrations/2026_10_05_000100_create_strength_tables.php`, `app/Models/ExerciseMax.php`, `app/Models/WorkingLoad.php`
- Modify : `app/Models/User.php`
- Test : `tests/Feature/Strength/StrengthModelsTest.php`

**Interfaces :**
- Produces :
  - `ExerciseMax` (`user_id`, `exercise_id`, `e1rm_kg` float, `source`, `measured_at`) avec `exercise()` ;
  - `WorkingLoad` (`user_id`, `exercise_id`, `target_kg` float, `fail_streak` int) avec `exercise()` ;
  - `User::exerciseMaxes()`, `User::workingLoads()` ; casts `calibrated_at` datetime et `power_factor` float.

- [ ] **Step 1 : test qui échoue**

`tests/Feature/Strength/StrengthModelsTest.php` :
```php
<?php

use App\Models\Exercise;
use App\Models\User;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(fn () => $this->seed(ExerciseSeeder::class));

it('stocke le profil de force et la charge prescrite d\'un joueur', function () {
    $user = User::factory()->create();
    $bench = Exercise::firstWhere('slug', 'bench-press');

    $user->exerciseMaxes()->create(['exercise_id' => $bench->id, 'e1rm_kg' => 82.5, 'source' => 'calibration', 'measured_at' => now()]);
    $user->workingLoads()->create(['exercise_id' => $bench->id, 'target_kg' => 57.5, 'fail_streak' => 0]);
    $user->forceFill(['calibrated_at' => now(), 'power_factor' => 1.25])->save();

    $user = $user->fresh();
    expect($user->exerciseMaxes->first()->e1rm_kg)->toBe(82.5)
        ->and($user->workingLoads->first()->target_kg)->toBe(57.5)
        ->and($user->workingLoads->first()->exercise->slug)->toBe('bench-press')
        ->and($user->power_factor)->toBe(1.25)
        ->and($user->calibrated_at)->not->toBeNull();
});

it('n\'a qu\'une charge prescrite par exercice', function () {
    $user = User::factory()->create();
    $bench = Exercise::firstWhere('slug', 'bench-press');
    $user->workingLoads()->create(['exercise_id' => $bench->id, 'target_kg' => 50, 'fail_streak' => 0]);

    $user->workingLoads()->create(['exercise_id' => $bench->id, 'target_kg' => 60, 'fail_streak' => 0]);
})->throws(UniqueConstraintViolationException::class);
```

- [ ] **Step 2 : vérifier l'échec** : `./vendor/bin/pest tests/Feature/Strength/StrengthModelsTest.php` → FAIL.

- [ ] **Step 3 : migration et modèles**

`database/migrations/2026_10_05_000100_create_strength_tables.php` :
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_maxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained();
            $table->decimal('e1rm_kg', 6, 1);
            $table->string('source', 12)->default('calibration');
            $table->timestamp('measured_at');
            $table->timestamps();
            $table->unique(['user_id', 'exercise_id']);
        });

        Schema::create('working_loads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained();
            $table->decimal('target_kg', 6, 2);
            $table->unsignedTinyInteger('fail_streak')->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'exercise_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('calibrated_at')->nullable()->after('paused_until');
            $table->decimal('power_factor', 4, 2)->nullable()->after('calibrated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['calibrated_at', 'power_factor']));
        Schema::dropIfExists('working_loads');
        Schema::dropIfExists('exercise_maxes');
    }
};
```

`app/Models/ExerciseMax.php` :
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseMax extends Model
{
    protected $fillable = ['exercise_id', 'e1rm_kg', 'source', 'measured_at'];

    protected function casts(): array
    {
        return ['exercise_id' => 'integer', 'e1rm_kg' => 'float', 'measured_at' => 'datetime'];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
```

`app/Models/WorkingLoad.php` :
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkingLoad extends Model
{
    protected $fillable = ['exercise_id', 'target_kg', 'fail_streak'];

    protected function casts(): array
    {
        return ['exercise_id' => 'integer', 'target_kg' => 'float', 'fail_streak' => 'integer'];
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
```

Dans `app/Models/User.php` :
- dans `casts()`, ajouter `'calibrated_at' => 'datetime',` et `'power_factor' => 'float',` ;
- ajouter :
```php
    public function exerciseMaxes(): HasMany
    {
        return $this->hasMany(ExerciseMax::class);
    }

    public function workingLoads(): HasMany
    {
        return $this->hasMany(WorkingLoad::class);
    }
```

- [ ] **Step 4 : vérifier** : `php artisan migrate --no-interaction && ./vendor/bin/pest` → tout PASS.

- [ ] **Step 5 : commit**

```bash
git add -A
git commit -m "feat: tables du profil de force et des charges prescrites

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 2 : charges de départ (`LoadPrescriber`)

**Files :**
- Create : `app/Game/LoadPrescriber.php`
- Test : `tests/Feature/Strength/LoadPrescriberTest.php`

**Interfaces :**
- Consumes : `StartingLoad::suggest`, `ExerciseMax`, `WorkingLoad`, programme actif
- Produces :
  - `LoadPrescriber::startingLoad(float $e1rm, int $repsMax): float` ;
  - `LoadPrescriber::derivedLoad(Exercise $exercise, User $user): ?float` ;
  - `LoadPrescriber::planLines(User $user): Collection<int, ProgramExercise>` (première occurrence de chaque exercice dans le programme actif, avec `reps_max` minimal sur ses occurrences) ;
  - `LoadPrescriber::prescribeProgram(User $user, bool $overwrite = false): void` ;
  - constante `LOWER_BODY_SLOTS`.

- [ ] **Step 1 : test qui échoue**

`tests/Feature/Strength/LoadPrescriberTest.php` :
```php
<?php

use App\Game\LoadPrescriber;
use App\Models\Exercise;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->prescriber = app(LoadPrescriber::class);
});

it('calcule la charge de départ avec 2 répétitions en réserve, arrondie à 2,5 kg en dessous', function (float $e1rm, int $repsMax, float $expected) {
    expect($this->prescriber->startingLoad($e1rm, $repsMax))->toBe($expected);
})->with([
    [100, 5, 80.0],
    [100, 10, 70.0],
    [82.5, 10, 57.5],
    [5, 15, 2.5],
]);

it('déduit la charge d\'un exercice non testé avec le facteur de puissance borné', function () {
    $user = User::factory()->withMorphology(sex: 'male', weightKg: 80.0, level: 'beginner')->create();
    $fly = Exercise::firstWhere('slug', 'dumbbell-fly');          // suggestion : 80 × 0,08 = 6,4 → arrondi 7,5 kg

    $user->forceFill(['power_factor' => 1.5])->save();
    expect($this->prescriber->derivedLoad($fly, $user->fresh()))->toBe(12.5);   // 7,5 × 1,5 = 11,25 → 12,5

    $user->forceFill(['power_factor' => 9])->save();
    expect($this->prescriber->derivedLoad($fly, $user->fresh()))->toBe(15.0);   // facteur borné à 2

    $user->forceFill(['power_factor' => null])->save();
    expect($this->prescriber->derivedLoad($fly, $user->fresh()))->toBe(7.5);

    expect($this->prescriber->derivedLoad(Exercise::firstWhere('slug', 'pull-up'), $user))->toBeNull()
        ->and($this->prescriber->derivedLoad($fly, User::factory()->create()))->toBeNull();
});

it('prescrit tout le programme : 1RM mesuré si connu, sinon déduit, rien au poids du corps', function () {
    $user = User::factory()->withMorphology(sex: 'male', weightKg: 80.0)->create();
    $program = app(ProgramBuilder::class)->build($user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3], 'equipment' => 'gym']);
    $squat = $program->days->first()->exercises->first();       // squat, 3 × 6–10
    $user->exerciseMaxes()->create(['exercise_id' => $squat->exercise_id, 'e1rm_kg' => 100, 'source' => 'calibration', 'measured_at' => now()]);
    $user->forceFill(['power_factor' => 1.0])->save();

    $this->prescriber->prescribeProgram($user->fresh());

    $loads = $user->workingLoads()->pluck('target_kg', 'exercise_id');
    expect($loads[$squat->exercise_id])->toBe(70.0)
        ->and($loads->count())->toBe($program->days->flatMap->exercises->filter(fn ($l) => ! $l->exercise->is_bodyweight)->unique('exercise_id')->count());
});

it('ne touche pas aux charges existantes sauf en mode écrasement', function () {
    $user = User::factory()->withMorphology()->create();
    $program = app(ProgramBuilder::class)->build($user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3], 'equipment' => 'gym']);
    $line = $program->days->first()->exercises->first();
    $user->workingLoads()->create(['exercise_id' => $line->exercise_id, 'target_kg' => 99, 'fail_streak' => 1]);

    $this->prescriber->prescribeProgram($user);
    expect($user->workingLoads()->where('exercise_id', $line->exercise_id)->value('target_kg'))->toBe(99.0);

    $user->exerciseMaxes()->create(['exercise_id' => $line->exercise_id, 'e1rm_kg' => 100, 'source' => 'calibration', 'measured_at' => now()]);
    $this->prescriber->prescribeProgram($user, overwrite: true);
    $load = $user->workingLoads()->where('exercise_id', $line->exercise_id)->first();
    expect($load->target_kg)->toBe(70.0)->and($load->fail_streak)->toBe(0);
});
```

- [ ] **Step 2 : vérifier l'échec** → FAIL.

- [ ] **Step 3 : implémentation**

`app/Game/LoadPrescriber.php` :
```php
<?php

namespace App\Game;

use App\Models\Exercise;
use App\Models\ProgramExercise;
use App\Models\User;
use App\Services\StartingLoad;
use Illuminate\Support\Collection;

final class LoadPrescriber
{
    public const LOWER_BODY_SLOTS = ['squat', 'hinge', 'lunge', 'quads_iso', 'hamstrings_iso', 'calves'];

    public function __construct(private StartingLoad $startingLoad) {}

    public function startingLoad(float $e1rm, int $repsMax): float
    {
        return max(2.5, floor($e1rm / (1 + ($repsMax + 2) / 30) / 2.5) * 2.5);
    }

    public function derivedLoad(Exercise $exercise, User $user): ?float
    {
        $suggestion = $this->startingLoad->suggest($exercise, $user);
        if ($suggestion === null) {
            return null;
        }

        $factor = min(2.0, max(0.5, $user->power_factor ?? 1.0));

        return max(2.5, round($suggestion * $factor / 2.5) * 2.5);
    }

    /** Première occurrence de chaque exercice du programme actif, avec le reps_max le plus bas de ses occurrences. */
    public function planLines(User $user): Collection
    {
        $program = $user->activeProgram()->with('days.exercises.exercise')->first();
        if (! $program) {
            return collect();
        }

        $lines = $program->days->flatMap->exercises;

        return $lines->groupBy('exercise_id')->map(function (Collection $occurrences) {
            $first = clone $occurrences->first();
            $first->reps_max = $occurrences->min('reps_max');

            return $first;
        });
    }

    public function prescribeProgram(User $user, bool $overwrite = false): void
    {
        $maxes = $user->exerciseMaxes()->pluck('e1rm_kg', 'exercise_id');
        $existing = $user->workingLoads()->pluck('target_kg', 'exercise_id');

        foreach ($this->planLines($user) as $exerciseId => $line) {
            /** @var ProgramExercise $line */
            if ($line->exercise->is_bodyweight || ($existing->has($exerciseId) && ! $overwrite)) {
                continue;
            }

            $target = $maxes->has($exerciseId)
                ? $this->startingLoad((float) $maxes[$exerciseId], $line->reps_max)
                : $this->derivedLoad($line->exercise, $user);

            if ($target === null) {
                continue;
            }

            $user->workingLoads()->updateOrCreate(['exercise_id' => $exerciseId], ['target_kg' => $target, 'fail_streak' => 0]);
        }
    }
}
```

- [ ] **Step 4 : vérifier** → PASS.

- [ ] **Step 5 : commit**

```bash
git add -A
git commit -m "feat: charges de départ prescrites (1RM mesuré ou déduit)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 3 : double progression après chaque séance

**Files :**
- Modify : `app/Game/LoadPrescriber.php` (`progress`), `app/Game/GameEvents.php` (progression, paramètre `$progressLoads`), `app/Support/SystemMessage.php` (`loadUp`, `deload`, `addWeight`)
- Test : `tests/Feature/Strength/LoadProgressionTest.php`

**Interfaces :**
- Produces :
  - `LoadPrescriber::progress(User $user, Workout $workout): list<notification>` ;
  - `GameEvents::afterWorkout(User $user, Workout $workout, array $newRecords, bool $progressLoads = true)` ;
  - `SystemMessage::loadUp(string $exercise, float $kg)`, `deload(string $exercise, float $kg)`, `addWeight(string $exercise)`.

- [ ] **Step 1 : test qui échoue**

`tests/Feature/Strength/LoadProgressionTest.php` :
```php
<?php

use App\Game\GameEvents;
use App\Game\LoadPrescriber;
use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkingLoad;
use App\Services\ProgramBuilder;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology()->create();
    $program = app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3], 'equipment' => 'gym']);
    $this->lines = $program->days->flatMap->exercises;
    $this->squat = $this->lines->firstWhere('slot', 'squat');                    // 3 × 6–10, bas du corps
    $this->push = $this->lines->firstWhere('slot', 'horizontal_push');           // 3 × 6–10, haut du corps
    $this->user->workingLoads()->create(['exercise_id' => $this->squat->exercise_id, 'target_kg' => 60, 'fail_streak' => 0]);
    $this->user->workingLoads()->create(['exercise_id' => $this->push->exercise_id, 'target_kg' => 40, 'fail_streak' => 0]);
});

/** @param list<array{0:int,1:float,2:int}> $sets [exercise_id, kg, reps] */
function lift(User $user, array $sets): array
{
    $at = now()->toIso8601String();
    $sync = app(WorkoutSync::class)->sync($user, [
        'client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
        'sets' => array_map(fn ($s) => ['exercise_id' => $s[0], 'weight_kg' => $s[1], 'reps' => $s[2], 'done_at' => $at], $sets),
    ]);

    return app(LoadPrescriber::class)->progress($user, $sync['workout']);
}

function target(User $user, int $exerciseId): array
{
    $load = WorkingLoad::where('user_id', $user->id)->where('exercise_id', $exerciseId)->first();

    return [$load->target_kg, $load->fail_streak];
}

it('augmente de 5 kg en bas du corps et 2,5 kg en haut quand le haut de la plage est atteint', function () {
    $sq = $this->squat->exercise_id;
    $bp = $this->push->exercise_id;

    $notes = lift($this->user, [[$sq, 60, 10], [$sq, 60, 10], [$sq, 60, 10], [$bp, 40, 10], [$bp, 40, 10], [$bp, 42.5, 11]]);

    expect(target($this->user, $sq))->toBe([65.0, 0])
        ->and(target($this->user, $bp))->toBe([42.5, 0])
        ->and(collect($notes)->pluck('title')->all())->toBe(['CHARGE AUGMENTÉE', 'CHARGE AUGMENTÉE']);
});

it('consolide sans changer quand la plage n\'est pas finie', function () {
    $sq = $this->squat->exercise_id;
    lift($this->user, [[$sq, 60, 10], [$sq, 60, 9], [$sq, 60, 8]]);

    expect(target($this->user, $sq))->toBe([60.0, 0]);
});

it('décharge de 10 % après deux séances d\'échec', function () {
    $sq = $this->squat->exercise_id;
    lift($this->user, [[$sq, 60, 8], [$sq, 60, 5]]);
    expect(target($this->user, $sq))->toBe([60.0, 1]);

    $notes = lift($this->user, [[$sq, 60, 4]]);
    expect(target($this->user, $sq))->toBe([55.0, 0])
        ->and($notes[0]['title'])->toBe('DÉCHARGE');
});

it('ignore une séance faite plus léger que prescrit', function () {
    $sq = $this->squat->exercise_id;
    $this->user->workingLoads()->where('exercise_id', $sq)->update(['fail_streak' => 1]);

    $notes = lift($this->user, [[$sq, 50, 4], [$sq, 50, 12], [$sq, 50, 12]]);

    expect(target($this->user, $sq))->toBe([60.0, 1])->and($notes)->toBe([]);
});

it('n\'évalue qu\'une fois un exercice présent deux fois', function () {
    $sq = $this->squat->exercise_id;
    lift($this->user, [[$sq, 60, 10], [$sq, 60, 10], [$sq, 60, 10], [$sq, 60, 10], [$sq, 60, 10], [$sq, 60, 10]]);

    expect(target($this->user, $sq))->toBe([65.0, 0]);
});

it('suggère du lest au poids du corps et ignore les exercices hors programme', function () {
    $pullUp = Exercise::firstWhere('slug', 'pull-up');
    $this->user->activeProgram->days->first()->exercises()->create(['position' => 9, 'exercise_id' => $pullUp->id, 'slot' => 'vertical_pull', 'sets' => 3, 'reps_min' => 6, 'reps_max' => 10, 'rest_seconds' => 90]);
    $curl = Exercise::firstWhere('slug', 'barbell-curl');

    $notes = lift($this->user, [[$pullUp->id, 0, 15], [$pullUp->id, 0, 15], [$pullUp->id, 0, 16], [$curl->id, 20, 15]]);

    expect(collect($notes)->pluck('title')->all())->toBe(['LESTE-TOI'])
        ->and(WorkingLoad::where('exercise_id', $curl->id)->exists())->toBeFalse();
});

it('place les notifications de charge dans les événements de jeu, sauf si la progression est coupée', function () {
    $sq = $this->squat->exercise_id;
    $at = now()->toIso8601String();
    $sync = app(WorkoutSync::class)->sync($this->user, ['client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
        'sets' => array_fill(0, 3, ['exercise_id' => $sq, 'weight_kg' => 60, 'reps' => 10, 'done_at' => $at])]);

    $off = app(GameEvents::class)->afterWorkout($this->user->fresh(), $sync['workout'], $sync['newRecords'], progressLoads: false);
    expect(collect($off['notifications'])->pluck('title'))->not->toContain('CHARGE AUGMENTÉE')
        ->and(target($this->user, $sq))->toBe([60.0, 0]);

    $sync = app(WorkoutSync::class)->sync($this->user, ['client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
        'sets' => array_fill(0, 3, ['exercise_id' => $sq, 'weight_kg' => 60, 'reps' => 10, 'done_at' => $at])]);
    $on = app(GameEvents::class)->afterWorkout($this->user->fresh(), $sync['workout'], $sync['newRecords']);
    expect(collect($on['notifications'])->pluck('title'))->toContain('CHARGE AUGMENTÉE');
});
```

- [ ] **Step 2 : vérifier l'échec** → FAIL.

- [ ] **Step 3 : messages système**

Dans `app/Support/SystemMessage.php`, ajouter avant `public static function title(` :
```php
    public static function loadUp(string $exercise, float $kg): array
    {
        return self::make('load_up', 'CHARGE AUGMENTÉE', [$exercise.' → '.Format::kg($kg), 'TON EGO a vu ta progression.'], highlight: '+');
    }

    public static function deload(string $exercise, float $kg): array
    {
        return self::make('deload', 'DÉCHARGE', [$exercise.' → '.Format::kg($kg), 'TON EGO allège. Reconstruis.'], 'alert');
    }

    public static function addWeight(string $exercise): array
    {
        return self::make('add_weight', 'LESTE-TOI', [$exercise, 'Ajoute 2,5 kg de lest la prochaine fois.']);
    }
```

- [ ] **Step 4 : `progress()`**

Dans `app/Game/LoadPrescriber.php`, ajouter `use App\Models\Workout;` et `use App\Models\WorkoutSet;`, puis la méthode :
```php
    /** Double progression après une séance. @return list<array> notifications */
    public function progress(User $user, Workout $workout): array
    {
        $plan = $this->planLines($user);
        if ($plan->isEmpty()) {
            return [];
        }

        $loads = $user->workingLoads()->get()->keyBy('exercise_id');
        $notes = [];

        foreach ($workout->sets->groupBy('exercise_id') as $exerciseId => $sets) {
            $line = $plan[$exerciseId] ?? null;
            if (! $line) {
                continue;
            }

            if ($line->exercise->is_bodyweight) {
                if ($sets->count() >= $line->sets && $sets->every(fn (WorkoutSet $s) => $s->reps >= $line->reps_max + 5)) {
                    $notes[] = \App\Support\SystemMessage::addWeight($line->exercise->name);
                }
                continue;
            }

            $load = $loads[$exerciseId] ?? null;
            if (! $load) {
                continue;
            }

            $kept = $sets->filter(fn (WorkoutSet $s) => $s->weight_kg + 0.001 >= $load->target_kg);
            if ($kept->isEmpty()) {
                continue;
            }

            if ($kept->contains(fn (WorkoutSet $s) => $s->reps < $line->reps_min)) {
                $load->fail_streak += 1;
                if ($load->fail_streak >= 2) {
                    $load->target_kg = max(2.5, round($load->target_kg * 0.9 / 2.5) * 2.5);
                    $load->fail_streak = 0;
                    $notes[] = \App\Support\SystemMessage::deload($line->exercise->name, $load->target_kg);
                }
            } elseif ($kept->count() >= $line->sets && $kept->every(fn (WorkoutSet $s) => $s->reps >= $line->reps_max)) {
                $load->target_kg += in_array($line->slot, self::LOWER_BODY_SLOTS, true) ? 5.0 : 2.5;
                $load->fail_streak = 0;
                $notes[] = \App\Support\SystemMessage::loadUp($line->exercise->name, $load->target_kg);
            } else {
                $load->fail_streak = 0;
            }

            $load->save();
        }

        return $notes;
    }
```

> Note : `planLines` retient le `reps_max` le plus bas des occurrences de l'exercice. La « plage » évaluée va de `reps_min` (de la première occurrence) à ce `reps_max`.

- [ ] **Step 5 : `GameEvents`**

Dans `app/Game/GameEvents.php` :
- ajouter `private LoadPrescriber $loads` à la fin du constructeur ;
- changer la signature en `public function afterWorkout(User $user, Workout $workout, array $newRecords, bool $progressLoads = true): array`, et passer `$progressLoads` à `play()` (signature `play(User $user, Workout $workout, array $newRecords, bool $progressLoads)`) ;
- dans `play()`, juste après `$notifications = $this->quests->onWorkout($user, $workout);`, ajouter :
```php
        if ($progressLoads) {
            array_push($notifications, ...$this->loads->progress($user, $workout));
        }
```

- [ ] **Step 6 : vérifier** : `./vendor/bin/pest` → tout PASS.

- [ ] **Step 7 : commit**

```bash
git add -A
git commit -m "feat: double progression des charges après chaque séance

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 4 : plan et fin du calibrage

**Files :**
- Create : `app/Game/CalibrationPlan.php`, `app/Game/Calibration.php`
- Modify : `app/Support/SystemMessage.php` (`powerMeasured`)
- Test : `tests/Feature/Strength/CalibrationTest.php`

**Interfaces :**
- Consumes : `ProgramGenerator::candidates`, `StartingLoad`, `WorkoutSync`, `GameEvents::afterWorkout(..., progressLoads: false)`, `LoadPrescriber`, `RecordCalculator`, `SystemNotifier`
- Produces :
  - `CalibrationPlan::movementsFor(User $user): Collection<Exercise>` (l'attribut ajouté `test_slot` donne l'emplacement) ;
  - `CalibrationPlan::plan(User $user): list<array{exercise_id: int, name: string, isBodyweight: bool, estimate: ?float, warmups: list<array{0: float, 1: int}>}>` ;
  - `CalibrationPlan::objectives(User $user): list<objective>` (3 séries, reps 1–30, repos 120) ;
  - `Calibration::complete(User $user, array $results, string $startedAtIso): array{workout: Workout, notifications: list<array>}`, où `$results = list<{exercise_id, warmups: list<[kg, reps]>, test: [kg, reps]}>`.

- [ ] **Step 1 : test qui échoue**

`tests/Feature/Strength/CalibrationTest.php` :
```php
<?php

use App\Game\Calibration;
use App\Game\CalibrationPlan;
use App\Models\Exercise;
use App\Models\User;
use App\Models\Workout;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;

beforeEach(fn () => $this->seed(ExerciseSeeder::class));

function calibratedProgram(User $user, string $level = 'beginner', string $equipment = 'gym')
{
    return app(ProgramBuilder::class)->build($user, ['level' => $level, 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => $equipment]);
}

it('choisit les mouvements testés dans le programme selon le niveau et le matériel', function () {
    $beginner = User::factory()->withMorphology()->create();
    calibratedProgram($beginner);
    expect(app(CalibrationPlan::class)->movementsFor($beginner)->pluck('test_slot')->all())->toBe(['horizontal_push', 'horizontal_pull', 'squat']);

    $advanced = User::factory()->withMorphology(level: 'advanced')->create();
    calibratedProgram($advanced, 'advanced');
    expect(app(CalibrationPlan::class)->movementsFor($advanced)->pluck('test_slot')->all())->toBe(['horizontal_push', 'horizontal_pull', 'squat', 'hinge']);

    $bodyweight = User::factory()->withMorphology(level: 'advanced')->create();
    calibratedProgram($bodyweight, 'advanced', 'bodyweight');
    expect(app(CalibrationPlan::class)->movementsFor($bodyweight)->pluck('test_slot')->all())->toBe(['horizontal_push', 'horizontal_pull', 'squat']);
});

it('propose des échauffements à 50 % puis 70 % de l\'estimation', function () {
    $user = User::factory()->withMorphology(sex: 'male', weightKg: 80.0)->create();
    calibratedProgram($user);

    $first = app(CalibrationPlan::class)->plan($user)[0];          // développé couché : estimation 40 kg

    expect($first['estimate'])->toBe(40.0)->and($first['warmups'])->toBe([[20.0, 8], [27.5, 4]]);
});

it('termine le calibrage : séance, profil de force, facteur, charges et notifications, sans progression', function () {
    $user = User::factory()->withMorphology(sex: 'male', weightKg: 80.0)->create();
    $program = calibratedProgram($user);
    $plan = app(CalibrationPlan::class)->plan($user);
    $results = array_map(fn ($m) => ['exercise_id' => $m['exercise_id'], 'warmups' => $m['warmups'], 'test' => [$m['isBodyweight'] ? 0 : 50, 10]], $plan);

    $out = app(Calibration::class)->complete($user, $results, now()->subMinutes(30)->toIso8601String());

    $user = $user->fresh();
    $bench = Exercise::firstWhere('slug', 'bench-press');
    expect(Workout::count())->toBe(1)
        ->and($out['workout']->sets)->toHaveCount(3 * count($plan))
        ->and($user->calibrated_at)->not->toBeNull()
        ->and($user->exerciseMaxes()->where('exercise_id', $bench->id)->value('e1rm_kg'))->toBe(66.7)
        ->and($user->workingLoads()->where('exercise_id', $bench->id)->value('target_kg'))->toBe(47.5)
        ->and($user->power_factor)->toBeGreaterThan(0.5)
        ->and($out['notifications'][0]['title'])->toBe('PUISSANCE MESURÉE')
        ->and($out['notifications'][0]['lines'][0])->toContain('Développé couché (barre) : 1RM ≈ 66,7 kg')
        ->and(collect($out['notifications'])->pluck('title'))->not->toContain('CHARGE AUGMENTÉE');
});

it('fonctionne sans poids ni sexe connus, avec 20 kg d\'estimation', function () {
    $user = User::factory()->create();
    calibratedProgram($user);
    $plan = app(CalibrationPlan::class)->plan($user);

    expect($plan[0]['estimate'])->toBe(20.0);

    $results = array_map(fn ($m) => ['exercise_id' => $m['exercise_id'], 'warmups' => $m['warmups'], 'test' => [$m['isBodyweight'] ? 0 : 30, 8]], $plan);
    app(Calibration::class)->complete($user, $results, now()->toIso8601String());

    expect($user->fresh()->calibrated_at)->not->toBeNull();
});

it('recalibre : charges des exercices testés recalculées et compteur d\'échecs remis à zéro', function () {
    $user = User::factory()->withMorphology()->create();
    calibratedProgram($user);
    $bench = Exercise::firstWhere('slug', 'bench-press');
    $user->workingLoads()->create(['exercise_id' => $bench->id, 'target_kg' => 100, 'fail_streak' => 1]);
    $plan = app(CalibrationPlan::class)->plan($user);
    $results = array_map(fn ($m) => ['exercise_id' => $m['exercise_id'], 'warmups' => $m['warmups'], 'test' => [$m['isBodyweight'] ? 0 : 50, 10]], $plan);

    app(Calibration::class)->complete($user, $results, now()->toIso8601String());

    $load = $user->workingLoads()->where('exercise_id', $bench->id)->first();
    expect($load->target_kg)->toBe(47.5)->and($load->fail_streak)->toBe(0);
});
```

> Calculs : 50 kg × 10 répétitions → 1RM = 50 × (1 + 10/30) = 66,67, arrondi à 66,7 ; charge de départ en 6–10 répétitions = floor(66,7 / 1,4 / 2,5) × 2,5 = floor(19,06) × 2,5 = 47,5 kg.

- [ ] **Step 2 : vérifier l'échec** → FAIL.

- [ ] **Step 3 : `CalibrationPlan`**

`app/Game/CalibrationPlan.php` :
```php
<?php

namespace App\Game;

use App\Models\Exercise;
use App\Models\User;
use App\Services\ProgramGenerator;
use App\Services\StartingLoad;
use Illuminate\Support\Collection;

final class CalibrationPlan
{
    /** Emplacements testés, par ordre de priorité ; chaque groupe donne au plus un mouvement. */
    private const GROUPS = [['horizontal_push'], ['horizontal_pull', 'vertical_pull'], ['squat', 'lunge'], ['hinge']];

    public const DEFAULT_ESTIMATE = 20.0;

    public function __construct(private ProgramGenerator $generator, private StartingLoad $startingLoad) {}

    public function movementsFor(User $user): Collection
    {
        $program = $user->activeProgram()->with('days.exercises.exercise')->first();
        $lines = $program ? $program->days->flatMap->exercises : collect();
        $equipment = $user->equipment ?? 'gym';
        $withHinge = in_array($user->level, ['intermediate', 'advanced'], true) && in_array($equipment, ['gym', 'dumbbells'], true);
        $library = Exercise::all();
        $picked = collect();

        foreach (self::GROUPS as $slots) {
            if ($slots === ['hinge'] && ! $withHinge) {
                continue;
            }

            foreach ($slots as $slot) {
                $exercise = $lines->firstWhere('slot', $slot)?->exercise ?? $this->generator->candidates($slot, $equipment, $library)->first();
                if ($exercise && ! $picked->contains('id', $exercise->id)) {
                    $exercise->setAttribute('test_slot', $slot);
                    $picked->push($exercise);
                    break;
                }
            }
        }

        return $picked->values();
    }

    public function plan(User $user): array
    {
        return $this->movementsFor($user)->map(function (Exercise $exercise) use ($user) {
            if ($exercise->is_bodyweight) {
                return ['exercise_id' => $exercise->id, 'name' => $exercise->name, 'isBodyweight' => true, 'estimate' => null, 'warmups' => [[0.0, 5], [0.0, 3]]];
            }

            $estimate = $this->startingLoad->suggest($exercise, $user) ?? self::DEFAULT_ESTIMATE;

            return [
                'exercise_id' => $exercise->id,
                'name' => $exercise->name,
                'isBodyweight' => false,
                'estimate' => (float) $estimate,
                'warmups' => [[round($estimate * 0.5 / 2.5) * 2.5, 8], [round($estimate * 0.7 / 2.5) * 2.5, 4]],
            ];
        })->values()->all();
    }

    public function objectives(User $user): array
    {
        return $this->movementsFor($user)->map(fn (Exercise $e) => [
            'exercise_id' => $e->id, 'name' => $e->name, 'slot' => $e->test_slot,
            'sets' => 3, 'reps_min' => 1, 'reps_max' => 30, 'rest_seconds' => 120,
        ])->values()->all();
    }
}
```

- [ ] **Step 4 : message système**

Dans `app/Support/SystemMessage.php`, ajouter :
```php
    /** @param list<string> $lines */
    public static function powerMeasured(array $lines): array
    {
        return self::make('power', 'PUISSANCE MESURÉE', $lines, highlight: 'CALIBRAGE');
    }
```

- [ ] **Step 5 : `Calibration`**

`app/Game/Calibration.php` :
```php
<?php

namespace App\Game;

use App\Models\Exercise;
use App\Models\User;
use App\Services\RecordCalculator;
use App\Services\WorkoutSync;
use App\Support\Format;
use App\Support\SystemMessage;
use Illuminate\Support\Str;

final class Calibration
{
    public function __construct(
        private WorkoutSync $sync,
        private GameEvents $game,
        private LoadPrescriber $prescriber,
        private CalibrationPlan $plan,
        private RecordCalculator $calc,
    ) {}

    /**
     * @param  list<array{exercise_id: int, warmups: list<array{0: float, 1: int}>, test: array{0: float, 1: int}}>  $results
     * @return array{workout: \App\Models\Workout, notifications: list<array>}
     */
    public function complete(User $user, array $results, string $startedAtIso): array
    {
        $now = now();
        $sets = [];
        foreach ($results as $result) {
            foreach ([...$result['warmups'], $result['test']] as [$kg, $reps]) {
                $sets[] = ['exercise_id' => $result['exercise_id'], 'weight_kg' => (float) $kg, 'reps' => (int) $reps, 'done_at' => $now->toIso8601String()];
            }
        }

        $synced = $this->sync->sync($user, [
            'client_id' => (string) Str::uuid(), 'started_at' => $startedAtIso, 'finished_at' => $now->toIso8601String(), 'sets' => $sets,
        ]);

        $estimates = collect($this->plan->plan($user))->keyBy('exercise_id');
        $exercises = Exercise::whereIn('id', array_column($results, 'exercise_id'))->get()->keyBy('id');
        $planLines = $this->prescriber->planLines($user);
        $lines = [];
        $ratios = [];

        foreach ($results as $result) {
            $exercise = $exercises[$result['exercise_id']];
            [$kg, $reps] = $result['test'];
            $e1rm = $this->calc->estimatedOneRepMax($this->calc->effectiveLoad((float) $kg, $exercise->is_bodyweight, $user->weight_kg), (int) $reps);

            $user->exerciseMaxes()->updateOrCreate(['exercise_id' => $exercise->id], ['e1rm_kg' => $e1rm, 'source' => 'calibration', 'measured_at' => $now]);
            $lines[] = $exercise->name.' : 1RM ≈ '.Format::kg($e1rm).((int) $reps > 12 ? ' (mesure moins précise)' : '');

            if (! $exercise->is_bodyweight) {
                $repsMax = $planLines[$exercise->id]->reps_max ?? 10;
                $estimate = $estimates[$exercise->id]['estimate'] ?? CalibrationPlan::DEFAULT_ESTIMATE;
                $ratios[] = $this->prescriber->startingLoad($e1rm, $repsMax) / $estimate;
            }
        }

        $factor = $ratios === [] ? 1.0 : min(2.0, max(0.5, array_sum($ratios) / count($ratios)));
        $user->forceFill(['calibrated_at' => $now, 'power_factor' => round($factor, 2)])->save();
        $this->prescriber->prescribeProgram($user, overwrite: true);

        $play = $this->game->afterWorkout($user->fresh(), $synced['workout'], $synced['newRecords'], progressLoads: false);

        return [
            'workout' => $synced['workout'],
            'notifications' => [
                SystemMessage::powerMeasured($lines),
                SystemMessage::workoutDone($synced['workout'], $play['xp']),
                ...$play['notifications'],
            ],
        ];
    }
}
```

- [ ] **Step 6 : vérifier** : `./vendor/bin/pest tests/Feature/Strength/CalibrationTest.php` → PASS.

- [ ] **Step 7 : commit**

```bash
git add -A
git commit -m "feat: plan et fin du calibrage (profil de force, facteur, charges)

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 5 : écran de calibrage

**Files :**
- Create : `app/Livewire/CalibrationPage.php`, `resources/views/livewire/calibration-page.blade.php`
- Modify : `routes/web.php`
- Test : `tests/Feature/Strength/CalibrationPageTest.php`

**Interfaces :**
- Consumes : `CalibrationPlan::plan`, `Calibration::complete`, `SystemNotifier::push`
- Produces :
  - routes `calibration` (`GET /calibration`) et `calibration.quest` (`GET /calibration/quest/{quest}`) ;
  - composant avec `movements` (le plan), `index`, `warmupsDone` (`array<int, list<bool>>`), `testKg`, `testReps`, `results`, `startedAt`, et les actions `toggleWarmup(int $i)`, `saveTest()`.

- [ ] **Step 1 : test qui échoue**

`tests/Feature/Strength/CalibrationPageTest.php` :
```php
<?php

use App\Livewire\CalibrationPage;
use App\Models\Quest;
use App\Models\SystemNotification;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(sex: 'male', weightKg: 80.0)->create();
    $this->actingAs($this->user);
});

it('renvoie vers le programme sans programme actif', function () {
    $this->get('/calibration')->assertRedirect(route('program'));
});

it('mène le calibrage mouvement par mouvement jusqu\'à la mesure', function () {
    app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);

    $this->get('/calibration')->assertOk()->assertSee('TON EGO ÉVALUE TA PUISSANCE')->assertSee('Développé couché (barre)');

    $page = Livewire::test(CalibrationPage::class);
    foreach (range(0, count($page->get('movements')) - 1) as $i) {
        $page->call('saveTest')->assertHasErrors(['warmups']);      // échauffements d'abord
        $page->call('toggleWarmup', 0)->call('toggleWarmup', 1)
            ->set('testKg', $page->get('movements')[$i]['isBodyweight'] ? '0' : '50')
            ->set('testReps', '10')
            ->call('saveTest')->assertHasNoErrors();
    }

    $page->assertRedirect(route('home'));
    expect($this->user->fresh()->calibrated_at)->not->toBeNull()
        ->and(SystemNotification::where('type', 'power')->exists())->toBeTrue();
});

it('refuse une série test vide ou hors limites', function (string $kg, string $reps) {
    app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);

    Livewire::test(CalibrationPage::class)
        ->call('toggleWarmup', 0)->call('toggleWarmup', 1)
        ->set('testKg', $kg)->set('testReps', $reps)
        ->call('saveTest')->assertHasErrors()->assertSee('Indique la charge et le nombre de répétitions réalisées');
})->with([['', '10'], ['0', '10'], ['50', '0'], ['50', '31']]);

it('n\'ouvre la quête de calibrage que pour son propriétaire, le jour même', function () {
    app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    $quest = $this->user->quests()->create(['date' => today(), 'type' => 'calibration', 'title' => 'Calibrage', 'objectives' => [], 'status' => 'pending']);

    $this->get(route('calibration.quest', $quest))->assertOk();
    $this->actingAs(User::factory()->create())->get(route('calibration.quest', $quest))->assertForbidden();
    $quest->update(['status' => 'done']);
    $this->actingAs($this->user)->get(route('calibration.quest', $quest))->assertRedirect(route('home'));
});
```

- [ ] **Step 2 : vérifier l'échec** → FAIL.

- [ ] **Step 3 : composant**

`app/Livewire/CalibrationPage.php` :
```php
<?php

namespace App\Livewire;

use App\Game\Calibration;
use App\Game\CalibrationPlan;
use App\Models\Quest;
use App\Support\SystemNotifier;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Calibrage')]
class CalibrationPage extends Component
{
    public array $movements = [];

    public int $index = 0;

    public array $warmupsDone = [];

    public $testKg = null;

    public $testReps = null;

    public array $results = [];

    public string $startedAt = '';

    public function mount(?Quest $quest = null)
    {
        $user = auth()->user();

        if ($quest && $quest->exists) {
            abort_unless($quest->user_id === $user->id, 403);
            if ($quest->type !== 'calibration' || $quest->status !== 'pending' || ! $quest->date->isToday()) {
                return $this->redirectRoute('home');
            }
        }

        if (! $user->active_program_id) {
            return $this->redirectRoute('program');
        }

        $this->movements = app(CalibrationPlan::class)->plan($user);
        $this->startedAt = now()->toIso8601String();
        $this->prepare();
    }

    private function prepare(): void
    {
        $this->warmupsDone = [false, false];
        $current = $this->movements[$this->index] ?? null;
        $this->testKg = $current && ! $current['isBodyweight'] ? $current['estimate'] : 0;
        $this->testReps = null;
    }

    public function toggleWarmup(int $i): void
    {
        $this->warmupsDone[$i] = ! ($this->warmupsDone[$i] ?? false);
    }

    public function saveTest()
    {
        $current = $this->movements[$this->index];

        if (in_array(false, $this->warmupsDone, true)) {
            throw ValidationException::withMessages(['warmups' => 'Valide d\'abord tes deux séries d\'échauffement.']);
        }

        $kg = is_string($this->testKg) ? str_replace(',', '.', $this->testKg) : $this->testKg;
        $minKg = $current['isBodyweight'] ? 0 : 0.5;
        $message = 'Indique la charge et le nombre de répétitions réalisées.';
        validator(['testKg' => $kg, 'testReps' => $this->testReps], [
            'testKg' => ['required', 'numeric', 'min:'.$minKg, 'max:500'],
            'testReps' => ['required', 'integer', 'between:1,30'],
        ], ['*' => $message, 'testKg.*' => $message, 'testReps.*' => $message])->validate();

        $this->results[] = ['exercise_id' => $current['exercise_id'], 'warmups' => $current['warmups'], 'test' => [(float) $kg, (int) $this->testReps]];

        if ($this->index + 1 < count($this->movements)) {
            $this->index++;
            $this->prepare();

            return null;
        }

        $user = auth()->user();
        $out = app(Calibration::class)->complete($user, $this->results, $this->startedAt);
        foreach ($out['notifications'] as $notification) {
            app(SystemNotifier::class)->push($user, $notification);
        }

        return $this->redirectRoute('home');
    }

    public function render()
    {
        return view('livewire.calibration-page', ['current' => $this->movements[$this->index] ?? null]);
    }
}
```

- [ ] **Step 4 : vue**

`resources/views/livewire/calibration-page.blade.php` :
```blade
<div class="space-y-5">
    <x-sys.label>Calibrage · mouvement {{ $index + 1 }} / {{ count($movements) }}</x-sys.label>
    <h1 class="text-2xl font-bold">TON EGO ÉVALUE TA PUISSANCE</h1>

    @if ($current)
        <x-sys.window :title="$current['name']" wire:key="calibration-{{ $index }}">
            <x-sys.label>Échauffement</x-sys.label>
            @foreach ($current['warmups'] as $i => [$kg, $reps])
                <button type="button" wire:click="toggleWarmup({{ $i }})" @class(['sys-row w-full text-left', 'text-ice' => $warmupsDone[$i] ?? false])>
                    <span class="sys-num">{{ $current['isBodyweight'] ? 'Poids du corps' : \App\Support\Format::kg($kg) }} × {{ $reps }}</span>
                    <span class="sys-label">{{ ($warmupsDone[$i] ?? false) ? 'Fait ✓' : 'Valider' }}</span>
                </button>
            @endforeach
            @error('warmups') <p class="sys-error">{{ $message }}</p> @enderror

            <x-sys.label class="mt-5">Série test : le maximum de répétitions</x-sys.label>
            <p class="mb-3 text-sm text-mist">
                @if ($current['isBodyweight'])
                    Sans lest, fais le plus de répétitions possible avec une exécution propre.
                @else
                    Prends une charge que tu penses soulever 5 à 10 fois, puis fais le plus de répétitions possible, proprement.
                @endif
            </p>
            <div class="grid grid-cols-2 gap-3">
                <x-sys.field name="testKg" label="Charge (kg)" inputmode="decimal" wire:model="testKg" />
                <x-sys.field name="testReps" label="Répétitions" inputmode="numeric" wire:model="testReps" />
            </div>
            <x-sys.button class="mt-4" :pulse="true" wire:click="saveTest" wire:loading.attr="disabled">
                {{ $index + 1 < count($movements) ? 'Valider et passer au suivant' : 'Terminer le calibrage' }}
            </x-sys.button>
        </x-sys.window>
    @endif

    @if ($results !== [])
        <x-sys.window title="DÉJÀ MESURÉ" :index="1">
            @foreach ($results as $r)
                <div class="sys-row sys-num text-sm">
                    <span>{{ collect($movements)->firstWhere('exercise_id', $r['exercise_id'])['name'] ?? '' }}</span>
                    <span class="text-ice">{{ \App\Support\Format::kg($r['test'][0]) }} × {{ $r['test'][1] }}</span>
                </div>
            @endforeach
        </x-sys.window>
    @endif
</div>
```

- [ ] **Step 5 : routes**

Dans `routes/web.php` (groupe `auth`), ajouter :
```php
    Route::get('/calibration', CalibrationPage::class)->name('calibration');
    Route::get('/calibration/quest/{quest}', CalibrationPage::class)->name('calibration.quest');
```
et `use App\Livewire\CalibrationPage;`.

- [ ] **Step 6 : vérifier** : `npm run build && ./vendor/bin/pest tests/Feature/Strength/CalibrationPageTest.php` → PASS.

- [ ] **Step 7 : commit**

```bash
git add -A
git commit -m "feat: écran de calibrage guidé

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 6 : quête CALIBRAGE

**Files :**
- Modify : `app/Game/QuestScheduler.php`, `app/Game/StreakCalculator.php`, `app/Game/PlayerStats.php`, `app/Livewire/Home.php`, `resources/views/livewire/home.blade.php`, `app/Http/Controllers/WorkoutController.php`
- Test : `tests/Feature/Strength/CalibrationQuestTest.php`

**Interfaces :**
- Consumes : `CalibrationPlan::objectives`
- Produces : une quête de type `calibration` traitée comme `training`. Le bouton de la quête mène à `calibration.quest`, et `workout.quest` redirige les quêtes de calibrage vers cette route.

- [ ] **Step 1 : test qui échoue**

`tests/Feature/Strength/CalibrationQuestTest.php` :
```php
<?php

use App\Game\Calibration;
use App\Game\CalibrationPlan;
use App\Game\QuestScheduler;
use App\Models\Quest;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-05 10:00'));          // lundi
    $this->user = User::factory()->withMorphology()->create();
    app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
});

it('assigne une quête de calibrage tant que le joueur n\'est pas calibré', function () {
    app(QuestScheduler::class)->sync($this->user, now());

    $quest = Quest::sole();
    expect($quest->type)->toBe('calibration')->and($quest->title)->toBe('Calibrage')
        ->and(array_column($quest->objectives, 'sets'))->toBe([3, 3, 3]);

    $this->actingAs($this->user)->get('/')->assertSee('QUÊTE DU JOUR')->assertSee(route('calibration.quest', $quest));
    $this->get(route('workout.quest', $quest))->assertRedirect(route('calibration.quest', $quest));
});

it('accomplit la quête par le calibrage, puis reprend au premier jour du programme', function () {
    app(QuestScheduler::class)->sync($this->user, now());
    $plan = app(CalibrationPlan::class)->plan($this->user);
    $results = array_map(fn ($m) => ['exercise_id' => $m['exercise_id'], 'warmups' => $m['warmups'], 'test' => [$m['isBodyweight'] ? 0 : 40, 8]], $plan);

    $out = app(Calibration::class)->complete($this->user, $results, now()->toIso8601String());

    expect(Quest::where('type', 'calibration')->value('status'))->toBe('done')
        ->and(collect($out['notifications'])->pluck('title'))->toContain('QUÊTE ACCOMPLIE')
        ->and($this->user->fresh()->streak_days)->toBe(1);

    app(QuestScheduler::class)->sync($this->user, Carbon::parse('2026-10-07'));
    expect(Quest::where('date', '2026-10-07')->value('type'))->toBe('training')
        ->and(Quest::where('date', '2026-10-07')->value('title'))->toBe('Full body A');
});

it('punit un calibrage raté comme une quête d\'entraînement et le repropose', function () {
    app(QuestScheduler::class)->sync($this->user, now());

    $notes = app(QuestScheduler::class)->sync($this->user, Carbon::parse('2026-10-07'));

    expect(collect($notes)->pluck('title'))->toContain('QUÊTE ÉCHOUÉE')
        ->and(Quest::where('date', '2026-10-06')->where('type', 'penalty')->exists())->toBeTrue()
        ->and(Quest::where('date', '2026-10-07')->value('type'))->toBe('calibration');
});
```

- [ ] **Step 2 : vérifier l'échec** → FAIL.

- [ ] **Step 3 : `QuestScheduler`**

Dans `app/Game/QuestScheduler.php` :
- ajouter `private CalibrationPlan $calibrationPlan` à la fin du constructeur ;
- remplacer **toutes** les occurrences de `whereIn('type', ['training', 'rest'])` par `whereIn('type', ['training', 'calibration', 'rest'])` ;
- dans `onWorkout`, remplacer `whereIn('type', ['training', 'penalty'])` par `whereIn('type', ['training', 'calibration', 'penalty'])` ;
- dans `ensureQuest`, remplacer le bloc de création de la quête `training` :
```php
            $programDay = $this->nextProgramDay($user, $program);
            $quest = $user->quests()->create([
                'date' => $day->toDateString(), 'type' => 'training', 'program_day_id' => $programDay->id,
                'title' => $programDay->name, 'objectives' => $programDay->objectives(), 'status' => 'pending',
            ]);
```
par :
```php
            $programDay = $this->nextProgramDay($user, $program);
            $calibrating = $user->calibrated_at === null;
            $quest = $user->quests()->create([
                'date' => $day->toDateString(),
                'type' => $calibrating ? 'calibration' : 'training',
                'program_day_id' => $programDay->id,
                'title' => $calibrating ? 'Calibrage' : $programDay->name,
                'objectives' => $calibrating ? $this->calibrationPlan->objectives($user) : $programDay->objectives(),
                'status' => 'pending',
            ]);
```
- dans `expire`, remplacer `if ($quest->type === 'training') {` par `if (in_array($quest->type, ['training', 'calibration'], true)) {`.

> `nextProgramDay` et `repointLaterQuests` gardent le filtre `'training'` : un calibrage ne fait pas avancer la rotation, et les quêtes de calibrage ne sont pas réassignées.

- [ ] **Step 4 : série, Régularité, accueil, route**

- `app/Game/StreakCalculator.php` : remplacer `in_array($q->type, ['training', 'rest'], true)` par `in_array($q->type, ['training', 'calibration', 'rest'], true)`.
- `app/Game/PlayerStats.php` : remplacer `whereIn('type', ['training', 'rest'])` par `whereIn('type', ['training', 'calibration', 'rest'])`.
- `app/Livewire/Home.php` :
  - remplacer `'quest' => $todayQuests['training'] ?? $todayQuests['rest'] ?? null,` par `'quest' => $todayQuests['training'] ?? $todayQuests['calibration'] ?? $todayQuests['rest'] ?? null,` ;
  - remplacer `'questProgress' => $questProgress($todayQuests['training'] ?? null),` par `'questProgress' => $questProgress($todayQuests['training'] ?? $todayQuests['calibration'] ?? null),`.
- `resources/views/livewire/home.blade.php` :
  - remplacer `@elseif ($quest && $quest->type === 'training')` par `@elseif ($quest && in_array($quest->type, ['training', 'calibration'], true))` ;
  - dans ce bloc, remplacer `:href="route('workout.quest', $quest)">Accepter la quête` par `:href="$quest->type === 'calibration' ? route('calibration.quest', $quest) : route('workout.quest', $quest)">Accepter la quête`.
- `app/Http/Controllers/WorkoutController.php`, méthode `quest`, juste après `abort_unless(...)` :
```php
        if ($quest->type === 'calibration') {
            return redirect()->route('calibration.quest', $quest);
        }
```

- [ ] **Step 5 : vérifier** : `npm run build && ./vendor/bin/pest` → tout PASS. Les tests de quêtes existants mettent `calibrated_at` à vide : leurs programmes produiraient alors des quêtes `calibration`. **Pour garder leur sens** (ils testent l'entraînement), ajouter dans leurs `beforeEach` ou leurs helpers de programme, juste après la construction du programme :
```php
    $user->forceFill(['calibrated_at' => now()])->save();
```
Fichiers concernés : `tests/Feature/Quests/QuestSchedulerTest.php` (`programOn`), `QuestCompletionTest.php`, `SyncQuestsMiddlewareTest.php`, `PauseTest.php`, `QuestUiTest.php` (`withProgram`), `QuestReviewFixesTest.php` (`buildProgram`). Relancer : tout PASS.

- [ ] **Step 6 : commit**

```bash
git add -A
git commit -m "feat: quête CALIBRAGE tant que le joueur n'est pas calibré

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 7 : afficher et pré-remplir la charge prescrite

**Files :**
- Modify :
  - `app/Services/WorkoutBootData.php` (`prescribedKg`), `resources/js/workout/store.js` et `component.js`, `resources/views/workout/run.blade.php` ;
  - `app/Livewire/Home.php`, `resources/views/livewire/home.blade.php` ;
  - `app/Livewire/ProgramPage.php`, `resources/views/livewire/program-page.blade.php` ;
  - `app/Livewire/Profile.php`, `resources/views/livewire/profile.blade.php` ;
  - `app/Services/ProgramBuilder.php` (charges après génération) ;
- Test : `tests/Feature/Strength/PrescribedLoadUiTest.php`, `resources/js/workout/store.test.js`

**Interfaces :**
- Produces :
  - `boot.exercises[].prescribedKg` et `boot.plan[].prescribedKg` ;
  - `addExercise(…, { prescribedKg })`, où l'exercice garde `prescribedKg`, et le brouillon prend `prescribedKg ?? dernière fois ?? suggestion ?? 0` ;
  - après `ProgramBuilder::build`, `LoadPrescriber::prescribeProgram($user)` est appelé si le joueur est calibré ;
  - après `ProgramPage::replace`, le même appel est fait (sans écrasement).

- [ ] **Step 1 : tests qui échouent**

`tests/Feature/Strength/PrescribedLoadUiTest.php` :
```php
<?php

use App\Livewire\ProgramPage;
use App\Models\Exercise;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-05 10:00'));
    $this->user = User::factory()->withMorphology()->create(['calibrated_at' => null]);
    $this->user->forceFill(['calibrated_at' => now(), 'power_factor' => 1.0])->save();
    $this->program = app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    $this->squat = $this->program->days->first()->exercises->firstWhere('slot', 'squat');
    $this->user->workingLoads()->updateOrCreate(['exercise_id' => $this->squat->exercise_id], ['target_kg' => 62.5, 'fail_streak' => 0]);
    $this->actingAs($this->user);
});

it('prescrit les charges dès la génération quand le joueur est calibré', function () {
    expect($this->user->workingLoads()->count())->toBeGreaterThan(1);
});

it('affiche la charge prescrite dans la quête du jour, le programme et la séance', function () {
    $this->get('/')->assertSee('· 62,5 kg', false);
    $this->get('/program')->assertSee('62,5 kg')->assertSee('Recalibrer');

    $quest = $this->user->quests()->where('type', 'training')->first();
    $this->get(route('workout.quest', $quest))->assertSee('"prescribedKg":62.5', false);
});

it('affiche le profil de force dans la fiche du joueur', function () {
    $this->user->exerciseMaxes()->create(['exercise_id' => $this->squat->exercise_id, 'e1rm_kg' => 95, 'source' => 'calibration', 'measured_at' => now()]);

    $this->get('/profile')->assertSee('PROFIL DE FORCE')->assertSee('95 kg');
});

it('prescrit une charge pour un exercice remplacé', function () {
    $front = Exercise::firstWhere('slug', 'front-squat');
    $this->user->workingLoads()->where('exercise_id', $front->id)->delete();

    Livewire::test(ProgramPage::class)->call('replace', $this->squat->id, $front->id);

    expect($this->user->workingLoads()->where('exercise_id', $front->id)->exists())->toBeTrue();
});
```

Ajouter à `resources/js/workout/store.test.js`, dans `describe('séance', …)` :
```js
    it('pré-remplit la charge prescrite avant la dernière fois et la suggestion', () => {
        let s = addExercise(newWorkout('id', 't'), bench, { lastTime: [{ weightKg: 60, reps: 8 }], suggestedKg: 40, prescribedKg: 62.5, restSeconds: 120 });
        expect(s.exercises[0].draft.weightKg).toBe(62.5);
        expect(s.exercises[0].prescribedKg).toBe(62.5);

        s = addExercise(s, bench, { lastTime: [{ weightKg: 60, reps: 8 }], restSeconds: 120 });
        expect(s.exercises[1].draft.weightKg).toBe(60);
        expect(s.exercises[1].prescribedKg).toBeNull();
    });
```

- [ ] **Step 2 : vérifier l'échec** : `./vendor/bin/pest tests/Feature/Strength/PrescribedLoadUiTest.php && npm test` → FAIL.

- [ ] **Step 3 : séance (JS et données)**

`resources/js/workout/store.js`, dans `addExercise` :
- signature : `export function addExercise(state, exercise, { lastTime = null, suggestedKg = null, prescribedKg = null, restSeconds, target = null })` ;
- ajouter `prescribedKg,` dans l'objet poussé (après `target,`) ;
- brouillon : `draft: { weightKg: prescribedKg ?? first?.weightKg ?? suggestedKg ?? 0, reps: first?.reps ?? target?.repsMin ?? 10 },`.

`resources/js/workout/component.js` :
- dans la boucle du plan (dans `init()`), ajouter `prescribedKg: p.prescribedKg ?? null,` aux options passées à `addExercise` ;
- dans `add(exercise)`, ajouter `prescribedKg: exercise.prescribedKg ?? null,` aux options.

`app/Services/WorkoutBootData.php` :
- dans `for()`, avant le `return`, ajouter `$loads = $user->workingLoads()->pluck('target_kg', 'exercise_id');`, et dans le tableau de chaque exercice, ajouter `'prescribedKg' => isset($loads[$e->id]) ? (float) $loads[$e->id] : null,` (la closure doit capturer `$loads` : `fn (Exercise $e) => [...]` le capture automatiquement) ;
- dans `forQuest()`, dans le tableau de chaque élément du plan, ajouter `'prescribedKg' => $library[$o['exercise_id']]['prescribedKg'] ?? null,`.

`resources/views/workout/run.blade.php` : juste après la ligne « Objectif · … » ajoutée par la livraison C, ajouter :
```blade
                            <p class="sys-num mt-1 text-xs uppercase tracking-wider text-ice" x-show="current.prescribedKg"
                               x-text="current.prescribedKg ? 'Charge prescrite par TON EGO · ' + formatKg(current.prescribedKg) : ''"></p>
```

- [ ] **Step 4 : accueil**

`app/Livewire/Home.php` : ajouter au tableau de la vue `'loads' => $user->workingLoads()->pluck('target_kg', 'exercise_id'),`.

`resources/views/livewire/home.blade.php`, dans la fenêtre QUÊTE DU JOUR, remplacer :
```blade
                    <span>{{ $objective['name'] }} · {{ $objective['sets'] }} × {{ $objective['reps_min'] }}–{{ $objective['reps_max'] }}</span>
```
par :
```blade
                    <span>{{ $objective['name'] }} · {{ $objective['sets'] }} × {{ $objective['reps_min'] }}–{{ $objective['reps_max'] }}@if (isset($loads[$objective['exercise_id']]) && $quest->type === 'training') · {{ \App\Support\Format::kg($loads[$objective['exercise_id']]) }}@endif</span>
```

- [ ] **Step 5 : programme**

`app/Services/ProgramBuilder.php` : ajouter `private \App\Game\LoadPrescriber $prescriber` au constructeur (après `$generator`). Puis, dans `build()`, juste avant `return $program->load(...)` dans la transaction, ajouter :
```php
            if ($user->calibrated_at) {
                $this->prescriber->prescribeProgram($user);
            }
```

`app/Livewire/ProgramPage.php`, méthode `replace()`, juste après `$line->update(['exercise_id' => $exerciseId]);`, ajouter :
```php
            app(\App\Game\LoadPrescriber::class)->prescribeProgram($user);
```
et dans `render()`, ajouter au tableau de la vue `'loads' => auth()->user()->workingLoads()->pluck('target_kg', 'exercise_id'),`.

`resources/views/livewire/program-page.blade.php` :
- remplacer `<p class="sys-num text-xs text-mist">{{ $line->sets }} × {{ $line->reps_min }}–{{ $line->reps_max }} · repos {{ $line->rest_seconds }} s</p>` par :
```blade
                            <p class="sys-num text-xs text-mist">{{ $line->sets }} × {{ $line->reps_min }}–{{ $line->reps_max }}@if (isset($loads[$line->exercise_id])) · <span class="text-ice">{{ \App\Support\Format::kg($loads[$line->exercise_id]) }}</span>@endif · repos {{ $line->rest_seconds }} s</p>
```
- remplacer `<x-sys.button variant="ghost" wire:click="regenerate">Régénérer le programme</x-sys.button>` par :
```blade
        <div class="grid grid-cols-2 gap-3">
            <x-sys.button variant="ghost" :href="route('calibration')">Recalibrer</x-sys.button>
            <x-sys.button variant="ghost" wire:click="regenerate">Régénérer le programme</x-sys.button>
        </div>
```

- [ ] **Step 6 : fiche du joueur**

`app/Livewire/Profile.php`, dans `render()`, ajouter au tableau de la vue `'maxes' => $user->exerciseMaxes()->with('exercise')->orderByDesc('e1rm_kg')->get(),`.

`resources/views/livewire/profile.blade.php`, juste **avant** la fenêtre `MODE PAUSE`, ajouter :
```blade
    <x-sys.window title="PROFIL DE FORCE" :index="3">
        @forelse ($maxes as $max)
            <div class="sys-row text-sm">
                <span>{{ $max->exercise->name }}</span>
                <span class="sys-num"><span class="font-bold text-ice">{{ \App\Support\Format::kg($max->e1rm_kg) }}</span> <span class="text-mist">· {{ $max->measured_at->translatedFormat('j M') }}</span></span>
            </div>
        @empty
            <p class="text-sm text-mist">Pas encore calibré : TON EGO ne connaît pas ta puissance.</p>
        @endforelse
        <a href="{{ route('calibration') }}" class="sys-label mt-3 block text-right">{{ $maxes->isEmpty() ? 'Lancer le calibrage' : 'Recalibrer' }}</a>
    </x-sys.window>
```

- [ ] **Step 7 : vérifier** : `npm run build && npm test && ./vendor/bin/pest` → tout PASS.

- [ ] **Step 8 : commit**

```bash
git add -A
git commit -m "feat: charge prescrite dans la quête, la séance, le programme et la fiche

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

### Task 8 : vérification finale

- [ ] **Step 1 : suites** : `npm test && npm run build && ./vendor/bin/pest` → tout PASS.
- [ ] **Step 2 : base locale** : `php artisan migrate --no-interaction`.
- [ ] **Step 3 : parcours réel** avec la sonde jsdom contre le serveur local, en utilisant un joueur temporaire **supprimé à la fin** :
  - programme → calibrage complet → accueil (notification « PUISSANCE MESURÉE ») ;
  - quête du jour avec charges ;
  - séance de quête pré-remplie avec la charge prescrite.
- [ ] **Step 4 : vérification par l'utilisateur** :
  - **Programme** : bouton « Recalibrer » et charges affichées ;
  - **Calibrage** : échauffements puis série test ;
  - **Accueil** : la quête affiche les charges ;
  - **Séance** : « Charge prescrite par TON EGO » ;
  - **Fiche du joueur** : PROFIL DE FORCE.
