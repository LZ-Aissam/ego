import {
    addExercise, addRest, adjustDraft, createStore, finishWorkout, goTo, hasSets,
    logSet, newWorkout, removeLastSet, skipRest, uuid,
} from './store';
import { flushPending, sendWorkout } from './sync';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export function formatKg(kg) {
    return `${String(Math.round(kg * 100) / 100).replace('.', ',')} kg`;
}

export default function workout() {
    return {
        boot: { restSeconds: 90, exercises: [], lastTime: {} },
        state: null,
        store: null,
        now: Date.now(),
        picking: false,
        search: '',
        finishing: false,
        confirmDiscard: false,
        done: false,
        summary: null,
        audio: null,

        init() {
            this.boot = JSON.parse(document.getElementById('workout-boot').textContent);
            this.store = createStore(window.localStorage, document.body.dataset.userId);
            this.state = this.store.loadActive() ?? newWorkout(uuid(), new Date().toISOString());
            this.picking = this.state.exercises.length === 0;
            this.persist();
            this.$watch('state', () => this.persist());
            setInterval(() => this.tick(), 250);
            navigator.wakeLock?.request('screen').catch(() => {});
        },

        persist() {
            if (!this.done) this.store.saveActive(this.state);
        },

        get current() {
            return this.state.exercises[this.state.currentIndex] ?? null;
        },

        get restRemaining() {
            if (!this.state.restEndsAt) return 0;
            return Math.max(0, Math.ceil((this.state.restEndsAt - this.now) / 1000));
        },

        get elapsed() {
            const total = Math.max(0, Math.floor((this.now - Date.parse(this.state.startedAt)) / 1000));
            const h = Math.floor(total / 3600);
            const m = String(Math.floor((total % 3600) / 60)).padStart(2, '0');
            const s = String(total % 60).padStart(2, '0');
            return h > 0 ? `${h}:${m}:${s}` : `${m}:${s}`;
        },

        get filtered() {
            const q = this.search.trim().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
            return this.boot.exercises
                .filter((e) => !q || e.name.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').includes(q))
                .slice(0, 50);
        },

        get canFinish() {
            return hasSets(this.state);
        },

        clock(seconds) {
            return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
        },

        formatKg,

        lastTimeText(ex) {
            if (!ex.lastTime?.length) return null;
            return ex.lastTime.map((s) => `${formatKg(s.weightKg)} × ${s.reps}`).join(' · ');
        },

        tick() {
            this.now = Date.now();
            if (this.state.restEndsAt && this.now >= this.state.restEndsAt) {
                skipRest(this.state);
                this.alarm();
            }
        },

        alarm() {
            navigator.vibrate?.([300, 100, 300]);
            if (!this.audio) return;
            [0, 0.35].forEach((offset) => {
                const osc = this.audio.createOscillator();
                const gain = this.audio.createGain();
                osc.frequency.value = 880;
                osc.connect(gain).connect(this.audio.destination);
                gain.gain.setValueAtTime(0.25, this.audio.currentTime + offset);
                osc.start(this.audio.currentTime + offset);
                osc.stop(this.audio.currentTime + offset + 0.2);
            });
        },

        add(exercise) {
            addExercise(this.state, exercise, {
                lastTime: this.boot.lastTime[exercise.id] ?? null,
                suggestedKg: exercise.suggestedKg,
                restSeconds: this.boot.restSeconds,
            });
            this.picking = false;
            this.search = '';
        },

        adjust(field, delta) { adjustDraft(this.state, field, delta); },
        go(index) { goTo(this.state, index); },
        undoSet() { removeLastSet(this.state); },
        skipRest() { skipRest(this.state); },
        moreRest() { addRest(this.state, 30, Date.now()); },

        validateSet() {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!this.audio && Ctx) this.audio = new Ctx();
            logSet(this.state, Date.now());
        },

        async finish() {
            if (!this.canFinish || this.finishing) return;
            this.finishing = true;
            this.done = true;
            const payload = finishWorkout(this.store, this.state, new Date().toISOString());
            const result = await flushPending(this.store, (p) => sendWorkout(p, { token: csrfToken() }));
            const body = result.sent[payload.client_id];
            this.summary = body
                ? { pending: false, ...body }
                : { pending: true, invalid: result.invalid.includes(payload.client_id) };
            this.finishing = false;
        },

        discard() {
            if (!this.confirmDiscard) {
                this.confirmDiscard = true;
                return;
            }
            this.done = true;
            this.store.clearActive();
            window.location.href = '/';
        },
    };
}
