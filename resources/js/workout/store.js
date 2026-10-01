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
    return { clientId, startedAt: startedAtIso, currentIndex: 0, restEndsAt: null, exercises: [] };
}

export function addExercise(state, exercise, { lastTime = null, suggestedKg = null, restSeconds }) {
    const first = lastTime?.[0];
    state.exercises.push({
        exerciseId: exercise.id,
        name: exercise.name,
        isBodyweight: exercise.isBodyweight,
        restSeconds,
        lastTime,
        draft: { weightKg: first?.weightKg ?? suggestedKg ?? 0, reps: first?.reps ?? 10 },
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

export function logSet(state, nowMs) {
    const ex = current(state);
    if (!ex) return state;
    ex.sets.push({
        weightKg: Math.max(0, round2(toNumber(ex.draft.weightKg))),
        reps: Math.max(1, Math.round(toNumber(ex.draft.reps))),
        doneAt: new Date(nowMs).toISOString(),
    });
    state.restEndsAt = nowMs + ex.restSeconds * 1000;
    return state;
}

export function removeLastSet(state) {
    current(state)?.sets.pop();
    return state;
}

export function skipRest(state) {
    state.restEndsAt = null;
    return state;
}

export function addRest(state, seconds, nowMs) {
    state.restEndsAt = Math.max(state.restEndsAt ?? nowMs, nowMs) + seconds * 1000;
    return state;
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
