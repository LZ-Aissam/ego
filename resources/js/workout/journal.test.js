import { describe, expect, it } from 'vitest';
import { addExercise, logSet, newWorkout, replaceExercise, setNote, setPain, setReadiness, toPayload } from './store';

const target = { sets: 3, repsMin: 5, repsMax: 5 };
const bench = { id: 1, name: 'Développé couché', isBodyweight: false, intent: 'wave', isBarbell: true, recordE1rm: 100 };
const curl = { id: 5, name: 'Curl barre', isBodyweight: false, intent: null };
const dips = { id: 7, name: 'Dips', isBodyweight: true, intent: 'max' };

function session() {
    const s = newWorkout('id-1', '2026-11-14T09:00:00.000Z');
    addExercise(s, bench, { prescribedKg: 80, restSeconds: 300, target: { sets: 1, repsMin: 5, repsMax: 5 } });
    addExercise(s, curl, { prescribedKg: 30, restSeconds: 120, target });
    addExercise(s, dips, { prescribedKg: 20, restSeconds: 180, target });
    s.currentIndex = 0;
    return s;
}

describe('forme du jour', () => {
    it('fatigué : les charges lourdes pas encore commencées baissent de 5 %, arrondies au disque', () => {
        const s = setReadiness(session(), 'low');
        expect(s.exercises.map((ex) => ex.draft.weightKg)).toEqual([75, 30, 17.5]);
        expect(toPayload(s, '2026-11-14T10:00:00.000Z').readiness).toBe('low');
    });

    it('revenir à normal remet la charge prescrite', () => {
        const s = setReadiness(setReadiness(session(), 'low'), 'normal');
        expect(s.exercises.map((ex) => ex.draft.weightKg)).toEqual([80, 30, 20]);
    });

    it('ne touche pas un exercice déjà commencé', () => {
        const s = session();
        logSet(s, Date.parse('2026-11-14T09:05:00Z'));
        setReadiness(s, 'low');
        expect(s.exercises[0].draft.weightKg).toBe(80);
    });

    it('sans forme annoncée, rien dans l\'envoi', () => {
        expect(toPayload(session(), '2026-11-14T10:00:00.000Z')).not.toHaveProperty('readiness');
    });
});

describe('remplacer un exercice', () => {
    it('passe l\'ancien et insère le remplaçant juste après, avec les mêmes séries visées', () => {
        const s = session();
        s.currentIndex = 1;
        replaceExercise(s, { id: 9, name: 'Curl haltères', isBodyweight: false }, { prescribedKg: 12 });

        expect(s.exercises.map((ex) => ex.exerciseId)).toEqual([1, 5, 9, 7]);
        expect(s.currentIndex).toBe(2);
        expect(s.exercises[2].target).toEqual(target);
        expect(s.exercises[2].draft.weightKg).toBe(12);
        expect(toPayload(s, '2026-11-14T10:00:00.000Z').skipped).toEqual([5]);
    });

    it('impossible une fois l\'exercice commencé', () => {
        const s = session();
        logSet(s, Date.parse('2026-11-14T09:05:00Z'));
        s.currentIndex = 0;
        replaceExercise(s, curl);
        expect(s.exercises).toHaveLength(3);
    });
});

describe('carnet', () => {
    it('envoie la note et la douleur de l\'exercice', () => {
        const s = session();
        setNote(s, '  prise trop large  ');
        setPain(s, 'shoulder');
        expect(toPayload(s, '2026-11-14T10:00:00.000Z').notes).toEqual([{ exercise_id: 1, text: 'prise trop large', pain_zone: 'shoulder' }]);
    });

    it('retoucher la même zone retire la douleur', () => {
        const s = session();
        setPain(s, 'elbow');
        setPain(s, 'elbow');
        expect(toPayload(s, '2026-11-14T10:00:00.000Z')).not.toHaveProperty('notes');
    });

    it('garde ce qui vient du serveur : dernière note, douleur récente, record à battre, barre', () => {
        const s = newWorkout('id-2', '2026-11-14T09:00:00.000Z');
        addExercise(s, { ...bench, lastNote: { text: 'coude', date: '12 nov.' }, pain: { zone: 'Coude', date: '12 nov.' }, alternatives: [3] }, { restSeconds: 90 });
        expect(s.exercises[0]).toMatchObject({ isBarbell: true, recordE1rm: 100, lastNote: { text: 'coude' }, pain: { zone: 'Coude' }, alternatives: [3] });
    });
});
