<?php

use App\Crew\Leaderboard;
use App\Game\XpLedger;
use App\Models\Crew;
use App\Models\Exercise;
use App\Models\User;
use Database\Seeders\ExerciseSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->board = app(Leaderboard::class);
    $this->travelTo(Carbon::parse('2026-10-07 12:00'));          // mercredi
});

function player(string $name, array $attributes = []): User
{
    $user = User::factory()->create(['first_name' => $name]);
    $user->forceFill($attributes)->save();

    return $user;
}

function xp(User $user, int $amount, string $at, int $id): void
{
    app(XpLedger::class)->award($user, 'workout', $id, $amount, at: Carbon::parse($at));
}

it('classe l\'XP de la semaine depuis lundi 00:00', function () {
    $ana = player('Ana');
    $bob = player('Bob');
    xp($ana, 100, '2026-10-05 00:00', 1);
    xp($ana, 500, '2026-10-04 23:30', 2);                       // dimanche : hors semaine
    xp($bob, 300, '2026-10-06 18:00', 3);

    $rows = $this->board->rank('xp', 'week');

    expect($rows->map(fn ($r) => [$r['user']->first_name, $r['value'], $r['rank']])->all())
        ->toBe([['Bob', 300, 1], ['Ana', 100, 2]]);
});

it('classe l\'XP du mois depuis le 1er et depuis toujours avec l\'XP totale', function () {
    $ana = player('Ana');
    $bob = player('Bob');
    xp($ana, 50, '2026-10-01 00:00', 1);
    xp($bob, 200, '2026-10-02 09:00', 2);
    xp($bob, 999, '2026-09-30 23:59', 3);                       // septembre
    $ana->forceFill(['xp_total' => 1900])->save();              // award() incrémente aussi xp_total : on fixe le total après

    expect($this->board->rank('xp', 'month')->pluck('value')->all())->toBe([200, 50])
        ->and($this->board->rank('xp', 'all')->map(fn ($r) => $r['user']->first_name)->all())->toBe(['Ana', 'Bob']);
});

it('partage le rang entre ex aequo, ordre alphabétique, zéros en bas', function () {
    player('Zoé', ['streak_days' => 5]);
    player('Ana', ['streak_days' => 5]);
    player('Max', ['streak_days' => 9]);
    player('Léo', ['streak_days' => 0]);

    $rows = $this->board->rank('streak');

    expect($rows->map(fn ($r) => [$r['user']->first_name, $r['rank']])->all())
        ->toBe([['Max', 1], ['Ana', 2], ['Zoé', 2], ['Léo', 4]]);
});

it('classe la force relative et exclut les joueurs sans poids ou sans record', function () {
    $this->seed(ExerciseSeeder::class);
    $bench = Exercise::firstWhere('slug', 'bench-press');
    $strong = player('Ana', ['weight_kg' => 60]);
    $heavy = player('Bob', ['weight_kg' => 100]);
    $noWeight = player('Cid');
    player('Dan', ['weight_kg' => 70]);                          // aucun record
    foreach ([[$strong, 81], [$heavy, 120], [$noWeight, 150]] as [$user, $e1rm]) {
        $user->personalRecords()->create(['exercise_id' => $bench->id, 'max_weight_kg' => $e1rm, 'max_weight_reps' => 1, 'best_e1rm_kg' => $e1rm, 'achieved_at' => now()]);
    }

    $rows = $this->board->rank('force', lift: 'bench');

    expect($rows->map(fn ($r) => [$r['user']->first_name, $r['value']])->all())->toBe([['Ana', 1.35], ['Bob', 1.2]]);
});

it('filtre sur les membres d\'un groupe', function () {
    $ana = player('Ana', ['streak_days' => 3]);
    player('Bob', ['streak_days' => 8]);
    $crew = Crew::create(['name' => 'Duo', 'color' => 'ice', 'code' => Crew::generateCode(), 'owner_id' => $ana->id]);
    $crew->members()->attach($ana->id, ['joined_at' => now()]);

    expect($this->board->rank('streak', crew: $crew)->map(fn ($r) => $r['user']->first_name)->all())->toBe(['Ana']);
});

it('garde un joueur en pause dans le classement', function () {
    player('Ana', ['streak_days' => 4, 'paused_at' => today()]);

    expect($this->board->rank('streak')->first()['user']->isPaused())->toBeTrue();
});

it('formate les valeurs', function () {
    expect(Leaderboard::format('xp', 1240))->toBe('1 240 XP')
        ->and(Leaderboard::format('streak', 12))->toBe('12 j')
        ->and(Leaderboard::format('force', 1.35))->toBe('×1,35');
});
