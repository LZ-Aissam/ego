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
        ->and($result['notifications'][1]['title'])->toBe('RANG D')
        ->and($result['notifications'][1]['lines'][0])->toContain('TON EGO');
});

it('ne rapporte rien en dehors des 2 premières séances du jour', function () {
    playSession($this->user, $this->bench->id, 40, 3, '2026-10-01T06:00:00Z');
    playSession($this->user, $this->bench->id, 40, 3, '2026-10-01T07:00:00Z');
    $third = playSession($this->user, $this->bench->id, 40, 3, '2026-10-01T08:00:00Z');

    expect($third['xp'])->toBe(0)->and($this->user->fresh()->xp_total)->toBe(130);
});

it('relit l\'XP en base pour ne pas rater une montée de niveau quand un autre envoi vient d\'en ajouter', function () {
    // Un envoi concurrent a déjà ajouté 90 XP en base ; l'objet en mémoire ne le sait pas.
    $this->user->xpEvents()->create(['source' => 'workout', 'source_id' => 999999, 'amount' => 90]);
    User::whereKey($this->user->id)->update(['xp_total' => 90]);

    $result = playSession($this->user, $this->bench->id, 40, 4); // +70 → 160 XP : niveau 2

    expect(collect($result['notifications'])->pluck('type')->all())->toContain('level_up')
        ->and($result['player']['level'])->toBe(2);
});
