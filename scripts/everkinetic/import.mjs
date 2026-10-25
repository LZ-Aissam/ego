// Import des illustrations Everkinetic (https://github.com/everkinetic/data, CC BY-SA 4.0, Greg Priday).
// Pour chaque exercice du catalogue qui a un équivalent, télécharge les deux positions (relâchée, contractée),
// vérifie que le SVG ne contient que du dessin, le recolore aux couleurs du Système et l'écrit dans public/demos/everkinetic.
// Usage : node scripts/everkinetic/import.mjs
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { segmentsOf } from '../../resources/js/demos/rig.js';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const OUT = path.join(ROOT, 'public/demos/everkinetic');
const BASE = 'https://raw.githubusercontent.com/everkinetic/data/master';
const ICE = '#5ad8ff';

/** Exercice du catalogue → exercice Everkinetic équivalent. Les autres gardent leur silhouette animée. */
export const MAPPING = {
    'bench-press': 'bench-press',
    'dumbbell-bench-press': 'bench-press-dumbbell',
    'incline-bench-press': 'incline-bench-press',
    'close-grip-bench-press': 'close-grip-barbell-bench-press',
    'incline-dumbbell-press': 'dumbbell-incline-bench-press',
    'machine-chest-press': 'machine-bench-press',
    'push-up': 'push-ups',
    'diamond-push-up': 'close-triceps-pushup',
    dips: 'chest-dips',
    'bench-dips': 'bench-dips',
    'seated-cable-row': 'seated-cable-rows',
    'inverted-row': 'body-row',
    'pull-up': 'pull-ups',
    'dumbbell-pullover': 'straight-arm-dumbbell-pullover',
    'back-squat': 'barbell-squat',
    'front-squat': 'front-squat-with-barbell',
    'leg-press': 'leg-press',
    deadlift: 'barbell-dead-lifts',
    'romanian-deadlift': 'romanian-dead-lift',
    'glute-bridge': 'bridging',
    'dumbbell-fly': 'dumbbell-flys',
    'cable-fly': 'cable-crossover',
    'lateral-raise': 'lateral-dumbbell-raises',
    'rear-delt-fly': 'bent-over-rear-deltoid-raise-with-head-on-bench',
    'barbell-curl': 'biceps-curls-with-barbell',
    'dumbbell-curl': 'biceps-curl-with-dumbbell',
    'hammer-curl': 'biceps-hammer-curl-with-dumbbell',
    'cable-curl': 'standing-biceps-curl-with-cable',
    'reverse-curl': 'biceps-curl-reverse-with-dumbbells',
    'triceps-pushdown': 'triceps-pushdown-with-cable',
    'skull-crusher': 'lying-triceps-press-with-barbell',
    'leg-extension': 'leg-extensions',
    'leg-curl': 'lying-leg-curl-machine',
    'standing-calf-raise': 'standing-calf-raises-using-machine',
    crunch: 'crunches',
};

/** Animations qui commencent en ramenant la charge : elles partent de la position contractée. */
const ECCENTRIC_FIRST = /^(Descente|Hanches en arrière|Ouverture)/;

/** Un SVG venu d'internet ne doit contenir que du dessin : rien d'exécutable ni de lien. */
export function assertDrawingOnly(svg, name) {
    const elements = [...svg.matchAll(/<([a-zA-Z][\w:-]*)/g)].map((m) => m[1].toLowerCase());
    const unexpected = elements.filter((e) => !['svg', 'g', 'path'].includes(e));
    if (unexpected.length || /\son\w+\s*=|javascript:|href\s*=|<!entity|<\?xml-stylesheet/i.test(svg)) {
        throw new Error(`${name} : contenu inattendu (${[...new Set(unexpected)].join(', ') || 'attribut'})`);
    }
    const fills = new Set([...svg.matchAll(/fill="([^"]+)"/g)].map((m) => m[1].toUpperCase()));
    if ([...fills].some((f) => !isWhite(f) && !isInk(f))) {
        throw new Error(`${name} : couleurs inattendues ${[...fills].join(', ')}`);
    }
}

const rgb = (hex) => {
    const h = hex.replace('#', '');
    const full = h.length === 3 ? [...h].map((c) => c + c).join('') : h;
    return /^[0-9A-F]{6}$/i.test(full) ? [0, 2, 4].map((i) => parseInt(full.slice(i, i + 2), 16)) : null;
};
const isWhite = (hex) => (rgb(hex) ?? [0]).every((c) => c >= 0xf0);
/** Trait du dessin : gris foncé, quelle qu'en soit la nuance exacte (#333, #40413F…). */
const isInk = (hex) => { const c = rgb(hex); return c !== null && c.every((v) => v < 0x80); };

/** Fond transparent, trait bleu glacé. */
export function recolor(svg) {
    return svg.replace(/fill="(#[0-9A-Fa-f]{3,6})"/g, (all, hex) => (isWhite(hex) ? 'fill="none"' : isInk(hex) ? `fill="${ICE}"` : all));
}

async function main() {
    const catalog = await (await fetch(`${BASE}/exercises.json`)).json();
    const byName = Object.fromEntries(catalog.map((e) => [e.name, e]));
    fs.mkdirSync(OUT, { recursive: true });

    for (const [slug, name] of Object.entries(MAPPING)) {
        const source = byName[name];
        if (!source) throw new Error(`${slug} : « ${name} » absent d'Everkinetic`);
        const startsContracted = ECCENTRIC_FIRST.test(segmentsOf(slug)[0][0]);
        const order = startsContracted ? ['tension', 'relaxation'] : ['relaxation', 'tension'];
        for (const [i, pose] of order.entries()) {
            const response = await fetch(`${BASE}/dist/svg/${source.id_num}-${pose}.svg`);
            if (!response.ok) throw new Error(`${slug} : ${pose} introuvable (${response.status})`);
            const svg = await response.text();
            assertDrawingOnly(svg, `${slug}-${pose}`);
            fs.writeFileSync(path.join(OUT, `${slug}-${i}.svg`), recolor(svg));
        }
        console.log(`${slug.padEnd(28)} ← ${name} (départ : ${order[0]})`);
    }

    const slugs = Object.keys(MAPPING).sort();
    fs.writeFileSync(path.join(ROOT, 'resources/js/demos/illustrations.js'),
        '// Généré par scripts/everkinetic/import.mjs : exercices illustrés par Everkinetic (deux positions, CC BY-SA 4.0).\n'
        + `export const ILLUSTRATED = new Set(${JSON.stringify(slugs, null, 4).replace(/"/g, "'")});\n\n`
        + "export const CREDIT = 'Illustration : Everkinetic (Greg Priday) · CC BY-SA 4.0';\n\n"
        + '/** Les deux positions : 0 au début de l’animation, 1 à l’autre bout du mouvement. */\n'
        + 'export const framesOf = (slug) => (ILLUSTRATED.has(slug) ? [0, 1].map((i) => `/demos/everkinetic/${slug}-${i}.svg`) : null);\n');
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
    main().catch((error) => {
        console.error(error.message);
        process.exit(1);
    });
}
