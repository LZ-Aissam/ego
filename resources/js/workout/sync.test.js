import { describe, expect, it } from 'vitest';
import { createStore } from './store';
import { flushPending, sendWorkout } from './sync';

function memoryStorage() {
    const data = new Map();
    return {
        getItem: (k) => (data.has(k) ? data.get(k) : null),
        setItem: (k, v) => data.set(k, String(v)),
        removeItem: (k) => data.delete(k),
    };
}

const fakeFetch = (status, body = {}) => async () => ({ ok: status >= 200 && status < 300, status, json: async () => body });

describe('sendWorkout', () => {
    it('renvoie ok avec le corps en cas de succès', async () => {
        const r = await sendWorkout({ client_id: 'a' }, { token: 't', fetchImpl: fakeFetch(201, { workout_id: 1 }) });
        expect(r).toEqual({ status: 'ok', body: { workout_id: 1 } });
    });

    it('renvoie invalid sur 422', async () => {
        const r = await sendWorkout({}, { token: 't', fetchImpl: fakeFetch(422, { errors: {} }) });
        expect(r.status).toBe('invalid');
    });

    it('renvoie conflict sur 409 (séance d\'un autre utilisateur)', async () => {
        const r = await sendWorkout({}, { token: 't', fetchImpl: fakeFetch(409) });
        expect(r.status).toBe('conflict');
    });

    it('renvoie retry sur session expirée (419/401), erreur serveur ou coupure réseau', async () => {
        for (const status of [419, 401, 500]) {
            expect((await sendWorkout({}, { token: 't', fetchImpl: fakeFetch(status) })).status).toBe('retry');
        }
        const offline = async () => { throw new TypeError('Failed to fetch'); };
        expect((await sendWorkout({}, { token: 't', fetchImpl: offline })).status).toBe('retry');
    });

    it('envoie le jeton CSRF et du JSON', async () => {
        let seen;
        const spy = async (url, init) => { seen = { url, init }; return { ok: true, status: 201, json: async () => ({}) }; };
        await sendWorkout({ client_id: 'a' }, { token: 'tok', fetchImpl: spy });
        expect(seen.url).toBe('/workouts/sync');
        expect(seen.init.headers['X-CSRF-TOKEN']).toBe('tok');
        expect(JSON.parse(seen.init.body)).toEqual({ client_id: 'a' });
    });
});

describe('flushPending', () => {
    it('retire les séances envoyées et garde les autres', async () => {
        const store = createStore(memoryStorage());
        store.queuePending({ client_id: 'ok' });
        store.queuePending({ client_id: 'bad' });
        store.queuePending({ client_id: 'later' });
        const send = async (p) => ({ ok: { status: 'ok', body: { id: 1 } }, bad: { status: 'invalid' }, later: { status: 'retry' } }[p.client_id]);

        const result = await flushPending(store, send);

        expect(result.sent).toEqual({ ok: { id: 1 } });
        expect(result.invalid).toEqual(['bad']);
        expect(result.remaining).toBe(2);
        expect(store.pending().map((p) => p.client_id)).toEqual(['bad', 'later']);
    });

    it('retire de la file une séance en conflit', async () => {
        const store = createStore(memoryStorage());
        store.queuePending({ client_id: 'other' });

        const result = await flushPending(store, async () => ({ status: 'conflict' }));

        expect(result.remaining).toBe(0);
        expect(store.pending()).toEqual([]);
    });
});
