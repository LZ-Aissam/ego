export function normalize(item) {
    if (!item || typeof item.title !== 'string' || item.title === '') return null;
    return {
        type: item.type ?? 'info',
        title: item.title,
        lines: Array.isArray(item.lines) ? item.lines.map(String) : [],
        variant: item.variant === 'alert' ? 'alert' : 'default',
        highlight: item.highlight ?? null,
    };
}

export function enqueue(list, items) {
    if (!Array.isArray(items)) return list;
    return [...list, ...items.map(normalize).filter(Boolean)];
}

export function dismiss(list) {
    return list.slice(1);
}

export function buzz(nav = globalThis.navigator) {
    if (typeof nav?.vibrate !== 'function') return false;
    nav.vibrate([60, 40, 60]);
    return true;
}

export default function sysNotifications() {
    return {
        items: [],

        init() {
            const data = document.getElementById('sys-notifications-data');
            this.push(data ? JSON.parse(data.textContent) : []);
            window.addEventListener('sys-notify', (event) => this.push(event.detail));
        },

        get current() {
            return this.items[0] ?? null;
        },

        push(items) {
            const wasEmpty = this.items.length === 0;
            this.items = enqueue(this.items, items);
            if (wasEmpty && this.items.length) buzz();
        },

        dismiss() {
            this.items = dismiss(this.items);
            if (this.items.length) buzz();
        },
    };
}
