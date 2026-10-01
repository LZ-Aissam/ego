<?php

use App\Crew\CrewService;
use App\Models\Crew;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->crews = app(CrewService::class);
    $this->owner = User::factory()->create();
});

function errorsOf(callable $action): array
{
    try {
        $action();
    } catch (ValidationException $e) {
        return $e->errors();
    }

    return [];
}

it('crée un groupe dont le créateur est membre', function () {
    $crew = $this->crews->create($this->owner, '  Les Ombres  ', 'violet');

    expect($crew->name)->toBe('Les Ombres')->and($crew->color)->toBe('violet')
        ->and($crew->isOwnedBy($this->owner))->toBeTrue()
        ->and($crew->hasMember($this->owner))->toBeTrue()
        ->and($crew->code)->toHaveLength(6);
});

it('refuse un nom trop court ou trop long et une couleur inconnue', function (string $name, string $color, string $key) {
    expect(errorsOf(fn () => $this->crews->create($this->owner, $name, $color)))->toHaveKey($key);
})->with([['A', 'ice', 'name'], [str_repeat('x', 31), 'ice', 'name'], ['Les Ombres', 'rose', 'color']]);

it('rejoint avec un code saisi en minuscules, avec espaces ou tirets', function (string $typed) {
    $crew = $this->crews->create($this->owner, 'Les Ombres', 'ice');
    $crew->update(['code' => 'AB3K9P']);
    $friend = User::factory()->create();

    $joined = $this->crews->join($friend, $typed);

    expect($joined->is($crew))->toBeTrue()->and($crew->hasMember($friend))->toBeTrue();
})->with([' ab3k9p ', 'ab3-k9p', 'AB3 K9P']);

it('ne change rien quand on rejoint un groupe dont on est déjà membre', function () {
    $crew = $this->crews->create($this->owner, 'Les Ombres', 'ice');

    expect($this->crews->join($this->owner, $crew->code)->is($crew))->toBeTrue()
        ->and($crew->members()->count())->toBe(1);
});

it('refuse un code inconnu', function () {
    expect(errorsOf(fn () => $this->crews->join($this->owner, 'ZZZZZZ')))->toBe(['code' => ['Code inconnu.']]);
});

it('limite à 10 groupes par membre', function () {
    foreach (range(1, 10) as $i) {
        $this->crews->create($this->owner, "Groupe $i", 'ice');
    }
    $other = $this->crews->create(User::factory()->create(), 'Onzième', 'ice');

    expect(errorsOf(fn () => $this->crews->create($this->owner, 'Groupe 11', 'ice')))->toBe(['name' => ['Tu fais déjà partie de 10 groupes.']])
        ->and(errorsOf(fn () => $this->crews->join($this->owner, $other->code)))->toBe(['code' => ['Tu fais déjà partie de 10 groupes.']]);
});

it('limite les essais de code à 10 par minute', function () {
    RateLimiter::clear('crew-join:'.$this->owner->id);
    foreach (range(1, 10) as $i) {
        errorsOf(fn () => $this->crews->join($this->owner, 'ZZZZZZ'));
    }

    expect(errorsOf(fn () => $this->crews->join($this->owner, 'ZZZZZZ')))->toBe(['code' => ['Trop d\'essais, réessaie dans une minute.']]);
});

it('laisse un membre quitter le groupe', function () {
    $crew = $this->crews->create($this->owner, 'Les Ombres', 'ice');
    $friend = User::factory()->create();
    $this->crews->join($friend, $crew->code);

    $this->crews->leave($friend, $crew);

    expect($crew->hasMember($friend))->toBeFalse()->and($crew->fresh()->isOwnedBy($this->owner))->toBeTrue();
});

it('transmet le rôle de créateur au membre le plus ancien quand le créateur part', function () {
    $crew = $this->crews->create($this->owner, 'Les Ombres', 'ice');
    $first = User::factory()->create();
    $second = User::factory()->create();
    $this->travelTo(Carbon::now()->addMinute());
    $this->crews->join($first, $crew->code);
    $this->travelTo(Carbon::now()->addMinute());
    $this->crews->join($second, $crew->code);

    $this->crews->leave($this->owner, $crew);

    expect($crew->fresh()->owner_id)->toBe($first->id);
});

it('supprime le groupe quand le dernier membre part', function () {
    $crew = $this->crews->create($this->owner, 'Les Ombres', 'ice');

    $this->crews->leave($this->owner, $crew);

    expect(Crew::find($crew->id))->toBeNull();
});

it('laisse le créateur renommer, changer la couleur et régénérer le code', function () {
    $crew = $this->crews->create($this->owner, 'Les Ombres', 'ice');
    $old = $crew->code;

    $this->crews->update($this->owner, $crew, 'Les Lames', 'gold');
    $new = $this->crews->regenerateCode($this->owner, $crew);

    $crew->refresh();
    expect($crew->name)->toBe('Les Lames')->and($crew->color)->toBe('gold')
        ->and($crew->code)->toBe($new)->and($new)->not->toBe($old)
        ->and(errorsOf(fn () => $this->crews->join(User::factory()->create(), $old)))->toBe(['code' => ['Code inconnu.']]);
});

it('laisse le créateur retirer un membre, mais ni lui-même ni un non-membre', function () {
    $crew = $this->crews->create($this->owner, 'Les Ombres', 'ice');
    $friend = User::factory()->create();
    $stranger = User::factory()->create();
    $this->crews->join($friend, $crew->code);

    $this->crews->kick($this->owner, $crew, $friend);
    $this->crews->kick($this->owner, $crew, $this->owner);
    $this->crews->kick($this->owner, $crew, $stranger);

    expect($crew->hasMember($friend))->toBeFalse()
        ->and($crew->hasMember($this->owner))->toBeTrue()
        ->and($crew->fresh()->isOwnedBy($this->owner))->toBeTrue();
});

it('supprime le groupe sans toucher aux joueurs', function () {
    $crew = $this->crews->create($this->owner, 'Les Ombres', 'ice');

    $this->crews->delete($this->owner, $crew);

    expect(Crew::find($crew->id))->toBeNull()->and($this->owner->fresh())->not->toBeNull();
});

it('refuse les actions de créateur aux autres membres', function (string $action) {
    $crew = $this->crews->create($this->owner, 'Les Ombres', 'ice');
    $friend = User::factory()->create();
    $this->crews->join($friend, $crew->code);

    match ($action) {
        'update' => $this->crews->update($friend, $crew, 'Pirates', 'red'),
        'regenerate' => $this->crews->regenerateCode($friend, $crew),
        'kick' => $this->crews->kick($friend, $crew, $this->owner),
        'delete' => $this->crews->delete($friend, $crew),
    };
})->with(['update', 'regenerate', 'kick', 'delete'])->throws(HttpException::class);
