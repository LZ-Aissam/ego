<?php

namespace App\Services;

use App\Game\LoadPrescriber;
use App\Game\QuestScheduler;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\WorkoutSet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Met un programme existant à jour avec les séances actuelles de son modèle, sans le refaire :
 * même programme (donc même cycle), maxis, charges, lest, calibrage et historique gardés.
 */
final class ProgramRefresher
{
    public function __construct(private ProgramGenerator $generator, private LoadPrescriber $prescriber, private QuestScheduler $scheduler) {}

    public function refresh(Program $program): bool
    {
        $program->loadMissing('days', 'user');
        $params = $program->params;
        $generated = $this->generator->generate([
            'level' => $params['level'], 'goal' => $params['goal'], 'days' => $program->days->count(), 'equipment' => $params['equipment'],
            'upper_only' => $params['upper_only'] ?? false, 'no_belt' => $params['no_belt'] ?? false,
        ], Exercise::all());
        if (count($generated['days']) !== $program->days->count()) {
            return false;
        }

        DB::transaction(function () use ($program, $generated) {
            // Les journées gardent leur identifiant : les quêtes passées restent attachées à leur jour.
            foreach ($program->days->sortBy('position')->values() as $i => $day) {
                $day->update(['name' => $generated['days'][$i]['name'], 'type' => $generated['days'][$i]['type']]);
                $day->exercises()->delete();
                foreach ($generated['days'][$i]['exercises'] as $j => $line) {
                    $day->exercises()->create([...$line, 'position' => $j + 1]);
                }
            }
        });

        $user = $program->user;
        if ($user->active_program_id === $program->id) {
            $this->refreshPendingQuests($program->fresh('days'));
            if ($user->calibrated_at) {
                // Sans écraser : seuls les nouveaux exercices reçoivent une charge.
                $this->prescriber->prescribeProgram($user->fresh());
            }
        }

        return true;
    }

    /** Séances à venir et séance du jour pas encore commencée : le nouveau contenu, avec les séries de la semaine du cycle. */
    private function refreshPendingQuests(Program $program): void
    {
        $user = $program->user;
        $days = $program->days->keyBy('id');
        $quests = $user->quests()->where('type', 'training')->where('status', 'pending')
            ->whereIn('program_day_id', $days->keys())->where('date', '>=', now()->toDateString())->get();

        foreach ($quests as $quest) {
            $date = Carbon::parse($quest->date->toDateString());
            $started = WorkoutSet::query()->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
                ->where('workouts.user_id', $user->id)->whereDate('workouts.finished_at', $date->toDateString())->exists();
            if ($started) {
                continue;
            }
            $day = $days[$quest->program_day_id];
            $quest->update(['title' => $day->name, 'objectives' => $this->scheduler->objectivesOn($user, $day, $date)]);
        }
    }
}
