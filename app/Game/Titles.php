<?php

namespace App\Game;

use App\Models\User;
use App\Models\Workout;
use Illuminate\Support\Facades\DB;

final class Titles
{
    public const CATALOG = [
        'awakened' => ['name' => "L'Éveillé", 'condition' => 'Terminer ta première séance'],
        'record_breaker' => ['name' => 'Briseur de records', 'condition' => 'Battre 10 records'],
        'centurion' => ['name' => 'Centurion', 'condition' => 'Terminer 100 séances'],
        'titan' => ['name' => 'Titan', 'condition' => 'Soulever 100 000 kg au total'],
        'steady' => ['name' => 'Assidu', 'condition' => 'Tenir une série de 7 jours'],
        'unstoppable' => ['name' => 'Inarrêtable', 'condition' => 'Tenir une série de 30 jours'],
        'revenant' => ['name' => 'Revenant', 'condition' => 'Réussir une quête de pénalité'],
        'rank_c' => ['name' => 'Chasseur de rang C', 'condition' => 'Atteindre le rang C'],
        'rank_b' => ['name' => 'Chasseur de rang B', 'condition' => 'Atteindre le rang B'],
        'rank_a' => ['name' => 'Chasseur de rang A', 'condition' => 'Atteindre le rang A'],
        'monarch' => ['name' => 'Monarque', 'condition' => 'Atteindre le rang S'],
    ];

    public static function name(string $key): ?string
    {
        return self::CATALOG[$key]['name'] ?? null;
    }

    /** @return list<string> */
    public function check(User $user): array
    {
        $earned = $this->earned($user);
        $owned = $user->titles()->pluck('title_key')->all();
        $new = array_values(array_diff($earned, $owned));

        foreach ($new as $key) {
            // insertOrIgnore : un envoi concurrent a pu débloquer le même titre entre-temps.
            DB::table('user_titles')->insertOrIgnore(['user_id' => $user->id, 'title_key' => $key, 'unlocked_at' => now()]);
        }

        if ($user->equipped_title === null && $earned !== []) {
            $user->forceFill(['equipped_title' => $earned[0]])->save();
        }

        return $new;
    }

    public function totalVolume(User $user): float
    {
        return (float) DB::table('workout_sets')
            ->join('workouts', 'workouts.id', '=', 'workout_sets.workout_id')
            ->join('exercises', 'exercises.id', '=', 'workout_sets.exercise_id')
            ->where('workouts.user_id', $user->id)
            ->selectRaw('COALESCE(SUM(CASE WHEN exercises.is_bodyweight = 1
                THEN (COALESCE(workouts.bodyweight_kg, 0) + workout_sets.weight_kg) * workout_sets.reps
                ELSE workout_sets.weight_kg * workout_sets.reps END), 0) AS volume')
            ->value('volume');
    }

    /** @return list<string> clés méritées, dans l'ordre du catalogue */
    private function earned(User $user): array
    {
        $workouts = Workout::where('user_id', $user->id)->count();
        $level = Leveling::levelForXp((int) $user->xp_total);
        $records = (int) $user->xpEvents()->where('source', 'record')->sum('quantity');

        $conditions = [
            'awakened' => $workouts >= 1,
            'record_breaker' => $records >= 10,
            'centurion' => $workouts >= 100,
            'titan' => fn () => $this->totalVolume($user) >= 100000,
            'rank_c' => $level >= 20,
            'rank_b' => $level >= 30,
            'rank_a' => $level >= 45,
            'monarch' => $level >= 60,
        ];

        $earned = [];
        foreach (array_keys(self::CATALOG) as $key) {
            $condition = $conditions[$key] ?? false;
            if ($condition instanceof \Closure ? $condition() : $condition) {
                $earned[] = $key;
            }
        }

        return $earned;
    }
}
