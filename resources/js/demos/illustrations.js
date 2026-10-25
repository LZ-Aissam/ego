// Généré par scripts/everkinetic/import.mjs : exercices illustrés par Everkinetic (deux positions, CC BY-SA 4.0).
export const ILLUSTRATED = new Set([
    'back-squat',
    'barbell-curl',
    'bench-dips',
    'bench-press',
    'cable-curl',
    'cable-fly',
    'close-grip-bench-press',
    'crunch',
    'deadlift',
    'diamond-push-up',
    'dips',
    'dumbbell-bench-press',
    'dumbbell-curl',
    'dumbbell-fly',
    'dumbbell-pullover',
    'front-squat',
    'glute-bridge',
    'hammer-curl',
    'incline-bench-press',
    'incline-dumbbell-press',
    'inverted-row',
    'lateral-raise',
    'leg-curl',
    'leg-extension',
    'leg-press',
    'machine-chest-press',
    'pull-up',
    'push-up',
    'rear-delt-fly',
    'reverse-curl',
    'romanian-deadlift',
    'seated-cable-row',
    'skull-crusher',
    'standing-calf-raise',
    'triceps-pushdown'
]);

export const CREDIT = 'Illustration : Everkinetic (Greg Priday) · CC BY-SA 4.0';

/** Les deux positions : 0 au début de l’animation, 1 à l’autre bout du mouvement. */
export const framesOf = (slug) => (ILLUSTRATED.has(slug) ? [0, 1].map((i) => `/demos/everkinetic/${slug}-${i}.svg`) : null);
