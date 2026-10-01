import { describe, expect, it } from 'vitest';
import { VIEWBOX } from './rig';
import { frameSvg } from './player';

describe('cadre des démonstrations', () => {
    it('pose « viewBox » avec la bonne casse, sinon le SVG ignore le cadre et la silhouette sort de l\'écran', () => {
        const attrs = {};
        const svg = { setAttribute: (name, value) => { attrs[name] = value; } };

        frameSvg(svg);

        expect(attrs).toEqual({ viewBox: VIEWBOX.join(' '), preserveAspectRatio: 'xMidYMid meet' });
    });
});
