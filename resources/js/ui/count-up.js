export function easeOutCubic(t) {
    return 1 - Math.pow(1 - t, 3);
}

export function formatNumber(n, decimals = 0) {
    return n.toLocaleString('fr-FR', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

export function countUp(el, { duration = 900, reducedMotion = false } = {}) {
    const target = parseFloat(el.dataset.countUp);
    if (!Number.isFinite(target)) return;
    const decimals = parseInt(el.dataset.decimals ?? '0', 10);
    const suffix = el.dataset.suffix ?? '';
    const render = (value) => { el.textContent = formatNumber(value, decimals) + suffix; };

    if (reducedMotion || typeof requestAnimationFrame !== 'function') {
        render(target);
        return;
    }

    const start = performance.now();
    const frame = (now) => {
        const t = Math.min(1, (now - start) / duration);
        render(target * easeOutCubic(t));
        if (t < 1) requestAnimationFrame(frame);
    };
    requestAnimationFrame(frame);
}

export function initCountUps(root = document) {
    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
    root.querySelectorAll('[data-count-up]').forEach((el) => countUp(el, { reducedMotion }));
}
