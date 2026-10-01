<?php

namespace App\Crew;

use App\Models\Crew;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CrewService
{
    public const MAX_CREWS = 10;

    public const MAX_JOIN_ATTEMPTS = 10;

    public function create(User $user, string $name, string $color): Crew
    {
        $data = $this->validated($name, $color);
        $this->ensureRoom($user, 'name');

        return DB::transaction(function () use ($user, $data) {
            $crew = Crew::create([...$data, 'code' => Crew::generateCode(), 'owner_id' => $user->id]);
            $crew->members()->attach($user->id, ['joined_at' => now()]);

            return $crew;
        });
    }

    public function join(User $user, string $code): Crew
    {
        $key = 'crew-join:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, self::MAX_JOIN_ATTEMPTS)) {
            throw ValidationException::withMessages(['code' => 'Trop d\'essais, réessaie dans une minute.']);
        }
        RateLimiter::hit($key, 60);

        $crew = Crew::firstWhere('code', Crew::normalizeCode($code));
        if (! $crew) {
            throw ValidationException::withMessages(['code' => 'Code inconnu.']);
        }

        if ($crew->hasMember($user)) {
            return $crew;
        }

        $this->ensureRoom($user, 'code');
        $crew->members()->attach($user->id, ['joined_at' => now()]);

        return $crew;
    }

    public function leave(User $user, Crew $crew): void
    {
        DB::transaction(function () use ($user, $crew) {
            $crew->members()->detach($user->id);

            if (! $crew->isOwnedBy($user)) {
                return;
            }

            $next = $crew->members()->orderBy('crew_user.joined_at')->orderBy('users.id')->first();
            $next ? $crew->update(['owner_id' => $next->id]) : $crew->delete();
        });
    }

    public function update(User $user, Crew $crew, string $name, string $color): void
    {
        $this->authorizeOwner($user, $crew);
        $crew->update($this->validated($name, $color));
    }

    public function regenerateCode(User $user, Crew $crew): string
    {
        $this->authorizeOwner($user, $crew);
        $crew->update(['code' => Crew::generateCode()]);

        return $crew->code;
    }

    public function kick(User $owner, Crew $crew, User $member): void
    {
        $this->authorizeOwner($owner, $crew);

        if ($member->id !== $owner->id) {
            $crew->members()->detach($member->id);
        }
    }

    public function delete(User $user, Crew $crew): void
    {
        $this->authorizeOwner($user, $crew);
        $crew->delete();
    }

    /** @return array{name: string, color: string} */
    private function validated(string $name, string $color): array
    {
        return validator(['name' => trim($name), 'color' => $color], [
            'name' => ['required', 'string', 'min:2', 'max:30'],
            'color' => ['required', Rule::in(array_keys(Crew::COLORS))],
        ], [
            'name.required' => 'Donne un nom à ton groupe.',
            'name.min' => 'Le nom doit faire au moins 2 caractères.',
            'name.max' => 'Le nom doit faire au plus 30 caractères.',
            'color.*' => 'Choisis une couleur d\'emblème.',
        ])->validate();
    }

    private function ensureRoom(User $user, string $field): void
    {
        if ($user->crews()->count() >= self::MAX_CREWS) {
            throw ValidationException::withMessages([$field => 'Tu fais déjà partie de 10 groupes.']);
        }
    }

    private function authorizeOwner(User $user, Crew $crew): void
    {
        abort_unless($crew->isOwnedBy($user), 403);
    }
}
