// Chrono de repos : réglages des signaux (son, vibration, compte à rebours) et moments où ils se déclenchent.

const SETTINGS_KEY = 'ego.signals';
const DEFAULTS = { sound: true, vibration: true, countdown: true };

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
