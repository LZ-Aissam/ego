import { describe, expect, it } from 'vitest';
import { platesFor, platesText, repsToBeat, warmupRamp } from './plates';

describe('montée d\'échauffement', () => {
    it('monte de la barre vers la charge de travail, de moins en moins de répétitions', () => {
        expect(warmupRamp(100)).toEqual([
            { kg: 20, reps: 8 }, { kg: 40, reps: 5 }, { kg: 60, reps: 3 }, { kg: 75, reps: 2 }, { kg: 85, reps: 1 },
        ]);
    });

    it('arrondit au disque de 2,5 kg et ne répète jamais une charge', () => {
        const ramp = warmupRamp(62.5);
        expect(ramp.map((s) => s.kg)).toEqual([20, 25, 37.5, 45, 52.5]);
        expect(new Set(ramp.map((s) => s.kg)).size).toBe(ramp.length);
    });

    it('reste toujours sous la charge de travail', () => {
        for (const work of [40, 47.5, 55, 72.5, 90, 142.5]) {
            expect(warmupRamp(work).every((s) => s.kg < work)).toBe(true);
        }
    });

    it('sans montée pour une charge légère', () => {
        expect(warmupRamp(30)).toEqual([]);
        expect(warmupRamp(0)).toEqual([]);
    });

    it('au poids du corps : une série sans lest, puis le lest qui monte', () => {
        expect(warmupRamp(20, { isBodyweight: true })).toEqual([{ kg: 0, reps: 3 }, { kg: 7.5, reps: 5 }, { kg: 10, reps: 3 }, { kg: 15, reps: 2 }]);
    });
});

describe('disques', () => {
    it('donne les disques de chaque côté de la barre de 20 kg', () => {
        expect(platesFor(92.5)).toEqual({ plates: [25, 10, 1.25], rest: 0 });
        expect(platesFor(60)).toEqual({ plates: [20], rest: 0 });
    });

    it('le texte de la séance', () => {
        expect(platesText(92.5)).toBe('25 + 10 + 1,25 par côté');
        expect(platesText(20)).toBe('Barre seule (20 kg)');
        expect(platesText(10)).toBeNull();
        expect(platesText(21)).toBeNull();                                    // 0,5 kg par côté : aucun disque
    });

    it('le lest de la ceinture au poids du corps', () => {
        expect(platesText(22.5, { isBodyweight: true })).toBe('Ceinture : 20 + 2,5');
        expect(platesText(0, { isBodyweight: true })).toBeNull();
    });
});

describe('répétitions pour battre son record', () => {
    it('une répétition de plus que l\'égalité avec le maximum estimé', () => {
        // 90 kg × 6 = 108 kg estimés ; à 90 kg, 7 répétitions battent un max de 108.
        expect(repsToBeat(108, 90)).toBe(7);
        expect(repsToBeat(100, 85)).toBe(6);
    });

    it('rien quand la charge seule bat déjà le record', () => {
        expect(repsToBeat(80, 85)).toBeNull();
        expect(repsToBeat(null, 85)).toBeNull();
    });
});
