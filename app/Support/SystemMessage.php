<?php

namespace App\Support;

use App\Game\Titles;
use App\Models\PersonalRecord;
use App\Models\Workout;

final class SystemMessage
{
    /** @return array{type: string, title: string, lines: list<string>, variant: string, highlight: ?string} */
    public static function make(string $type, string $title, array $lines = [], string $variant = 'default', ?string $highlight = null): array
    {
        return ['type' => $type, 'title' => $title, 'lines' => array_values($lines), 'variant' => $variant, 'highlight' => $highlight];
    }

    public static function workoutDone(Workout $workout, int $xp = 0): array
    {
        $lines = [
            Format::duration($workout->durationMinutes()).' · '.$workout->sets->count().' séries',
            'Volume : '.Format::kg($workout->volume()),
        ];

        if ($xp > 0) {
            $lines[] = '+'.$xp.' XP';
        }

        return self::make('workout_done', 'SÉANCE TERMINÉE', $lines);
    }

    /** @param array{level: int, rank: string, into: int, needed: int} $progress */
    public static function levelUp(array $progress): array
    {
        return self::make('level_up', 'NIVEAU '.$progress['level'], [
            'Tu as monté de niveau !',
            'Rang '.$progress['rank'].' · '.$progress['into'].' / '.$progress['needed'].' XP',
        ], highlight: 'LEVEL UP');
    }

    public static function rankUp(string $rank): array
    {
        return self::make('rank_up', 'RANG '.$rank, ['TON EGO te reconnaît comme chasseur de rang '.$rank.'.'], highlight: 'RANG '.$rank);
    }

    public static function title(string $key): array
    {
        return self::make('title', 'TITRE DÉBLOQUÉ', [Titles::name($key) ?? $key, Titles::CATALOG[$key]['condition'] ?? '']);
    }

    public static function record(PersonalRecord $record): array
    {
        return self::make('record', 'NOUVEAU RECORD', [
            $record->exercise->name,
            Format::kg($record->max_weight_kg).' × '.$record->max_weight_reps,
        ], highlight: 'NEW PR');
    }
}
