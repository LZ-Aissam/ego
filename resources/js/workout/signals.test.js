import { describe, expect, it } from 'vitest';
import { alertPlan, countdownCue, loadSettings, restEndDelay, saveSettings, subtractRest, wavDataUri } from './signals';

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
    it('par défaut : son, vibration, compte à rebours et notification activés', () => {
        expect(loadSettings(memoryStorage())).toEqual({ sound: true, vibration: true, countdown: true, notify: true });
    });

    it('retient les choix du joueur', () => {
        const storage = memoryStorage();
        saveSettings(storage, { sound: false, vibration: true, countdown: false, notify: false });
        expect(loadSettings(storage)).toEqual({ sound: false, vibration: true, countdown: false, notify: false });
    });

    it('reste utilisable si le stockage est indisponible ou corrompu', () => {
        const broken = { getItem() { throw new Error('bloqué'); }, setItem() { throw new Error('bloqué'); } };
        expect(loadSettings(broken)).toEqual({ sound: true, vibration: true, countdown: true, notify: true });
        expect(() => saveSettings(broken, { sound: false })).not.toThrow();

        const storage = memoryStorage();
        storage.setItem('ego.signals', '{pas du json');
        expect(loadSettings(storage).sound).toBe(true);
    });
});

describe('alertes de fin de repos', () => {
    it('active par défaut la notification hors de l’appli, même pour un réglage enregistré avant elle', () => {
        const storage = memoryStorage();
        storage.setItem('ego.signals', JSON.stringify({ sound: false, vibration: true, countdown: true }));
        expect(loadSettings(storage)).toEqual({ sound: false, vibration: true, countdown: true, notify: true });
    });

    it('choisit les canaux selon la visibilité de l’appli et la permission', () => {
        const settings = { sound: true, vibration: true, countdown: true, notify: true };
        expect(alertPlan({ hidden: false, permission: 'granted', settings })).toEqual({ vibrate: true, sound: true, notification: false });
        expect(alertPlan({ hidden: true, permission: 'granted', settings })).toEqual({ vibrate: false, sound: true, notification: true });
        expect(alertPlan({ hidden: true, permission: 'denied', settings })).toEqual({ vibrate: false, sound: true, notification: false });
        expect(alertPlan({ hidden: true, permission: 'granted', settings: { ...settings, notify: false, sound: false } }))
            .toEqual({ vibrate: false, sound: false, notification: false });
    });

    it('fabrique un WAV mono 16 bits valide, encadré de silence pour éviter le clic', () => {
        const uri = wavDataUri([{ hz: 880, ms: 100 }], { rate: 8000, lead: 10 });
        expect(uri.startsWith('data:audio/wav;base64,')).toBe(true);

        const bytes = Uint8Array.from(atob(uri.split(',')[1]), (c) => c.charCodeAt(0));
        const text = (offset, length) => String.fromCharCode(...bytes.slice(offset, offset + length));
        const view = new DataView(bytes.buffer);
        expect([text(0, 4), text(8, 4), text(12, 4), text(36, 4)]).toEqual(['RIFF', 'WAVE', 'fmt ', 'data']);
        expect([view.getUint16(22, true), view.getUint32(24, true), view.getUint16(34, true)]).toEqual([1, 8000, 16]);
        expect(view.getUint32(40, true)).toBe((800 + 2 * 80) * 2);
        expect(view.getInt16(44, true)).toBe(0);
        expect(bytes.length).toBe(44 + (800 + 2 * 80) * 2);
    });

    it('programme la fin du repos juste après l’échéance, jamais dans le passé', () => {
        expect(restEndDelay(10000, 9000)).toBe(1030);
        expect(restEndDelay(1000, 5000)).toBe(30);
    });
});
