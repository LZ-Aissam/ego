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

    public function __construct(private ProgramGenerator $generator) {}

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

            return $program->load('days.exercises.exercise');
        });
    }
}
