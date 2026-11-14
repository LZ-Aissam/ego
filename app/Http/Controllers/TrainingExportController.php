<?php

namespace App\Http\Controllers;

use App\Models\WorkoutSet;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Toutes les séries du joueur en tableur (séparateur « ; » et BOM : s'ouvre tel quel dans Excel en français). */
class TrainingExportController extends Controller
{
    public const EFFORTS = ['easy' => 'Facile', 'hard' => 'Dur', 'limit' => 'Limite'];

    public function __invoke(Request $request): StreamedResponse
    {
        $user = $request->user();
        $filename = 'ego-series-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($user) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Heure', 'Exercice', 'Série', 'Charge (kg)', 'Répétitions', 'Ressenti', 'Poids du corps (kg)'], ';');

            WorkoutSet::query()->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
                ->join('exercises', 'exercises.id', '=', 'workout_sets.exercise_id')
                ->where('workouts.user_id', $user->id)
                ->orderBy('workouts.finished_at')->orderBy('workout_sets.position')
                ->select(['workouts.finished_at', 'workouts.bodyweight_kg', 'exercises.name', 'workout_sets.position', 'workout_sets.weight_kg', 'workout_sets.reps', 'workout_sets.effort'])
                ->chunk(500, function ($sets) use ($out) {
                    foreach ($sets as $set) {
                        $at = \Illuminate\Support\Carbon::parse($set->finished_at);
                        fputcsv($out, [
                            $at->format('d/m/Y'), $at->format('H:i'), $set->name, $set->position,
                            str_replace('.', ',', (string) (float) $set->weight_kg), $set->reps,
                            self::EFFORTS[$set->effort] ?? '', $set->bodyweight_kg === null ? '' : str_replace('.', ',', (string) (float) $set->bodyweight_kg),
                        ], ';');
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
