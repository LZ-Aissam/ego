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
