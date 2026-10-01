<?php

namespace App\Support;

use App\Models\PersonalRecord;
use App\Models\Workout;

final class SystemMessage
{
    /** @return array{type: string, title: string, lines: list<string>, variant: string, highlight: ?string} */
    public static function make(string $type, string $title, array $lines = [], string $variant = 'default', ?string $highlight = null): array
    {
        return ['type' => $type, 'title' => $title, 'lines' => array_values($lines), 'variant' => $variant, 'highlight' => $highlight];
    }

    public static function workoutDone(Workout $workout): array
    {
        return self::make('workout_done', 'SÉANCE TERMINÉE', [
            Format::duration($workout->durationMinutes()).' · '.$workout->sets->count().' séries',
            'Volume : '.Format::kg($workout->volume()),
        ]);
    }

    public static function record(PersonalRecord $record): array
    {
        return self::make('record', 'NOUVEAU RECORD', [
            $record->exercise->name,
            Format::kg($record->max_weight_kg).' × '.$record->max_weight_reps,
        ], highlight: 'NEW PR');
    }
}
