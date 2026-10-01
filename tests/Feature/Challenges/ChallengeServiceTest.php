<?php

use App\Crew\ChallengeService;
use App\Crew\CrewService;
use App\Models\Challenge;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-11 20:00'));
    $this->service = app(ChallengeService::class);
    $this->owner = User::factory()->create(['first_name' => 'Aïssam']);
    $this->friend = User::factory()->create(['first_name' => 'Renée']);
    $this->crew = app(CrewService::class)->create($this->owner, 'Les Ombres', 'violet');
    app(CrewService::class)->join($this->friend, $this->crew->code);
});

function challengeErrors(callable $action): array
{
    try {
        $action();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

it('crée un défi qui démarre le lendemain, créateur inscrit', function () {
    $challenge = $this->service->create($this->owner, $this->crew, 'xp', null, '1w', '  le perdant paie le resto  ');

    expect($challenge->status)->toBe('upcoming')
        ->and($challenge->starts_at->toDateTimeString())->toBe('2026-10-12 00:00:00')
        ->and($challenge->stake)->toBe('le perdant paie le resto')
        ->and($challenge->lift)->toBeNull()
        ->and($challenge->hasParticipant($this->owner))->toBeTrue();
});

it('exige un mouvement pour un défi de force et valide les champs', function (string $metric, ?string $lift, string $duration, ?string $stake, string $key) {
    expect(challengeErrors(fn () => $this->service->create($this->owner, $this->crew, $metric, $lift, $duration, $stake)))->toHaveKey($key);
})->with([
    ['strength', null, '1w', null, 'lift'],
    ['strength', 'curl', '1w', null, 'lift'],
    ['poids', null, '1w', null, 'metric'],
    ['xp', null, '5y', null, 'duration'],
    ['xp', null, '1w', str_repeat('x', 101), 'stake'],
]);

it('limite à 3 défis à venir ou en cours par groupe', function () {
    foreach (range(1, 3) as $i) {
        $this->service->create($this->owner, $this->crew, 'xp', null, '1w', null);
    }

    expect(challengeErrors(fn () => $this->service->create($this->friend, $this->crew, 'sessions', null, '3d', null)))
        ->toBe(['crew' => ['Ce groupe a déjà 3 défis en cours.']]);
});

it('refuse de créer pour un groupe dont on n\'est pas membre', function () {
    $this->service->create(User::factory()->create(), $this->crew, 'xp', null, '1w', null);
})->throws(HttpException::class);

it('laisse rejoindre, même en cours, mais pas un défi terminé', function () {
    $challenge = $this->service->create($this->owner, $this->crew, 'xp', null, '1w', null);
    $this->service->join($this->friend, $challenge);
    $this->service->join($this->friend, $challenge);
    expect($challenge->participants()->count())->toBe(2);

    $challenge->update(['status' => 'finished']);
    $late = User::factory()->create();
    app(CrewService::class)->join($late, $this->crew->code);
    expect(challengeErrors(fn () => $this->service->join($late, $challenge)))->toBe(['challenge' => ['Ce défi est terminé.']]);
});

it('laisse quitter seulement avant le début, jamais le créateur', function () {
    $challenge = $this->service->create($this->owner, $this->crew, 'xp', null, '1w', null);
    $this->service->join($this->friend, $challenge);

    $this->service->leave($this->friend, $challenge);
    expect($challenge->hasParticipant($this->friend))->toBeFalse();

    $this->service->join($this->friend, $challenge);
    $challenge->update(['status' => 'active']);
    expect(challengeErrors(fn () => $this->service->leave($this->friend, $challenge)))
        ->toBe(['challenge' => ['Le défi a commencé : tu ne peux plus le quitter.']]);
});

it('interdit au créateur de quitter son défi', function () {
    $challenge = $this->service->create($this->owner, $this->crew, 'xp', null, '1w', null);
    $this->service->leave($this->owner, $challenge);
})->throws(HttpException::class);

it('laisse le créateur annuler avant le début, et personne d\'autre', function () {
    $challenge = $this->service->create($this->owner, $this->crew, 'xp', null, '1w', null);
    $this->service->cancel($this->owner, $challenge);
    expect($challenge->fresh()->status)->toBe('cancelled');

    $other = $this->service->create($this->owner, $this->crew, 'xp', null, '1w', null);
    expect(fn () => $this->service->cancel($this->friend, $other))->toThrow(HttpException::class);
});

it('retire des défis non terminés du groupe quand on quitte le groupe', function () {
    $open = $this->service->create($this->owner, $this->crew, 'xp', null, '1w', null);
    $done = $this->service->create($this->owner, $this->crew, 'sessions', null, '1w', null);
    $this->service->join($this->friend, $open);
    $this->service->join($this->friend, $done);
    $done->update(['status' => 'finished']);

    app(CrewService::class)->leave($this->friend, $this->crew);

    expect($open->hasParticipant($this->friend))->toBeFalse()->and($done->hasParticipant($this->friend))->toBeTrue();
});
