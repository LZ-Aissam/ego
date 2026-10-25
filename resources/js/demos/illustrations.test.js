import { describe, expect, it } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import { CREDIT, ILLUSTRATED, framesOf } from './illustrations';
import { DEMOS } from './rig';
import { crossfade, illustrationMarkup } from './player';
import { assertDrawingOnly, recolor } from '../../../scripts/everkinetic/import.mjs';

const ROOT = path.resolve(__dirname, '../../..');
const catalog = fs.readFileSync(path.join(ROOT, 'database/data/exercises.php'), 'utf8');

describe('illustrations Everkinetic', () => {
    it('illustre une trentaine d\'exercices du catalogue, chacun avec ses deux positions sur le disque', () => {
        expect(ILLUSTRATED.size).toBeGreaterThanOrEqual(30);
        for (const slug of ILLUSTRATED) {
            expect(catalog, slug).toContain(`['${slug}',`);
            expect(DEMOS[slug], slug).toBeDefined();
            for (const frame of framesOf(slug)) {
                expect(fs.existsSync(path.join(ROOT, 'public', frame)), frame).toBe(true);
            }
        }
    });

    it('laisse la silhouette animée aux exercices sans dessin fidèle', () => {
        for (const slug of ['overhead-press', 'barbell-row', 'chin-up', 'box-jump', 'wrist-curl', 'face-pull']) {
            expect(framesOf(slug), slug).toBeNull();
        }
    });

    it('n\'a dans chaque dessin que du trait bleu glacé, sans rien d\'exécutable', () => {
        for (const slug of ILLUSTRATED) {
            for (const frame of framesOf(slug)) {
                const svg = fs.readFileSync(path.join(ROOT, 'public', frame), 'utf8');
                expect(svg).toContain('#5ad8ff');
                expect(svg).not.toMatch(/<script|\son\w+=|href=|#333/i);
            }
        }
    });

    it('cite l\'auteur et la licence', () => {
        expect(CREDIT).toBe('Illustration : Everkinetic (Greg Priday) · CC BY-SA 4.0');
    });
});

describe('garde-fous de l\'import', () => {
    const drawing = '<svg viewBox="0 0 10 10"><g fill="#FFF"><path d="M0 0h10v10H0z"/></g><g fill="#40413F"><path d="M1 1h2v2H1z"/></g></svg>';

    it('accepte un dessin et le recolore : fond transparent, trait bleu glacé', () => {
        expect(() => assertDrawingOnly(drawing, 'test')).not.toThrow();
        expect(recolor(drawing)).toBe(drawing.replace('fill="#FFF"', 'fill="none"').replace('fill="#40413F"', 'fill="#5ad8ff"'));
    });

    it.each([
        ['un script', '<svg><script>alert(1)</script></svg>'],
        ['un gestionnaire d\'événement', '<svg><path onload="alert(1)" d="M0 0"/></svg>'],
        ['un lien', '<svg><image href="https://exemple.test/x.png"/></svg>'],
        ['une couleur inattendue', '<svg><g fill="#ff0000"><path d="M0 0"/></g></svg>'],
    ])('refuse %s', (_, svg) => {
        expect(() => assertDrawingOnly(svg, 'test')).toThrow();
    });
});

describe('lecteur avec dessins', () => {
    it('pose les deux positions dans le cadre, la seconde invisible au départ', () => {
        const markup = illustrationMarkup(['/demos/everkinetic/bench-press-0.svg', '/demos/everkinetic/bench-press-1.svg']);

        expect(markup.match(/<image /g)).toHaveLength(2);
        expect(markup).toContain('href="/demos/everkinetic/bench-press-0.svg"');
        expect(markup).toMatch(/data-frame="1"[^>]*opacity="0"/);
    });

    it('passe d\'une position à l\'autre en fondu, au tempo du mouvement', () => {
        expect(crossfade(0)).toEqual([1, 0]);
        expect(crossfade(1)).toEqual([0, 1]);
        expect(crossfade(0.25)).toEqual([0.75, 0.25]);
    });
});
