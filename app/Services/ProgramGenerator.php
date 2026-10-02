<?php

namespace App\Services;

use App\Models\Exercise;
use Illuminate\Support\Collection;

final class ProgramGenerator
{
    public const SPLITS = [
        2 => ['full_body', [['Full body A', 'full_body'], ['Full body B', 'full_body']]],
        3 => ['full_body', [['Full body A', 'full_body'], ['Full body B', 'full_body'], ['Full body C', 'full_body']]],
        4 => ['upper_lower', [['Haut A', 'upper'], ['Bas A', 'lower'], ['Haut B', 'upper'], ['Bas B', 'lower']]],
        5 => ['ppl_ul', [['Push', 'push'], ['Pull', 'pull'], ['Legs', 'legs'], ['Haut', 'upper'], ['Bas', 'lower']]],
        6 => ['ppl', [['Push A', 'push'], ['Pull A', 'pull'], ['Legs A', 'legs'], ['Push B', 'push'], ['Pull B', 'pull'], ['Legs B', 'legs']]],
    ];

    /** Emplacements ordonnés par type de jour : poly-articulaires d'abord. */
    public const DAY_SLOTS = [
        'full_body' => ['squat', 'horizontal_push', 'horizontal_pull', 'hinge', 'vertical_push', 'vertical_pull', 'core'],
        'upper' => ['horizontal_push', 'horizontal_pull', 'vertical_push', 'vertical_pull', 'biceps', 'triceps', 'shoulder_iso'],
        'lower' => ['squat', 'hinge', 'lunge', 'quads_iso', 'hamstrings_iso', 'calves', 'core'],
        'push' => ['horizontal_push', 'vertical_push', 'chest_iso', 'triceps', 'shoulder_iso', 'calves', 'core'],
        'pull' => ['vertical_pull', 'horizontal_pull', 'hinge', 'biceps', 'shoulder_iso', 'core', 'calves'],
        'legs' => ['squat', 'hinge', 'lunge', 'quads_iso', 'hamstrings_iso', 'calves', 'core'],
    ];

    /**
     * Ordre d'exécution, quand il diffère de l'ordre de choix : le mouvement le plus exigeant d'abord,
     * dos et prise encore frais, puis les tirages, l'isolation, mollets et gainage en dernier.
     */
    public const EXECUTION_ORDER = [
        'pull' => ['hinge', 'vertical_pull', 'horizontal_pull', 'shoulder_iso', 'biceps', 'calves', 'core'],
    ];

    /** Exercice de bras en fin de séance : le groupe tourne d'une journée à l'autre. */
    public const ARM_ROTATION = ['biceps', 'triceps', 'forearms'];

    public const SLOTS_BY_LEVEL = ['beginner' => 5, 'intermediate' => 6, 'advanced' => 7];

    /** [séries, reps min, reps max, repos s] */
    private const PRESCRIPTION = [
        'strength' => ['compound' => [5, 3, 5, 180], 'isolation' => [3, 8, 10, 90]],
        'hypertrophy' => ['compound' => [4, 6, 10, 120], 'isolation' => [3, 10, 15, 60]],
        'fitness' => ['compound' => [3, 10, 12, 90], 'isolation' => [3, 12, 15, 60]],
    ];

    public function generate(array $params, Collection $exercises): array
    {
        if (in_array($params['goal'] ?? null, ['power', 'pure'], true)) {
            return (new PowerProgram($params['goal']))->generate($params, $exercises);
        }

        [$split, $days] = self::SPLITS[$params['days']];
        $limit = self::SLOTS_BY_LEVEL[$params['level']] ?? 5;
        $variants = [];
        $out = [];

        foreach ($days as $index => [$name, $type]) {
            $variant = $variants[$type] = ($variants[$type] ?? -1) + 1;
            $used = [];
            $items = [];

            foreach (array_slice(self::DAY_SLOTS[$type], 0, $limit) as $slot) {
                $pick = $this->pick($slot, $params['equipment'], $exercises, $variant, $used);
                if (! $pick) {
                    continue;
                }
                $used[] = $pick->id;
                $items[] = ['exercise_id' => $pick->id, 'slot' => $slot, ...$this->prescription($params['goal'], $params['level'], $pick->kind)];
            }

            $items = self::inExecutionOrder($type, $items);
            if ($arm = $this->armFinisher($index, $items, $params, $exercises, $variant, $used)) {
                $items[] = $arm;
            }

            $out[] = ['name' => $name, 'type' => $type, 'exercises' => $items];
        }

        return ['split' => $split, 'days' => $out];
    }

    /** @param  list<array{slot: string}>  $items */
    public static function inExecutionOrder(string $type, array $items): array
    {
        $order = self::EXECUTION_ORDER[$type] ?? self::DAY_SLOTS[$type];
        // Un emplacement hors de l'ordre du jour (l'exercice de bras ajouté) reste en fin de séance.
        $rank = fn (array $item) => ($i = array_search($item['slot'], $order, true)) === false ? PHP_INT_MAX : $i;
        usort($items, fn (array $a, array $b) => $rank($a) <=> $rank($b));

        return $items;
    }

    public function candidates(string $slot, string $equipment, Collection $exercises): Collection
    {
        return $exercises
            ->filter(fn (Exercise $e) => in_array($slot, $e->slots, true) && in_array($equipment, $e->equipment, true))
            ->sortBy('id')
            ->values();
    }

    public function alternativesFor(string $slot, string $equipment, Collection $exercises, ?int $exceptId = null): Collection
    {
        return $this->candidates($slot, $equipment, $exercises)->reject(fn (Exercise $e) => $e->id === $exceptId)->values();
    }

    /** Premier groupe de bras du tour absent de la journée et disponible pour ce matériel. */
    private function armFinisher(int $index, array $items, array $params, Collection $exercises, int $variant, array $used): ?array
    {
        $present = array_column($items, 'slot');

        foreach (range(0, 2) as $step) {
            $slot = self::ARM_ROTATION[($index + $step) % 3];
            if (in_array($slot, $present, true) || ! $pick = $this->pick($slot, $params['equipment'], $exercises, $variant, $used)) {
                continue;
            }

            return ['exercise_id' => $pick->id, 'slot' => $slot, ...$this->prescription($params['goal'], $params['level'], $pick->kind)];
        }

        return null;
    }

    private function pick(string $slot, string $equipment, Collection $exercises, int $variant, array $used): ?Exercise
    {
        $candidates = $this->candidates($slot, $equipment, $exercises)->reject(fn (Exercise $e) => in_array($e->id, $used, true))->values();

        return $candidates->isEmpty() ? null : $candidates[$variant % $candidates->count()];
    }

    /** @return array{sets: int, reps_min: int, reps_max: int, rest_seconds: int} */
    private function prescription(string $goal, string $level, string $kind): array
    {
        [$sets, $min, $max, $rest] = self::PRESCRIPTION[$goal][$kind];

        if ($level === 'beginner') {
            $sets = match (true) {
                $goal === 'strength' && $kind === 'compound' => 4,
                $goal === 'hypertrophy' && $kind === 'compound' => 3,
                $goal === 'fitness' && $kind === 'isolation' => 2,
                default => $sets,
            };
        }

        return ['sets' => $sets, 'reps_min' => $min, 'reps_max' => $max, 'rest_seconds' => $rest];
    }
}
