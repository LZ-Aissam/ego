const ACTIVE_KEY = 'ego.activeWorkout';
const PENDING_KEY = 'ego.pendingWorkouts';

export function uuid() {
    if (typeof globalThis.crypto?.randomUUID === 'function') {
        return globalThis.crypto.randomUUID();
    }
    const bytes = globalThis.crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export function toNumber(value) {
    const n = parseFloat(String(value).replace(',', '.'));
    return Number.isFinite(n) ? n : 0;
}

const round2 = (n) => Math.round(n * 100) / 100;

export function newWorkout(clientId, startedAtIso) {
    return { clientId, startedAt: startedAtIso, currentIndex: 0, restEndsAt: null, restTotalMs: null, exercises: [] };
}

export function addExercise(state, exercise, { lastTime = null, suggestedKg = null, prescribedKg = null, restSeconds, target = null }) {
    const first = lastTime?.[0];
    state.exercises.push({
        exerciseId: exercise.id,
        name: exercise.name,
        isBodyweight: exercise.isBodyweight,
        perDumbbell: exercise.perDumbbell ?? false,
        intent: exercise.intent ?? null,
        intentBadge: exercise.intentBadge ?? '',
        intentCue: exercise.intentCue ?? null,
        restSeconds,
        lastTime,
        target,
        prescribedKg,
        draft: { weightKg: prescribedKg ?? first?.weightKg ?? suggestedKg ?? 0, reps: first?.reps ?? target?.repsMin ?? 10 },
        sets: [],
    });
    state.currentIndex = state.exercises.length - 1;
    return state;
}

function current(state) {
    return state.exercises[state.currentIndex] ?? null;
}

export function adjustDraft(state, field, delta) {
    const ex = current(state);
    if (!ex) return state;
    if (field === 'weightKg') {
        ex.draft.weightKg = Math.max(0, round2(toNumber(ex.draft.weightKg) + delta));
    } else {
        ex.draft.reps = Math.max(1, Math.round(toNumber(ex.draft.reps) + delta));
    }
    return state;
}

export function isResting(state, nowMs) {
    return state.restEndsAt !== null && state.restEndsAt !== undefined && nowMs < state.restEndsAt;
}

const targetDone = (ex) => ex.target !== null && ex.target !== undefined && ex.sets.length >= ex.target.sets;

// Prochain exercice de la quête pas encore terminé : d'abord ceux qui suivent, puis ceux laissés en arrière.
function nextUnfinished(state) {
    const n = state.exercises.length;
    for (let step = 1; step < n; step++) {
        const i = (state.currentIndex + step) % n;
        const ex = state.exercises[i];
        if (ex.target && !targetDone(ex)) return i;
    }
    return null;
}

export function allTargetsDone(state) {
    const planned = state.exercises.filter((ex) => ex.target);
    return planned.length > 0 && planned.every(targetDone);
}

// Séries lourdes dont le ressenti (facile · dur · limite) règle la hausse de charge.
export const RATED_INTENTS = ['top', 'max'];

export function logSet(state, nowMs) {
    const ex = current(state);
    if (!ex || isResting(state, nowMs)) return state;
    state.lastLogged = RATED_INTENTS.includes(ex.intent) ? { exercise: state.currentIndex, set: ex.sets.length } : null;
    ex.sets.push({
        weightKg: Math.max(0, round2(toNumber(ex.draft.weightKg))),
        reps: Math.max(1, Math.round(toNumber(ex.draft.reps))),
        doneAt: new Date(nowMs).toISOString(),
    });
    state.restEndsAt = nowMs + ex.restSeconds * 1000;
    state.restTotalMs = ex.restSeconds * 1000;
    state.justAdvanced = false;
    if (targetDone(ex)) {
        const next = nextUnfinished(state);
        if (next !== null) {
            state.currentIndex = next;
            state.justAdvanced = true;
        }
    }
    return state;
}

export function removeLastSet(state) {
    current(state)?.sets.pop();
    state.lastLogged = null;
    return state;
}

function lastLoggedSet(state) {
    const at = state.lastLogged;
    return at ? state.exercises[at.exercise]?.sets[at.set] ?? null : null;
}

export function effortPending(state) {
    const set = lastLoggedSet(state);
    return set !== null && !set.effort;
}

export function rateLastSet(state, effort) {
    const set = lastLoggedSet(state);
    if (set) set.effort = effort;
    return state;
}

export function skipRest(state) {
    state.restEndsAt = null;
    state.restTotalMs = null;
    return state;
}

export function addRest(state, seconds, nowMs) {
    state.restEndsAt = Math.max(state.restEndsAt ?? nowMs, nowMs) + seconds * 1000;
    state.restTotalMs = (state.restTotalMs ?? 0) + seconds * 1000;
    return state;
}

export function restProgress(state, nowMs) {
    if (!state.restEndsAt || !state.restTotalMs) return 0;
    return Math.min(1, Math.max(0, (state.restEndsAt - nowMs) / state.restTotalMs));
}

export function goTo(state, index) {
    if (index >= 0 && index < state.exercises.length) state.currentIndex = index;
    return state;
}

export function hasSets(state) {
    return state.exercises.some((ex) => ex.sets.length > 0);
}

export function toPayload(state, finishedAtIso) {
    return {
        client_id: state.clientId,
        started_at: state.startedAt,
        finished_at: finishedAtIso,
        sets: state.exercises.flatMap((ex) => ex.sets.map((set) => ({
            exercise_id: ex.exerciseId,
            weight_kg: set.weightKg,
            reps: set.reps,
            done_at: set.doneAt,
            ...(set.effort ? { effort: set.effort } : {}),
        }))),
    };
}

function readJson(storage, key, fallback) {
    try {
        const raw = storage.getItem(key);
        return raw === null ? fallback : JSON.parse(raw);
    } catch {
        return fallback;
    }
}

// userId : les séances sont rangées par utilisateur, pour qu'un autre compte connecté
// sur le même appareil ne reprenne ni n'envoie la séance de quelqu'un d'autre.
export function createStore(storage, userId = null) {
    const suffix = userId === null ? '' : `.${userId}`;
    const activeKey = ACTIVE_KEY + suffix;
    const pendingKey = PENDING_KEY + suffix;

    return {
        loadActive: () => readJson(storage, activeKey, null),
        saveActive: (state) => storage.setItem(activeKey, JSON.stringify(state)),
        clearActive: () => storage.removeItem(activeKey),
        pending: () => readJson(storage, pendingKey, []),
        queuePending(payload) {
            const list = this.pending().filter((p) => p.client_id !== payload.client_id);
            list.push(payload);
            storage.setItem(pendingKey, JSON.stringify(list));
        },
        removePending(clientId) {
            storage.setItem(pendingKey, JSON.stringify(this.pending().filter((p) => p.client_id !== clientId)));
        },
    };
}

export function finishWorkout(store, state, finishedAtIso) {
    const payload = toPayload(state, finishedAtIso);
    store.queuePending(payload);
    store.clearActive();
    return payload;
}
