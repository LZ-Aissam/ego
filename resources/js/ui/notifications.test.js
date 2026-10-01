import { describe, expect, it } from 'vitest';
import { buzz, dismiss, enqueue, normalize } from './notifications';

const rec = { type: 'record', title: 'NOUVEAU RECORD', lines: ['Squat', '90 kg × 3'], highlight: 'NEW PR' };

describe('normalize', () => {
    it('complète les champs facultatifs', () => {
        expect(normalize({ type: 'x', title: 'T' })).toEqual({ type: 'x', title: 'T', lines: [], variant: 'default', highlight: null });
    });

    it('rejette un élément sans titre', () => {
        expect(normalize({ type: 'x' })).toBeNull();
        expect(normalize(null)).toBeNull();
    });
});

describe('file', () => {
    it('ajoute à la fin en ignorant les éléments invalides puis retire le premier', () => {
        let list = enqueue([], [rec, { nope: true }, { type: 'workout_done', title: 'SÉANCE TERMINÉE' }]);
        expect(list.map((n) => n.title)).toEqual(['NOUVEAU RECORD', 'SÉANCE TERMINÉE']);
        list = dismiss(list);
        expect(list.map((n) => n.title)).toEqual(['SÉANCE TERMINÉE']);
        expect(dismiss(dismiss(list))).toEqual([]);
    });

    it('accepte une entrée qui n\'est pas un tableau sans planter', () => {
        expect(enqueue([], undefined)).toEqual([]);
    });
});

describe('buzz', () => {
    it('fait vibrer si possible', () => {
        let pattern = null;
        expect(buzz({ vibrate: (p) => { pattern = p; return true; } })).toBe(true);
        expect(pattern).toEqual([60, 40, 60]);
        expect(buzz({})).toBe(false);
    });
});
