// Démonstrations animées des exercices : silhouettes « Système » vues de profil (ou de face).
// Repère SVG : x vers la droite (le joueur regarde à droite), y vers le bas, sol en y = 0.
// Chaque famille calcule une pose pour t ∈ [0, 1] ; les membres gardent toujours leur longueur.

export const VIEWBOX = [-110, -195, 240, 215];

const L = { shin: 40, thigh: 40, torso: 50, upper: 26, fore: 24, head: 20 };
const RAD = Math.PI / 180;

const dir = (deg) => [Math.cos(deg * RAD), -Math.sin(deg * RAD)];
const add = (p, len, deg) => { const d = dir(deg); return [p[0] + len * d[0], p[1] + len * d[1]]; };
const lerp = (a, b, t) => a + (b - a) * t;
const lerpP = (p, q, t) => [lerp(p[0], q[0], t), lerp(p[1], q[1], t)];
const angleOf = (a, b) => Math.atan2(-(b[1] - a[1]), b[0] - a[0]) / RAD;
const shift = (p, dx, dy) => [p[0] + dx, p[1] + dy];

/**
 * Deux segments de longueur fixe, de `root` vers `target`.
 * bend = +1 place l'articulation à droite du trajet root → target tel qu'on le voit à l'écran, -1 à gauche.
 */
function ik(root, target, l1, l2, bend) {
    const dx = target[0] - root[0], dy = target[1] - root[1];
    const d = Math.max(Math.abs(l1 - l2) + 0.01, Math.min(Math.hypot(dx, dy), l1 + l2 - 0.01));
    const base = Math.atan2(dy, dx);
    const k = Math.acos((l1 * l1 + d * d - l2 * l2) / (2 * l1 * d));
    const mid = [root[0] + l1 * Math.cos(base + bend * k), root[1] + l1 * Math.sin(base + bend * k)];
    return [root, mid, [root[0] + d * Math.cos(base), root[1] + d * Math.sin(base)]];
}

const arm = (shoulder, target, bend) => ik(shoulder, target, L.upper, L.fore, bend);
const leg = (hip, ankle, bend) => ik(hip, ankle, L.thigh, L.shin, bend);
const fixedArm = (shoulder, a1, a2) => { const e = add(shoulder, L.upper, a1); return [shoulder, e, add(e, L.fore, a2)]; };
const headOf = (hip, shoulder) => add(shoulder, L.head, angleOf(hip, shoulder));

/** Corps debout : cheville en (x, 0), angles en degrés depuis la verticale (tibia vers l'avant, cuisse vers l'arrière, buste vers l'avant). */
function standing(shin, thigh, torso, x = 0) {
    const ankle = [x, 0];
    const knee = add(ankle, L.shin, 90 - shin);
    const hip = add(knee, L.thigh, 90 + thigh);
    const shoulder = add(hip, L.torso, 90 - torso);
    return { ankle, knee, hip, shoulder, foot: [[x - 5, 0], [x + 17, 0]] };
}

/** Corps assis, hanche au-dessus du siège, cuisses horizontales, pieds au sol. */
function seated(hip, back = 95) {
    const shoulder = add(hip, L.torso, back);
    const knee = add(hip, L.thigh, 0);
    const ankle = add(knee, L.shin, -90);
    return { hip, knee, ankle, shoulder, foot: [ankle, add(ankle, 16, 0)] };
}

const scene = (chains, head, props = [], fixtures = [], guides = [], note = '') => ({ chains, head, props, fixtures, guides, note });
const plate = (at, r = 12) => ({ type: 'plate', at, r });
const dumbbell = (at) => ({ type: 'dumbbell', at, r: 6 });
const handle = (at) => ({ type: 'handle', at, r: 3 });
const pad = (at) => ({ type: 'pad', at, r: 5 });
const line = (from, to) => ({ type: 'line', from, to });
const rect = (x, y, w, h) => ({ type: 'rect', x, y, w, h });
const guide = (from, to, label) => ({ from, to, label });

const FLAT_BENCH = [rect(-50, -30, 116, 6), line([-40, -24], [-40, 0]), line([56, -24], [56, 0])];

// ───────────────────────── Familles ─────────────────────────

const FAMILIES = {
    squat: {
        segments: [['Descente', 2000], ['Bas · cuisses parallèles', 300], ['Remontée', 1000], ['Haut · respire', 700]],
        pose(t, v) {
            const lean = { back: 38, front: 20, goblet: 24, bodyweight: 32 }[v];
            const b = standing(lerp(2, v === 'back' ? 34 : 38, t), lerp(0, 84, t), lerp(4, lean, t));
            let a, props = [];
            if (v === 'back') {
                a = fixedArm(b.shoulder, -120, 70);
                props = [plate(shift(a[2], 0, 1), 13)];
            } else if (v === 'front') {
                a = fixedArm(b.shoulder, 5, 168);
                props = [plate(shift(a[2], 2, -3), 13)];
            } else if (v === 'goblet') {
                a = fixedArm(b.shoulder, -70, 75);
                props = [dumbbell(a[2])];
            } else {
                a = fixedArm(b.shoulder, 0, 0);
            }
            const guides = v === 'back' || v === 'front' ? [guide([8, -180], [8, -2], 'axe de la barre')] : [];
            return scene({ foot: b.foot, leg: [b.ankle, b.knee, b.hip], torso: [b.hip, b.shoulder], arm: a }, headOf(b.hip, b.shoulder), props, [], guides);
        },
    },

    sissy: {
        segments: [['Descente', 2000], ['Bas · genoux en avant', 300], ['Remontée', 1000], ['Haut', 700]],
        pose(t) {
            const toe = [18, 0];
            const ankle = add(toe, 18, 180 - lerp(0, 28, t));
            const knee = add(ankle, L.shin, 90 - lerp(4, 58, t));
            const back = lerp(0, 34, t);
            const hip = add(knee, L.thigh, 90 + back);
            const shoulder = add(hip, L.torso, 90 + back);
            return scene({ foot: [toe, ankle], leg: [ankle, knee, hip], torso: [hip, shoulder], arm: fixedArm(shoulder, 0, 0) },
                headOf(hip, shoulder), [], [line([-60, -100], [-60, 0])]);
        },
    },

    legPress: {
        segments: [['Descente', 2000], ['Genoux pliés', 300], ['Poussée', 1000], ['Jambes presque tendues', 700]],
        pose(t) {
            const hip = [-30, -40];
            const shoulder = add(hip, L.torso, 140);
            const ankle = add(hip, lerp(76, 42, t), 35);
            const toe = add(ankle, 16, 125);
            return scene(
                { leg: leg(hip, ankle, -1), foot: [ankle, toe], torso: [hip, shoulder], arm: fixedArm(shoulder, -70, -20) },
                add(shoulder, L.head, 140), [],
                [
                    line(add(hip, 7, 230), add(shoulder, 7, 230)),
                    line(add(ankle, 12, -55), add(toe, 12, 125)),
                    line(add(add(hip, 16, -55), 10, 35), add(add(hip, 16, -55), 100, 35)),
                    line(add(hip, 9, -90), [hip[0], 0]),
                    line(add(add(hip, 16, -55), 95, 35), [add(add(hip, 16, -55), 95, 35)[0], 0]),
                ],
            );
        },
    },

    deadlift: {
        segments: [['Descente contrôlée', 2000], ['Barre au sol', 300], ['Tirage', 1000], ['Verrouillage', 700]],
        pose(t) {
            const b = standing(lerp(2, 24, t), lerp(0, 78, t), lerp(4, 64, t));
            const a = fixedArm(b.shoulder, -90, -90);
            return scene({ foot: b.foot, leg: [b.ankle, b.knee, b.hip], torso: [b.hip, b.shoulder], arm: a },
                headOf(b.hip, b.shoulder), [plate(a[2], 13)], [], [guide([20, -160], [20, -2], 'barre près des jambes')]);
        },
    },

    rdl: {
        segments: [['Hanches en arrière', 2000], ['Étirement des ischios', 300], ['Remontée', 1000], ['Serre les fessiers', 700]],
        pose(t, v) {
            const b = standing(lerp(2, 10, t), lerp(0, 28, t), lerp(4, 72, t));
            const a = fixedArm(b.shoulder, -90, -90);
            return scene({ foot: b.foot, leg: [b.ankle, b.knee, b.hip], torso: [b.hip, b.shoulder], arm: a },
                headOf(b.hip, b.shoulder), [v === 'db' ? dumbbell(a[2]) : plate(a[2], 12)]);
        },
    },

    hipThrust: {
        segments: [['Poussée', 1000], ['Contraction des fessiers', 500], ['Descente', 2000], ['Bas', 300]],
        pose(t, v) {
            const bench = v === 'bench';
            const shoulder = bench ? [-40, -36] : [-45, -6];
            const angle = bench ? lerp(-28, 0, t) : lerp(0, 24, t);
            const hip = add(shoulder, L.torso, angle);
            const ankle = bench ? [38, 0] : [32, 0];
            const a = bench ? arm(shoulder, shift(hip, -2, -10), -1) : fixedArm(shoulder, -8, 0);
            return scene(
                { leg: leg(hip, ankle, -1), foot: [ankle, shift(ankle, 16, 0)], torso: [hip, shoulder], arm: a },
                add(shoulder, L.head, angle + 180), bench ? [plate(shift(hip, 0, -13), 13)] : [],
                bench ? [rect(-100, -34, 60, 6), line([-90, -28], [-90, 0])] : [],
            );
        },
    },

    lunge: {
        segments: [['Descente', 2000], ['Genou arrière près du sol', 300], ['Remontée', 1000], ['Haut', 700]],
        pose(t, v) {
            const bulgarian = v === 'bulgarian';
            const hip = [bulgarian ? -8 : -5, lerp(-72, bulgarian ? -40 : -44, t)];
            const front = bulgarian ? [22, 0] : [26, 0];
            const rear = bulgarian ? [-58, -32] : [-36, -7];
            const rearToe = bulgarian ? [-76, -31] : [-16, 0];
            const shoulder = add(hip, L.torso, 88);
            const a = v === 'bw' ? fixedArm(shoulder, -130, -30) : fixedArm(shoulder, -92, -90);
            return scene(
                { legFront: leg(hip, front, -1), legBack: leg(hip, rear, -1), footFront: [shift(front, -5, 0), shift(front, 17, 0)], footBack: [rear, rearToe], torso: [hip, shoulder], arm: a },
                headOf(hip, shoulder), v === 'bw' ? [] : [dumbbell(a[2])],
                bulgarian ? [rect(-92, -30, 40, 6), line([-84, -24], [-84, 0]), line([-60, -24], [-60, 0])] : [],
            );
        },
    },

    stepUp: {
        segments: [['Montée', 1200], ['En haut', 300], ['Descente contrôlée', 2000], ['Bas', 300]],
        pose(t) {
            const hip = lerpP([-12, -48], [22, -104], t);
            const front = [24, -28];
            const rear = [lerp(-30, 8, t), lerp(0, -28, t) - Math.sin(Math.PI * t) * 14];
            const shoulder = add(hip, L.torso, 86);
            const a = fixedArm(shoulder, -92, -90);
            return scene(
                { legFront: leg(hip, front, -1), legBack: leg(hip, rear, -1), footFront: [shift(front, -5, 0), shift(front, 17, 0)], footBack: [rear, shift(rear, 18, 0)], torso: [hip, shoulder], arm: a },
                headOf(hip, shoulder), [dumbbell(a[2])], [rect(0, -28, 62, 28)],
            );
        },
    },

    bench: {
        segments: [['Descente', 2000], ['Touche · bas des pecs', 300], ['Poussée', 1000], ['Bras tendus · respire', 700]],
        pose(t, v) {
            const db = v.endsWith('db');
            if (v.startsWith('incline')) {
                const hip = [36, -30];
                const shoulder = add(hip, L.torso, 148);
                const knee = add(hip, L.thigh, 10);
                const ankle = add(knee, 36, -85);
                const hand = lerpP(shift(shoulder, 4, -48), shift(shoulder, 13, -9), t);
                return scene(
                    { torso: [hip, shoulder], leg: [hip, knee, ankle], foot: [ankle, shift(ankle, 16, 0)], arm: arm(shoulder, hand, 1) },
                    add(shoulder, L.head, 148), [db ? dumbbell(hand) : plate(hand, 12)],
                    [line(shift(hip, 4, 6), shift(shoulder, 2, 8)), line([30, -24], [30, 0]), line([-20, -40], [-20, 0])],
                );
            }
            const shoulder = [0, -34];
            const hip = [48, -34];
            // Prise serrée : coudes le long du buste, la barre descend plus bas sur la poitrine.
            const close = v === 'close-bar';
            const [top, low] = close ? [[6, -84], [16, -46]] : [[2, -84], [11, -48]];
            const hand = lerpP(top, low, t);
            return scene(
                { torso: [shoulder, [24, -37], hip], leg: [hip, [76, -52], [80, 0]], foot: [[80, 0], [96, 0]], arm: arm(shoulder, hand, 1) },
                [-17, -37], [db ? dumbbell(hand) : plate(hand, 12)], FLAT_BENCH,
                db ? [] : [guide(top, shift(low, 0, -2), close ? 'coudes le long du buste' : 'trajet de la barre')],
            );
        },
    },

    skull: {
        segments: [['Descente contrôlée', 2000], ['Barre près du front', 300], ['Extension', 1000], ['Bras tendus', 400]],
        pose(t) {
            const shoulder = [0, -34];
            const elbow = add(shoulder, L.upper, 100);
            const hand = add(elbow, L.fore, lerp(95, 205, t));
            return scene(
                { torso: [shoulder, [24, -37], [48, -34]], leg: [[48, -34], [76, -52], [80, 0]], foot: [[80, 0], [96, 0]], arm: [shoulder, elbow, hand] },
                [-17, -37], [plate(hand, 8)], FLAT_BENCH,
            );
        },
    },

    pullover: {
        segments: [['Descente derrière la tête', 2000], ['Étirement', 300], ['Retour', 1000], ['Au-dessus de la poitrine', 400]],
        pose(t) {
            const shoulder = [0, -34];
            const angle = lerp(85, 172, t);
            const a = fixedArm(shoulder, angle, angle + 10);
            return scene(
                { torso: [shoulder, [24, -37], [48, -34]], leg: [[48, -34], [76, -52], [80, 0]], foot: [[80, 0], [96, 0]], arm: a },
                [-17, -37], [dumbbell(a[2])], FLAT_BENCH,
            );
        },
    },

    seatedPress: {
        segments: [['Poussée', 1000], ['Bras tendus', 300], ['Retour contrôlé', 2000], ['Départ', 400]],
        pose(t) {
            const b = seated([-30, -40], 95);
            const hand = lerpP(shift(b.shoulder, 16, 6), shift(b.shoulder, 48, 4), t);
            return scene(
                { leg: [b.hip, b.knee, b.ankle], foot: b.foot, torso: [b.hip, b.shoulder], arm: arm(b.shoulder, hand, 1) },
                add(b.shoulder, L.head, 95), [handle(hand)],
                [line(shift(b.hip, -7, 0), shift(b.shoulder, -7, 0)), line(shift(b.hip, -8, 5), shift(b.hip, 34, 5))],
            );
        },
    },

    pushup: {
        segments: [['Descente', 2000], ['Poitrine près du sol', 300], ['Poussée', 1000], ['Bras tendus', 700]],
        pose(t, v) {
            const ankle = [-70, -3];
            const clap = v === 'clap';
            const height = lerp(clap ? 68 : 44, 14, t);
            const shoulder = [-70 + Math.sqrt(130 ** 2 - (height - 3) ** 2), -height];
            const u = [(shoulder[0] - ankle[0]) / 130, (shoulder[1] - ankle[1]) / 130];
            const at = (k) => [ankle[0] + u[0] * k, ankle[1] + u[1] * k];
            const lift = clap ? Math.max(0, height - 44) : 0;               // mains en l'air : le claquement
            return scene(
                { body: [ankle, at(40), at(80), shoulder], foot: [ankle, [-73, 0]], arm: arm(shoulder, [55 - lift * 1.1, -lift], 1) },
                add(shoulder, L.head, angleOf(ankle, shoulder)),
            );
        },
    },

    pike: {
        segments: [['Descente', 2000], ['Tête près du sol', 300], ['Poussée', 1000], ['Bras tendus', 700]],
        pose(t) {
            const ankle = [-55, -3];
            const hip = add(ankle, 80, 62);
            const angle = lerp(-40, -62, t);
            const shoulder = add(hip, L.torso, angle);
            return scene(
                { leg: [ankle, add(ankle, 40, 62), hip], foot: [ankle, [-58, 0]], torso: [hip, shoulder], arm: arm(shoulder, [40, 0], 1) },
                add(shoulder, L.head, angle),
            );
        },
    },

    dips: {
        segments: [['Descente', 2000], ['Bas · épaules au niveau des coudes', 300], ['Poussée', 1000], ['Bras tendus', 700]],
        pose(t) {
            const hand = [16, -72];
            const shoulder = lerpP([12, -120], [8, -98], t);
            const hip = add(shoulder, L.torso, -90 - lerp(8, 24, t));
            const knee = add(hip, L.thigh, -80);
            const ankle = add(knee, L.shin, 150);
            return scene(
                { torso: [hip, shoulder], leg: [hip, knee, ankle], arm: arm(shoulder, hand, 1) },
                headOf(hip, shoulder), [], [line([-4, -72], [44, -72]), line([40, -72], [40, 0])],
            );
        },
    },

    benchDips: {
        segments: [['Descente', 2000], ['Bas', 300], ['Poussée', 1000], ['Bras tendus', 700]],
        pose(t) {
            const hand = [-30, -34];
            const shoulder = lerpP([-26, -84], [-22, -58], t);
            const hip = add(shoulder, L.torso, -86);
            const ankle = [46, 0];
            return scene(
                { torso: [hip, shoulder], leg: leg(hip, ankle, -1), foot: [ankle, [62, 0]], arm: arm(shoulder, hand, 1) },
                headOf(hip, shoulder), [], [rect(-80, -34, 58, 6), line([-70, -28], [-70, 0])],
            );
        },
    },

    ohp: {
        segments: [['Poussée', 1000], ['Bras tendus', 300], ['Descente contrôlée', 2000], ['Départ', 400]],
        pose(t, v) {
            if (v === 'push') {
                const dip = t < 0.25 ? Math.sin((t / 0.25) * Math.PI / 2) : Math.max(0, 1 - (t - 0.25) / 0.25);
                const b = standing(2 + 14 * dip, 16 * dip, 3);
                const press = Math.min(1, Math.max(0, (t - 0.3) / 0.7));
                const hand = lerpP(shift(b.shoulder, 12, -2), shift(b.shoulder, 4, -48), press);
                return scene(
                    { leg: [b.ankle, b.knee, b.hip], foot: b.foot, torso: [b.hip, b.shoulder], arm: arm(b.shoulder, hand, 1) },
                    headOf(b.hip, b.shoulder), [plate(hand, 12)], [], [guide([10, -184], [10, -120], 'axe de la barre')],
                );
            }
            const standingPress = v === 'bar';
            const b = standingPress ? standing(2, 0, 3) : seated([-20, -40], 92);
            const start = standingPress ? shift(b.shoulder, 12, -2) : shift(b.shoulder, 20, -6);
            const hand = lerpP(start, shift(b.shoulder, 4, -48), t);
            const fixtures = standingPress ? [] : [line(shift(b.hip, -7, 0), shift(b.shoulder, -7, 0)), line(shift(b.hip, -8, 5), shift(b.hip, 34, 5))];
            const prop = { bar: plate(hand, 12), db: dumbbell(hand), machine: handle(hand) }[v];
            return scene(
                { leg: [b.ankle, b.knee, b.hip], foot: b.foot, torso: [b.hip, b.shoulder], arm: arm(b.shoulder, hand, 1) },
                headOf(b.hip, b.shoulder), [prop], fixtures,
                standingPress ? [guide([10, -184], [10, -120], 'axe de la barre')] : [],
            );
        },
    },

    row: {
        segments: [['Tirage', 1000], ['Coudes en arrière', 400], ['Retour contrôlé', 2000], ['Bras tendus', 400]],
        pose(t, v) {
            const lean = { bar: 70, one: 80, chest: 48 }[v];
            const b = v === 'chest' ? standing(4, 6, lean) : standing(12, 30, lean);
            const hand = lerpP(shift(b.shoulder, 0, 49), shift(add(b.hip, 22, 90 - lean), 0, 6), t);
            const props = [v === 'bar' ? plate(hand, 12) : dumbbell(hand)];
            const fixtures = v === 'one'
                ? [rect(48, -44, 50, 6), line([90, -38], [90, 0])]
                : v === 'chest' ? (() => {
                    const low = add(b.hip, 10, -lean), high = add(add(b.shoulder, 10, -lean), 8, 90 - lean);
                    return [line(low, high), line(low, [low[0] + 6, 0]), line([low[0] - 6, 0], [low[0] + 22, 0])];
                })() : [];
            const chains = { foot: b.foot, leg: [b.ankle, b.knee, b.hip], torso: [b.hip, b.shoulder], arm: arm(b.shoulder, hand, 1) };
            if (v === 'one') chains.support = arm(b.shoulder, [b.shoulder[0] + 24, -44], -1);
            return scene(chains, headOf(b.hip, b.shoulder), props, fixtures);
        },
    },

    cableRow: {
        segments: [['Tirage', 1000], ['Omoplates serrées', 400], ['Retour contrôlé', 2000], ['Bras tendus', 400]],
        pose(t) {
            const hip = [-30, -24];
            const knee = add(hip, L.thigh, 12);
            const ankle = add(knee, L.shin, -12);
            const shoulder = add(hip, L.torso, lerp(72, 92, t));
            const hand = lerpP(add(shoulder, 48, -6), shift(add(hip, 18, 85), 14, 0), t);
            return scene(
                { leg: [hip, knee, ankle], foot: [ankle, add(ankle, 14, 80)], torso: [hip, shoulder], arm: arm(shoulder, hand, 1) },
                headOf(hip, shoulder), [handle(hand)],
                [line([110, -30], hand), line([-40, -18], [10, -18]), line(shift(ankle, 6, 10), shift(ankle, 6, -18))],
            );
        },
    },

    invertedRow: {
        segments: [['Tirage', 1000], ['Poitrine à la barre', 400], ['Descente contrôlée', 2000], ['Bras tendus', 400]],
        pose(t) {
            const ankle = [-70, -2];
            const angle = lerp(12, 26, t);
            const bar = [40, -70];
            const shoulder = add(ankle, 130, angle);
            return scene(
                { body: [ankle, add(ankle, 40, angle), add(ankle, 80, angle), shoulder], foot: [ankle, [-73, 0]], arm: arm(shoulder, bar, -1) },
                add(shoulder, L.head, angle), [handle(bar)], [line([20, -70], [64, -70]), line([60, -70], [60, 0])],
            );
        },
    },

    // Pronation de face (mains larges, coudes ouverts) ; supination de profil (coudes devant).
    pullup: {
        segments: [['Tirage', 1200], ['Menton au-dessus de la barre', 300], ['Descente contrôlée', 2000], ['Bras tendus', 400]],
        pose(t, v) {
            if (v === 'narrow') {
                // Supination de profil : les coudes descendent devant le corps, ce qu'une vue de face ne montre pas.
                const bar = [10, -182];
                const shoulder = lerpP([-10, -136], [-4, -165], t);       // épaules en retrait : le bras se voit devant le visage
                const hip = add(shoulder, L.torso, -98);                   // corps légèrement penché en arrière
                const knee = add(hip, L.thigh, -70);
                return scene(
                    { torso: [hip, shoulder], leg: [hip, knee, add(knee, L.shin, -150)], arm: arm(shoulder, bar, 1) },
                    headOf(hip, shoulder), [], [line([-30, -182], [50, -182])],
                );
            }
            const wide = v === 'wide';
            const y = lerp(-133, -164, t);
            const shR = [18, y], shL = [-18, y];
            const handX = wide ? 38 : 19;
            const hipR = [9, y + 50], hipL = [-9, y + 50];
            const legs = (side) => {
                const hip = side > 0 ? hipR : hipL;
                const knee = [hip[0] + side * 1, hip[1] + 40];
                return [hip, knee, [knee[0] + side * 1, knee[1] + 39]];
            };
            return scene(
                { legR: legs(1), legL: legs(-1), torso: [hipL, hipR, shR, shL, hipL],
                  armR: ik(shR, [handX, -182], L.upper, L.fore, 1), armL: ik(shL, [-handX, -182], L.upper, L.fore, -1) },     // coudes vers l'extérieur
                [0, y - 20], [], [line([-60, -182], [60, -182])], [], 'Vue de face',
            );
        },
    },

    legRaise: {
        segments: [['Montée des jambes', 1000], ['Contraction', 400], ['Descente contrôlée', 1500], ['Jambes tendues', 400]],
        pose(t) {
            const shoulder = [6, -134];
            const a = fixedArm(shoulder, 88, 88);
            const hip = add(shoulder, L.torso, -92);
            const angle = lerp(-92, -2, t);
            const knee = add(hip, L.thigh, angle);
            return scene(
                { torso: [hip, shoulder], leg: [hip, knee, add(knee, L.shin, angle)], arm: a },
                headOf(hip, shoulder), [handle(a[2])], [line([-30, a[2][1]], [50, a[2][1]])],
            );
        },
    },

    pulldown: {
        segments: [['Tirage', 1000], ['Barre sous le menton', 400], ['Retour contrôlé', 2000], ['Bras tendus', 400]],
        pose(t) {
            const b = seated([-10, -40], 98);
            const hand = lerpP(shift(b.shoulder, 6, -48), shift(b.shoulder, 12, -6), t);
            return scene(
                { leg: [b.hip, b.knee, b.ankle], foot: b.foot, torso: [b.hip, b.shoulder], arm: arm(b.shoulder, hand, 1) },
                headOf(b.hip, b.shoulder), [handle(hand)],
                [line([hand[0], -192], hand), line(shift(b.knee, -16, -8), shift(b.knee, 10, -8)), line(shift(b.hip, -8, 5), shift(b.hip, 34, 5))],
            );
        },
    },

    facePull: {
        segments: [['Tirage vers le visage', 1000], ['Coudes hauts', 400], ['Retour contrôlé', 2000], ['Bras tendus', 400]],
        pose(t) {
            const b = standing(2, 0, 2);
            const hand = lerpP(shift(b.shoulder, 48, -6), shift(b.shoulder, 14, -16), t);
            return scene(
                { foot: b.foot, leg: [b.ankle, b.knee, b.hip], torso: [b.hip, b.shoulder], arm: arm(b.shoulder, hand, -1) },
                headOf(b.hip, b.shoulder), [handle(hand)], [line([112, -146], hand), line([116, -192], [116, 0])],
            );
        },
    },

    curl: {
        segments: [['Montée', 1000], ['Contraction', 400], ['Descente contrôlée', 2000], ['Bras tendus', 400]],
        pose(t, v) {
            const b = standing(2, 0, 2);
            const elbow = add(b.shoulder, L.upper, -88);
            const hand = add(elbow, L.fore, lerp(-86, 106, t));
            const prop = { bar: plate(hand, 8), db: dumbbell(hand), cable: handle(hand) }[v];
            return scene(
                { foot: b.foot, leg: [b.ankle, b.knee, b.hip], torso: [b.hip, b.shoulder], arm: [b.shoulder, elbow, hand] },
                headOf(b.hip, b.shoulder), [prop], v === 'cable' ? [line([40, -4], hand), line([36, 0], [44, 0])] : [],
            );
        },
    },

    wrist: {
        segments: [['Enroulement du poignet', 800], ['Contraction', 300], ['Descente contrôlée', 1600], ['Étirement', 300]],
        pose(t, v) {
            // Assis penché, avant-bras posés sur les cuisses : seul le poignet bouge.
            const b = seated([-10, -40], 45);
            const elbow = add(b.shoulder, L.upper, -95);
            const wrist = add(elbow, L.fore, -8);
            const hand = add(wrist, 11, v === 'flex' ? lerp(-70, 45, t) : lerp(-55, 30, t));
            return scene(
                { leg: [b.hip, b.knee, b.ankle], foot: b.foot, torso: [b.hip, b.shoulder], arm: [b.shoulder, elbow, wrist, hand] },
                headOf(b.hip, b.shoulder), [dumbbell(hand)], [rect(-45, -38, 50, 5), line([-40, -33], [-40, 0])],
            );
        },
    },

    pushdown: {
        segments: [['Extension', 1000], ['Bras tendus', 400], ['Retour contrôlé', 2000], ['Départ', 400]],
        pose(t) {
            const b = standing(2, 0, 6);
            const elbow = add(b.shoulder, L.upper, -76);           // coude un peu devant le buste, pour qu'on le voie
            const hand = add(elbow, L.fore, lerp(64, -84, t));
            return scene(
                { foot: b.foot, leg: [b.ankle, b.knee, b.hip], torso: [b.hip, b.shoulder], arm: [b.shoulder, elbow, hand] },
                headOf(b.hip, b.shoulder), [handle(hand)], [line([hand[0] + 2, -192], hand), line([-20, -192], [60, -192])],
            );
        },
    },

    overheadExt: {
        segments: [['Descente derrière la tête', 2000], ['Étirement', 300], ['Extension', 1000], ['Bras tendus', 400]],
        pose(t) {
            const b = standing(2, 0, 3);
            const elbow = add(b.shoulder, L.upper, 95);
            const hand = add(elbow, L.fore, lerp(92, 238, t));
            return scene(
                { foot: b.foot, leg: [b.ankle, b.knee, b.hip], torso: [b.hip, b.shoulder], arm: [b.shoulder, elbow, hand] },
                headOf(b.hip, b.shoulder), [dumbbell(hand)],
            );
        },
    },

    legExtension: {
        segments: [['Extension', 1000], ['Jambes tendues', 400], ['Retour contrôlé', 2000], ['Genoux pliés', 400]],
        pose(t) {
            const hip = [-20, -50];
            const shoulder = add(hip, L.torso, 100);
            const knee = add(hip, L.thigh, 0);
            const ankle = add(knee, L.shin, lerp(-95, -6, t));
            return scene(
                { leg: [hip, knee, ankle], torso: [hip, shoulder], arm: fixedArm(shoulder, -80, -10) },
                headOf(hip, shoulder), [pad(shift(ankle, 0, 5))],
                [line(shift(hip, -8, 0), shift(shoulder, -8, 0)), line(shift(hip, -8, 6), shift(knee, 4, 6)), line([-20, -44], [-20, 0])],
            );
        },
    },

    legCurl: {
        segments: [['Flexion', 1000], ['Talons vers les fessiers', 400], ['Retour contrôlé', 2000], ['Jambes tendues', 400]],
        pose(t) {
            const shoulder = [-60, -34];
            const hip = [-10, -34];
            const knee = [30, -34];
            const ankle = add(knee, L.shin, lerp(0, 112, t));
            return scene(
                { torso: [shoulder, hip], leg: [hip, knee, ankle], arm: fixedArm(shoulder, -100, -20) },
                add(shoulder, L.head, 180), [pad(shift(ankle, 0, -5))], [rect(-90, -28, 130, 6), line([-80, -22], [-80, 0]), line([30, -22], [30, 0])],
            );
        },
    },

    nordic: {
        segments: [['Descente lente', 3000], ['Mains au sol', 300], ['Remontée', 1000], ['Buste droit', 400]],
        pose(t) {
            const ankle = [-38, -6];
            const knee = [0, -6];
            const angle = lerp(90, 22, t);
            const hip = add(knee, L.thigh, angle);
            const shoulder = add(hip, L.torso, angle);
            return scene(
                { leg: [ankle, knee, hip], torso: [hip, shoulder], arm: fixedArm(shoulder, angle - 120, angle - 70) },
                add(shoulder, L.head, angle), [], [rect(-46, -4, 56, 4), line([-42, -14], [-34, -14])],
            );
        },
    },

    calf: {
        segments: [['Montée', 800], ['Contraction', 500], ['Descente', 1500], ['Étirement', 300]],
        pose(t, v) {
            const toe = [16, 0];
            const ankle = add(toe, 18, 180 - lerp(0, 28, t));
            const knee = add(ankle, L.shin, 90);
            const hip = add(knee, L.thigh, 90);
            const shoulder = add(hip, L.torso, 90);
            const a = v === 'bw' ? arm(shoulder, [40, -120], 1) : fixedArm(shoulder, -90, -90);
            const props = v === 'db' ? [dumbbell(a[2])] : v === 'machine' ? [pad(shift(shoulder, 0, -6))] : [];
            const fixtures = v === 'bw' ? [line([44, -192], [44, 0])] : v === 'machine' ? [line(shift(shoulder, -14, -6), shift(shoulder, 14, -6)), line([30, -190], [30, 0])] : [];
            return scene({ foot: [toe, ankle], leg: [ankle, knee, hip], torso: [hip, shoulder], arm: a }, headOf(hip, shoulder), props, fixtures);
        },
    },

    crunch: {
        segments: [['Enroulement', 1000], ['Contraction', 400], ['Retour contrôlé', 1500], ['Allongé', 400]],
        pose(t) {
            const hip = [0, -6];
            const mid = add(hip, 22, 180);
            const angle = lerp(180, 142, t);
            const shoulder = add(mid, 28, angle);
            return scene(
                { leg: [[42, 0], [22, -36], hip], torso: [hip, mid, shoulder], arm: fixedArm(shoulder, angle - 60, angle + 73) },
                add(shoulder, L.head, angle),
            );
        },
    },

    cableCrunch: {
        segments: [['Enroulement', 1000], ['Contraction', 400], ['Retour contrôlé', 1500], ['Buste haut', 400]],
        pose(t) {
            const knee = [0, -4];
            const hip = add(knee, L.thigh, 90);
            const angle = lerp(76, 14, t);
            const shoulder = add(hip, L.torso, angle);
            const a = fixedArm(shoulder, angle - 150, angle + 100);
            return scene(
                { leg: [[-38, -4], knee, hip], torso: [hip, shoulder], arm: a },
                add(shoulder, L.head, angle), [handle(a[2])], [line([a[2][0], -192], a[2]), line([-20, -192], [60, -192])],
            );
        },
    },

    // Sauts : joués dans un seul sens (élan, envol, réception, stabilisation), puis on recommence au départ.
    jump: {
        oneWay: true,
        segments: [['Flexion et élan', 600], ['Envol', 500], ['Réception souple', 700], ['Stabilisation', 600]],
        pose(t, v) {
            const smooth = (x) => 0.5 - 0.5 * Math.cos(Math.PI * Math.min(1, Math.max(0, x)));
            const takeoff = 0.25, landing = 0.46;
            const air = smooth((t - takeoff) / (landing - takeoff));                       // avancée 0 → 1 pendant l'envol
            const flying = t > takeoff && t < landing ? Math.sin(Math.PI * (t - takeoff) / (landing - takeoff)) : 0;
            const crouch = t < takeoff ? Math.sin((t / takeoff) * Math.PI / 2)              // flexion avant l'impulsion
                : t < landing ? 0
                : 0.6 * Math.sin(Math.PI * Math.min(1, (t - landing) / 0.3));            // amorti à la réception
            const x = { box: 62 * air, broad: -45 + 95 * air, squat: 0 }[v];
            const lift = { box: 30 * air + 8 * flying, broad: 26 * flying, squat: 30 * flying }[v];
            const b = standing(4 + 30 * crouch, 50 * crouch, 6 + 30 * crouch, x);
            const up = (p) => [p[0], p[1] - lift];
            // Bras : élan en arrière, lancés vers le haut à l'envol, ramenés devant soi à la réception.
            const swing = t < takeoff ? lerp(-60, -120, t / takeoff) : t < landing ? lerp(-120, 40, (t - takeoff) / (landing - takeoff)) : lerp(40, 0, Math.min(1, (t - landing) / 0.3));
            const shoulder = up(b.shoulder);
            return scene(
                { foot: b.foot.map(up), leg: [b.ankle, b.knee, b.hip].map(up), torso: [up(b.hip), shoulder], arm: fixedArm(shoulder, swing, swing) },
                headOf(up(b.hip), shoulder), [], v === 'box' ? [rect(40, -30, 50, 30)] : [],
            );
        },
    },

    // Vues de face : les bras tournent dans le plan du corps.
    lateral: {
        segments: [['Montée', 1000], ['Bras à l\'horizontale', 300], ['Descente contrôlée', 2000], ['Bas', 400]],
        pose(t, v) {
            const bent = v === 'rear';
            const shoulderY = bent ? -86 : -128;
            const shR = [18, shoulderY], shL = [-18, shoulderY];
            const hipR = [10, bent ? -96 : -80], hipL = [-10, bent ? -96 : -80];
            const angle = lerp(-84, -4, t);
            const right = fixedArm(shR, angle, angle + 4);
            const left = v === 'cable' ? fixedArm(shL, -96, -92) : fixedArm(shL, 180 - angle, 176 - angle);
            const props = v === 'cable' ? [handle(right[2])] : [dumbbell(right[2]), dumbbell(left[2])];
            return scene(
                { legR: [[11, 0], [bent ? 15 : 10, bent ? -48 : -40], hipR], legL: [[-11, 0], [bent ? -15 : -10, bent ? -48 : -40], hipL], torso: [hipL, hipR, shR, shL, hipL], armR: right, armL: left },
                [0, bent ? shoulderY + 10 : shoulderY - 20], props, v === 'cable' ? [line([-60, -4], right[2])] : [], [], bent ? 'Vue de face · buste penché' : 'Vue de face',
            );
        },
    },

    fly: {
        segments: [['Ouverture', 2000], ['Étirement', 300], ['Fermeture', 1000], ['Bras au-dessus', 400]],
        pose(t) {
            const shR = [18, -36], shL = [-18, -36];
            const angle = lerp(84, -6, t);
            const right = fixedArm(shR, angle, angle - 8);
            const left = fixedArm(shL, 180 - angle, 188 - angle);
            return scene(
                { shoulders: [shL, shR], armR: right, armL: left },
                [0, -46], [dumbbell(right[2]), dumbbell(left[2])], [rect(-26, -30, 52, 6), line([0, -24], [0, 0])], [], 'Vue depuis les pieds',
            );
        },
    },

    // Vue de dessus : les bras balaient l'horizontale vers l'avant (le haut de l'écran).
    cableFly: {
        segments: [['Fermeture', 1000], ['Contraction', 400], ['Retour contrôlé', 2000], ['Bras ouverts', 400]],
        pose(t, v) {
            const shR = [18, -80], shL = [-18, -80];
            const angle = lerp(-10, 70, t);
            const right = fixedArm(shR, angle, angle + 40);
            const left = fixedArm(shL, 180 - angle, 140 - angle);
            const fixtures = v === 'deck'
                ? [line([-34, -70], [34, -70])]
                : [line([105, -80], right[2]), line([-105, -80], left[2]), line([105, -90], [105, -70]), line([-105, -90], [-105, -70])];
            return scene(
                { shoulders: [shL, shR], armR: right, armL: left },
                [0, -82], [handle(right[2]), handle(left[2])], fixtures, [], 'Vue de dessus',
            );
        },
    },
};

// ───────────────────────── Catalogue ─────────────────────────

export const DEMOS = {
    'bench-press': ['bench', 'flat-bar'],
    'dumbbell-bench-press': ['bench', 'flat-db'],
    'incline-bench-press': ['bench', 'incline-bar'],
    'close-grip-bench-press': ['bench', 'close-bar'],
    'incline-dumbbell-press': ['bench', 'incline-db'],
    'machine-chest-press': ['seatedPress'],
    'push-up': ['pushup'],
    'diamond-push-up': ['pushup'],
    dips: ['dips'],
    'bench-dips': ['benchDips'],
    'overhead-press': ['ohp', 'bar'],
    'dumbbell-shoulder-press': ['ohp', 'db'],
    'arnold-press': ['ohp', 'db'],
    'machine-shoulder-press': ['ohp', 'machine'],
    'pike-push-up': ['pike'],
    'barbell-row': ['row', 'bar'],
    'dumbbell-row': ['row', 'one'],
    'chest-supported-row': ['row', 'chest'],
    'seated-cable-row': ['cableRow'],
    'inverted-row': ['invertedRow'],
    'pull-up': ['pullup', 'wide'],
    'chin-up': ['pullup', 'narrow'],
    'lat-pulldown': ['pulldown'],
    'dumbbell-pullover': ['pullover'],
    'back-squat': ['squat', 'back'],
    'front-squat': ['squat', 'front'],
    'goblet-squat': ['squat', 'goblet'],
    'bodyweight-squat': ['squat', 'bodyweight'],
    'leg-press': ['legPress'],
    deadlift: ['deadlift'],
    'romanian-deadlift': ['rdl', 'bar'],
    'dumbbell-romanian-deadlift': ['rdl', 'db'],
    'hip-thrust': ['hipThrust', 'bench'],
    'glute-bridge': ['hipThrust', 'floor'],
    'walking-lunge': ['lunge', 'db'],
    'bodyweight-lunge': ['lunge', 'bw'],
    'bulgarian-split-squat': ['lunge', 'bulgarian'],
    'step-up': ['stepUp'],
    'dumbbell-fly': ['fly'],
    'cable-fly': ['cableFly', 'cable'],
    'pec-deck': ['cableFly', 'deck'],
    'lateral-raise': ['lateral', 'db'],
    'cable-lateral-raise': ['lateral', 'cable'],
    'rear-delt-fly': ['lateral', 'rear'],
    'face-pull': ['facePull'],
    'barbell-curl': ['curl', 'bar'],
    'dumbbell-curl': ['curl', 'db'],
    'hammer-curl': ['curl', 'db'],
    'cable-curl': ['curl', 'cable'],
    'reverse-curl': ['curl', 'bar'],
    'wrist-curl': ['wrist', 'flex'],
    'wrist-extension': ['wrist', 'ext'],
    'triceps-pushdown': ['pushdown'],
    'overhead-dumbbell-extension': ['overheadExt'],
    'skull-crusher': ['skull'],
    'leg-extension': ['legExtension'],
    'sissy-squat': ['sissy'],
    'leg-curl': ['legCurl'],
    'nordic-curl': ['nordic'],
    'standing-calf-raise': ['calf', 'machine'],
    'dumbbell-calf-raise': ['calf', 'db'],
    'bodyweight-calf-raise': ['calf', 'bw'],
    crunch: ['crunch'],
    'hanging-leg-raise': ['legRaise'],
    'cable-crunch': ['cableCrunch'],
    'push-press': ['ohp', 'push'],
    'clap-push-up': ['pushup', 'clap'],
    'box-jump': ['jump', 'box'],
    'broad-jump': ['jump', 'broad'],
    'jump-squat': ['jump', 'squat'],
};

const family = (slug) => FAMILIES[DEMOS[slug][0]];

/** Encart « Prise » : ce que la vue ne montre pas, l'orientation et l'écartement des mains. */
const GRIPS = {
    'pull-up': 'pronation', 'chin-up': 'supination', 'lat-pulldown': 'pronation', 'barbell-row': 'pronation',
    'push-up': 'wide', 'diamond-push-up': 'diamond',
    'barbell-curl': 'curl', 'dumbbell-curl': 'curl', 'cable-curl': 'curl', 'hammer-curl': 'neutral',
    'reverse-curl': 'reverse', 'wrist-curl': 'curl', 'wrist-extension': 'reverse',
    'dumbbell-shoulder-press': 'pronation', 'arnold-press': 'arnold',
};

export const GRIP_LABELS = {
    pronation: 'Paumes vers l’avant',
    supination: 'Paumes vers toi',
    curl: 'Paumes vers le haut',
    neutral: 'Paumes face à face',
    diamond: 'Mains en losange',
    wide: 'Mains un peu plus larges que les épaules',
    arnold: 'Paumes vers toi → vers l’avant',
    reverse: 'Paumes vers le bas',
};

/** Positions retournées (allongé dos à la barre, à plat ventre) : le regard part de l'autre côté de l'axe. */
const FACE_DOWN = new Set(['invertedRow', 'legCurl']);

export function sceneAt(slug, t) {
    const [name, variant = ''] = DEMOS[slug];
    const time = Math.min(1, Math.max(0, t));
    const s = FAMILIES[name].pose(time, variant);
    const kind = GRIPS[slug];
    s.grip = kind ? { kind, label: GRIP_LABELS[kind], turn: kind === 'arnold' ? Math.round(lerp(0, 180, time)) : 0 } : null;
    // Regard (de profil seulement) : perpendiculaire à l'axe épaule → tête, du côté du visage.
    const spine = s.chains.torso ?? s.chains.body;
    if (s.note || !spine) {
        s.gaze = null;
    } else {
        const ends = [spine[0], spine[spine.length - 1]];
        const neck = Math.hypot(ends[0][0] - s.head[0], ends[0][1] - s.head[1]) < Math.hypot(ends[1][0] - s.head[0], ends[1][1] - s.head[1]) ? ends[0] : ends[1];
        s.gaze = angleOf(neck, s.head) + (FACE_DOWN.has(name) ? 90 : -90);
    }
    return s;
}

export function segmentsOf(slug) {
    return family(slug).segments;
}

const ease = (x) => 0.5 - 0.5 * Math.cos(Math.PI * x);

/** Position dans le cycle (ms) → [t de la pose, libellé de la phase]. Phase 1 : 0 → 1, phase 2 : tenue, phase 3 : 1 → 0, phase 4 : tenue. */
export function phaseAt(slug, ms) {
    const segments = segmentsOf(slug);
    const total = segments.reduce((sum, [, d]) => sum + d, 0);
    let m = ((ms % total) + total) % total;
    if (family(slug).oneWay) {
        // Mouvement à sens unique : t suit le temps d'un bout à l'autre du cycle.
        const label = segments.find(([, d], i) => m < segments.slice(0, i + 1).reduce((sum, [, x]) => sum + x, 0))?.[0] ?? segments[0][0];
        return [m / total, label];
    }
    for (let i = 0; i < segments.length; i++) {
        const [label, d] = segments[i];
        if (m < d) {
            const x = m / d;
            return [[ease(x), 1, 1 - ease(x), 0][i], label];
        }
        m -= d;
    }
    return [0, segments[3][0]];
}
