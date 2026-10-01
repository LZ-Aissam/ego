<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ProgramBuilder
{
    public const GOALS = ['strength' => 'Force', 'hypertrophy' => 'Prise de muscle', 'fitness' => 'Remise en forme'];
    public const LEVELS = ['beginner' => 'Débutant', 'intermediate' => 'Intermédiaire', 'advanced' => 'Avancé'];
    public const EQUIPMENT = ['gym' => 'Salle complète', 'dumbbells' => 'Haltères seuls', 'bodyweight' => 'Poids du corps'];

    /** Du moins au plus équipé : sert à prévenir quand un programme copié demande plus de matériel. */
    public const EQUIPMENT_RANK = ['bodyweight' => 0, 'dumbbells' => 1, 'gym' => 2];

    public function __construct(private ProgramGenerator $generator, private \App\Game\LoadPrescriber $prescriber) {}

    /** @param array{level: string, goal: string, weekdays: array<int|string>, equipment: string} $params */
    public function build(User $user, array $params): Program
    {
        $weekdays = collect($params['weekdays'])->map(fn ($d) => (int) $d)->unique()->sort()->values()->all();
        $generated = $this->generator->generate([
            'level' => $params['level'], 'goal' => $params['goal'], 'days' => count($weekdays), 'equipment' => $params['equipment'],
        ], Exercise::all());

        return DB::transaction(function () use ($user, $params, $weekdays, $generated) {
            $program = $user->programs()->create([
                'name' => 'Programme '.self::GOALS[$params['goal']].' · '.count($weekdays).' j',
                'split' => $generated['split'],
                'params' => [...$params, 'weekdays' => $weekdays],
            ]);

            foreach ($generated['days'] as $i => $day) {
                $programDay = $program->days()->create(['position' => $i + 1, 'name' => $day['name'], 'type' => $day['type']]);
                foreach ($day['exercises'] as $j => $exercise) {
                    $programDay->exercises()->create([...$exercise, 'position' => $j + 1]);
                }
            }

            $user->forceFill([
                'level' => $params['level'],
                'goal' => $params['goal'],
                'days_per_week' => count($weekdays),
                'equipment' => $params['equipment'],
                'training_weekdays' => $weekdays,
                'active_program_id' => $program->id,
            ])->save();

            if ($user->calibrated_at) {
                $this->prescriber->prescribeProgram($user);
            }

            return $program->load('days.exercises.exercise');
        });
    }

    /**
     * Copie à l'identique le programme d'un autre joueur et l'active. Le niveau du joueur ne change pas,
     * et ses charges sont recalculées d'après son propre profil de force.
     *
     * @param  list<int>  $weekdays  autant de jours que le programme copié
     */
    public function copy(User $user, Program $source, array $weekdays): Program
    {
        $source->loadMissing('days.exercises', 'user');
        $weekdays = collect($weekdays)->map(fn ($d) => (int) $d)->unique()->sort()->values()->all();

        return DB::transaction(function () use ($user, $source, $weekdays) {
            $program = $user->programs()->create([
                'name' => 'Programme de '.$source->user->first_name.' · '.$source->days->count().' j',
                'split' => $source->split,
                'params' => [...$source->params, 'weekdays' => $weekdays, 'copied_from' => $source->id],
            ]);

            foreach ($source->days as $day) {
                $copy = $program->days()->create(['position' => $day->position, 'name' => $day->name, 'type' => $day->type]);
                foreach ($day->exercises as $line) {
                    $copy->exercises()->create($line->only(['exercise_id', 'slot', 'sets', 'reps_min', 'reps_max', 'rest_seconds', 'position']));
                }
            }

            $user->forceFill([
                'goal' => $source->params['goal'] ?? $user->goal,
                'days_per_week' => count($weekdays),
                'equipment' => $source->params['equipment'] ?? $user->equipment,
                'training_weekdays' => $weekdays,
                'active_program_id' => $program->id,
            ])->save();

            if ($user->calibrated_at) {
                $this->prescriber->prescribeProgram($user);
            }

            return $program->load('days.exercises.exercise');
        });
    }
}
