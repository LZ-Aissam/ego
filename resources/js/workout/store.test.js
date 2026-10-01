import { describe, expect, it, vi } from 'vitest';
import {
    addExercise, addRest, adjustDraft, createStore, finishWorkout, goTo, hasSets,
    logSet, newWorkout, removeLastSet, restProgress, skipRest, toNumber, toPayload, uuid,
} from './store';

function memoryStorage() {
    const data = new Map();
    return {
        getItem: (k) => (data.has(k) ? data.get(k) : null),
        setItem: (k, v) => data.set(k, String(v)),
        removeItem: (k) => data.delete(k),
    };
}

const bench = { id: 1, name: 'Développé couché', isBodyweight: false };
const pullUp = { id: 2, name: 'Tractions', isBodyweight: true };

describe('uuid', () => {
    it('génère un uuid v4 même sans crypto.randomUUID (http sur IP locale)', () => {
        const real = globalThis.crypto;
        vi.stubGlobal('crypto', { getRandomValues: (a) => real.getRandomValues(a) });
        const id = uuid();
        vi.unstubAllGlobals();
        expect(id).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
    });
});

describe('toNumber', () => {
    it('accepte la virgule décimale', () => {
        expect(toNumber('62,5')).toBe(62.5);
        expect(toNumber(60)).toBe(60);
        expect(toNumber('')).toBe(0);
        expect(toNumber('abc')).toBe(0);
    });
});

describe('séance', () => {
    it('pré-remplit avec la dernière fois, sinon la charge suggérée, sinon 0 × 10', () => {
        let s = newWorkout('id-1', '2026-10-01T16:00:00.000Z');
        s = addExercise(s, bench, { lastTime: [{ weightKg: 60, reps: 8 }], suggestedKg: 40, restSeconds: 90 });
        expect(s.exercises[0].draft).toEqual({ weightKg: 60, reps: 8 });

        s = addExercise(s, bench, { lastTime: null, suggestedKg: 40, restSeconds: 90 });
        expect(s.exercises[1].draft).toEqual({ weightKg: 40, reps: 10 });

        s = addExercise(s, pullUp, { restSeconds: 90 });
        expect(s.exercises[2].draft).toEqual({ weightKg: 0, reps: 10 });
        expect(s.currentIndex).toBe(2);
    });

    it('garde l\'objectif de la quête et commence au bas de la plage de répétitions', () => {
        let s = addExercise(newWorkout('id', 't'), bench, { restSeconds: 120, target: { sets: 3, repsMin: 6, repsMax: 10 } });
        expect(s.exercises[0].target).toEqual({ sets: 3, repsMin: 6, repsMax: 10 });
        expect(s.exercises[0].draft.reps).toBe(6);

        s = addExercise(s, bench, { lastTime: [{ weightKg: 60, reps: 8 }], restSeconds: 120, target: { sets: 3, repsMin: 6, repsMax: 10 } });
        expect(s.exercises[1].draft.reps).toBe(8);

        s = addExercise(s, pullUp, { restSeconds: 90 });
        expect(s.exercises[2].target).toBeNull();
    });

    it('pré-remplit la charge prescrite avant la dernière fois et la suggestion', () => {
        let s = addExercise(newWorkout('id', 't'), bench, { lastTime: [{ weightKg: 60, reps: 8 }], suggestedKg: 40, prescribedKg: 62.5, restSeconds: 120 });
        expect(s.exercises[0].draft.weightKg).toBe(62.5);
        expect(s.exercises[0].prescribedKg).toBe(62.5);

        s = addExercise(s, bench, { lastTime: [{ weightKg: 60, reps: 8 }], restSeconds: 120 });
        expect(s.exercises[1].draft.weightKg).toBe(60);
        expect(s.exercises[1].prescribedKg).toBeNull();
    });

    it('ajuste le brouillon par pas de 2,5 kg et 1 rep sans passer sous les minimums', () => {
        let s = addExercise(newWorkout('id', 't'), bench, { suggestedKg: 2.5, restSeconds: 90 });
        s = adjustDraft(s, 'weightKg', -2.5);
        s = adjustDraft(s, 'weightKg', -2.5);
        expect(s.exercises[0].draft.weightKg).toBe(0);
        s.exercises[0].draft.weightKg = '62,5';
        s = adjustDraft(s, 'weightKg', 2.5);
        expect(s.exercises[0].draft.weightKg).toBe(65);
        s.exercises[0].draft.reps = 1;
        s = adjustDraft(s, 'reps', -1);
        expect(s.exercises[0].draft.reps).toBe(1);
    });

    it('valide une série et démarre le repos', () => {
        let s = addExercise(newWorkout('id', 't'), bench, { lastTime: [{ weightKg: 60, reps: 8 }], restSeconds: 90 });
        s = logSet(s, 1_000_000);
        expect(s.exercises[0].sets).toHaveLength(1);
        expect(s.exercises[0].sets[0]).toMatchObject({ weightKg: 60, reps: 8 });
        expect(s.restEndsAt).toBe(1_000_000 + 90_000);
        s = addRest(s, 30, 1_000_000);
        expect(s.restEndsAt).toBe(1_000_000 + 120_000);
        s = skipRest(s);
        expect(s.restEndsAt).toBeNull();
        s = removeLastSet(s);
        expect(s.exercises[0].sets).toHaveLength(0);
    });

    it('ne fait rien sans exercice courant', () => {
        const s = logSet(newWorkout('id', 't'), 0);
        expect(s.restEndsAt).toBeNull();
        expect(hasSets(s)).toBe(false);
    });

    it('produit le contenu envoyé au serveur en ignorant les exercices sans série', () => {
        let s = newWorkout('abc', '2026-10-01T16:00:00.000Z');
        s = addExercise(s, bench, { lastTime: [{ weightKg: 60, reps: 8 }], restSeconds: 90 });
        s = logSet(s, 0);
        s = addExercise(s, pullUp, { restSeconds: 90 });
        s = goTo(s, 0);
        expect(s.currentIndex).toBe(0);
        const p = toPayload(s, '2026-10-01T17:00:00.000Z');
        expect(p).toMatchObject({ client_id: 'abc', started_at: '2026-10-01T16:00:00.000Z', finished_at: '2026-10-01T17:00:00.000Z' });
        expect(p.sets).toEqual([{ exercise_id: 1, weight_kg: 60, reps: 8, done_at: expect.any(String) }]);
    });
});

describe('stockage', () => {
    it('sauvegarde et reprend une séance active', () => {
        const store = createStore(memoryStorage());
        expect(store.loadActive()).toBeNull();
        const s = addExercise(newWorkout('id', 't'), bench, { restSeconds: 90 });
        store.saveActive(s);
        expect(store.loadActive()).toEqual(s);
        store.clearActive();
        expect(store.loadActive()).toBeNull();
    });

    it('sépare les séances de chaque utilisateur sur un même appareil', () => {
        const storage = memoryStorage();
        const alice = createStore(storage, 1);
        const bob = createStore(storage, 2);
        alice.saveActive(addExercise(newWorkout('a', 't'), bench, { restSeconds: 90 }));
        alice.queuePending({ client_id: 'a' });

        expect(bob.loadActive()).toBeNull();
        expect(bob.pending()).toEqual([]);
        expect(alice.pending()).toHaveLength(1);
    });

    it('ignore un contenu corrompu', () => {
        const storage = memoryStorage();
        storage.setItem('ego.activeWorkout', '{pas du json');
        expect(createStore(storage).loadActive()).toBeNull();
    });

    it('met en file d\'attente sans doublon et efface la séance active à la fin', () => {
        const store = createStore(memoryStorage());
        let s = addExercise(newWorkout('abc', 't'), bench, { lastTime: [{ weightKg: 60, reps: 8 }], restSeconds: 90 });
        s = logSet(s, 0);
        store.saveActive(s);
        finishWorkout(store, s, 'fin');
        finishWorkout(store, s, 'fin');
        expect(store.pending()).toHaveLength(1);
        expect(store.loadActive()).toBeNull();
        store.removePending('abc');
        expect(store.pending()).toEqual([]);
    });
});

describe('restProgress', () => {
    it('donne la fraction de repos restante, bornée entre 0 et 1, même après +30 s', () => {
        let s = addExercise(newWorkout('id', 't'), bench, { restSeconds: 90 });
        expect(restProgress(s, 0)).toBe(0);
        s = logSet(s, 0);
        expect(restProgress(s, 0)).toBe(1);
        expect(restProgress(s, 45_000)).toBe(0.5);
        s = addRest(s, 30, 45_000);
        expect(restProgress(s, 45_000)).toBeCloseTo(75 / 120);
        expect(restProgress(s, 999_999)).toBe(0);
        s = skipRest(s);
        expect(restProgress(s, 0)).toBe(0);
    });
});
