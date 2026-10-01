<?php

// Objectif Force × Vitesse : séances par rôle. Ligne = [emplacement, intention, séries, reps min, reps max, repos s, slugs préférés].
// Ordre des lignes = ordre d'exécution : explosif → force max ou vitesse → masse → abdos.

return [
    'sessions' => [
        'push' => ['Poussée · FORCE MAX', [
            ['horizontal_push', 'explosive', 4, 3, 3, 120, ['clap-push-up']],
            ['horizontal_push', 'max', 5, 2, 3, 240, ['bench-press', 'dumbbell-bench-press', 'push-up']],
            ['horizontal_push', 'mass', 4, 6, 8, 120, ['incline-dumbbell-press', 'dumbbell-bench-press', 'push-up']],
            ['triceps', 'max', 4, 3, 5, 180, ['dips']],
            ['triceps', 'mass', 3, 8, 10, 90, ['skull-crusher', 'overhead-dumbbell-extension', 'diamond-push-up']],
            ['core', 'mass', 3, 8, 12, 60, ['cable-crunch', 'crunch']],
        ]],
        'pull' => ['Tirage · FORCE MAX', [
            ['vertical_pull', 'explosive', 4, 3, 3, 120, ['pull-up']],
            ['horizontal_pull', 'max', 5, 3, 5, 180, ['barbell-row', 'dumbbell-row', 'inverted-row']],
            ['vertical_pull', 'max', 4, 3, 5, 180, ['chin-up']],
            ['horizontal_pull', 'mass', 3, 6, 8, 120, ['dumbbell-row', 'chest-supported-row', 'inverted-row']],
            ['biceps', 'mass', 4, 6, 8, 90, ['barbell-curl', 'dumbbell-curl']],
            ['biceps', 'mass', 3, 8, 10, 90, ['hammer-curl']],
            ['core', 'mass', 3, 8, 12, 60, ['hanging-leg-raise', 'crunch']],
        ]],
        'shoulders' => ['Épaules · PUISSANCE', [
            ['vertical_push', 'explosive', 5, 3, 3, 150, ['push-press', 'dumbbell-shoulder-press', 'pike-push-up']],
            ['vertical_push', 'max', 5, 3, 5, 180, ['overhead-press', 'dumbbell-shoulder-press', 'pike-push-up']],
            ['shoulder_iso', 'mass', 3, 10, 12, 60, ['lateral-raise']],
            ['shoulder_iso', 'mass', 3, 10, 12, 60, ['rear-delt-fly']],
            ['shoulder_iso', 'mass', 3, 12, 15, 60, ['face-pull']],
            ['core', 'mass', 3, 10, 15, 60, ['crunch']],
        ]],
        'speed' => ['Haut du corps · VITESSE', [
            ['horizontal_push', 'speed', 8, 3, 3, 60, ['bench-press', 'dumbbell-bench-press', 'clap-push-up']],
            ['horizontal_pull', 'speed', 6, 3, 3, 60, ['barbell-row', 'dumbbell-row', 'inverted-row']],
            ['triceps', 'explosive', 4, 5, 5, 90, ['diamond-push-up', 'clap-push-up']],
            ['triceps', 'mass', 3, 8, 10, 90, ['dips', 'bench-dips']],
            ['biceps', 'mass', 3, 8, 10, 60, ['hammer-curl', 'dumbbell-curl']],
            ['triceps', 'mass', 3, 10, 12, 60, ['triceps-pushdown', 'overhead-dumbbell-extension']],
            ['core', 'mass', 3, 8, 12, 60, ['hanging-leg-raise', 'crunch']],
        ]],
        'upper' => ['Haut du corps · FORCE MAX', [
            ['horizontal_push', 'explosive', 4, 3, 3, 120, ['clap-push-up']],
            ['horizontal_push', 'max', 5, 2, 3, 240, ['bench-press', 'dumbbell-bench-press', 'push-up']],
            ['horizontal_pull', 'max', 5, 3, 5, 180, ['barbell-row', 'dumbbell-row', 'inverted-row']],
            ['vertical_push', 'max', 4, 3, 5, 180, ['overhead-press', 'dumbbell-shoulder-press', 'pike-push-up']],
            ['vertical_pull', 'max', 4, 3, 5, 180, ['chin-up', 'pull-up']],
            ['biceps', 'mass', 3, 8, 10, 90, ['hammer-curl', 'dumbbell-curl']],
            ['core', 'mass', 3, 8, 12, 60, ['hanging-leg-raise', 'crunch']],
        ]],
        'legs_max' => ['Jambes · FORCE MAX', [
            ['jump', 'explosive', 3, 3, 3, 120, ['jump-squat']],
            ['squat', 'max', 5, 2, 3, 240, ['back-squat', 'goblet-squat', 'bodyweight-squat']],
            ['hinge', 'max', 4, 2, 3, 240, ['deadlift', 'dumbbell-romanian-deadlift', 'glute-bridge']],
            ['lunge', 'mass', 3, 6, 8, 120, ['bulgarian-split-squat', 'bodyweight-lunge']],
            ['hamstrings_iso', 'mass', 3, 6, 10, 90, ['leg-curl', 'nordic-curl']],
            ['calves', 'mass', 3, 10, 12, 60, ['standing-calf-raise', 'dumbbell-calf-raise', 'bodyweight-calf-raise']],
            ['core', 'mass', 3, 8, 12, 60, ['cable-crunch', 'crunch']],
        ]],
        'legs_explosive' => ['Jambes & hanches · EXPLOSIF', [
            ['jump', 'explosive', 5, 3, 3, 120, ['box-jump']],
            ['jump', 'explosive', 4, 3, 3, 120, ['broad-jump']],
            ['squat', 'speed', 8, 2, 2, 60, ['back-squat', 'goblet-squat', 'bodyweight-squat']],
            ['hinge', 'speed', 6, 2, 2, 90, ['deadlift', 'dumbbell-romanian-deadlift', 'glute-bridge']],
            ['jump', 'explosive', 3, 5, 5, 120, ['jump-squat']],
            ['hinge', 'max', 4, 3, 5, 180, ['hip-thrust', 'glute-bridge']],
            ['core', 'mass', 3, 8, 12, 60, ['cable-crunch', 'crunch']],
        ]],
    ],
    // Séances sans assez d'exercices pour un matériel : au poids du corps, pas d'isolation d'épaules ni de 2ᵉ poussée verticale.
    'substitutes' => [
        'bodyweight' => ['shoulders' => 'upper'],
    ],
    // Séances dans l'ordre de la semaine ; les jambes explosives toujours en dernier.
    'weeks' => [
        2 => ['upper', 'legs_explosive'],
        3 => ['push', 'pull', 'legs_explosive'],
        4 => ['push', 'pull', 'speed', 'legs_explosive'],
        5 => ['push', 'pull', 'shoulders', 'speed', 'legs_explosive'],
        6 => ['push', 'pull', 'legs_max', 'shoulders', 'speed', 'legs_explosive'],
    ],
];
