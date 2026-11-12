// Chrono de repos : réglages des signaux (son, vibration, compte à rebours) et moments où ils se déclenchent.

const SETTINGS_KEY = 'ego.signals';
const DEFAULTS = { sound: true, vibration: true, countdown: true, notify: true };

export function loadSettings(storage) {
    try {
        const saved = JSON.parse(storage.getItem(SETTINGS_KEY) ?? 'null');
        return { ...DEFAULTS, ...(saved && typeof saved === 'object' ? saved : {}) };
    } catch {
        return { ...DEFAULTS };
    }
}

export function saveSettings(storage, settings) {
    try {
        storage.setItem(SETTINGS_KEY, JSON.stringify({ ...DEFAULTS, ...settings }));
    } catch {
        // Stockage bloqué (navigation privée) : les réglages valent pour cette séance seulement.
    }
}

/** Retire du temps de repos ; un repos qui tombe à zéro est terminé. */
export function subtractRest(state, seconds, nowMs) {
    if (!state.restEndsAt) return state;
    const endsAt = state.restEndsAt - seconds * 1000;
    if (endsAt <= nowMs) {
        state.restEndsAt = null;
        state.restTotalMs = null;
    } else {
        state.restEndsAt = endsAt;
    }
    return state;
}

/**
 * Signal à émettre entre deux lectures du temps restant (ms) : « tick » en franchissant 3, 2 puis 1 s,
 * « end » en franchissant 0 — même si l'écran était éteint et que plusieurs seuils ont été sautés.
 */
export function countdownCue(previousMs, remainingMs) {
    if (previousMs > 0 && remainingMs <= 0) return 'end';
    for (const threshold of [3000, 2000, 1000]) {
        if (previousMs > threshold && remainingMs <= threshold) return 'tick';
    }
    return null;
}

/** Canaux d'alerte à la fin du repos : à l'écran, vibreur et son ; appli en arrière-plan, vibrer est interdit et une notification prend le relais. */
export function alertPlan({ hidden = false, permission = 'default', settings = DEFAULTS } = {}) {
    return {
        vibrate: Boolean(settings.vibration) && !hidden,
        sound: Boolean(settings.sound),
        notification: Boolean(settings.notify) && hidden && permission === 'granted',
    };
}

/** Délai (ms) avant de constater la fin du repos : juste après l'échéance, jamais dans le passé. */
export function restEndDelay(endsAtMs, nowMs) {
    return Math.max(0, endsAtMs - nowMs) + 30;
}

/**
 * Petit fichier WAV (mono, 16 bits) en URI de données : des tons [{ hz, ms }] séparés d'un silence,
 * encadrés d'un silence de garde pour qu'une lecture lancée puis arrêtée aussitôt ne fasse aucun bruit.
 */
export function wavDataUri(tones, { rate = 22050, lead = 40, gap = 150, volume = 0.6 } = {}) {
    const samples = [];
    const silence = (ms) => {
        for (let i = 0, n = Math.round((rate * ms) / 1000); i < n; i++) samples.push(0);
    };
    silence(lead);
    tones.forEach(({ hz, ms }, index) => {
        if (index > 0) silence(gap);
        const n = Math.round((rate * ms) / 1000);
        for (let i = 0; i < n; i++) {
            // Attaque 5 ms et relâche 20 ms : pas de clic au début ni à la fin du ton.
            const envelope = Math.min(1, i / (rate * 0.005), (n - i) / (rate * 0.02));
            samples.push(Math.round(Math.sin((2 * Math.PI * hz * i) / rate) * envelope * volume * 32767));
        }
    });
    silence(lead);

    const bytes = new Uint8Array(44 + samples.length * 2);
    const view = new DataView(bytes.buffer);
    const ascii = (offset, text) => [...text].forEach((char, i) => view.setUint8(offset + i, char.charCodeAt(0)));
    ascii(0, 'RIFF');
    view.setUint32(4, 36 + samples.length * 2, true);
    ascii(8, 'WAVE');
    ascii(12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true);
    view.setUint16(22, 1, true);
    view.setUint32(24, rate, true);
    view.setUint32(28, rate * 2, true);
    view.setUint16(32, 2, true);
    view.setUint16(34, 16, true);
    ascii(36, 'data');
    view.setUint32(40, samples.length * 2, true);
    samples.forEach((sample, i) => view.setInt16(44 + i * 2, sample, true));

    let binary = '';
    for (let i = 0; i < bytes.length; i += 0x8000) binary += String.fromCharCode(...bytes.subarray(i, i + 0x8000));

    return `data:audio/wav;base64,${btoa(binary)}`;
}
