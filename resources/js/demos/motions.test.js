import { describe, expect, it } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import { MOTIONS, MOTION_CREDIT, demoSource } from './motions';
import { exerciseIds } from '../vendor/workout-motion/h2.js';
import { DEMOS } from './rig';

const ROOT = path.resolve(__dirname, '../../..');
const catalog = fs.readFileSync(path.join(ROOT, 'database/data/exercises.php'), 'utf8');

describe('animations workout-motion', () => {
    it('relie une cinquantaine d\'exercices du catalogue à une animation qui existe', () => {
        expect(Object.keys(MOTIONS).length).toBeGreaterThanOrEqual(45);
        for (const [slug, motion] of Object.entries(MOTIONS)) {
            expect(catalog, slug).toContain(`['${slug}',`);
            expect(exerciseIds, `${slug} → ${motion.id}`).toContain(motion.id);
        }
    });

    it('anime tout le programme d\'Aïssam en détail, sauf les poignets', () => {
        for (const slug of ['bench-press', 'overhead-press', 'barbell-row', 'pull-up', 'chin-up', 'dips', 'barbell-curl', 'hammer-curl',
            'lateral-raise', 'push-press', 'face-pull', 'chest-supported-row']) {
            expect(demoSource(slug).kind, slug).toBe('motion');
        }
        expect(demoSource('wrist-curl').kind).toBe('rig');
        // Animation continue indisponible : le dessin, puis la silhouette.
        expect(demoSource('crunch', false).kind).toBe('frames');
        expect(demoSource('push-press', false).kind).toBe('rig');
    });

    it('prévient quand l\'animation montre une variante proche', () => {
        expect(demoSource('push-press').note).toContain('jambes');
        expect(demoSource('chin-up').note.toLowerCase()).toContain('paumes vers toi');
        expect(demoSource('bench-press').note).toBe('');
    });

    it('cite les auteurs et la licence non commerciale', () => {
        expect(MOTION_CREDIT).toContain('workout-motion');
        expect(MOTION_CREDIT).toContain('PolyForm Noncommercial 1.0.0');
    });

    it('garde une silhouette pour chaque exercice, en dernier recours', () => {
        for (const slug of Object.keys(MOTIONS)) {
            expect(DEMOS[slug], slug).toBeDefined();
        }
    });
});

describe('animation continue partout où elle montre le geste', () => {
    it('anime tous les exercices du catalogue sauf ceux qui n\'ont aucun équivalent', () => {
        const slugs = [...catalog.matchAll(/^\s*\['([a-z0-9-]+)',/gm)].map((m) => m[1]);
        const without = slugs.filter((slug) => demoSource(slug).kind !== 'motion').sort();

        expect(without).toEqual(['cable-crunch', 'crunch', 'hanging-leg-raise', 'nordic-curl', 'sissy-squat', 'wrist-curl', 'wrist-extension']);
    });

    it('explique la différence quand l\'animation est une variante', () => {
        for (const slug of ['reverse-curl', 'skull-crusher', 'push-up', 'diamond-push-up', 'bench-dips', 'incline-bench-press', 'clap-push-up', 'broad-jump', 'jump-squat']) {
            expect(demoSource(slug).note, slug).not.toBe('');
        }
    });
});
