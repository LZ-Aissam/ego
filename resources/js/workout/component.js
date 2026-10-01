import {
    addExercise, addRest, adjustDraft, allTargetsDone, createStore, finishWorkout, goTo, hasSets,
    isResting, logSet, newWorkout, removeLastSet, restProgress, skipRest, uuid,
} from './store';
import { collectNotifications, flushPending, sendWorkout } from './sync';
import { countdownCue, loadSettings, saveSettings, subtractRest } from './signals';

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
        signals: { sound: true, vibration: true, countdown: true },
        showSettings: false,
        restExpanded: true,
        lastRemainingMs: Infinity,

        init() {
            this.boot = JSON.parse(document.getElementById('workout-boot').textContent);
            this.signals = loadSettings(window.localStorage);
            this.store = createStore(window.localStorage, document.body.dataset.userId);
            const stored = this.store.loadActive();
            this.state = stored ?? newWorkout(uuid(), new Date().toISOString());
            // Séance de quête : exercices, séries et repos du programme, sauf si une séance est déjà en cours.
            if (!stored && this.boot.plan?.length) {
                for (const p of this.boot.plan) {
                    addExercise(this.state, p, {
                        lastTime: this.boot.lastTime[p.id] ?? null,
                        suggestedKg: p.suggestedKg,
                        prescribedKg: p.prescribedKg ?? null,
                        restSeconds: p.restSeconds,
                        target: { sets: p.sets, repsMin: p.repsMin, repsMax: p.repsMax },
                    });
                }
                goTo(this.state, 0);
            }
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

        get restPercent() {
            return Math.round(restProgress(this.state, this.now) * 100);
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

        demoId: null,

        get demo() {
            return this.demoId === null ? null : this.boot.exercises.find((e) => e.id === this.demoId) ?? null;
        },

        guideOf(exerciseId) {
            return this.boot.exercises.find((e) => e.id === exerciseId)?.guide ?? null;
        },

        openDemo(exerciseId) { this.demoId = exerciseId; },
        closeDemo() { this.demoId = null; },

        get resting() {
            return isResting(this.state, this.now);
        },

        get allDone() {
            return allTargetsDone(this.state);
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
            if (!this.state.restEndsAt) {
                this.lastRemainingMs = Infinity;
                return;
            }
            // Temps calculé sur l'heure : juste même après un écran éteint ou une appli rouverte.
            const remaining = this.state.restEndsAt - this.now;
            const cue = countdownCue(this.lastRemainingMs, remaining);
            this.lastRemainingMs = remaining;
            if (cue === 'tick' && this.signals.countdown) this.beep([0], 660, 0.12);
            if (cue === 'end') {
                skipRest(this.state);
                this.lastRemainingMs = Infinity;
                this.alarm();
            }
        },

        alarm() {
            if (this.signals.vibration) navigator.vibrate?.([300, 100, 300]);
            this.beep([0, 0.35], 880, 0.2);
        },

        beep(offsets, frequency, duration) {
            if (!this.signals.sound || !this.audio) return;
            offsets.forEach((offset) => {
                const osc = this.audio.createOscillator();
                const gain = this.audio.createGain();
                osc.frequency.value = frequency;
                osc.connect(gain).connect(this.audio.destination);
                gain.gain.setValueAtTime(0.25, this.audio.currentTime + offset);
                osc.start(this.audio.currentTime + offset);
                osc.stop(this.audio.currentTime + offset + duration);
            });
        },

        toggleSignal(key) {
            this.signals[key] = !this.signals[key];
            saveSettings(window.localStorage, this.signals);
        },

        lessRest() { subtractRest(this.state, 15, Date.now()); },

        add(exercise) {
            addExercise(this.state, exercise, {
                lastTime: this.boot.lastTime[exercise.id] ?? null,
                suggestedKg: exercise.suggestedKg,
                prescribedKg: exercise.prescribedKg ?? null,
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
            this.now = Date.now();
            if (this.resting) return;
            logSet(this.state, this.now);
            this.restExpanded = true;
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
            // Toutes les séances envoyées (y compris d'anciennes en attente) : aucune récompense perdue.
            const notifications = collectNotifications(result);
            if (notifications.length) {
                window.dispatchEvent(new CustomEvent('sys-notify', { detail: notifications }));
            }
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
