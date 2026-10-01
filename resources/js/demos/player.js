// Lecteur Alpine des démonstrations : dessine la silhouette d'un exercice et l'anime au tempo réel.
import { VIEWBOX, phaseAt, sceneAt } from './rig';

const NS = 'http://www.w3.org/2000/svg';
const ICE = '#5ad8ff';
const DEEP = '#3d6bff';
const MIST = '#9bb7c4';
const VOID = '#04060a';
let uid = 0;

function el(tag, attrs = {}, parent = null) {
    const node = document.createElementNS(NS, tag);
    for (const [k, v] of Object.entries(attrs)) node.setAttribute(k, v);
    if (parent) parent.append(node);
    return node;
}

const points = (pts) => pts.map((p) => `${p[0].toFixed(1)},${p[1].toFixed(1)}`).join(' ');

export default function exerciseDemo(initialSlug = null) {
    return {
        slug: initialSlug,
        phase: '',
        note: '',
        speed: 1,
        paused: false,
        viewBox: VIEWBOX.join(' '),
        clock: 0,
        last: null,
        raf: null,
        nodes: null,

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
            svg.replaceChildren();
            const glow = `demo-glow-${++uid}`;
            const filter = el('filter', { id: glow, x: '-50%', y: '-50%', width: '200%', height: '200%' }, el('defs', {}, svg));
            el('feGaussianBlur', { stdDeviation: '2.2', result: 'b' }, filter);
            const merge = el('feMerge', {}, filter);
            el('feMergeNode', { in: 'b' }, merge);
            el('feMergeNode', { in: 'SourceGraphic' }, merge);

            const [vx, , vw] = VIEWBOX;
            el('line', { x1: vx, y1: 1.5, x2: vx + vw, y2: 1.5, stroke: DEEP, 'stroke-opacity': '.5' }, svg);

            const first = sceneAt(this.slug, 0);
            this.note = first.note;
            for (const g of first.guides) {
                el('line', { x1: g.from[0], y1: g.from[1], x2: g.to[0], y2: g.to[1], stroke: ICE, 'stroke-opacity': '.35', 'stroke-dasharray': '3 4' }, svg);
                const label = el('text', { x: g.from[0] + 4, y: g.from[1] + 8, fill: MIST, 'font-size': '7', 'font-family': 'JetBrains Mono, monospace' }, svg);
                label.textContent = g.label;
            }

            const fixtures = el('g', { stroke: DEEP, 'stroke-opacity': '.8', 'stroke-width': 2, fill: 'rgba(61,107,255,.15)' }, svg);
            const body = el('g', { filter: `url(#${glow})`, stroke: ICE, 'stroke-width': 5, 'stroke-linecap': 'round', 'stroke-linejoin': 'round', fill: 'none' }, svg);
            this.nodes = {
                fixtures: first.fixtures.map((f) => el(f.type === 'rect' ? 'rect' : 'line', {}, fixtures)),
                chains: Object.keys(first.chains).map((name) => [name, el('polyline', name.startsWith('arm') ? { 'stroke-width': 4 } : {}, body)]),
                head: el('circle', { r: 8, fill: VOID }, body),
                props: first.props.map((p) => this.propNode(p, body)),
            };
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

        propNode(prop, parent) {
            const g = el('g', {}, parent);
            if (prop.type === 'plate') {
                el('circle', { r: prop.r, 'stroke-width': 3, fill: 'rgba(90,216,255,.12)' }, g);
                el('circle', { r: 2.5, fill: ICE, stroke: 'none' }, g);
            } else if (prop.type === 'dumbbell') {
                el('rect', { x: -7, y: -3, width: 14, height: 6, rx: 1.5, 'stroke-width': 2.5, fill: 'rgba(90,216,255,.2)' }, g);
            } else {
                el('circle', { r: prop.r, 'stroke-width': 2.5, fill: VOID }, g);
            }
            return g;
        },

        draw() {
            if (!this.nodes) return;
            const [t, label] = phaseAt(this.slug, this.clock);
            const s = sceneAt(this.slug, t);
            this.phase = label;
            s.fixtures.forEach((f, i) => {
                const node = this.nodes.fixtures[i];
                if (!node) return;
                if (f.type === 'rect') {
                    node.setAttribute('x', f.x); node.setAttribute('y', f.y); node.setAttribute('width', f.w); node.setAttribute('height', f.h);
                } else {
                    node.setAttribute('x1', f.from[0]); node.setAttribute('y1', f.from[1]); node.setAttribute('x2', f.to[0]); node.setAttribute('y2', f.to[1]);
                }
            });
            for (const [name, node] of this.nodes.chains) node.setAttribute('points', points(s.chains[name]));
            this.nodes.head.setAttribute('cx', s.head[0].toFixed(1));
            this.nodes.head.setAttribute('cy', s.head[1].toFixed(1));
            s.props.forEach((p, i) => this.nodes.props[i]?.setAttribute('transform', `translate(${p.at[0].toFixed(1)} ${p.at[1].toFixed(1)})`));
        },
    };
}
