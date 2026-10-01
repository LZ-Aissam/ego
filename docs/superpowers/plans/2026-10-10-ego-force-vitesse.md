# EGO — Livraison F « Force × Vitesse » Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Donner à chaque exercice d'un programme une intention (vitesse, force max, explosif, masse) qui fixe la charge, la consigne et la progression ; ajouter 5 exercices explosifs ; installer le programme « Force × Vitesse » d'Aïssam.

**Architecture:** Colonne `program_exercises.intent` et classe `App\Game\Intent` (libellés, consignes, pourcentages). `LoadPrescriber::prescriptionFor()` calcule la charge par intention à partir du meilleur 1RM estimé ; les charges stockées (`working_loads`) ne servent plus qu'aux lignes « masse » ou sans intention. Une commande `ego:programme-force-vitesse {prenom}` installe le programme décrit dans un fichier de données.

**Tech Stack:** Laravel 12, Livewire 3, Alpine, Pest 4 (MariaDB `ego_testing`), Vitest.

**Spec:** `docs/superpowers/specs/2026-10-10-ego-force-vitesse-design.md`

## Global Constraints

- Intentions : `speed` ⚡ VITESSE 57,5 % ; `max` 🔥 FORCE MAX 87,5 % (arrondi vers le bas) ; `explosive` 💥 EXPLOSIF 40 % si chargé, rien au poids du corps ; `mass` 🧱 MASSE calcul actuel ; `null` comportement actuel. Arrondi à 2,5 kg, minimum 2,5 kg.
- Meilleur 1RM estimé = max(`exercise_maxes.e1rm_kg`, `personal_records.best_e1rm_kg`) ; sans les deux : charge déduite × (1 + reps_max / 30).
- Consignes exactes : vitesse « Charge légère : chaque répétition le plus vite possible, arrête la série dès que ça ralentit. » ; force max « Lourd : 1 à 3 répétitions parfaites, repos complet. » ; explosif « Explosion maximale, repos complet entre les séries : la qualité avant la fatigue. » ; masse « Contrôle la descente, tension continue. »
- Conseil programme : « Corps sec : vise 1,8 à 2 g de protéines par kg et un léger déficit si tu veux sécher. »
- Nouvel emplacement `jump` compté bas du corps, absent du générateur.
- Les programmes générés (intent `null`) gardent exactement leur comportement actuel.

## Review Focus

1. Un même exercice présent avec deux intentions (développé couché 🔥 lundi et ⚡ jeudi) : chaque séance prescrit sa propre charge (test Task 2).
2. Exercice au poids du corps en vitesse ou force max (tractions 🔥, dips 🔥) : aucune charge prescrite, aucune erreur (test Task 2).
3. Joueur jamais calibré et sans record : charge vitesse/force calculée depuis la charge déduite, ou rien si on ne sait pas, sans erreur (test Task 2).
4. Séance libre (sans quête) : la charge prescrite reste celle d'aujourd'hui, sans intention (test Task 3).
5. Commande lancée pour un prénom inconnu : message clair, rien de modifié (test Task 5).

---

## File Structure

| Fichier | Rôle |
|---|---|
| `database/migrations/2026_10_10_000100_add_intent_to_program_exercises.php` | Colonne `intent` |
| `app/Game/Intent.php` | Libellés, icônes, consignes, pourcentages |
| `app/Models/ProgramExercise.php`, `app/Models/ProgramDay.php` | `intent` remplissable et dans les objectifs |
| `app/Game/LoadPrescriber.php` | `bestE1rm`, `prescriptionFor`, lignes « à charge stockée » |
| `app/Livewire/Home.php` + vue, `app/Services/WorkoutBootData.php`, `resources/js/workout/store.js`, `resources/views/workout/run.blade.php`, `resources/views/livewire/program-page.blade.php` | Affichage |
| `database/data/exercises.php`, `database/data/exercise_guides.php`, `resources/js/demos/rig.js` | 5 nouveaux exercices |
| `database/data/programs/force-vitesse.php`, `app/Console/Commands/ForceVitesseProgram.php` | Programme d'Aïssam |

---

### Task 1: Intention sur les lignes de programme

**Files:**
- Create: `database/migrations/2026_10_10_000100_add_intent_to_program_exercises.php`, `app/Game/Intent.php`
- Modify: `app/Models/ProgramExercise.php`, `app/Models/ProgramDay.php`
- Test: `tests/Feature/Strength/IntentTest.php`

**Interfaces:**
- Produces: `Intent::META` (clé → `['icon','label','cue','pct']`), `Intent::KEYS`, `Intent::badge(?string): string`, `Intent::cue(?string): ?string` ; `ProgramExercise->intent` ; `ProgramDay::objectives()` ajoute `'intent' => ?string`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Game\Intent;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;

it('décrit chaque intention', function () {
    expect(Intent::KEYS)->toBe(['speed', 'max', 'explosive', 'mass'])
        ->and(Intent::badge('speed'))->toBe('⚡ VITESSE')
        ->and(Intent::badge(null))->toBe('')
        ->and(Intent::cue('max'))->toBe('Lourd : 1 à 3 répétitions parfaites, repos complet.')
        ->and(Intent::META['speed']['pct'])->toBe(0.575);
});

it('porte l\'intention dans les objectifs de la quête', function () {
    $this->seed(ExerciseSeeder::class);
    $user = User::factory()->withMorphology()->create();
    $program = app(ProgramBuilder::class)->build($user, ['level' => 'beginner', 'goal' => 'strength', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    $line = $program->days->first()->exercises->first();
    $line->update(['intent' => 'speed']);

    $objectives = $program->days->first()->fresh()->objectives();
    expect($objectives[0]['intent'])->toBe('speed')->and($objectives[1]['intent'])->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Strength/IntentTest.php`
Expected: FAIL with `Class "App\Game\Intent" not found`.

- [ ] **Step 3: Write the implementation**

Migration :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_exercises', fn (Blueprint $table) => $table->string('intent', 10)->nullable()->after('rest_seconds'));
    }

    public function down(): void
    {
        Schema::table('program_exercises', fn (Blueprint $table) => $table->dropColumn('intent'));
    }
};
```

`app/Game/Intent.php` :

```php
<?php

namespace App\Game;

/** Intention d'un exercice dans un programme : elle fixe la charge, la consigne et la progression. */
final class Intent
{
    public const META = [
        'speed' => ['icon' => '⚡', 'label' => 'VITESSE', 'pct' => 0.575, 'cue' => 'Charge légère : chaque répétition le plus vite possible, arrête la série dès que ça ralentit.'],
        'max' => ['icon' => '🔥', 'label' => 'FORCE MAX', 'pct' => 0.875, 'cue' => 'Lourd : 1 à 3 répétitions parfaites, repos complet.'],
        'explosive' => ['icon' => '💥', 'label' => 'EXPLOSIF', 'pct' => 0.40, 'cue' => 'Explosion maximale, repos complet entre les séries : la qualité avant la fatigue.'],
        'mass' => ['icon' => '🧱', 'label' => 'MASSE', 'pct' => null, 'cue' => 'Contrôle la descente, tension continue.'],
    ];

    public const KEYS = ['speed', 'max', 'explosive', 'mass'];

    public const ADVICE = 'Corps sec : vise 1,8 à 2 g de protéines par kg et un léger déficit si tu veux sécher.';

    public static function badge(?string $intent): string
    {
        return isset(self::META[$intent]) ? self::META[$intent]['icon'].' '.self::META[$intent]['label'] : '';
    }

    public static function cue(?string $intent): ?string
    {
        return self::META[$intent]['cue'] ?? null;
    }
}
```

`ProgramExercise` : ajouter `'intent'` à `$fillable`. `ProgramDay::objectives()` : ajouter `'intent' => $e->intent,` après `'rest_seconds'`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan migrate --env=testing && ./vendor/bin/pest tests/Feature/Strength/IntentTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
php artisan migrate
git add database/migrations/2026_10_10_000100_add_intent_to_program_exercises.php app/Game/Intent.php app/Models/ProgramExercise.php app/Models/ProgramDay.php tests/Feature/Strength/IntentTest.php
git commit -m "feat: intention (vitesse, force max, explosif, masse) sur les lignes de programme"
```

---

### Task 2: Charges et progression selon l'intention

**Files:**
- Modify: `app/Game/LoadPrescriber.php`
- Test: `tests/Feature/Strength/IntentLoadsTest.php`

**Interfaces:**
- Consumes: Task 1.
- Produces: `bestE1rm(User, Exercise, int $repsMax): ?float`, `prescriptionFor(User, Exercise, ?string $intent, int $repsMax): ?float`, `loadLines(User): Collection` (lignes `mass`/`null` seulement, groupées par exercice comme `planLines`). `prescribeProgram` et `progress` utilisent `loadLines`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Game\LoadPrescriber;
use App\Models\Exercise;
use App\Models\User;
use App\Services\ProgramBuilder;
use App\Services\WorkoutSync;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->user = User::factory()->withMorphology(weightKg: 80.0)->create();
    $this->prescriber = app(LoadPrescriber::class);
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
});

it('prend le meilleur 1RM entre calibrage et record', function () {
    $this->user->exerciseMaxes()->create(['exercise_id' => $this->bench->id, 'e1rm_kg' => 100, 'source' => 'calibration', 'measured_at' => now()]);
    expect($this->prescriber->bestE1rm($this->user, $this->bench, 5))->toBe(100.0);

    $this->user->personalRecords()->create(['exercise_id' => $this->bench->id, 'max_weight_kg' => 100, 'max_weight_reps' => 4, 'best_e1rm_kg' => 113.3, 'achieved_at' => now()]);
    expect($this->prescriber->bestE1rm($this->user, $this->bench, 5))->toBe(113.3);
});

it('prescrit selon l\'intention', function () {
    $this->user->exerciseMaxes()->create(['exercise_id' => $this->bench->id, 'e1rm_kg' => 100, 'source' => 'calibration', 'measured_at' => now()]);

    expect($this->prescriber->prescriptionFor($this->user, $this->bench, 'speed', 3))->toBe(57.5)
        ->and($this->prescriber->prescriptionFor($this->user, $this->bench, 'max', 3))->toBe(87.5)
        ->and($this->prescriber->prescriptionFor($this->user, $this->bench, 'explosive', 3))->toBe(40.0)
        ->and($this->prescriber->prescriptionFor($this->user, Exercise::firstWhere('slug', 'pull-up'), 'max', 5))->toBeNull()
        ->and($this->prescriber->prescriptionFor($this->user, Exercise::firstWhere('slug', 'pull-up'), 'explosive', 3))->toBeNull();
});

it('repli sur la charge déduite sans 1RM connu, et rien sans aucune donnée', function () {
    $this->user->forceFill(['power_factor' => 1.0])->save();
    // suggestion débutant 80 kg × 0,5 = 40 kg ; 1RM estimé = 40 × (1 + 5/30) = 46,7 → vitesse 57,5 % = 26,8 → 27,5
    expect($this->prescriber->prescriptionFor($this->user, $this->bench, 'speed', 5))->toBe(27.5)
        ->and($this->prescriber->prescriptionFor(User::factory()->create(), $this->bench, 'speed', 5))->toBeNull();
});

it('ne stocke ni ne fait progresser que les lignes masse ou sans intention', function () {
    $program = app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'strength', 'weekdays' => [1, 3], 'equipment' => 'gym']);
    $lines = $program->days->flatMap->exercises;
    $lines->where('exercise_id', $this->bench->id)->each->update(['intent' => 'speed']);
    $this->user->forceFill(['calibrated_at' => now()])->save();

    $this->prescriber->prescribeProgram($this->user->fresh());

    expect($this->user->workingLoads()->where('exercise_id', $this->bench->id)->exists())->toBeFalse()
        ->and($this->user->workingLoads()->count())->toBeGreaterThan(0);

    $at = now()->toIso8601String();
    $workout = app(WorkoutSync::class)->sync($this->user, ['client_id' => (string) Str::uuid(), 'started_at' => $at, 'finished_at' => $at,
        'sets' => array_fill(0, 5, ['exercise_id' => $this->bench->id, 'weight_kg' => 40, 'reps' => 5, 'done_at' => $at])])['workout'];
    expect($this->prescriber->progress($this->user, $workout))->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Strength/IntentLoadsTest.php`
Expected: FAIL with `Call to undefined method App\Game\LoadPrescriber::bestE1rm()`.

- [ ] **Step 3: Write the implementation**

Dans `app/Game/LoadPrescriber.php`, ajouter :

```php
    /** Meilleur 1RM estimé : calibrage ou record ; sinon charge déduite rapportée à un 1RM (Epley sur repsMax). */
    public function bestE1rm(User $user, Exercise $exercise, int $repsMax): ?float
    {
        $calibrated = $user->exerciseMaxes()->where('exercise_id', $exercise->id)->value('e1rm_kg');
        $record = $user->personalRecords()->where('exercise_id', $exercise->id)->value('best_e1rm_kg');
        $best = max((float) $calibrated, (float) $record);
        if ($best > 0) {
            return round($best, 1);
        }

        $derived = $this->derivedLoad($exercise, $user);

        return $derived === null ? null : round($derived * (1 + $repsMax / 30), 1);
    }

    /** Charge prescrite pour une ligne de programme selon son intention (null : rien à prescrire). */
    public function prescriptionFor(User $user, Exercise $exercise, ?string $intent, int $repsMax): ?float
    {
        if ($exercise->is_bodyweight) {
            return null;
        }

        $pct = \App\Game\Intent::META[$intent]['pct'] ?? null;
        if ($pct === null) {
            return $user->workingLoads()->where('exercise_id', $exercise->id)->value('target_kg');
        }

        $best = $this->bestE1rm($user, $exercise, $repsMax);
        if ($best === null) {
            return null;
        }

        $value = $best * $pct;
        $rounded = $intent === 'max' ? floor($value / 2.5) * 2.5 : round($value / 2.5) * 2.5;

        return max(2.5, $rounded);
    }

    /** Lignes dont la charge est stockée et progresse par les répétitions : intention « masse » ou aucune. */
    public function loadLines(User $user): Collection
    {
        $program = $user->activeProgram()->with('days.exercises.exercise')->first();
        if (! $program) {
            return collect();
        }

        return $program->days->flatMap->exercises
            ->filter(fn (ProgramExercise $l) => $l->intent === null || $l->intent === 'mass')
            ->groupBy('exercise_id')->map(function (Collection $occurrences) {
                $first = clone $occurrences->first();
                $first->reps_max = $occurrences->min('reps_max');

                return $first;
            });
    }
```

Dans `prescribeProgram()`, remplacer `foreach ($this->planLines($user) as $exerciseId => $line)` par `foreach ($this->loadLines($user) as $exerciseId => $line)`. Dans `progress()`, remplacer `$plan = $this->planLines($user);` par `$plan = $this->loadLines($user);`.

Note : `workingLoads()->value('target_kg')` renvoie une chaîne décimale ; caster : `$v = ...->value('target_kg'); return $v === null ? null : (float) $v;`.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Strength`
Expected: PASS (les tests existants restent verts : les programmes générés n'ont pas d'intention).

- [ ] **Step 5: Commit**

```bash
git add app/Game/LoadPrescriber.php tests/Feature/Strength/IntentLoadsTest.php
git commit -m "feat: charges par intention depuis le meilleur 1RM, progression réservée aux lignes masse"
```

---

### Task 3: Affichage de l'intention (accueil, séance, programme)

**Files:**
- Modify: `app/Livewire/Home.php`, `resources/views/livewire/home.blade.php`, `app/Services/WorkoutBootData.php`, `resources/js/workout/store.js`, `resources/js/workout/store.test.js`, `resources/views/workout/run.blade.php`, `app/Livewire/ProgramPage.php`, `resources/views/livewire/program-page.blade.php`
- Test: `tests/Feature/Strength/IntentDisplayTest.php`

**Interfaces:**
- Consumes: Task 1 (`Intent`), Task 2 (`prescriptionFor`).
- Produces: plan items de séance avec `intent`, `intentBadge`, `intentCue`, `prescribedKg` calculé par objectif ; `addExercise` garde `intent`, `intentBadge`, `intentCue`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Strength/IntentDisplayTest.php` :

```php
<?php

use App\Models\Exercise;
use App\Models\User;
use App\Services\ProgramBuilder;
use App\Services\WorkoutBootData;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-12 10:00'));                         // lundi
    $this->user = User::factory()->withMorphology(weightKg: 80.0)->create();
    $this->user->forceFill(['calibrated_at' => now()])->save();
    $this->program = app(ProgramBuilder::class)->build($this->user, ['level' => 'beginner', 'goal' => 'strength', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    $this->bench = Exercise::firstWhere('slug', 'bench-press');
    $this->user->exerciseMaxes()->create(['exercise_id' => $this->bench->id, 'e1rm_kg' => 100, 'source' => 'calibration', 'measured_at' => now()]);
    $this->program->days->first()->exercises->where('exercise_id', $this->bench->id)->each->update(['intent' => 'speed', 'sets' => 8, 'reps_min' => 3, 'reps_max' => 3]);
    $this->actingAs($this->user);
});

it('montre l\'intention et la charge de chaque objectif sur l\'accueil', function () {
    $this->get('/')->assertSee('⚡ Développé couché (barre)')->assertSee('8 × 3–3 · 57,5 kg');
});

it('donne à la séance l\'intention, la consigne et la charge de l\'objectif', function () {
    $quest = $this->user->quests()->where('date', today()->toDateString())->firstOrFail();
    $plan = collect(app(WorkoutBootData::class)->forQuest($this->user, $quest)['plan'])->firstWhere('id', $this->bench->id);

    expect($plan['intent'])->toBe('speed')
        ->and($plan['intentBadge'])->toBe('⚡ VITESSE')
        ->and($plan['intentCue'])->toBe('Charge légère : chaque répétition le plus vite possible, arrête la série dès que ça ralentit.')
        ->and($plan['prescribedKg'])->toBe(57.5);
});

it('garde la charge stockée en séance libre, sans intention', function () {
    $boot = app(WorkoutBootData::class)->for($this->user);
    $bench = collect($boot['exercises'])->firstWhere('id', $this->bench->id);

    expect(array_key_exists('intent', $bench))->toBeFalse();
});

it('affiche l\'intention et le conseil sur la page Programme', function () {
    $this->get(route('program'))->assertSee('⚡ VITESSE')->assertSee('Corps sec : vise 1,8 à 2 g de protéines par kg');
});

it('n\'affiche pas le conseil pour un programme sans intention', function () {
    $this->program->days->flatMap->exercises->each->update(['intent' => null]);
    $this->get(route('program'))->assertDontSee('Corps sec');
});
```

Dans `resources/js/workout/store.test.js`, ajouter :

```js
    it("garde l'intention et sa consigne pour l'écran de séance", () => {
        const s = addExercise(newWorkout('id', 't'), { ...bench, intent: 'speed', intentBadge: '⚡ VITESSE', intentCue: 'Vite !' }, { restSeconds: 60 });
        expect(s.exercises[0].intent).toBe('speed');
        expect(s.exercises[0].intentBadge).toBe('⚡ VITESSE');
        expect(s.exercises[0].intentCue).toBe('Vite !');
    });
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/Strength/IntentDisplayTest.php` puis `npx vitest run resources/js/workout/store.test.js`
Expected: FAIL (texte absent, clés absentes).

- [ ] **Step 3: Write the implementation**

`app/Services/WorkoutBootData.php` — dans `forQuest`, chaque plan item reçoit :

```php
            'intent' => $o['intent'] ?? null,
            'intentBadge' => \App\Game\Intent::badge($o['intent'] ?? null),
            'intentCue' => \App\Game\Intent::cue($o['intent'] ?? null),
            'prescribedKg' => ($o['intent'] ?? null) === null
                ? ($library[$o['exercise_id']]['prescribedKg'] ?? null)
                : app(\App\Game\LoadPrescriber::class)->prescriptionFor($user, \App\Models\Exercise::find($o['exercise_id']), $o['intent'], $o['reps_max']),
```

(remplacer la ligne `'prescribedKg' => $library[...]['prescribedKg'] ?? null,` existante).

`resources/js/workout/store.js` — dans `addExercise`, après `perDumbbell` :

```js
        intent: exercise.intent ?? null,
        intentBadge: exercise.intentBadge ?? '',
        intentCue: exercise.intentCue ?? null,
```

`resources/views/workout/run.blade.php` — juste avant la ligne « Charge prescrite par TON EGO » :

```blade
                            <p class="sys-num mt-1 text-xs font-bold uppercase tracking-wider text-amber" x-show="current.intentBadge" x-text="current.intentBadge"></p>
                            <p class="mt-0.5 text-sm text-mist" x-show="current.intentCue" x-text="current.intentCue"></p>
```

`app/Livewire/Home.php` — passer à la vue `'objectiveLoads'` :

```php
            'objectiveLoads' => collect(($todayQuests['training'] ?? null)?->objectives ?? [])->mapWithKeys(fn ($o, $i) => [$i => ($o['intent'] ?? null) === null
                ? (isset($o['exercise_id']) ? $user->workingLoads()->where('exercise_id', $o['exercise_id'])->value('target_kg') : null)
                : app(\App\Game\LoadPrescriber::class)->prescriptionFor($user, \App\Models\Exercise::find($o['exercise_id']), $o['intent'], $o['reps_max'])]),
```

`resources/views/livewire/home.blade.php` — dans la ligne d'objectif, préfixer le nom par l'icône et utiliser `$objectiveLoads[$i]` à la place de `$loads[$objective['exercise_id']]` :

```blade
<span>{{ isset($objective['intent']) ? \App\Game\Intent::META[$objective['intent']]['icon'].' ' : '' }}{{ $objective['name'] }} · @if ($quest->type === 'calibration')2 échauffements + 1 série max @else{{ $objective['sets'] }} × {{ $objective['reps_min'] }}–{{ $objective['reps_max'] }}@endif@if (($objectiveLoads[$i] ?? null) && $quest->type === 'training') · {{ \App\Support\Format::kg((float) $objectiveLoads[$i]) }}{{ in_array($objective['exercise_id'], $perDumbbell, true) ? ' par haltère' : '' }}@endif</span>
```

`app/Livewire/ProgramPage.php` — passer `'hasIntents' => $program?->days->flatMap->exercises->contains(fn ($l) => $l->intent !== null) ?? false` et `'intentLoads'` (par id de ligne : `prescriptionFor` pour les lignes avec intention, sinon charge stockée).

`resources/views/livewire/program-page.blade.php` — sous le titre du programme : `@if ($hasIntents)<p class="text-xs text-amber">{{ \App\Game\Intent::ADVICE }}</p>@endif` ; dans chaque ligne, avant le nom : `@if ($line->intent)<span class="sys-label !text-amber">{{ \App\Game\Intent::badge($line->intent) }}</span>@endif`, et la charge affichée devient `$intentLoads[$line->id] ?? null`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Feature/Strength tests/Feature/HomeTest.php tests/Feature/Program` et `npx vitest run`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app resources tests
git commit -m "feat: intention, consigne et charge par objectif sur l'accueil, en séance et sur la page Programme"
```

---

### Task 4: Cinq exercices explosifs

**Files:**
- Modify: `database/data/exercises.php`, `database/data/exercise_guides.php`, `app/Game/LoadPrescriber.php` (`LOWER_BODY_SLOTS` + `jump`), `resources/js/demos/rig.js`
- Test: `tests/Feature/Strength/ExplosiveExercisesTest.php` (+ tests JS existants sur tout le catalogue)

**Interfaces:**
- Produces: slugs `push-press`, `clap-push-up`, `box-jump`, `broad-jump`, `jump-squat` ; slot `jump`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Game\LoadPrescriber;
use App\Models\Exercise;
use App\Services\ProgramGenerator;
use App\Support\ExerciseGuide;
use Database\Seeders\ExerciseSeeder;

it('ajoute les exercices explosifs avec leur fiche', function () {
    $this->seed(ExerciseSeeder::class);

    foreach (['push-press', 'clap-push-up', 'box-jump', 'broad-jump', 'jump-squat'] as $slug) {
        expect(Exercise::firstWhere('slug', $slug), $slug)->not->toBeNull()
            ->and(ExerciseGuide::for($slug), $slug)->not->toBeNull();
    }
    expect(Exercise::firstWhere('slug', 'push-press')->is_bodyweight)->toBeFalse()
        ->and(Exercise::firstWhere('slug', 'box-jump')->slots)->toBe(['jump']);
});

it('compte les sauts en bas du corps et ne les met pas dans les programmes générés', function () {
    expect(LoadPrescriber::LOWER_BODY_SLOTS)->toContain('jump');
    foreach (ProgramGenerator::DAY_SLOTS as $slots) {
        expect($slots)->not->toContain('jump');
    }
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Strength/ExplosiveExercisesTest.php` puis `npx vitest run resources/js/demos`
Expected: FAIL (exercices absents) ; les tests JS échoueront dès l'ajout au catalogue tant que les démos n'existent pas.

- [ ] **Step 3: Catalogue, fiches et charges**

`database/data/exercises.php`, à la fin du tableau :

```php
    // Puissance et explosivité
    ['push-press', 'Développé poussé (barre)', ['épaules', 'triceps', 'jambes'], ['gym'], 'compound', ['vertical_push'], 0.4],
    ['clap-push-up', 'Pompes claquées', ['pectoraux', 'triceps', 'épaules'], ['bodyweight', 'dumbbells', 'gym'], 'compound', ['horizontal_push'], null],
    ['box-jump', 'Sauts sur box', ['quadriceps', 'fessiers', 'mollets'], ['bodyweight', 'dumbbells', 'gym'], 'compound', ['jump'], null],
    ['broad-jump', 'Saut en longueur', ['fessiers', 'quadriceps', 'ischio-jambiers'], ['bodyweight', 'dumbbells', 'gym'], 'compound', ['jump'], null],
    ['jump-squat', 'Squat sauté', ['quadriceps', 'fessiers', 'mollets'], ['bodyweight', 'dumbbells', 'gym'], 'compound', ['jump'], null],
```

`LoadPrescriber::LOWER_BODY_SLOTS` : ajouter `'jump'`.

`database/data/exercise_guides.php`, à la fin :

```php
    // Puissance et explosivité
    'push-press' => [
        'muscles' => 'Épaules · triceps · jambes',
        'tempo' => 'Impulsion explosive · Descente 2 s',
        'steps' => [
            'Debout, barre posée en haut de la poitrine, coudes légèrement devant.',
            'Fléchis légèrement les genoux, buste droit, sans t\'asseoir.',
            'Pousse le sol avec les jambes et enchaîne la poussée des bras d\'un seul geste explosif.',
            'Redescends la barre en contrôle sur la poitrine.',
        ],
        'mistake' => 'Plier les genoux trop bas ou se pencher en avant pendant l\'impulsion.',
    ],
    'clap-push-up' => [
        'muscles' => 'Pectoraux · triceps · épaules',
        'tempo' => 'Descente contrôlée · Poussée explosive',
        'steps' => [
            'Position de pompe, corps gainé.',
            'Descends en contrôle, poitrine près du sol.',
            'Pousse le sol si fort que les mains décollent, frappe dans les mains.',
            'Réceptionne-toi bras souples et enchaîne.',
        ],
        'mistake' => 'Atterrir bras tendus et verrouillés : risque pour les coudes.',
    ],
    'box-jump' => [
        'muscles' => 'Quadriceps · fessiers · mollets',
        'tempo' => 'Impulsion explosive · Réception souple',
        'steps' => [
            'Face à une box stable, pieds largeur de hanches.',
            'Fléchis rapidement hanches et genoux en lançant les bras en arrière.',
            'Saute en lançant les bras vers l\'avant et pose les deux pieds entiers sur la box.',
            'Redescends en marchant, ne saute pas en arrière.',
        ],
        'mistake' => 'Choisir une box trop haute et atterrir en squat profond.',
    ],
    'broad-jump' => [
        'muscles' => 'Fessiers · quadriceps · ischio-jambiers',
        'tempo' => 'Impulsion explosive · Réception stable',
        'steps' => [
            'Pieds largeur de hanches, bras tendus devant.',
            'Lance les bras en arrière en fléchissant les hanches.',
            'Projette-toi le plus loin possible vers l\'avant en tendant hanches et genoux.',
            'Réceptionne-toi genoux fléchis, stable, puis reviens en marchant.',
        ],
        'mistake' => 'Genoux qui rentrent vers l\'intérieur à la réception.',
    ],
    'jump-squat' => [
        'muscles' => 'Quadriceps · fessiers · mollets',
        'tempo' => 'Descente rapide · Saut maximal',
        'steps' => [
            'Pieds largeur d\'épaules, mains sur les hanches ou devant toi.',
            'Descends en quart de squat, rapidement.',
            'Saute le plus haut possible en tendant hanches, genoux et chevilles.',
            'Réceptionne-toi souplement et enchaîne.',
        ],
        'mistake' => 'Atterrir jambes tendues et talons en premier.',
    ],
```

- [ ] **Step 4: Animations**

Dans `resources/js/demos/rig.js` :

1. `ohp` — nouvelle variante `push` : flexion-extension des jambes avant la poussée.

```js
            if (v === 'push') {
                const dip = t < 0.25 ? Math.sin((t / 0.25) * Math.PI / 2) : Math.max(0, 1 - (t - 0.25) / 0.25);
                const b = standing(2 + 14 * dip, 16 * dip, 3);
                const press = Math.min(1, Math.max(0, (t - 0.3) / 0.7));
                const hand = lerpP(shift(b.shoulder, 12, -2), shift(b.shoulder, 4, -48), press);
                return scene(
                    { leg: [b.ankle, b.knee, b.hip], foot: b.foot, torso: [b.hip, b.shoulder], arm: arm(b.shoulder, hand, 1) },
                    headOf(b.hip, b.shoulder), [plate(hand, 12)], [], [guide([10, -184], [10, -120], 'axe de la barre')],
                );
            }
```

(à placer en tête de `ohp.pose`), et les segments de `ohp` restent ceux du développé. Catalogue : `'push-press': ['ohp', 'push']`.

2. `pushup` — variante `clap` : en haut, les mains décollent et se rejoignent devant la poitrine.

```js
        pose(t, v) {
            const ankle = [-70, -3];
            const clap = v === 'clap';
            const height = lerp(clap ? 58 : 44, 14, t);
            const shoulder = [-70 + Math.sqrt(130 ** 2 - (height - 3) ** 2), -height];
            const u = [(shoulder[0] - ankle[0]) / 130, (shoulder[1] - ankle[1]) / 130];
            const at = (k) => [ankle[0] + u[0] * k, ankle[1] + u[1] * k];
            const lift = clap ? Math.max(0, height - 44) : 0;
            return scene(
                { body: [ankle, at(40), at(80), shoulder], foot: [ankle, [-73, 0]], arm: arm(shoulder, [55 - lift * 0.6, -lift], 1) },
                add(shoulder, L.head, angleOf(ankle, shoulder)),
            );
        },
```

Catalogue : `'clap-push-up': ['pushup', 'clap']`.

3. Nouvelle famille `jump` (variantes `box`, `broad`, `squat`) :

```js
    jump: {
        segments: [['Flexion et élan', 600], ['Envol', 300], ['Réception souple', 900], ['Stabilisation', 600]],
        pose(t, v) {
            const crouch = t < 0.35 ? Math.sin((t / 0.35) * Math.PI / 2) : Math.max(0, 1 - (t - 0.35) / 0.25);
            const air = t < 0.35 ? 0 : 0.5 - 0.5 * Math.cos(Math.PI * Math.min(1, (t - 0.35) / 0.65));
            const x = { box: 62 * air, broad: -45 + 95 * air, squat: 0 }[v];
            const lift = { box: 30 * air, broad: 34 * Math.sin(Math.PI * air), squat: 40 * Math.sin(Math.PI * air) }[v];
            const b = standing(4 + 30 * crouch, 50 * crouch, 6 + 30 * crouch, x);
            const up = (p) => [p[0], p[1] - lift];
            const swing = lerp(-120, 70, Math.max(air, 0)) - 40 * crouch;
            const shoulder = up(b.shoulder);
            return scene(
                { foot: b.foot.map(up), leg: [b.ankle, b.knee, b.hip].map(up), torso: [up(b.hip), shoulder], arm: fixedArm(shoulder, swing, swing) },
                headOf(up(b.hip), shoulder), [], v === 'box' ? [rect(40, -30, 50, 30)] : [],
            );
        },
    },
```

Catalogue : `'box-jump': ['jump', 'box']`, `'broad-jump': ['jump', 'broad']`, `'jump-squat': ['jump', 'squat']`.

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Feature/Strength tests/Feature/Demos tests/Feature/ExerciseSeederTest.php` et `npx vitest run`
Expected: PASS ; si un test JS de cadre ou de longueur échoue, ajuster les amplitudes (`lift`, `dx`) jusqu'à rester dans le cadre `VIEWBOX`, puis vérifier visuellement les 5 animations dans Chrome (mémoire « vérifier l'UI dans un vrai Chrome »).

- [ ] **Step 6: Commit**

```bash
php artisan db:seed --class=ExerciseSeeder
git add database/data app/Game/LoadPrescriber.php resources/js/demos tests/Feature/Strength/ExplosiveExercisesTest.php
git commit -m "feat: développé poussé, pompes claquées, sauts sur box, saut en longueur et squat sauté"
```

---

### Task 5: Programme « Force × Vitesse » et installation pour Aïssam

**Files:**
- Create: `database/data/programs/force-vitesse.php`, `app/Console/Commands/ForceVitesseProgram.php`
- Test: `tests/Feature/Strength/ForceVitesseProgramTest.php`

**Interfaces:**
- Consumes: Tasks 1, 2, 4.
- Produces: commande `ego:programme-force-vitesse {prenom}`.

- [ ] **Step 1: Write the failing test**

```php
<?php

use App\Models\User;
use Database\Seeders\ExerciseSeeder;

beforeEach(fn () => $this->seed(ExerciseSeeder::class));

it('installe le programme Force × Vitesse et l\'active', function () {
    $user = User::factory()->withMorphology(level: 'advanced')->create(['first_name' => 'Aïssam']);

    $this->artisan('ego:programme-force-vitesse', ['prenom' => 'Aïssam'])->assertSuccessful();

    $program = $user->fresh()->activeProgram()->with('days.exercises.exercise')->first();
    $days = $program->days->map(fn ($d) => [$d->name, $d->exercises->map(fn ($l) => [$l->exercise->slug, $l->intent, $l->sets, $l->reps_min, $l->reps_max, $l->rest_seconds])->all()])->all();

    expect($program->name)->toBe('Programme Force × Vitesse · 5 j')
        ->and($user->fresh()->training_weekdays)->toBe([1, 2, 3, 4, 5])
        ->and($days)->toBe(require database_path('data/programs/force-vitesse.php'));
});

it('refuse un prénom inconnu sans rien modifier', function () {
    $this->artisan('ego:programme-force-vitesse', ['prenom' => 'Personne'])
        ->expectsOutput('Aucun joueur nommé « Personne ».')->assertFailed();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Strength/ForceVitesseProgramTest.php`
Expected: FAIL (commande inconnue).

- [ ] **Step 3: Data and command**

`database/data/programs/force-vitesse.php` (format `[nom du jour, [[slug, intention, séries, reps min, reps max, repos], …]]`, conforme au tableau de la spec §5) :

```php
<?php

return [
    ['Poussée · FORCE MAX', [
        ['clap-push-up', 'explosive', 4, 3, 3, 120], ['bench-press', 'max', 5, 2, 3, 240], ['incline-dumbbell-press', 'mass', 4, 6, 8, 120],
        ['dips', 'max', 4, 3, 5, 180], ['skull-crusher', 'mass', 3, 8, 10, 90], ['cable-crunch', 'mass', 3, 8, 12, 60],
    ]],
    ['Tirage · FORCE MAX', [
        ['pull-up', 'explosive', 4, 3, 3, 120], ['barbell-row', 'max', 5, 3, 5, 180], ['chin-up', 'max', 4, 3, 5, 180],
        ['dumbbell-row', 'mass', 3, 6, 8, 120], ['barbell-curl', 'mass', 4, 6, 8, 90], ['hammer-curl', 'mass', 3, 8, 10, 90],
        ['hanging-leg-raise', 'mass', 3, 8, 12, 60],
    ]],
    ['Épaules · PUISSANCE', [
        ['push-press', 'explosive', 5, 3, 3, 150], ['overhead-press', 'max', 5, 3, 5, 180], ['lateral-raise', 'mass', 3, 10, 12, 60],
        ['rear-delt-fly', 'mass', 3, 10, 12, 60], ['face-pull', 'mass', 3, 12, 15, 60], ['crunch', 'mass', 3, 10, 15, 60],
    ]],
    ['Haut du corps · VITESSE', [
        ['bench-press', 'speed', 8, 3, 3, 60], ['barbell-row', 'speed', 6, 3, 3, 60], ['diamond-push-up', 'explosive', 4, 5, 5, 90],
        ['dips', 'mass', 3, 8, 10, 90], ['hammer-curl', 'mass', 3, 8, 10, 60], ['triceps-pushdown', 'mass', 3, 10, 12, 60],
        ['hanging-leg-raise', 'mass', 3, 8, 12, 60],
    ]],
    ['Jambes & hanches · EXPLOSIF', [
        ['box-jump', 'explosive', 5, 3, 3, 120], ['broad-jump', 'explosive', 4, 3, 3, 120], ['back-squat', 'speed', 8, 2, 2, 60],
        ['deadlift', 'speed', 6, 2, 2, 90], ['jump-squat', 'explosive', 3, 5, 5, 120], ['hip-thrust', 'max', 4, 3, 5, 180],
        ['cable-crunch', 'mass', 3, 8, 12, 60],
    ]],
];
```

`app/Console/Commands/ForceVitesseProgram.php` :

```php
<?php

namespace App\Console\Commands;

use App\Game\LoadPrescriber;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ForceVitesseProgram extends Command
{
    protected $signature = 'ego:programme-force-vitesse {prenom}';

    protected $description = 'Installe le programme Force × Vitesse (4 jours haut du corps, vendredi jambes explosives) et l\'active';

    public function handle(LoadPrescriber $prescriber): int
    {
        $user = User::where('first_name', $this->argument('prenom'))->first();
        if (! $user) {
            $this->error('Aucun joueur nommé « '.$this->argument('prenom').' ».');

            return self::FAILURE;
        }

        $exercises = Exercise::all()->keyBy('slug');
        $days = require database_path('data/programs/force-vitesse.php');

        $program = DB::transaction(function () use ($user, $exercises, $days) {
            $program = $user->programs()->create([
                'name' => 'Programme Force × Vitesse · 5 j',
                'split' => 'custom',
                'params' => ['level' => $user->level ?? 'advanced', 'goal' => 'strength', 'equipment' => 'gym', 'weekdays' => [1, 2, 3, 4, 5], 'custom' => true],
            ]);
            foreach ($days as $i => [$name, $lines]) {
                $day = $program->days()->create(['position' => $i + 1, 'name' => $name, 'type' => 'custom']);
                foreach ($lines as $j => [$slug, $intent, $sets, $min, $max, $rest]) {
                    $day->exercises()->create([
                        'exercise_id' => $exercises[$slug]->id, 'slot' => $exercises[$slug]->slots[0], 'position' => $j + 1,
                        'intent' => $intent, 'sets' => $sets, 'reps_min' => $min, 'reps_max' => $max, 'rest_seconds' => $rest,
                    ]);
                }
            }
            $user->forceFill(['active_program_id' => $program->id, 'goal' => 'strength', 'equipment' => 'gym', 'training_weekdays' => [1, 2, 3, 4, 5], 'days_per_week' => 5])->save();

            return $program;
        });

        if ($user->calibrated_at) {
            $prescriber->prescribeProgram($user->fresh());
        }

        $this->info('Programme activé pour '.$user->first_name.' : '.$program->name);

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Strength/ForceVitesseProgramTest.php` puis `./vendor/bin/pest` et `npx vitest run`
Expected: PASS.

- [ ] **Step 5: Commit, install for Aïssam, real check**

```bash
git add database/data/programs app/Console/Commands/ForceVitesseProgram.php tests/Feature/Strength/ForceVitesseProgramTest.php
git commit -m "feat: commande ego:programme-force-vitesse et programme Force × Vitesse"
npm run build
php artisan migrate
php artisan db:seed --class=ExerciseSeeder
php artisan ego:programme-force-vitesse Aïssam
```

Vérification réelle : capture Chrome (puppeteer-core dans un dossier à part) de la page Programme d'Aïssam — impossible sans son mot de passe : utiliser un joueur temporaire installé avec la même commande, capturer la page Programme (badges, conseil) et la démo d'un nouvel exercice, puis supprimer le joueur temporaire.
