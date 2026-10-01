import { describe, expect, it } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import { DEMOS, sceneAt } from './rig';
import { sceneMarkup } from './markup';

const catalog = fs.readFileSync(path.resolve(__dirname, '../../../database/data/exercises.php'), 'utf8');
const slugs = [...catalog.matchAll(/^\s*\['([a-z0-9-]+)',/gm)].map((m) => m[1]);
const signature = (slug) => JSON.stringify([0.2, 0.5, 0.8].map((t) => sceneMarkup(sceneAt(slug, t))));

describe('chaque exercice a sa propre animation', () => {
    it('deux exercices différents ne montrent jamais exactement la même chose', () => {
        const seen = new Map();
        const twins = [];
        for (const slug of slugs) {
            const sig = signature(slug);
            if (seen.has(sig)) twins.push(`${seen.get(sig)} = ${slug}`);
            seen.set(sig, slug);
        }
        expect(twins).toEqual([]);
    });

    it('montre la largeur de prise des tractions en vue de face', () => {
        const width = (slug) => {
            const { armR, armL } = sceneAt(slug, 0).chains;
            return armR.at(-1)[0] - armL.at(-1)[0];
        };
        expect(sceneAt('pull-up', 0).note).toBe('Vue de face');
        expect(width('pull-up')).toBeGreaterThan(width('chin-up') + 15);
    });

    it.each([
        ['pull-up', 'Paumes vers l’avant'],
        ['chin-up', 'Paumes vers toi'],
        ['hammer-curl', 'Paumes face à face'],
        ['dumbbell-curl', 'Paumes vers le haut'],
        ['diamond-push-up', 'Mains en losange'],
        ['push-up', 'Mains un peu plus larges que les épaules'],
        ['arnold-press', 'Paumes vers toi → vers l’avant'],
    ])('%s : encart « Prise » « %s »', (slug, label) => {
        expect(sceneMarkup(sceneAt(slug, 0.5))).toContain(label);
    });

    it('fait tourner les paumes pendant le développé Arnold', () => {
        expect(sceneAt('arnold-press', 0).grip.turn).toBe(0);
        expect(sceneAt('arnold-press', 1).grip.turn).toBe(180);
    });
});

describe('détails des silhouettes', () => {
    it.each(Object.keys(DEMOS))('%s : des mains au bout des bras', (slug) => {
        const scene = sceneAt(slug, 0.5);
        const arms = Object.keys(scene.chains).filter((n) => n.startsWith('arm') || n === 'support');
        expect(arms.length).toBeGreaterThan(0);
        expect((sceneMarkup(scene).match(/data-part="hand"/g) ?? []).length).toBe(arms.length);
    });

    it('indique le regard de profil : devant debout, vers le plafond allongé', () => {
        expect(Math.abs(sceneAt('overhead-press', 0).gaze)).toBeLessThan(10);
        expect(Math.round(sceneAt('bench-press', 0).gaze)).toBeGreaterThan(70);
        expect(sceneMarkup(sceneAt('back-squat', 0.5))).toContain('data-part="gaze"');
        expect(sceneAt('lateral-raise', 0.5).gaze).toBeNull();
    });
});
