<?php

namespace App\Support;

/** Fiche « Comment faire ? » d'un exercice (database/data/exercise_guides.php). */
final class ExerciseGuide
{
    private static ?array $guides = null;

    /** @return array{muscles: string, tempo: string, steps: list<string>, mistake: string}|null */
    public static function for(string $slug): ?array
    {
        self::$guides ??= require database_path('data/exercise_guides.php');

        return self::$guides[$slug] ?? null;
    }
}
