export async function sendWorkout(payload, { token, fetchImpl = (...args) => globalThis.fetch(...args), url = '/workouts/sync' }) {
    try {
        const response = await fetchImpl(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(payload),
        });
        if (response.ok) return { status: 'ok', body: await response.json() };
        if (response.status === 422) return { status: 'invalid', body: await response.json() };
        if (response.status === 409) return { status: 'conflict' };
        return { status: 'retry' };
    } catch {
        return { status: 'retry' };
    }
}

export function collectNotifications(result) {
    return Object.values(result?.sent ?? {}).flatMap((body) => body?.notifications ?? []);
}

export async function flushPending(store, send) {
    const result = { sent: {}, invalid: [], remaining: 0 };
    for (const payload of store.pending()) {
        const response = await send(payload);
        if (response.status === 'ok') {
            store.removePending(payload.client_id);
            result.sent[payload.client_id] = response.body;
        } else if (response.status === 'conflict') {
            store.removePending(payload.client_id);
        } else if (response.status === 'invalid') {
            result.invalid.push(payload.client_id);
        }
    }
    result.remaining = store.pending().length;
    return result;
}
