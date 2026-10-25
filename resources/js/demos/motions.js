// Source de la démonstration d'un exercice, de la plus détaillée à la plus simple :
// animation continue workout-motion, sinon dessin Everkinetic en deux positions, sinon silhouette animée.
import { CREDIT as FRAMES_CREDIT, framesOf } from './illustrations.js';

export const MOTION_CREDIT = 'Animation : workout-motion (februarysea et contributeurs) · PolyForm Noncommercial 1.0.0';

const same = (id) => ({ id, note: '' });
const near = (id, note) => ({ id, note });

/** Exercice du catalogue → animation workout-motion (resources/js/vendor/workout-motion). */
export const MOTIONS = {
    'bench-press': same('bench-press'),
    'dumbbell-bench-press': same('dumbbell-bench-press'),
    'close-grip-bench-press': same('close-grip-bench-press'),
    'incline-dumbbell-press': same('incline-dumbbell-press'),
    dips: same('dip'),
    'pike-push-up': same('pike-push-up'),
    'overhead-press': same('overhead-press'),
    'push-press': near('overhead-press', 'Push press : plie légèrement les genoux, puis pousse avec les jambes pour lancer la barre au-dessus de la tête.'),
    'dumbbell-shoulder-press': same('dumbbell-shoulder-press'),
    'arnold-press': near('dumbbell-shoulder-press', 'Arnold : départ paumes vers toi, tourne les mains vers l’avant pendant la montée.'),
    'machine-shoulder-press': same('shoulder-press-machine'),
    'barbell-row': same('bent-over-row'),
    'dumbbell-row': same('single-arm-dumbbell-row'),
    'chest-supported-row': near('chest-supported-row-machine', 'Ici sur machine : fais-le avec des haltères, buste appuyé sur un banc incliné.'),
    'seated-cable-row': same('seated-cable-row'),
    'inverted-row': same('inverted-row'),
    'pull-up': same('pull-up'),
    'chin-up': near('pull-up', 'Tractions supination : paumes vers toi, mains à la largeur des épaules.'),
    'lat-pulldown': same('lat-pulldown'),
    'dumbbell-pullover': same('dumbbell-pullover'),
    'back-squat': same('squat'),
    'front-squat': same('front-squat'),
    'goblet-squat': same('goblet-squat'),
    'bodyweight-squat': same('bodyweight-squat'),
    'leg-press': same('leg-press'),
    deadlift: same('conventional-deadlift'),
    'romanian-deadlift': same('romanian-deadlift'),
    'dumbbell-romanian-deadlift': same('dumbbell-romanian-deadlift'),
    'hip-thrust': same('hip-thrust'),
    'glute-bridge': same('glute-bridge'),
    'walking-lunge': near('forward-lunge', 'Fentes marchées : enchaîne les pas vers l’avant, une jambe après l’autre.'),
    'bodyweight-lunge': same('forward-lunge'),
    'bulgarian-split-squat': same('bulgarian-split-squat'),
    'step-up': same('step-up'),
    'dumbbell-fly': same('dumbbell-fly'),
    'cable-fly': same('standing-cable-chest-fly'),
    'pec-deck': same('pec-deck'),
    'lateral-raise': same('lateral-raise'),
    'cable-lateral-raise': same('cable-lateral-raise'),
    'rear-delt-fly': same('bent-over-dumbbell-reverse-fly'),
    'face-pull': same('cable-face-pull'),
    'barbell-curl': same('barbell-curl'),
    'dumbbell-curl': same('dumbbell-curl'),
    'hammer-curl': same('hammer-curl'),
    'cable-curl': same('cable-biceps-curl'),
    'triceps-pushdown': same('triceps-pushdown'),
    'overhead-dumbbell-extension': same('overhead-dumbbell-triceps-extension'),
    'leg-extension': same('seated-leg-extension'),
    'leg-curl': same('prone-leg-curl'),
    'standing-calf-raise': same('machine-calf-raise'),
    'dumbbell-calf-raise': same('standing-calf-raise'),
    'bodyweight-calf-raise': same('standing-calf-raise'),
    'box-jump': same('box-jump'),
};

/**
 * @returns {{kind: 'motion', id: string, note: string, credit: string}
 *   | {kind: 'frames', frames: string[], note: string, credit: string}
 *   | {kind: 'rig', note: string, credit: string}}
 */
export function demoSource(slug, withMotion = true) {
    const motion = withMotion ? MOTIONS[slug] : null;
    if (motion) {
        return { kind: 'motion', id: motion.id, note: motion.note, credit: MOTION_CREDIT };
    }
    const frames = framesOf(slug);
    if (frames) {
        return { kind: 'frames', frames, note: '', credit: FRAMES_CREDIT };
    }

    return { kind: 'rig', note: '', credit: '' };
}
