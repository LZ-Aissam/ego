import { describe, expect, it } from 'vitest';
import { matchExercises } from './search';

const exercises = [
    { id: 1, name: 'Barre au front', muscles: ['triceps'] },
    { id: 2, name: 'Dips', muscles: ['pectoraux', 'triceps'] },
    { id: 3, name: 'Curl marteau', muscles: ['biceps', 'avant-bras'] },
    { id: 4, name: 'Curl poignets', muscles: ['avant-bras'] },
    { id: 5, name: 'Squat (barre)', muscles: ['quadriceps', 'fessiers'] },
    { id: 6, name: 'Développé couché (barre)', muscles: ['pectoraux', 'triceps', 'épaules'] },
];
const ids = (q) => matchExercises(exercises, q).map((e) => e.id);

describe('recherche d’exercice', () => {
    it('trouve par le nom, sans tenir compte des accents ni des majuscules', () => {
        expect(ids('developpe')).toEqual([6]);
        expect(ids('SQUAT')).toEqual([5]);
    });

    it('trouve par muscle travaillé', () => {
        expect(ids('triceps')).toEqual([1, 2, 6]);
        expect(ids('avant-bras')).toEqual([3, 4]);
        expect(ids('epaules')).toEqual([6]);
    });

    it('« bras » regroupe biceps, triceps et avant-bras', () => {
        expect(ids('bras')).toEqual([1, 2, 3, 4, 6]);
    });

    it('sans recherche, tout le catalogue', () => {
        expect(ids('  ')).toEqual([1, 2, 3, 4, 5, 6]);
    });

    it('tolère un exercice sans liste de muscles', () => {
        expect(matchExercises([{ id: 9, name: 'Planche' }], 'triceps')).toEqual([]);
    });
});
