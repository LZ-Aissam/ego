<?php

// Objectif Force pure (mode Anatoly) : très lourd, peu de répétitions, peu de volume. Même format que power-templates.php.
// « weeks_upper » : option sans jambes, 100 % haut du corps.
// « @same » : la ligne reprend l'exercice de la ligne précédente (série record puis séries lourdes du même mouvement).

$bench = ['bench-press', 'dumbbell-bench-press', 'push-up'];
$squat = ['back-squat', 'goblet-squat', 'bodyweight-squat'];
$hinge = ['deadlift', 'dumbbell-romanian-deadlift', 'glute-bridge'];
$press = ['overhead-press', 'dumbbell-shoulder-press', 'pike-push-up'];
$row = ['barbell-row', 'dumbbell-row', 'inverted-row'];

return [
    'sessions' => [
        'bench' => ['Couché · LOURD', [
            ['horizontal_push', 'explosive', 4, 3, 3, 120, ['clap-push-up']],
            ['horizontal_push', 'top', 1, 1, 2, 300, $bench],
            ['horizontal_push', 'max', 4, 2, 3, 240, ['@same']],
            ['vertical_pull', 'max', 5, 3, 3, 180, ['pull-up', 'chin-up', 'inverted-row']],
            ['shoulder_iso', 'mass', 3, 12, 15, 60, ['face-pull', 'rear-delt-fly']],
            ['core', 'mass', 3, 8, 12, 60, ['hanging-leg-raise', 'crunch']],
        ]],
        'squat' => ['Squat · LOURD', [
            ['jump', 'explosive', 4, 3, 3, 120, ['box-jump']],
            ['squat', 'max', 5, 2, 3, 240, $squat],
            ['vertical_push', 'max', 5, 3, 5, 180, $press],
            ['core', 'mass', 3, 8, 12, 60, ['cable-crunch', 'crunch']],
        ]],
        'upper_speed' => ['Haut du corps · VITESSE', [
            ['horizontal_push', 'speed', 6, 2, 2, 60, ['bench-press', 'dumbbell-bench-press', 'clap-push-up']],
            ['horizontal_pull', 'max', 5, 3, 5, 180, $row],
            ['triceps', 'max', 4, 3, 5, 180, ['dips']],
            ['shoulder_iso', 'mass', 3, 12, 15, 60, ['lateral-raise', 'cable-lateral-raise']],
            ['core', 'mass', 3, 8, 12, 60, ['hanging-leg-raise', 'crunch']],
        ]],
        'deadlift' => ['Soulevé de terre · LOURD', [
            ['jump', 'explosive', 4, 3, 3, 120, ['broad-jump']],
            ['hinge', 'top', 1, 1, 2, 300, $hinge],
            ['hinge', 'max', 3, 2, 3, 240, ['@same']],
            ['squat', 'speed', 6, 2, 2, 90, $squat],
            ['hinge', 'max', 4, 3, 5, 180, ['hip-thrust', 'glute-bridge']],
            ['core', 'mass', 3, 8, 12, 60, ['cable-crunch', 'crunch']],
        ]],
        'shoulders_speed' => ['Épaules & tractions · VITESSE', [
            ['vertical_push', 'explosive', 5, 3, 3, 150, ['push-press', 'dumbbell-shoulder-press', 'pike-push-up']],
            ['vertical_push', 'speed', 6, 2, 2, 90, $press],
            ['vertical_pull', 'explosive', 5, 3, 3, 120, ['pull-up', 'chin-up']],
            ['shoulder_iso', 'mass', 3, 12, 15, 60, ['face-pull', 'rear-delt-fly']],
            ['core', 'mass', 3, 10, 15, 60, ['crunch']],
        ]],
        'legs_speed' => ['Jambes · VITESSE', [
            ['jump', 'explosive', 5, 3, 3, 120, ['box-jump']],
            ['jump', 'explosive', 3, 5, 5, 120, ['jump-squat']],
            ['squat', 'speed', 8, 2, 2, 60, $squat],
            ['hinge', 'speed', 6, 2, 2, 90, $hinge],
            ['core', 'mass', 3, 8, 12, 60, ['cable-crunch', 'crunch']],
        ]],
        'full_bench_squat' => ['Full · COUCHÉ & SQUAT', [
            ['horizontal_push', 'top', 1, 1, 2, 300, $bench],
            ['horizontal_push', 'max', 4, 2, 3, 240, ['@same']],
            ['squat', 'max', 5, 2, 3, 240, $squat],
            ['vertical_pull', 'max', 5, 3, 3, 180, ['pull-up', 'chin-up', 'inverted-row']],
            ['core', 'mass', 3, 8, 12, 60, ['hanging-leg-raise', 'crunch']],
        ]],
        'full_deadlift_press' => ['Full · TERRE & ÉPAULES', [
            ['hinge', 'top', 1, 1, 2, 300, $hinge],
            ['hinge', 'max', 3, 2, 3, 240, ['@same']],
            ['vertical_push', 'max', 5, 3, 5, 180, $press],
            ['horizontal_pull', 'max', 5, 3, 5, 180, $row],
            ['core', 'mass', 3, 8, 12, 60, ['cable-crunch', 'crunch']],
        ]],
        'press' => ['Développé militaire · LOURD', [
            ['vertical_push', 'explosive', 4, 3, 3, 150, ['push-press', 'dumbbell-shoulder-press', 'pike-push-up']],
            ['vertical_push', 'top', 1, 1, 2, 300, $press],
            ['vertical_push', 'max', 4, 2, 3, 240, ['@same']],
            // Rowing et dips sont lourds le lendemain (Haut du corps · VITESSE) : ici, du volume modéré.
            ['horizontal_pull', 'mass', 3, 6, 8, 120, ['chest-supported-row', 'dumbbell-row', 'inverted-row']],
            ['triceps', 'mass', 3, 8, 10, 90, ['skull-crusher', 'overhead-dumbbell-extension', 'diamond-push-up']],
            ['core', 'mass', 3, 8, 12, 60, ['hanging-leg-raise', 'crunch']],
        ]],
        'bench_b' => ['Couché · LOURD B', [
            ['horizontal_push', 'top', 1, 1, 2, 300, $bench],
            ['horizontal_push', 'max', 4, 2, 3, 240, ['@same']],
            ['horizontal_push', 'max', 4, 3, 5, 180, ['incline-bench-press', 'incline-dumbbell-press', 'dips']],
            ['vertical_pull', 'max', 5, 3, 3, 180, ['chin-up', 'pull-up', 'inverted-row']],
            ['shoulder_iso', 'mass', 3, 12, 15, 60, ['rear-delt-fly', 'face-pull']],
            ['core', 'mass', 3, 8, 12, 60, ['cable-crunch', 'crunch']],
        ]],
        // Semaine vague (sans jambes, 5 jours) : un seul jour très lourd par mouvement (5/3/1 + séries de suite),
        // du volume à 75 % et de la vitesse les autres jours. « null » : accessoire sans intention (double progression).
        'wave_bench' => ['Couché · LOURD', [
            ['horizontal_push', 'wave', 1, 5, 5, 300, $bench],
            ['horizontal_push', 'backoff', 3, 5, 5, 180, ['@same']],
            ['vertical_pull', 'max', 5, 3, 3, 180, ['pull-up', 'chin-up', 'inverted-row']],
            ['biceps', null, 4, 5, 6, 120, ['barbell-curl', 'dumbbell-curl']],
        ]],
        'wave_press' => ['Militaire · LOURD', [
            ['vertical_push', 'wave', 1, 5, 5, 300, $press],
            ['vertical_push', 'backoff', 3, 5, 5, 180, ['@same']],
            ['horizontal_pull', 'max', 5, 3, 5, 180, $row],
            ['biceps', null, 3, 6, 8, 90, ['hammer-curl', 'dumbbell-curl']],
            ['forearms', null, 3, 10, 12, 60, ['wrist-curl']],
            ['shoulder_iso', null, 3, 10, 12, 60, ['lateral-raise', 'cable-lateral-raise']],
        ]],
        'wave_volume' => ['Couché · VOLUME', [
            ['horizontal_push', 'volume', 5, 5, 5, 150, $bench],
            ['vertical_pull', null, 4, 5, 5, 120, ['chin-up', 'pull-up', 'inverted-row']],
            ['triceps', null, 4, 5, 5, 120, ['dips', 'bench-dips']],
            ['forearms', null, 3, 8, 8, 60, ['reverse-curl']],
        ]],
        'wave_speed_press' => ['Épaules & tirage · VITESSE', [
            ['vertical_push', 'explosive', 5, 3, 3, 150, ['push-press', 'dumbbell-shoulder-press', 'pike-push-up']],
            ['vertical_push', 'speed', 8, 3, 3, 90, $press],
            ['vertical_pull', 'explosive', 5, 3, 3, 120, ['pull-up', 'chin-up']],
            ['shoulder_iso', null, 3, 12, 15, 60, ['face-pull', 'rear-delt-fly']],
            ['triceps', null, 3, 6, 8, 90, ['skull-crusher', 'overhead-dumbbell-extension', 'diamond-push-up']],
        ]],
        'wave_speed_bench' => ['Couché · VITESSE + bras', [
            ['horizontal_push', 'speed', 8, 3, 3, 60, ['bench-press', 'dumbbell-bench-press', 'clap-push-up']],
            ['triceps', 'max', 4, 3, 5, 180, ['dips']],
            ['horizontal_pull', null, 4, 6, 6, 120, ['chest-supported-row', 'dumbbell-row', 'inverted-row']],
            ['biceps', null, 3, 6, 8, 90, ['hammer-curl', 'dumbbell-curl']],
            ['forearms', null, 3, 12, 12, 60, ['wrist-extension']],
            ['shoulder_iso', null, 3, 10, 12, 60, ['lateral-raise', 'cable-lateral-raise']],
        ]],
        // Semaine de 7 jours (sans jambes) : 3 jours très lourds (couché, militaire, dos), un jour de volume, deux jours de vitesse,
        // une récupération active. Chaque mouvement de base n'est très lourd qu'une fois, jamais deux jours d'affilée.
        // Bras complets : biceps longue (curl incliné) et courte portion (curl concentré), triceps long (au-dessus de la tête)
        // et court (poulie, prise serrée), avant-bras (poignets, curl inversé), en 5 à 10 répétitions pour la force.
        'w7_bench' => ['Couché · LOURD', [
            ['horizontal_push', 'wave', 1, 5, 5, 300, $bench],
            ['horizontal_push', 'backoff', 3, 5, 5, 180, ['@same']],
            ['vertical_pull', 'max', 5, 3, 3, 180, ['pull-up', 'chin-up', 'inverted-row']],
            ['biceps', null, 4, 5, 6, 120, ['barbell-curl', 'dumbbell-curl']],
            ['shoulder_iso', null, 3, 12, 15, 60, ['face-pull', 'rear-delt-fly']],
        ]],
        'w7_press' => ['Militaire · LOURD', [
            ['vertical_push', 'wave', 1, 5, 5, 300, $press],
            ['vertical_push', 'backoff', 3, 5, 5, 180, ['@same']],
            ['triceps', 'max', 4, 3, 5, 180, ['dips']],
            ['triceps', null, 3, 8, 10, 90, ['overhead-cable-extension', 'overhead-dumbbell-extension', 'diamond-push-up']],
            ['shoulder_iso', null, 3, 10, 12, 60, ['lateral-raise', 'cable-lateral-raise']],
            ['forearms', null, 3, 10, 12, 60, ['wrist-curl']],
        ]],
        'w7_back' => ['Dos · LOURD', [
            ['horizontal_pull', 'max', 5, 3, 5, 180, $row],
            ['vertical_pull', 'max', 4, 3, 5, 180, ['chin-up', 'pull-up', 'inverted-row']],
            ['biceps', null, 3, 6, 8, 90, ['incline-dumbbell-curl', 'dumbbell-curl']],
            ['biceps', null, 3, 6, 8, 90, ['hammer-curl', 'dumbbell-curl']],
            ['shoulder_iso', null, 3, 12, 15, 60, ['rear-delt-fly', 'face-pull']],
            ['forearms', null, 3, 8, 10, 60, ['reverse-curl']],
        ]],
        'w7_volume' => ['Couché · VOLUME + triceps', [
            ['horizontal_push', 'volume', 5, 5, 5, 150, $bench],
            ['triceps', null, 4, 5, 6, 150, ['close-grip-bench-press', 'dips', 'diamond-push-up']],
            ['triceps', null, 3, 8, 10, 90, ['triceps-pushdown', 'bench-dips']],
            ['shoulder_iso', null, 3, 12, 15, 60, ['cable-lateral-raise', 'lateral-raise']],
            ['forearms', null, 3, 12, 12, 60, ['wrist-extension']],
        ]],
        'w7_speed_bench' => ['Couché · VITESSE + bras', [
            ['horizontal_push', 'speed', 8, 3, 3, 60, ['bench-press', 'dumbbell-bench-press', 'clap-push-up']],
            ['horizontal_pull', null, 4, 6, 6, 120, ['chest-supported-row', 'dumbbell-row', 'inverted-row']],
            ['biceps', null, 3, 8, 10, 60, ['concentration-curl', 'dumbbell-curl']],
            ['triceps', null, 3, 8, 10, 90, ['overhead-dumbbell-extension', 'skull-crusher', 'diamond-push-up']],
            ['shoulder_iso', null, 3, 10, 12, 60, ['lateral-raise', 'cable-lateral-raise']],
        ]],
        // Récupération active : rien de lourd, coiffe des rotateurs, arrière d'épaule et poignets, pour revenir frais au couché lourd.
        'recovery' => ['Épaules & poignets · RÉCUP', [
            ['shoulder_iso', null, 3, 15, 15, 60, ['face-pull', 'rear-delt-fly']],
            ['shoulder_iso', null, 3, 15, 15, 60, ['rear-delt-fly', 'lateral-raise']],
            ['forearms', null, 3, 12, 15, 60, ['wrist-curl']],
            ['forearms', null, 3, 12, 15, 60, ['wrist-extension']],
            ['forearms', null, 3, 10, 12, 60, ['reverse-curl', 'hammer-curl']],
        ]],
        // Au poids du corps, ni poulie ni haltère : tirage léger, triceps et gainage.
        'recovery_bw' => ['Tirage léger & gainage · RÉCUP', [
            ['horizontal_pull', null, 3, 8, 12, 90, ['inverted-row']],
            ['horizontal_push', null, 3, 10, 15, 60, ['push-up']],
            ['triceps', null, 3, 10, 15, 60, ['diamond-push-up', 'bench-dips']],
            ['core', null, 3, 10, 15, 60, ['hanging-leg-raise', 'crunch']],
        ]],
    ],
    'substitutes' => ['bodyweight' => ['recovery' => 'recovery_bw']],
    'weeks' => [
        2 => ['full_bench_squat', 'full_deadlift_press'],
        3 => ['bench', 'squat', 'deadlift'],
        4 => ['bench', 'squat', 'upper_speed', 'deadlift'],
        5 => ['bench', 'squat', 'upper_speed', 'deadlift', 'shoulders_speed'],
        6 => ['bench', 'squat', 'upper_speed', 'deadlift', 'shoulders_speed', 'legs_speed'],
        7 => ['bench', 'squat', 'upper_speed', 'deadlift', 'shoulders_speed', 'legs_speed', 'recovery'],
    ],
    'weeks_upper' => [
        2 => ['bench', 'press'],
        3 => ['bench', 'press', 'upper_speed'],
        4 => ['bench', 'press', 'upper_speed', 'bench_b'],
        5 => ['wave_bench', 'wave_press', 'wave_volume', 'wave_speed_press', 'wave_speed_bench'],
        6 => ['bench', 'press', 'upper_speed', 'bench_b', 'shoulders_speed', 'upper_speed'],
        7 => ['w7_bench', 'w7_press', 'w7_back', 'w7_volume', 'wave_speed_press', 'w7_speed_bench', 'recovery'],
    ],
];
