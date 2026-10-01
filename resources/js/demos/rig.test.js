import { describe, expect, it } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import { DEMOS, VIEWBOX, phaseAt, sceneAt } from './rig';

const catalog = fs.readFileSync(path.resolve(__dirname, '../../../database/data/exercises.php'), 'utf8');
const slugs = [...catalog.matchAll(/^\s*\['([a-z0-9-]+)',/gm)].map((m) => m[1]);
const STEPS = [0, 0.1, 0.25, 0.4, 0.5, 0.6, 0.75, 0.9, 1];
const [minX, minY, width, height] = VIEWBOX;

const allPoints = (scene) => [
    ...Object.values(scene.chains).flat(),
    scene.head,
    ...scene.props.map((p) => p.at),
];
const dist = (a, b) => Math.hypot(a[0] - b[0], a[1] - b[1]);

describe('démos des exercices', () => {
    it('couvre tout le catalogue', () => {
        expect(slugs.length).toBeGreaterThan(50);
        expect(slugs.filter((s) => !DEMOS[s])).toEqual([]);
    });

    it.each(slugs)('%s : silhouette dans le cadre, coordonnées valides', (slug) => {
        for (const t of STEPS) {
            for (const [x, y] of allPoints(sceneAt(slug, t))) {
                expect(Number.isFinite(x) && Number.isFinite(y)).toBe(true);
                expect(x).toBeGreaterThanOrEqual(minX);
                expect(x).toBeLessThanOrEqual(minX + width);
                expect(y).toBeGreaterThanOrEqual(minY);
                expect(y).toBeLessThanOrEqual(minY + height);
            }
        }
    });

    it.each(slugs)('%s : les membres gardent leur longueur pendant le mouvement', (slug) => {
        const start = sceneAt(slug, 0).chains;
        for (const t of STEPS) {
            const chains = sceneAt(slug, t).chains;
            for (const [name, points] of Object.entries(chains)) {
                points.slice(1).forEach((p, i) => {
                    expect(Math.abs(dist(points[i], p) - dist(start[name][i], start[name][i + 1])), `${name}[${i}] à t=${t}`).toBeLessThan(0.8);
                });
            }
        }
    });

    it.each(slugs)('%s : le mouvement se voit', (slug) => {
        const a = allPoints(sceneAt(slug, 0));
        const b = allPoints(sceneAt(slug, 1));
        expect(Math.max(...a.map((p, i) => dist(p, b[i])))).toBeGreaterThan(8);
    });

    it('donne la phase et sa progression selon le temps', () => {
        const [t0, label0] = phaseAt('back-squat', 0);
        const [tBottom] = phaseAt('back-squat', 2100);
        expect(t0).toBe(0);
        expect(label0).toBe('Descente');
        expect(tBottom).toBe(1);
    });
});
