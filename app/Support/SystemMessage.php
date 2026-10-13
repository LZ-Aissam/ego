<?php

namespace App\Support;

use App\Models\Challenge;
use App\Game\Titles;
use App\Models\PersonalRecord;
use App\Models\Quest;
use App\Models\Workout;
use Carbon\CarbonInterface;

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

    public static function programForged(string $name): array
    {
        return self::make('program', 'PROGRAMME FORGÉ', [$name, 'TON EGO t\'assignera ta première quête dès aujourd\'hui.']);
    }

    public static function questDone(Quest $quest, int $xp): array
    {
        return self::make('quest_done', 'QUÊTE ACCOMPLIE', [$quest->title, '+'.$xp.' XP'], highlight: 'QUÊTE');
    }

    public static function questFailed(Quest $quest): array
    {
        return self::make('quest_failed', 'QUÊTE ÉCHOUÉE', [$quest->title.' — '.$quest->date->translatedFormat('l j F'), 'TON EGO t\'impose une quête de pénalité le lendemain.'], 'alert');
    }

    public static function penaltyDone(): array
    {
        return self::make('penalty_done', 'PÉNALITÉ ACCOMPLIE', ['Ta série est sauvée.', '+50 XP']);
    }

    public static function penaltyFailed(): array
    {
        return self::make('penalty_failed', 'PÉNALITÉ ÉCHOUÉE', ['Ta série retombe à 0.', 'TON EGO attend mieux de toi.'], 'alert');
    }

    public static function pauseOn(?CarbonInterface $until): array
    {
        return self::make('pause_on', 'QUÊTES SUSPENDUES', [$until ? 'Jusqu\'au '.$until->translatedFormat('j F').' inclus.' : 'Jusqu\'à ton retour.', 'Ta série est gelée.']);
    }

    public static function pauseOff(): array
    {
        return self::make('pause_off', 'RETOUR DE TON EGO', ['Tes quêtes reprennent.']);
    }

    public static function loadUp(string $exercise, float $kg): array
    {
        return self::make('load_up', 'CHARGE AUGMENTÉE', [$exercise.' → '.Format::kg($kg), 'TON EGO a vu ta progression.'], highlight: '+');
    }

    public static function topUp(string $exercise, float $kg): array
    {
        return self::make('top_up', 'RECORD RELEVÉ', ['🏆 '.$exercise.' → '.Format::kg($kg), 'TON EGO a vu ta série record. On monte.'], highlight: '+');
    }

    public static function deload(string $exercise, float $kg): array
    {
        return self::make('deload', 'DÉCHARGE', [$exercise.' → '.Format::kg($kg), 'TON EGO allège. Reconstruis.'], 'alert');
    }

    public static function addWeight(string $exercise): array
    {
        return self::make('add_weight', 'LESTE-TOI', [$exercise, 'Ajoute 2,5 kg de lest la prochaine fois.']);
    }

    /** @param list<string> $lines */
    public static function powerMeasured(array $lines): array
    {
        return self::make('power', 'PUISSANCE MESURÉE', $lines, highlight: 'CALIBRAGE');
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

    public static function challengeStarted(Challenge $challenge): array
    {
        return self::make('challenge', 'DÉFI LANCÉ', [
            $challenge->title().' · '.$challenge->crew->name,
            'Fin '.$challenge->ends_at->copy()->subSecond()->translatedFormat('l j F'),
        ], highlight: 'GO');
    }

    public static function challengeFinished(Challenge $challenge, ?int $rank, int $total, string $winners): array
    {
        $place = $rank === null ? 'Non classé' : 'Tu finis '.$rank.($rank === 1 ? 'ᵉʳ' : 'ᵉ').' / '.$total;

        return self::make('challenge', 'DÉFI TERMINÉ', array_values(array_filter([
            $challenge->title().' · '.$challenge->crew->name,
            $place.' · Vainqueur : '.$winners,
            $challenge->stake ? 'Enjeu : '.$challenge->stake : null,
        ])), highlight: $rank === 1 ? 'VICTOIRE' : null);
    }
}
