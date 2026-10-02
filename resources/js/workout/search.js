// Recherche dans le catalogue : par nom ou par muscle travaillé, sans accents ni majuscules.
const fold = (text) => String(text ?? '').trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

/** Mots qui désignent un groupe de muscles. */
const GROUPS = { bras: ['biceps', 'triceps', 'avant-bras'] };

export function matchExercises(exercises, query) {
    const q = fold(query);
    if (!q) return exercises;
    const muscles = GROUPS[q] ?? [q];

    return exercises.filter((e) => fold(e.name).includes(q)
        || (e.muscles ?? []).some((m) => muscles.some((wanted) => fold(m).includes(fold(wanted)))));
}
