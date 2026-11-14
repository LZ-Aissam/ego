import {
    addExercise, addRest, adjustDraft, allTargetsDone, closeStale, createStore, effortPending, finishWorkout, goTo, hasSets,
    isResting, logSet, newWorkout, rateLastSet, removeLastSet, replaceExercise, restProgress, setNote, setPain, setReadiness, skipExercise, skipRest, toNumber, uuid,
} from './store';
import { RAMP_INTENTS, platesText, repsToBeat, warmupRamp } from './plates';
import { collectNotifications, flushPending, sendWorkout } from './sync';
import { alertPlan, countdownCue, loadSettings, restEndDelay, saveSettings, subtractRest, wavDataUri } from './signals';
import { matchExercises } from './search';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export function formatKg(kg) {
    return `${String(Math.round(kg * 100) / 100).replace('.', ',')} kg`;
}

/** Sons du chrono en petits fichiers WAV : un élément audio se joue même avec l'interrupteur silencieux d'un iPhone, contrairement au bip Web Audio. */
function buildTones() {
    if (typeof Audio === 'undefined') return null;
    const make = (tones) => {
        const audio = new Audio(wavDataUri(tones));
        audio.preload = 'auto';
        return audio;
    };

    return { tick: make([{ hz: 660, ms: 120 }]), alarm: make([{ hz: 880, ms: 200 }, { hz: 880, ms: 200 }]) };
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
        tones: null,
        tonesReady: false,
        wakeLock: null,
        restEndTimer: null,
        askedNotify: false,
        signals: { sound: true, vibration: true, countdown: true, notify: true },
        showSettings: false,
        restExpanded: true,
        lastRemainingMs: Infinity,

        init() {
            this.boot = JSON.parse(document.getElementById('workout-boot').textContent);
            this.signals = loadSettings(window.localStorage);
            this.store = createStore(window.localStorage, document.body.dataset.userId);
            // Séance oubliée ouverte : envoyée pour son jour, et la quête d'aujourd'hui démarre à neuf.
            if (closeStale(this.store, Date.now())) this.sendPending();
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
            this.tones = buildTones();
            this.keepAwake();
            // Fin du repos programmée à la milliseconde : l'intervalle est ralenti quand l'appli passe en arrière-plan.
            this.$watch('state.restEndsAt', (endsAt) => this.scheduleRestEnd(endsAt));
            this.scheduleRestEnd(this.state.restEndsAt);
            // Appli rouverte ou écran rallumé : le verrou d'écran est perdu, l'audio peut être suspendu, le temps a filé.
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) return;
                this.keepAwake();
                this.unlockAudio();
                this.tick();
            });
            // Le son n'est permis qu'après un geste : débloqué au premier toucher, réveillé à chaque fois s'il a été suspendu.
            ['pointerdown', 'touchend', 'keydown'].forEach((type) => document.addEventListener(type, () => this.unlockAudio(), { passive: true }));
        },

        scheduleRestEnd(endsAt) {
            clearTimeout(this.restEndTimer);
            if (!endsAt) return;
            this.restEndTimer = setTimeout(() => this.tick(), restEndDelay(endsAt, Date.now()));
        },

        /** Garde l'écran allumé pendant la séance ; le verrou tombe dès qu'on quitte l'appli, on le reprend au retour. */
        async keepAwake() {
            if (!navigator.wakeLock || document.hidden || this.done) return;
            try {
                this.wakeLock = await navigator.wakeLock.request('screen');
            } catch {
                // Refusé (batterie faible, navigateur) : le chrono reste juste grâce à l'heure, l'alerte peut arriver écran éteint.
            }
        },

        releaseWakeLock() {
            this.wakeLock?.release().catch(() => {});
            this.wakeLock = null;
        },

        /** À appeler dans un geste : crée ou réveille l'audio, et amorce les sons pour que le téléphone accepte de les jouer seul ensuite. */
        unlockAudio() {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!this.audio && Ctx) this.audio = new Ctx();
            if (this.audio?.state === 'suspended') this.audio.resume().catch(() => {});
            if (!this.tones || this.tonesReady) return;
            this.tonesReady = true;
            Object.values(this.tones).forEach((tone) => {
                // Lecture lancée puis arrêtée aussitôt (le fichier commence par du silence) : l'élément est désormais autorisé, iPhone compris.
                const started = tone.play();
                tone.pause();
                tone.currentTime = 0;
                started?.catch((error) => {
                    if (error?.name === 'NotAllowedError') this.tonesReady = false;
                });
            });
        },

        /** Joue un son amorcé (audible même en mode silencieux sur iPhone), sinon le bip Web Audio. */
        play(name, fallback) {
            if (!this.signals.sound) return;
            const tone = this.tones?.[name];
            if (!tone || !this.tonesReady) return fallback();
            tone.currentTime = 0;
            tone.play().catch(fallback);
        },

        /** Dans un geste, une fois : permission de prévenir quand l'appli est en arrière-plan (vibrer y est interdit). */
        askNotifications() {
            if (!this.signals.notify || this.askedNotify || !('Notification' in window) || Notification.permission !== 'default') return;
            this.askedNotify = true;
            Notification.requestPermission().catch(() => {});
        },

        get notifyBlocked() {
            return 'Notification' in window && Notification.permission === 'denied';
        },

        notifyOutside() {
            const options = { body: 'Repos terminé. À toi.', tag: 'ego-rest', renotify: true, vibrate: [300, 100, 300], icon: '/icons/icon-192.png', badge: '/icons/icon-192.png' };
            const fromPage = () => {
                try {
                    new Notification('EGO', options);
                } catch {
                    // iPhone : seulement via le service worker.
                }
            };
            if (!navigator.serviceWorker) return fromPage();
            navigator.serviceWorker.ready.then((registration) => registration.showNotification('EGO', options)).catch(fromPage);
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
            return matchExercises(this.boot.exercises, this.search).slice(0, 50);
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

        get effortPending() {
            return effortPending(this.state);
        },

        /**
         * Ressenti de la série lourde. Limite : 60 s de repos en plus pour récupérer. Facile en force max, des séries
         * restent : 2,5 kg de plus pour la suivante.
         */
        rate(effort) {
            const at = this.state.lastLogged;
            rateLastSet(this.state, effort);
            const ex = at ? this.state.exercises[at.exercise] : null;
            if (!ex) return;
            if (effort === 'limit' && this.state.restEndsAt) {
                addRest(this.state, 60, Date.now());
                this.restHint = 'Série à la limite : +60 s pour récupérer.';
            }
            if (effort === 'easy' && ex.intent === 'max' && ex.target && ex.sets.length < ex.target.sets) {
                ex.draft.weightKg = Math.round((toNumber(ex.draft.weightKg) + 2.5) * 100) / 100;
                this.restHint = 'Facile : +2,5 kg sur la prochaine série.';
            }
        },

        restHint: '',
        journalOpen: false,

        /** Forme du jour : demandée une fois, avant la première série d'une séance de quête. */
        get askReadiness() {
            return !!this.boot.plan?.length && !this.state.readiness && !hasSets(this.state);
        },

        readiness(level) { setReadiness(this.state, level); },

        /** Montée d'échauffement : avant la 1re série lourde d'un mouvement (pas avant ses séries de suite). */
        warmup(ex) {
            if (!ex || ex.sets.length || !RAMP_INTENTS.includes(ex.intent)) return [];
            const first = this.state.exercises.findIndex((e) => e.exerciseId === ex.exerciseId && !e.replaced);
            if (this.state.exercises[first] !== ex) return [];
            return warmupRamp(toNumber(ex.draft.weightKg), { isBodyweight: ex.isBodyweight });
        },

        toggleWarmup(ex, i) {
            ex.warmupDone = ex.warmupDone ?? [];
            ex.warmupDone = ex.warmupDone.includes(i) ? ex.warmupDone.filter((j) => j !== i) : [...ex.warmupDone, i];
        },

        plates(ex, kg) {
            if (!ex) return null;
            const load = toNumber(kg ?? ex.draft.weightKg);
            if (ex.isBodyweight) return platesText(load, { isBodyweight: true });
            return ex.isBarbell ? platesText(load) : null;
        },

        /** « Record : 8 reps à 90 kg » sur la série 5+, 3+, 1+ ou la série record. */
        recordText(ex) {
            if (!ex?.recordE1rm || !['wave', 'top'].includes(ex.intent)) return null;
            const load = toNumber(ex.draft.weightKg);
            const reps = repsToBeat(ex.recordE1rm, load);
            return reps === null ? 'Nouveau record dès 1 répétition à ' + formatKg(load) : 'Record à battre : ' + reps + ' reps à ' + formatKg(load);
        },

        note(text) { setNote(this.state, text); },
        pain(zone) { setPain(this.state, zone); },

        get alternativesOfCurrent() {
            const ids = this.current?.alternatives ?? [];
            return ids.map((id) => this.boot.exercises.find((e) => e.id === id)).filter(Boolean);
        },

        replace(exercise) {
            replaceExercise(this.state, exercise, {
                lastTime: this.boot.lastTime[exercise.id] ?? null,
                suggestedKg: exercise.suggestedKg,
                prescribedKg: exercise.prescribedKg ?? null,
            });
            this.journalOpen = false;
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
            if (cue === 'tick' && this.signals.countdown) this.play('tick', () => this.beep([0], 660, 0.12));
            if (cue === 'end') {
                skipRest(this.state);
                this.lastRemainingMs = Infinity;
                this.alarm();
            }
        },

        alarm() {
            const permission = 'Notification' in window ? Notification.permission : 'denied';
            const plan = alertPlan({ hidden: document.hidden, permission, settings: this.signals });
            if (plan.vibrate) navigator.vibrate?.([300, 100, 300]);
            if (plan.sound) this.play('alarm', () => this.beep([0, 0.35], 880, 0.2));
            if (plan.notification) this.notifyOutside();
        },

        beep(offsets, frequency, duration) {
            if (!this.signals.sound || !this.audio) return;
            if (this.audio.state === 'suspended') this.audio.resume().catch(() => {});
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
            if (key === 'notify' && this.signals.notify) {
                this.askedNotify = false;
                this.askNotifications();
            }
            if (key === 'sound' && this.signals.sound) this.unlockAudio();
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
        skip() { if (this.current) skipExercise(this.state); },
        skipRest() { skipRest(this.state); },
        moreRest() { addRest(this.state, 30, Date.now()); },

        validateSet() {
            this.unlockAudio();
            this.askNotifications();
            this.now = Date.now();
            if (this.resting) return;
            logSet(this.state, this.now);
            this.restHint = '';
            this.journalOpen = false;
            this.restExpanded = true;
        },

        async sendPending() {
            const result = await flushPending(this.store, (p) => sendWorkout(p, { token: csrfToken() }));
            const notifications = collectNotifications(result);
            if (notifications.length) window.dispatchEvent(new CustomEvent('sys-notify', { detail: notifications }));
        },

        async finish() {
            if (!this.canFinish || this.finishing) return;
            this.finishing = true;
            this.done = true;
            this.releaseWakeLock();
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
            this.releaseWakeLock();
            this.store.clearActive();
            window.location.href = '/';
        },
    };
}
