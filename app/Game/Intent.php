<?php

namespace App\Game;

/** Intention d'un exercice dans un programme : elle fixe la charge, la consigne et la progression. */
final class Intent
{
    public const META = [
        'speed' => ['icon' => '⚡', 'label' => 'VITESSE', 'pct' => 0.575, 'cue' => 'Charge légère : chaque répétition le plus vite possible, arrête la série dès que ça ralentit.'],
        'max' => ['icon' => '🔥', 'label' => 'FORCE MAX', 'pct' => 0.875, 'cue' => 'Lourd : 1 à 3 répétitions parfaites, repos complet.'],
        'explosive' => ['icon' => '💥', 'label' => 'EXPLOSIF', 'pct' => 0.40, 'cue' => 'Explosion maximale, repos complet entre les séries : la qualité avant la fatigue.'],
        'mass' => ['icon' => '🧱', 'label' => 'MASSE', 'pct' => null, 'cue' => 'Contrôle la descente, tension continue.'],
    ];

    public const KEYS = ['speed', 'max', 'explosive', 'mass'];

    public const ADVICE = 'Corps sec : vise 1,8 à 2 g de protéines par kg et un léger déficit si tu veux sécher.';

    public static function badge(?string $intent): string
    {
        return isset(self::META[$intent]) ? self::META[$intent]['icon'].' '.self::META[$intent]['label'] : '';
    }

    public static function cue(?string $intent): ?string
    {
        return self::META[$intent]['cue'] ?? null;
    }
}
