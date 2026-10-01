# Objectif Force × Vitesse dans le questionnaire — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tout joueur peut choisir l'objectif « Force × Vitesse » dans le questionnaire et obtenir un programme à intentions adapté à son niveau, son matériel et son nombre de jours.

**Architecture:** Un modèle de séances (`database/data/programs/power-templates.php`) décrit chaque séance par lignes `[emplacement, intention, séries, reps min, reps max, repos, slugs préférés]`. Une classe `App\Services\PowerProgram` choisit les exercices selon le matériel, applique les adaptations (niveau, poids du corps) et rend le même format que `ProgramGenerator::generate`, qui lui délègue quand l'objectif vaut `power`. `ProgramBuilder`, `ProgramPage` et `QuestScheduler` acceptent ensuite ce nouvel objectif.

**Tech Stack:** Laravel 12, Livewire 3, Pest 4 sur MariaDB (`ego_testing`).

**Spec:** `docs/superpowers/specs/2026-10-11-ego-force-vitesse-questionnaire-design.md`

## Global Constraints

- Valeur d'objectif `power`, libellé « Force × Vitesse » ; programme « Programme Force × Vitesse · N j », `split` = `power`.
- 5 j, salle complète, avancé = `database/data/programs/force-vitesse.php` ligne pour ligne (slug, intention, séries, reps min, reps max, repos, ordre).
- Les objectifs strength, hypertrophy, fitness génèrent exactement comme avant.
- La commande `ego:programme-force-vitesse` et `force-vitesse.php` ne changent pas.
- Lignes par séance : ≤ 5 (débutant), 6 (intermédiaire), 7 (avancé) ; abdos toujours en dernier.
- Poids du corps : 🔥 sur exercice sans charge → 🧱 5–8 ; ⚡ sur exercice sans charge → 💥. Débutant : 🔥 → 🧱 4–6, séries − 1 (min 3).

## Review Focus

- Haltères seuls : aucun exercice réservé à la salle dans un programme power (test Task 1).
- Programme power copié par un ami : ses séances restent attachées aux jours de la semaine (test Task 2).
- Régénérer en « Prise de muscle » après un programme power : plus aucune intention (test Task 2).
- Une ligne dont aucun exercice n'est disponible est omise sans casser la séance (couvert par le test poids du corps de Task 1 : pas de ligne vide, pas d'id nul).
- Remplacer un exercice d'une ligne power garde son intention (comportement existant de `ProgramPage::replace`, qui ne modifie que `exercise_id`).

---

### Task 1: Générateur Force × Vitesse

**Files:**
- Create: `database/data/programs/power-templates.php`
- Create: `app/Services/PowerProgram.php`
- Modify: `app/Services/ProgramGenerator.php` (début de `generate`)
- Test: `tests/Feature/Program/PowerProgramTest.php`

**Interfaces:**
- Produces: `PowerProgram::generate(array $params, Collection $exercises): array` → `['split' => 'power', 'days' => list<['name' => string, 'type' => 'custom', 'exercises' => list<['exercise_id' => int, 'slot' => string, 'intent' => string, 'sets' => int, 'reps_min' => int, 'reps_max' => int, 'rest_seconds' => int]>]>]` ; `$params` = `['level', 'goal' => 'power', 'days' => 2..6, 'equipment']`.
- `ProgramGenerator::generate($params, $exercises)` renvoie ce résultat quand `$params['goal'] === 'power'`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Program/PowerProgramTest.php` :

```php
<?php

use App\Models\Exercise;
use App\Services\ProgramGenerator;
use Database\Seeders\ExerciseSeeder;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->exercises = Exercise::all();
    $this->byId = $this->exercises->keyBy('id');
    $this->power = fn (array $over = []) => (new ProgramGenerator)->generate([...['level' => 'advanced', 'goal' => 'power', 'days' => 5, 'equipment' => 'gym'], ...$over], $this->exercises);
});

it('reproduit exactement le programme Force × Vitesse en 5 jours, salle, avancé', function () {
    $program = ($this->power)();
    $expected = require database_path('data/programs/force-vitesse.php');

    expect($program['split'])->toBe('power')
        ->and(array_column($program['days'], 'name'))->toBe(array_column($expected, 0));

    foreach ($expected as $i => [, $lines]) {
        $got = array_map(fn ($l) => [$this->byId[$l['exercise_id']]->slug, $l['intent'], $l['sets'], $l['reps_min'], $l['reps_max'], $l['rest_seconds']], $program['days'][$i]['exercises']);
        expect($got)->toBe($lines);
    }
});

it('enchaîne les séances selon le nombre de jours, jambes explosives en dernier', function (int $days, array $names) {
    expect(array_column(($this->power)(['days' => $days])['days'], 'name'))->toBe($names);
})->with([
    [2, ['Haut du corps · FORCE MAX', 'Jambes & hanches · EXPLOSIF']],
    [3, ['Poussée · FORCE MAX', 'Tirage · FORCE MAX', 'Jambes & hanches · EXPLOSIF']],
    [4, ['Poussée · FORCE MAX', 'Tirage · FORCE MAX', 'Haut du corps · VITESSE', 'Jambes & hanches · EXPLOSIF']],
    [6, ['Poussée · FORCE MAX', 'Tirage · FORCE MAX', 'Jambes · FORCE MAX', 'Épaules · PUISSANCE', 'Haut du corps · VITESSE', 'Jambes & hanches · EXPLOSIF']],
]);

it('limite les lignes selon le niveau et garde les abdos en dernier', function (string $level, int $max) {
    foreach ([2, 3, 4, 5, 6] as $days) {
        foreach (($this->power)(['level' => $level, 'days' => $days])['days'] as $day) {
            expect(count($day['exercises']))->toBeLessThanOrEqual($max)
                ->and(end($day['exercises'])['slot'])->toBe('core');
        }
    }
})->with([['beginner', 5], ['intermediate', 6], ['advanced', 7]]);

it('ne donne pas de force max 87,5 % à un débutant', function () {
    $days = ($this->power)(['level' => 'beginner', 'days' => 6])['days'];
    $lines = collect($days)->flatMap(fn ($d) => $d['exercises']);

    expect($lines->pluck('intent')->all())->not->toContain('max')
        ->and(collect(end($days)['exercises'])->firstWhere('slot', 'squat'))->toMatchArray(['intent' => 'speed']);
});

it('respecte le matériel, sans doublon dans une séance', function (string $equipment) {
    foreach ([2, 3, 4, 5, 6] as $days) {
        foreach (($this->power)(['equipment' => $equipment, 'days' => $days])['days'] as $day) {
            $ids = array_column($day['exercises'], 'exercise_id');
            expect($ids)->not->toBeEmpty()->and($ids)->toBe(array_values(array_unique($ids)));
            foreach ($ids as $id) {
                expect($this->byId[$id]->equipment)->toContain($equipment);
            }
        }
    }
})->with(['dumbbells', 'bodyweight']);

it('au poids du corps, aucune force max ni vitesse sur un exercice sans charge', function () {
    foreach (($this->power)(['equipment' => 'bodyweight', 'days' => 6])['days'] as $day) {
        foreach ($day['exercises'] as $line) {
            if ($this->byId[$line['exercise_id']]->is_bodyweight) {
                expect($line['intent'])->not->toBeIn(['max', 'speed']);
            }
        }
    }
});

it('laisse les autres objectifs sans intention', function () {
    $program = (new ProgramGenerator)->generate(['level' => 'advanced', 'goal' => 'strength', 'days' => 5, 'equipment' => 'gym'], $this->exercises);

    expect($program['split'])->toBe('ppl_ul')
        ->and(collect($program['days'])->flatMap(fn ($d) => $d['exercises'])->pluck('intent')->filter()->all())->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Program/PowerProgramTest.php`
Expected: FAIL — `ProgramGenerator` lève « Undefined array key "power" » (aucun modèle power).

- [ ] **Step 3: Write the template**

`database/data/programs/power-templates.php` :

```php
<?php

// Objectif Force × Vitesse : séances par rôle. Ligne = [emplacement, intention, séries, reps min, reps max, repos s, slugs préférés].
// Ordre des lignes = ordre d'exécution : explosif → force max ou vitesse → masse → abdos.

return [
    'sessions' => [
        'push' => ['Poussée · FORCE MAX', [
            ['horizontal_push', 'explosive', 4, 3, 3, 120, ['clap-push-up']],
            ['horizontal_push', 'max', 5, 2, 3, 240, ['bench-press', 'dumbbell-bench-press', 'push-up']],
            ['horizontal_push', 'mass', 4, 6, 8, 120, ['incline-dumbbell-press', 'dumbbell-bench-press', 'push-up']],
            ['triceps', 'max', 4, 3, 5, 180, ['dips', 'bench-dips']],
            ['triceps', 'mass', 3, 8, 10, 90, ['skull-crusher', 'overhead-dumbbell-extension', 'diamond-push-up']],
            ['core', 'mass', 3, 8, 12, 60, ['cable-crunch', 'crunch']],
        ]],
        'pull' => ['Tirage · FORCE MAX', [
            ['vertical_pull', 'explosive', 4, 3, 3, 120, ['pull-up']],
            ['horizontal_pull', 'max', 5, 3, 5, 180, ['barbell-row', 'dumbbell-row', 'inverted-row']],
            ['vertical_pull', 'max', 4, 3, 5, 180, ['chin-up']],
            ['horizontal_pull', 'mass', 3, 6, 8, 120, ['dumbbell-row', 'chest-supported-row', 'inverted-row']],
            ['biceps', 'mass', 4, 6, 8, 90, ['barbell-curl', 'dumbbell-curl']],
            ['biceps', 'mass', 3, 8, 10, 90, ['hammer-curl']],
            ['core', 'mass', 3, 8, 12, 60, ['hanging-leg-raise', 'crunch']],
        ]],
        'shoulders' => ['Épaules · PUISSANCE', [
            ['vertical_push', 'explosive', 5, 3, 3, 150, ['push-press', 'dumbbell-shoulder-press', 'pike-push-up']],
            ['vertical_push', 'max', 5, 3, 5, 180, ['overhead-press', 'dumbbell-shoulder-press', 'pike-push-up']],
            ['shoulder_iso', 'mass', 3, 10, 12, 60, ['lateral-raise']],
            ['shoulder_iso', 'mass', 3, 10, 12, 60, ['rear-delt-fly']],
            ['shoulder_iso', 'mass', 3, 12, 15, 60, ['face-pull']],
            ['core', 'mass', 3, 10, 15, 60, ['crunch']],
        ]],
        'speed' => ['Haut du corps · VITESSE', [
            ['horizontal_push', 'speed', 8, 3, 3, 60, ['bench-press', 'dumbbell-bench-press', 'clap-push-up']],
            ['horizontal_pull', 'speed', 6, 3, 3, 60, ['barbell-row', 'dumbbell-row', 'inverted-row']],
            ['triceps', 'explosive', 4, 5, 5, 90, ['diamond-push-up', 'clap-push-up']],
            ['triceps', 'mass', 3, 8, 10, 90, ['dips', 'bench-dips']],
            ['biceps', 'mass', 3, 8, 10, 60, ['hammer-curl', 'dumbbell-curl']],
            ['triceps', 'mass', 3, 10, 12, 60, ['triceps-pushdown', 'overhead-dumbbell-extension']],
            ['core', 'mass', 3, 8, 12, 60, ['hanging-leg-raise', 'crunch']],
        ]],
        'upper' => ['Haut du corps · FORCE MAX', [
            ['horizontal_push', 'explosive', 4, 3, 3, 120, ['clap-push-up']],
            ['horizontal_push', 'max', 5, 2, 3, 240, ['bench-press', 'dumbbell-bench-press', 'push-up']],
            ['horizontal_pull', 'max', 5, 3, 5, 180, ['barbell-row', 'dumbbell-row', 'inverted-row']],
            ['vertical_push', 'max', 4, 3, 5, 180, ['overhead-press', 'dumbbell-shoulder-press', 'pike-push-up']],
            ['vertical_pull', 'max', 4, 3, 5, 180, ['chin-up', 'pull-up']],
            ['biceps', 'mass', 3, 8, 10, 90, ['hammer-curl', 'dumbbell-curl']],
            ['core', 'mass', 3, 8, 12, 60, ['hanging-leg-raise', 'crunch']],
        ]],
        'legs_max' => ['Jambes · FORCE MAX', [
            ['jump', 'explosive', 3, 3, 3, 120, ['jump-squat']],
            ['squat', 'max', 5, 2, 3, 240, ['back-squat', 'goblet-squat', 'bodyweight-squat']],
            ['hinge', 'max', 4, 2, 3, 240, ['deadlift', 'dumbbell-romanian-deadlift', 'glute-bridge']],
            ['lunge', 'mass', 3, 6, 8, 120, ['bulgarian-split-squat', 'bodyweight-lunge']],
            ['hamstrings_iso', 'mass', 3, 6, 10, 90, ['leg-curl', 'nordic-curl']],
            ['calves', 'mass', 3, 10, 12, 60, ['standing-calf-raise', 'dumbbell-calf-raise', 'bodyweight-calf-raise']],
            ['core', 'mass', 3, 8, 12, 60, ['cable-crunch', 'crunch']],
        ]],
        'legs_explosive' => ['Jambes & hanches · EXPLOSIF', [
            ['jump', 'explosive', 5, 3, 3, 120, ['box-jump']],
            ['jump', 'explosive', 4, 3, 3, 120, ['broad-jump']],
            ['squat', 'speed', 8, 2, 2, 60, ['back-squat', 'goblet-squat', 'bodyweight-squat']],
            ['hinge', 'speed', 6, 2, 2, 90, ['deadlift', 'dumbbell-romanian-deadlift', 'glute-bridge']],
            ['jump', 'explosive', 3, 5, 5, 120, ['jump-squat']],
            ['hinge', 'max', 4, 3, 5, 180, ['hip-thrust', 'glute-bridge']],
            ['core', 'mass', 3, 8, 12, 60, ['cable-crunch', 'crunch']],
        ]],
    ],
    // Séances dans l'ordre de la semaine ; les jambes explosives toujours en dernier.
    'weeks' => [
        2 => ['upper', 'legs_explosive'],
        3 => ['push', 'pull', 'legs_explosive'],
        4 => ['push', 'pull', 'speed', 'legs_explosive'],
        5 => ['push', 'pull', 'shoulders', 'speed', 'legs_explosive'],
        6 => ['push', 'pull', 'legs_max', 'shoulders', 'speed', 'legs_explosive'],
    ],
];
```

- [ ] **Step 4: Write `PowerProgram` and the delegation**

`app/Services/PowerProgram.php` :

```php
<?php

namespace App\Services;

use App\Models\Exercise;
use Illuminate\Support\Collection;

/** Objectif Force × Vitesse : séances à intentions tirées de power-templates.php, adaptées au niveau et au matériel. */
final class PowerProgram
{
    public function generate(array $params, Collection $exercises): array
    {
        $templates = require database_path('data/programs/power-templates.php');
        $limit = ProgramGenerator::SLOTS_BY_LEVEL[$params['level']] ?? 5;
        $days = [];

        foreach ($templates['weeks'][$params['days']] as $key) {
            [$name, $lines] = $templates['sessions'][$key];
            $used = [];
            $main = [];
            $core = [];

            foreach ($lines as [$slot, $intent, $sets, $min, $max, $rest, $preferred]) {
                $pick = $this->pick($slot, $preferred, $params['equipment'], $exercises, $used);
                if (! $pick) {
                    continue;
                }
                $used[] = $pick->id;
                $item = $this->adapt(['exercise_id' => $pick->id, 'slot' => $slot, 'intent' => $intent, 'sets' => $sets, 'reps_min' => $min, 'reps_max' => $max, 'rest_seconds' => $rest], $pick, $params['level'], $params['equipment']);
                $slot === 'core' ? $core[] = $item : $main[] = $item;
            }

            $days[] = ['name' => $name, 'type' => 'custom', 'exercises' => [...array_slice($main, 0, max(0, $limit - count($core))), ...$core]];
        }

        return ['split' => 'power', 'days' => $days];
    }

    /** Premier slug préféré disponible, sinon le premier exercice de l'emplacement pour ce matériel. */
    private function pick(string $slot, array $preferred, string $equipment, Collection $exercises, array $used): ?Exercise
    {
        $fits = fn (Exercise $e) => in_array($equipment, $e->equipment, true) && ! in_array($e->id, $used, true);

        foreach ($preferred as $slug) {
            $exercise = $exercises->firstWhere('slug', $slug);
            if ($exercise && $fits($exercise)) {
                return $exercise;
            }
        }

        return $exercises->filter(fn (Exercise $e) => in_array($slot, $e->slots, true) && $fits($e))->sortBy('id')->first();
    }

    private function adapt(array $item, Exercise $exercise, string $level, string $equipment): array
    {
        // Sans charge, force max et vitesse n'ont pas de sens : séries lourdes en répétitions, ou explosivité.
        if ($equipment === 'bodyweight' && $exercise->is_bodyweight) {
            $item = match ($item['intent']) {
                'max' => [...$item, 'intent' => 'mass', 'reps_min' => 5, 'reps_max' => 8],
                'speed' => [...$item, 'intent' => 'explosive'],
                default => $item,
            };
        }

        // Un débutant apprend le geste lourd en 4 à 6 répétitions avant de viser 87,5 %.
        if ($level === 'beginner' && $item['intent'] === 'max') {
            $item = [...$item, 'intent' => 'mass', 'reps_min' => 4, 'reps_max' => 6, 'sets' => max(3, $item['sets'] - 1)];
        }

        return $item;
    }
}
```

Dans `app/Services/ProgramGenerator.php`, première ligne de `generate` :

```php
    public function generate(array $params, Collection $exercises): array
    {
        if (($params['goal'] ?? null) === 'power') {
            return (new PowerProgram)->generate($params, $exercises);
        }

        [$split, $days] = self::SPLITS[$params['days']];
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Program`
Expected: PASS (PowerProgramTest + ProgramGeneratorTest + ProgramPageTest inchangés).

- [ ] **Step 6: Commit**

```bash
git add database/data/programs/power-templates.php app/Services/PowerProgram.php app/Services/ProgramGenerator.php tests/Feature/Program/PowerProgramTest.php
git commit -m "feat: générateur de programmes Force × Vitesse adapté au niveau et au matériel"
```

---

### Task 2: Objectif dans le questionnaire et séances attachées aux jours

**Files:**
- Modify: `app/Services/ProgramBuilder.php` (`GOALS`)
- Modify: `app/Livewire/ProgramPage.php` (règle d'étape 3)
- Modify: `app/Game/QuestScheduler.php` (`nextProgramDay`)
- Test: `tests/Feature/Program/PowerQuestionnaireTest.php`

**Interfaces:**
- Consumes: `ProgramGenerator::generate` avec `goal = power` (Task 1).
- Produces: `ProgramBuilder::GOALS['power'] === 'Force × Vitesse'`.

- [ ] **Step 1: Write the failing test**

`tests/Feature/Program/PowerQuestionnaireTest.php` :

```php
<?php

use App\Game\QuestScheduler;
use App\Livewire\ProgramPage;
use App\Models\Quest;
use App\Models\User;
use App\Services\ProgramBuilder;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(ExerciseSeeder::class);
    $this->travelTo(Carbon::parse('2026-10-12 08:00'));                         // lundi
    $this->user = User::factory()->withMorphology(weightKg: 80.0)->create();
    $this->actingAs($this->user);
});

it('propose Force × Vitesse dans le questionnaire et forge le programme', function () {
    Livewire::test(ProgramPage::class)
        ->call('next')->set('level', 'advanced')->call('next')
        ->assertSeeText('Force × Vitesse')
        ->set('goal', 'power')->call('next')
        ->set('weekdays', [1, 3, 5])->call('next')
        ->set('equipment', 'gym')
        ->call('generate')->assertHasNoErrors();

    $program = $this->user->fresh()->activeProgram;
    expect($program->name)->toBe('Programme Force × Vitesse · 3 j')
        ->and($program->split)->toBe('power')
        ->and($this->user->fresh()->goal)->toBe('power')
        ->and($program->days->flatMap->exercises->pluck('intent')->filter()->count())->toBe($program->days->flatMap->exercises->count());
});

it('attache chaque séance Force × Vitesse à son jour de la semaine', function () {
    app(ProgramBuilder::class)->build($this->user, ['level' => 'advanced', 'goal' => 'power', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    $this->user->forceFill(['calibrated_at' => now()])->save();

    foreach (['2026-10-14' => 'Tirage · FORCE MAX', '2026-10-16' => 'Jambes & hanches · EXPLOSIF'] as $date => $name) {
        $this->travelTo(Carbon::parse($date.' 10:00'));
        app(QuestScheduler::class)->sync($this->user->fresh(), now());
        expect(Quest::whereDate('date', $date)->where('type', 'training')->value('title'), $date)->toBe($name);
    }
});

it('garde les jours attachés quand un ami copie un programme Force × Vitesse', function () {
    $source = app(ProgramBuilder::class)->build(User::factory()->withMorphology()->create(['first_name' => 'Aïssam']), ['level' => 'advanced', 'goal' => 'power', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    app(ProgramBuilder::class)->copy($this->user, $source, [2, 4, 6]);
    $this->user->forceFill(['calibrated_at' => now()])->save();

    $this->travelTo(Carbon::parse('2026-10-17 10:00'));                         // samedi = 3ᵉ jour coché
    app(QuestScheduler::class)->sync($this->user->fresh(), now());
    expect(Quest::whereDate('date', '2026-10-17')->where('type', 'training')->value('title'))->toBe('Jambes & hanches · EXPLOSIF');
});

it('retire les intentions quand on régénère en Prise de muscle', function () {
    $builder = app(ProgramBuilder::class);
    $builder->build($this->user, ['level' => 'advanced', 'goal' => 'power', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);
    $program = $builder->build($this->user->fresh(), ['level' => 'advanced', 'goal' => 'hypertrophy', 'weekdays' => [1, 3, 5], 'equipment' => 'gym']);

    expect($program->days->flatMap->exercises->pluck('intent')->filter()->all())->toBe([]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Program/PowerQuestionnaireTest.php`
Expected: FAIL — « Force × Vitesse » absent du questionnaire, `goal.in` refuse `power`, `ProgramBuilder` lève « Undefined array key "power" » sur `GOALS`.

- [ ] **Step 3: Implement**

`app/Services/ProgramBuilder.php` :

```php
    public const GOALS = ['strength' => 'Force', 'hypertrophy' => 'Prise de muscle', 'fitness' => 'Remise en forme', 'power' => 'Force × Vitesse'];
```

`app/Livewire/ProgramPage.php`, règle de l'étape 3 :

```php
            3 => ['goal' => ['required', 'in:'.implode(',', array_keys(ProgramBuilder::GOALS))]],
```

`app/Game/QuestScheduler.php`, dans `nextProgramDay` :

```php
        // Programme personnalisé ou Force × Vitesse, une journée par jour d'entraînement : chaque journée garde son jour de la semaine.
        $weekdays = collect($user->training_weekdays ?? [])->sort()->values();
        $attached = ($program->params['custom'] ?? false) || ($program->params['goal'] ?? null) === 'power';
        if ($attached && $weekdays->count() === $days->count()) {
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Program tests/Feature/Strength tests/Feature/Quest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/ProgramBuilder.php app/Livewire/ProgramPage.php app/Game/QuestScheduler.php tests/Feature/Program/PowerQuestionnaireTest.php
git commit -m "feat: objectif Force × Vitesse dans le questionnaire, séances attachées aux jours"
```
