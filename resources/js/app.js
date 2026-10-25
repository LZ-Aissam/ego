import '@fontsource/space-grotesk/400.css';
import '@fontsource/space-grotesk/500.css';
import '@fontsource/space-grotesk/700.css';
import '@fontsource/jetbrains-mono/500.css';
import '@fontsource/jetbrains-mono/700.css';
import '@fontsource/jetbrains-mono/800.css';
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import workout from './workout/component';
import sysNotifications from './ui/notifications';
import exerciseDemo from './demos/player';
import { initCountUps } from './ui/count-up';
import { closeStale, createStore } from './workout/store';
import { collectNotifications, flushPending, sendWorkout } from './workout/sync';

window.Alpine = Alpine;
Alpine.data('workout', workout);
Alpine.data('sysNotifications', sysNotifications);
Alpine.data('exerciseDemo', exerciseDemo);
Livewire.start();
document.addEventListener('livewire:navigated', () => initCountUps());

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
}

// Présent uniquement sur les pages d'un utilisateur connecté (layout app).
const userId = () => document.body.dataset.userId;
const userStore = () => createStore(window.localStorage, userId());

// Après une réinitialisation : oublier la séance en cours et les séances en attente de ce joueur sur ce téléphone.
if (document.body.dataset.clearLocal && userId()) {
    try {
        const store = userStore();
        store.clearActive();
        for (const p of store.pending()) store.removePending(p.client_id);
    } catch {
        // Stockage indisponible : rien à oublier.
    }
}

function updateOfflineBanner() {
    const banner = document.getElementById('offline-banner');
    if (banner) banner.hidden = navigator.onLine;
}

function syncPending() {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!token || !userId() || !navigator.onLine) return;
    flushPending(userStore(), (p) => sendWorkout(p, { token })).then((result) => {
        // Séance faite hors ligne puis envoyée en arrière-plan : on affiche quand même ses récompenses.
        const notifications = collectNotifications(result);
        if (notifications.length) window.dispatchEvent(new CustomEvent('sys-notify', { detail: notifications }));
    });
}

// Séance oubliée ouverte depuis plus de 6 h : terminée à sa dernière série, puis envoyée avec les autres.
function closeStaleWorkout() {
    if (!userId()) return;
    try {
        closeStale(userStore(), Date.now());
    } catch {
        // Stockage indisponible : rien à clore.
    }
}

function showResumeBanner() {
    const banner = document.getElementById('resume-banner');
    if (banner && userId() && userStore().loadActive()) banner.hidden = false;
}

// À la déconnexion, on vide le cache des pages pour que le compte suivant ne voie pas celles-ci hors ligne.
// Les séances non envoyées restent rangées sous l'identifiant de leur propriétaire.
document.addEventListener('submit', (event) => {
    if (event.target.matches('form[action$="/logout"]') && 'caches' in window) {
        caches.delete('ego-v2');
    }
});

window.addEventListener('online', () => { updateOfflineBanner(); syncPending(); });
window.addEventListener('offline', updateOfflineBanner);
document.addEventListener('DOMContentLoaded', () => {
    initCountUps();
    updateOfflineBanner();
    closeStaleWorkout();
    showResumeBanner();
    if (!document.getElementById('workout-boot')) syncPending();
});
