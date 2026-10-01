<?php

namespace App\Crew;

use App\Models\Challenge;
use App\Models\Crew;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ChallengeService
{
    public const MAX_OPEN = 3;

    public function create(User $user, Crew $crew, string $metric, ?string $lift, string $duration, ?string $stake): Challenge
    {
        abort_unless($crew->hasMember($user), 404);
        $stake = $stake === null ? null : (trim($stake) === '' ? null : trim($stake));

        validator(compact('metric', 'lift', 'duration', 'stake'), [
            'metric' => ['required', Rule::in(array_keys(Challenge::METRICS))],
            'lift' => [Rule::requiredIf($metric === 'strength'), 'nullable', Rule::in(array_keys(Challenge::LIFTS))],
            'duration' => ['required', Rule::in(array_keys(Challenge::DURATIONS))],
            'stake' => ['nullable', 'string', 'max:100'],
        ], [
            'metric.*' => 'Choisis un type de défi.',
            'lift.*' => 'Choisis le mouvement du défi de force.',
            'duration.*' => 'Choisis une durée.',
            'stake.max' => "L'enjeu doit faire au plus 100 caractères.",
        ])->validate();

        if ($crew->challenges()->whereIn('status', ['upcoming', 'active'])->count() >= self::MAX_OPEN) {
            throw ValidationException::withMessages(['crew' => 'Ce groupe a déjà 3 défis en cours.']);
        }

        [$start, $end] = Challenge::window($duration, now());

        return DB::transaction(function () use ($user, $crew, $metric, $lift, $duration, $stake, $start, $end) {
            $challenge = $crew->challenges()->create([
                'creator_id' => $user->id, 'metric' => $metric, 'lift' => $metric === 'strength' ? $lift : null,
                'duration' => $duration, 'starts_at' => $start, 'ends_at' => $end, 'stake' => $stake, 'status' => 'upcoming',
            ]);
            $challenge->participants()->attach($user->id, ['joined_at' => now()]);

            return $challenge;
        });
    }

    public function join(User $user, Challenge $challenge): void
    {
        abort_unless($challenge->crew->hasMember($user), 404);
        if (! $challenge->isOpen()) {
            throw ValidationException::withMessages(['challenge' => 'Ce défi est terminé.']);
        }
        if (! $challenge->hasParticipant($user)) {
            $challenge->participants()->attach($user->id, ['joined_at' => now()]);
        }
    }

    public function leave(User $user, Challenge $challenge): void
    {
        abort_unless($challenge->crew->hasMember($user), 404);
        abort_if($challenge->creator_id === $user->id, 403);
        if ($challenge->status !== 'upcoming') {
            throw ValidationException::withMessages(['challenge' => 'Le défi a commencé : tu ne peux plus le quitter.']);
        }
        $challenge->participants()->detach($user->id);
    }

    public function cancel(User $user, Challenge $challenge): void
    {
        abort_unless($challenge->creator_id === $user->id, 403);
        if ($challenge->status !== 'upcoming') {
            throw ValidationException::withMessages(['challenge' => 'Le défi a commencé : il ne peut plus être annulé.']);
        }
        $challenge->update(['status' => 'cancelled']);
    }

    /** Départ du groupe : retrait des défis du groupe qui ne sont pas terminés. */
    public function leaveCrew(User $user, Crew $crew): void
    {
        $crew->challenges()->whereIn('status', ['upcoming', 'active'])->get()
            ->each(fn (Challenge $c) => $c->participants()->detach($user->id));
    }
}
