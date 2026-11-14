// Outils de la barre : montée d'échauffement avant une série lourde, disques à charger de chaque côté.

export const BAR_KG = 20;

/** Disques d'une salle classique, du plus lourd au plus léger. */
export const PLATES = [25, 20, 15, 10, 5, 2.5, 1.25];

/** Intentions lourdes précédées d'une montée : vague, série record, force max. */
export const RAMP_INTENTS = ['wave', 'top', 'max'];

/** Paliers de la montée : [part de la charge de travail, répétitions]. */
const RAMP = [[0.4, 5], [0.6, 3], [0.75, 2], [0.85, 1]];

const floorTo = (kg, step) => Math.floor(kg / step + 1e-9) * step;

/**
 * Séries de montée vers une charge de travail : de plus en plus lourdes, de moins en moins de répétitions,
 * jamais sous la barre à vide, jamais deux fois la même charge, toujours sous la charge de travail.
 * Au poids du corps, le lest se monte de la même façon à partir de zéro (une série sans lest d'abord).
 *
 * @returns {{kg: number, reps: number}[]}
 */
export function warmupRamp(workKg, { isBodyweight = false } = {}) {
    if (!(workKg > 0)) return [];
    const floor = isBodyweight ? 0 : BAR_KG;
    if (!isBodyweight && workKg < 40) return [];                                   // charge légère : la première série suffit
    const out = isBodyweight ? [{ kg: 0, reps: 3 }] : [{ kg: BAR_KG, reps: 8 }];
    for (const [pct, reps] of RAMP) {
        const kg = Math.max(floor, floorTo(workKg * pct, 2.5));
        if (kg >= workKg || kg <= out.at(-1).kg) continue;
        out.push({ kg, reps });
    }
    return out;
}

/**
 * Disques à mettre de chaque côté pour une charge totale sur barre (barre comprise), ou pour un lest à la ceinture.
 * @returns {{plates: number[], rest: number}} rest > 0 : charge impossible à faire exactement avec ces disques
 */
export function platesFor(totalKg, { bar = BAR_KG, perSide = true } = {}) {
    let left = perSide ? (totalKg - bar) / 2 : totalKg;
    if (!(left > 0)) return { plates: [], rest: 0 };
    const plates = [];
    for (const plate of PLATES) {
        while (left + 1e-9 >= plate) {
            plates.push(plate);
            left -= plate;
        }
    }
    return { plates, rest: Math.round(left * 100) / 100 };
}

const kg = (n) => String(n).replace('.', ',');

/** « 25 + 10 + 1,25 par côté », « barre seule », ou le lest à accrocher. */
export function platesText(totalKg, { isBodyweight = false } = {}) {
    if (isBodyweight) {
        if (!(totalKg > 0)) return null;
        const { plates } = platesFor(totalKg, { perSide: false });
        return `Ceinture : ${plates.map(kg).join(' + ')}`;
    }
    if (totalKg < BAR_KG) return null;
    const { plates, rest } = platesFor(totalKg);
    if (!plates.length) return rest ? null : 'Barre seule (20 kg)';
    return `${plates.map(kg).join(' + ')} par côté${rest ? ` (+${kg(rest * 2)} kg introuvables)` : ''}`;
}

/**
 * Répétitions à faire à cette charge pour battre le maximum estimé (Epley) : une de plus que l'égalité.
 * null : la charge seule le bat déjà, ou pas de maximum connu.
 */
export function repsToBeat(e1rm, workKg) {
    if (!(e1rm > 0) || !(workKg > 0) || workKg >= e1rm) return null;
    return Math.floor(30 * (e1rm / workKg - 1) + 1e-9) + 1;
}
