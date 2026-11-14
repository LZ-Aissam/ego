<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\User;
use App\Models\WorkoutSet;

final class WorkoutBootData
{
    public const REST_SECONDS = 90;

    public function __construct(private StartingLoad $startingLoad) {}

    public function for(User $user): array
    {
        $loads = $user->workingLoads()->pluck('target_kg', 'exercise_id');
        $notes = $this->lastNotes($user);
        $pains = $this->recentPains($user);

        return [
            'restSeconds' => self::REST_SECONDS,
            'painZones' => \App\Models\ExerciseNote::PAIN_ZONES,
            'exercises' => Exercise::orderBy('name')->get()->map(fn (Exercise $e) => [
                'isBarbell' => in_array($e->slug, Exercise::BARBELL, true),
                'lastNote' => $notes[$e->id] ?? null,
                'pain' => $pains[$e->id] ?? null,
                'slots' => $e->slots ?? [],
                'equipment' => $e->equipment ?? [],
                'id' => $e->id,
                'slug' => $e->slug,
                'name' => $e->name,
                'muscles' => $e->primary_muscles ?? [],
                'guide' => \App\Support\ExerciseGuide::for($e->slug),
                'isBodyweight' => $e->is_bodyweight,
                'suggestedKg' => $this->startingLoad->suggest($e, $user),
                'prescribedKg' => isset($loads[$e->id]) ? (float) $loads[$e->id] : null,
                'perDumbbell' => $e->isPerDumbbell(),
            ])->values()->all(),
            'lastTime' => $this->lastTime($user),
        ];
    }

    public function forQuest(User $user, \App\Models\Quest $quest): array
    {
        $boot = $this->for($user);
        $library = collect($boot['exercises'])->keyBy('id');

        $boot['questTitle'] = $quest->title;
        $prescriber = app(\App\Game\LoadPrescriber::class);
        $catalog = Exercise::all()->keyBy('id');
        $equipment = $user->equipment ?? 'gym';
        $boot['plan'] = array_map(fn (array $o) => [
            'id' => $o['exercise_id'],
            'name' => $o['name'],
            'isBarbell' => $library[$o['exercise_id']]['isBarbell'] ?? false,
            'lastNote' => $library[$o['exercise_id']]['lastNote'] ?? null,
            'pain' => $library[$o['exercise_id']]['pain'] ?? null,
            // Maximum estimé à battre sur la série 5+, 3+, 1+ ou la série record.
            'recordE1rm' => isset($catalog[$o['exercise_id']]) ? $prescriber->recordToBeat($user, $catalog[$o['exercise_id']], $o['intent'] ?? null) : null,
            // Remplaçants du même emplacement, pour une douleur ou une machine prise.
            'alternatives' => $this->alternatives($o['slot'] ?? null, $equipment, $o['exercise_id'], $catalog),
            'isBodyweight' => $library[$o['exercise_id']]['isBodyweight'] ?? false,
            'suggestedKg' => $library[$o['exercise_id']]['suggestedKg'] ?? null,
            'intent' => $o['intent'] ?? null,
            'intentBadge' => \App\Game\Intent::badge($o['intent'] ?? null),
            'intentCue' => \App\Game\Intent::cue($o['intent'] ?? null),
            'prescribedKg' => ($o['intent'] ?? null) === null
                ? ($library[$o['exercise_id']]['prescribedKg'] ?? null)
                : app(\App\Game\LoadPrescriber::class)->prescriptionFor($user, \App\Models\Exercise::find($o['exercise_id']), $o['intent'], $o['reps_max'], \Illuminate\Support\Carbon::parse($quest->date->toDateString())),
            'perDumbbell' => $library[$o['exercise_id']]['perDumbbell'] ?? false,
            'sets' => $o['sets'],
            'repsMin' => $o['reps_min'],
            'repsMax' => $o['reps_max'],
            'restSeconds' => $o['rest_seconds'],
            'resume' => false,
        ], $quest->objectives);

        // Exercices passés à la dernière séance et plus réentraînés : remontés en fin de séance, à reprendre.
        $planned = array_column($boot['plan'], 'id');
        // Un exercice laissé pour une douleur récente n'est pas réclamé.
        foreach (array_diff($this->resumeDebt($user), $planned, array_keys($this->recentPains($user))) as $id) {
            $lib = $library[$id] ?? null;
            if ($lib === null) {
                continue;
            }
            $boot['plan'][] = [
                'id' => $id,
                'name' => $lib['name'],
                'isBarbell' => $lib['isBarbell'],
                'lastNote' => $lib['lastNote'],
                'pain' => $lib['pain'],
                'recordE1rm' => null,
                'alternatives' => [],
                'isBodyweight' => $lib['isBodyweight'],
                'suggestedKg' => $lib['suggestedKg'],
                'intent' => null,
                'intentBadge' => \App\Game\Intent::badge(null),
                'intentCue' => \App\Game\Intent::cue(null),
                'prescribedKg' => $lib['prescribedKg'] ?? null,
                'perDumbbell' => $lib['perDumbbell'],
                'sets' => 3,
                'repsMin' => 6,
                'repsMax' => 10,
                'restSeconds' => self::REST_SECONDS,
                'resume' => true,
            ];
        }

        return $boot;
    }

    /** @return list<int> trois exercices au plus du même emplacement, faisables avec le matériel du joueur */
    private function alternatives(?string $slot, string $equipment, int $exceptId, \Illuminate\Support\Collection $catalog): array
    {
        if ($slot === null) {
            return [];
        }
        $owned = $equipment === 'gym' ? ['gym', 'bodyweight'] : [$equipment];

        return $catalog->filter(fn (Exercise $e) => $e->id !== $exceptId && in_array($slot, $e->slots ?? [], true)
            && array_intersect($owned, $e->equipment ?? []) !== [])
            ->sortBy('id')->take(3)->keys()->values()->all();
    }

    /** Dernière note écrite par exercice. @return array<int, array{text: string, date: string}> */
    private function lastNotes(User $user): array
    {
        return $user->exerciseNotes()->whereNotNull('text')->orderBy('id')->get(['exercise_id', 'text', 'created_at'])
            ->keyBy('exercise_id')
            ->map(fn ($n) => ['text' => $n->text, 'date' => $n->created_at->translatedFormat('j M')])->all();
    }

    /** Douleur signalée ces 14 derniers jours, par exercice. @return array<int, array{zone: string, date: string}> */
    private function recentPains(User $user): array
    {
        return $user->exerciseNotes()->whereNotNull('pain_zone')->where('created_at', '>=', now()->subDays(\App\Models\ExerciseNote::PAIN_DAYS))
            ->orderBy('id')->get(['exercise_id', 'pain_zone', 'created_at'])
            ->keyBy('exercise_id')
            ->map(fn ($n) => ['zone' => \App\Models\ExerciseNote::PAIN_ZONES[$n->pain_zone] ?? $n->pain_zone, 'date' => $n->created_at->translatedFormat('j M')])->all();
    }

    /**
     * Exercices passés faute de temps dont la dernière occurrence reste un saut (jamais réentraînés depuis) : à reprendre.
     *
     * @return list<int>
     */
    private function resumeDebt(User $user): array
    {
        $workouts = $user->workouts()->whereNotNull('skipped_exercise_ids')->whereNotNull('finished_at')
            ->get(['finished_at', 'skipped_exercise_ids']);

        $lastSkip = [];
        foreach ($workouts as $workout) {
            foreach ($workout->skipped_exercise_ids ?? [] as $id) {
                if (! isset($lastSkip[$id]) || $workout->finished_at->gt($lastSkip[$id])) {
                    $lastSkip[$id] = $workout->finished_at;
                }
            }
        }
        if ($lastSkip === []) {
            return [];
        }

        $lastTrain = WorkoutSet::query()
            ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
            ->where('workouts.user_id', $user->id)
            ->whereIn('workout_sets.exercise_id', array_keys($lastSkip))
            ->groupBy('workout_sets.exercise_id')
            ->selectRaw('workout_sets.exercise_id as eid, MAX(workouts.finished_at) as trained_at')
            ->pluck('trained_at', 'eid');

        $debt = [];
        foreach ($lastSkip as $id => $skippedAt) {
            $trainedAt = $lastTrain[$id] ?? null;
            if ($trainedAt === null || $skippedAt->gt(\Illuminate\Support\Carbon::parse($trainedAt))) {
                $debt[] = (int) $id;
            }
        }

        return $debt;
    }

    /** @return array<int, list<array{weightKg: float, reps: int}>> */
    private function lastTime(User $user): array
    {
        $sets = WorkoutSet::query()
            ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
            ->where('workouts.user_id', $user->id)
            ->orderByDesc('workouts.finished_at')
            ->orderByDesc('workouts.id')
            ->orderBy('workout_sets.position')
            ->get(['workout_sets.exercise_id', 'workout_sets.workout_id', 'workout_sets.weight_kg', 'workout_sets.reps']);

        $result = [];
        $latestWorkout = [];

        foreach ($sets as $set) {
            $latestWorkout[$set->exercise_id] ??= $set->workout_id;

            if ($latestWorkout[$set->exercise_id] === $set->workout_id) {
                $result[$set->exercise_id][] = ['weightKg' => $set->weight_kg, 'reps' => $set->reps];
            }
        }

        return $result;
    }
}
