<?php

namespace App\Services;

use App\Models\Exercise;
use Illuminate\Support\Collection;

/**
 * Programmes à intentions tirés d'un modèle de séances (Force × Vitesse : power-templates.php,
 * Force pure : pure-templates.php), adaptés au niveau et au matériel.
 */
final class PowerProgram
{
    /** @param  string  $split  objectif et nom du découpage : power, pure */
    public function __construct(private string $split = 'power') {}

    public function generate(array $params, Collection $exercises): array
    {
        $templates = require database_path("data/programs/{$this->split}-templates.php");
        // Comme les autres objectifs avec leur exercice de bras : 6, 7 ou 8 exercices par séance.
        $limit = (ProgramGenerator::SLOTS_BY_LEVEL[$params['level']] ?? 5) + 1;
        $weeks = ($params['upper_only'] ?? false) && isset($templates['weeks_upper']) ? $templates['weeks_upper'] : $templates['weeks'];
        $days = [];

        foreach ($weeks[$params['days']] as $key) {
            [$name, $lines] = $templates['sessions'][$templates['substitutes'][$params['equipment']][$key] ?? $key];
            $used = [];
            $main = [];
            $core = [];

            $previous = null;

            foreach ($lines as [$slot, $intent, $sets, $min, $max, $rest, $preferred]) {
                $pick = $preferred === ['@same'] ? $previous : $this->pick($slot, $intent, $preferred, $params['equipment'], $exercises, $used);
                if (! $pick) {
                    continue;
                }
                $previous = $pick;
                $used[] = $pick->id;

                // Série record : pas pour un débutant, ni sans charge à mesurer. Ses séries lourdes (@same) restent.
                if ($intent === 'top' && ($params['level'] === 'beginner' || $pick->is_bodyweight)) {
                    continue;
                }
                $item = $this->adapt(['exercise_id' => $pick->id, 'slot' => $slot, 'intent' => $intent, 'sets' => $sets, 'reps_min' => $min, 'reps_max' => $max, 'rest_seconds' => $rest], $pick, $params['level'], $params['equipment']);
                $slot === 'core' ? $core[] = $item : $main[] = $item;
            }

            $days[] = ['name' => $name, 'type' => 'custom', 'exercises' => [...array_slice($main, 0, max(0, $limit - count($core))), ...$core]];
        }

        return ['split' => $this->split, 'days' => $days];
    }

    /** Premier slug préféré disponible, sinon le premier exercice de l'emplacement pour ce matériel. */
    private function pick(string $slot, string $intent, array $preferred, string $equipment, Collection $exercises, array $used): ?Exercise
    {
        // Une salle complète a tout : barre de traction, barres parallèles et sol pour le poids du corps.
        $owned = $equipment === 'gym' ? ['gym', 'bodyweight'] : [$equipment];
        $fits = fn (Exercise $e) => array_intersect($owned, $e->equipment) !== [] && ! in_array($e->id, $used, true);

        foreach ($preferred as $slug) {
            $exercise = $exercises->firstWhere('slug', $slug);
            if ($exercise && $fits($exercise)) {
                return $exercise;
            }
        }

        // En repli, lourd, rapide ou explosif seulement sur un poly-articulaire : jamais un pull-over à 87,5 %.
        return $exercises->filter(fn (Exercise $e) => in_array($slot, $e->slots, true) && $fits($e) && ($intent === 'mass' || $e->kind === 'compound'))
            ->sortBy('id')->first();
    }

    private function adapt(array $item, Exercise $exercise, string $level, string $equipment): array
    {
        // Sans charge, force max et vitesse n'ont pas de sens : séries lourdes en répétitions, ou explosivité.
        if ($equipment === 'bodyweight' && $exercise->is_bodyweight) {
            $item = match ($item['intent']) {
                'max' => [...$item, 'intent' => 'mass', 'reps_min' => 5, 'reps_max' => 8],
                'speed' => [...$item, 'intent' => 'explosive'],
                default => $item,
            };
        }

        // Un débutant apprend le geste lourd en 4 à 6 répétitions avant de viser 87,5 %.
        if ($level === 'beginner' && $item['intent'] === 'max') {
            $item = [...$item, 'intent' => 'mass', 'reps_min' => 4, 'reps_max' => 6, 'sets' => max(3, $item['sets'] - 1)];
        }

        return $item;
    }
}
