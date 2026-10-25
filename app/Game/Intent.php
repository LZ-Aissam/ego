<?php

namespace App\Game;

/** Intention d'un exercice dans un programme : elle fixe la charge, la consigne et la progression. */
final class Intent
{
    public const META = [
        'speed' => ['icon' => '⚡', 'label' => 'VITESSE', 'pct' => 0.575, 'cue' => 'Charge légère : chaque répétition le plus vite possible, arrête la série dès que ça ralentit.'],
        'max' => ['icon' => '🔥', 'label' => 'FORCE MAX', 'pct' => 0.875, 'cue' => 'Lourd : peu de répétitions, toutes parfaites, repos complet.'],
        'explosive' => ['icon' => '💥', 'label' => 'EXPLOSIF', 'pct' => 0.40, 'cue' => 'Explosion maximale, repos complet entre les séries : la qualité avant la fatigue.'],
        'top' => ['icon' => '🏆', 'label' => 'RECORD', 'pct' => 0.925, 'cue' => 'Une seule série très lourde : 1 à 2 répétitions parfaites, puis repos complet avant la suite.'],
        // Vague 5/3/1 : charge fixée par la semaine du cycle (Cycle::WAVE), sur 90 % de la base 1RM.
        'wave' => ['icon' => '🌊', 'label' => 'VAGUE', 'pct' => null, 'cue' => 'Série lourde de la vague : les répétitions du jour, propres. Si elle est Limite, fais une série de moins derrière.'],
        'backoff' => ['icon' => '🔁', 'label' => 'SUITE', 'pct' => null, 'cue' => 'Même mouvement un peu plus léger : répétitions propres et rapides, sans aller à l\'échec.'],
        'volume' => ['icon' => '📦', 'label' => 'VOLUME', 'pct' => 0.70, 'cue' => 'Charge moyenne, toutes les répétitions propres : la base de ta force, sans aller à l\'échec.'],
        'mass' => ['icon' => '🧱', 'label' => 'MASSE', 'pct' => null, 'cue' => 'Contrôle la descente, tension continue.'],
    ];

    public const KEYS = ['speed', 'max', 'explosive', 'top', 'wave', 'backoff', 'volume', 'mass'];

    public const ADVICE = 'Corps sec : vise 1,8 à 2 g de protéines par kg et un léger déficit si tu veux sécher.';

    /** Conseil en tête d'un programme à intentions : la force pure se construit à maintien, pas en déficit. */
    public static function adviceFor(?string $goal): string
    {
        return $goal === 'pure'
            ? 'Force pure : mange à maintien (ni déficit ni surplus), 1,8 à 2 g de protéines par kg, et dors au moins 7 h 30.'
            : self::ADVICE;
    }

    public static function badge(?string $intent): string
    {
        return isset(self::META[$intent]) ? self::META[$intent]['icon'].' '.self::META[$intent]['label'] : '';
    }

    public static function cue(?string $intent): ?string
    {
        return self::META[$intent]['cue'] ?? null;
    }
}
