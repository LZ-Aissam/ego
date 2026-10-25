// Lecteur Alpine des démonstrations : dessin Everkinetic en fondu entre ses deux positions quand il existe,
// sinon silhouette animée ; dans les deux cas au tempo réel de l'exercice.
import { VIEWBOX, phaseAt, sceneAt } from './rig.js';
import { sceneMarkup } from './markup.js';
import { CREDIT, framesOf } from './illustrations.js';

const DEEP = '#3d6bff';
let uid = 0;

/** Cadre de l'animation, posé en JS : un `:viewBox` Alpine dans le HTML devient « viewbox », que le SVG ignore. */
export function frameSvg(svg) {
    svg.setAttribute('viewBox', VIEWBOX.join(' '));
    svg.setAttribute('preserveAspectRatio', 'xMidYMid meet');
}

/** Fond fixe d'une démonstration : filtre de lueur, sol, et le calque redessiné à chaque image. */
export function stageMarkup(glowId) {
    const [vx, , vw] = VIEWBOX;
    return `<defs><filter id="${glowId}" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="2.2" result="b"/>`
        + '<feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter></defs>'
        + `<line x1="${vx}" y1="1.5" x2="${vx + vw}" y2="1.5" stroke="${DEEP}" stroke-opacity=".5"/><g data-layer="scene"></g>`;
}

/** Les deux positions d'un dessin, superposées dans le cadre ; la seconde apparaît en fondu. */
export function illustrationMarkup(frames) {
    const [x, y, w, h] = VIEWBOX;
    return frames.map((href, i) => `<image data-frame="${i}" href="${href}" x="${x}" y="${y}" width="${w}" height="${h}" preserveAspectRatio="xMidYMid meet" opacity="${i === 0 ? 1 : 0}"/>`).join('');
}

/** Opacités des deux positions pour l'avancement t du mouvement (0 : départ, 1 : autre bout). */
export function crossfade(t) {
    return [1 - t, t];
}

export default function exerciseDemo(initialSlug = null) {
    return {
        slug: initialSlug,
        phase: '',
        note: '',
        speed: 1,
        paused: false,
        clock: 0,
        last: null,
        raf: null,
        layer: null,
        glowId: '',
        credit: '',
        frames: null,

        init() {
            this.paused = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
            if (this.slug) this.mount();
            this.$watch('slug', () => this.mount());
        },

        destroy() {
            cancelAnimationFrame(this.raf);
        },

        setSpeed(value) {
            this.speed = value;
            this.paused = false;
        },

        mount() {
            cancelAnimationFrame(this.raf);
            const svg = this.$refs.svg;
            if (!svg || !this.slug) return;
            frameSvg(svg);
            const frames = framesOf(this.slug);
            this.credit = frames ? CREDIT : '';
            if (frames) {
                svg.innerHTML = illustrationMarkup(frames);
                this.frames = [...svg.querySelectorAll('[data-frame]')];
                this.layer = null;
                this.note = '';
            } else {
                this.frames = null;
                this.glowId = `demo-glow-${++uid}`;
                svg.innerHTML = stageMarkup(this.glowId);
                this.layer = svg.querySelector('[data-layer="scene"]');
                this.note = sceneAt(this.slug, 0).note;
            }
            this.clock = 0;
            this.last = null;
            const frame = (now) => {
                if (this.last !== null && !this.paused) this.clock += (now - this.last) * this.speed;
                this.last = now;
                this.draw();
                this.raf = requestAnimationFrame(frame);
            };
            this.raf = requestAnimationFrame(frame);
        },

        draw() {
            if (!this.layer && !this.frames) return;
            const [t, label] = phaseAt(this.slug, this.clock);
            this.phase = label;
            if (this.frames) {
                crossfade(t).forEach((opacity, i) => this.frames[i].setAttribute('opacity', opacity.toFixed(3)));
                return;
            }
            this.layer.innerHTML = sceneMarkup(sceneAt(this.slug, t), this.glowId);
        },
    };
}
