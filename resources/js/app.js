import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import workout from './workout/component';
import { createStore } from './workout/store';
import { flushPending, sendWorkout } from './workout/sync';

window.Alpine = Alpine;
Alpine.data('workout', workout);
Livewire.start();

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
}

function updateOfflineBanner() {
    const banner = document.getElementById('offline-banner');
    if (banner) banner.hidden = navigator.onLine;
}

function syncPending() {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!token || !document.body.dataset.auth || !navigator.onLine) return;
    flushPending(createStore(window.localStorage), (p) => sendWorkout(p, { token }));
}

function showResumeBanner() {
    const banner = document.getElementById('resume-banner');
    if (banner && createStore(window.localStorage).loadActive()) banner.hidden = false;
}

window.addEventListener('online', () => { updateOfflineBanner(); syncPending(); });
window.addEventListener('offline', updateOfflineBanner);
document.addEventListener('DOMContentLoaded', () => {
    updateOfflineBanner();
    showResumeBanner();
    if (!document.getElementById('workout-boot')) syncPending();
});
