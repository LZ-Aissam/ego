import { describe, expect, it } from 'vitest';
import { countdownCue, loadSettings, saveSettings, subtractRest } from './signals';

function memoryStorage() {
    const data = new Map();
    return { getItem: (k) => (data.has(k) ? data.get(k) : null), setItem: (k, v) => data.set(k, String(v)), removeItem: (k) => data.delete(k) };
}

describe('chrono de repos', () => {
    it('retire 15 s sans descendre sous maintenant, et termine le repos s\'il est écoulé', () => {
        let s = { restEndsAt: 60000, restTotalMs: 90000 };
        s = subtractRest(s, 15, 10000);
        expect(s.restEndsAt).toBe(45000);

        s = subtractRest(s, 15, 40000);
        expect(s.restEndsAt).toBeNull();
        expect(s.restTotalMs).toBeNull();
    });

    it('sonne 3-2-1 puis la fin, une seule fois chacun', () => {
        const cues = [];
        let prev = 5000;
        for (let remaining = 4900; remaining >= -200; remaining -= 100) {
            const cue = countdownCue(prev, remaining);
            if (cue) cues.push([cue, remaining]);
            prev = remaining;
        }
        expect(cues).toEqual([['tick', 3000], ['tick', 2000], ['tick', 1000], ['end', 0]]);
    });

    it('rattrape la fin même après un long saut (écran éteint)', () => {
        expect(countdownCue(40000, -5000)).toBe('end');
        expect(countdownCue(-5000, -6000)).toBeNull();
    });
});

describe('réglages des signaux', () => {
    it('par défaut : son, vibration et compte à rebours activés', () => {
        expect(loadSettings(memoryStorage())).toEqual({ sound: true, vibration: true, countdown: true });
    });

    it('retient les choix du joueur', () => {
        const storage = memoryStorage();
        saveSettings(storage, { sound: false, vibration: true, countdown: false });
        expect(loadSettings(storage)).toEqual({ sound: false, vibration: true, countdown: false });
    });

    it('reste utilisable si le stockage est indisponible ou corrompu', () => {
        const broken = { getItem() { throw new Error('bloqué'); }, setItem() { throw new Error('bloqué'); } };
        expect(loadSettings(broken)).toEqual({ sound: true, vibration: true, countdown: true });
        expect(() => saveSettings(broken, { sound: false })).not.toThrow();

        const storage = memoryStorage();
        storage.setItem('ego.signals', '{pas du json');
        expect(loadSettings(storage).sound).toBe(true);
    });
});
