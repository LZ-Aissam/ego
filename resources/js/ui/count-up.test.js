import { describe, expect, it } from 'vitest';
import { countUp, easeOutCubic, formatNumber } from './count-up';

describe('easeOutCubic', () => {
    it('part de 0, arrive à 1 et décélère', () => {
        expect(easeOutCubic(0)).toBe(0);
        expect(easeOutCubic(1)).toBe(1);
        expect(easeOutCubic(0.5)).toBeGreaterThan(0.5);
    });
});

describe('formatNumber', () => {
    it('formate à la française', () => {
        expect(formatNumber(12480).replace(/\s/g, ' ')).toBe('12 480');
        expect(formatNumber(62.5, 1)).toBe('62,5');
    });
});

describe('countUp', () => {
    it('affiche directement la valeur finale si les animations sont réduites', () => {
        const el = { dataset: { countUp: '4250', suffix: ' kg' }, textContent: '' };
        countUp(el, { reducedMotion: true });
        expect(el.textContent.replace(/\s/g, ' ')).toBe('4 250 kg');
    });
});
