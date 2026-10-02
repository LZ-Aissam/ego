// Dessin d'une pose en balises SVG, partagé par le lecteur de l'app et la planche de contrôle.
const ICE = '#5ad8ff';
const DEEP = '#3d6bff';
const MIST = '#9bb7c4';
const VOID = '#04060a';
const RAD = Math.PI / 180;
const HEAD_R = 8;

const n = (v) => (Math.round(v * 10) / 10).toString();
const pts = (list) => list.map((p) => `${n(p[0])},${n(p[1])}`).join(' ');
const esc = (text) => String(text).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

function fixture(f) {
    return f.type === 'rect'
        ? `<rect x="${n(f.x)}" y="${n(f.y)}" width="${n(f.w)}" height="${n(f.h)}" rx="1.5"/>`
        : `<line x1="${n(f.from[0])}" y1="${n(f.from[1])}" x2="${n(f.to[0])}" y2="${n(f.to[1])}"/>`;
}

function prop(p) {
    const at = `transform="translate(${n(p.at[0])} ${n(p.at[1])})"`;
    if (p.type === 'plate') {
        return `<g ${at}><circle r="${p.r}" stroke-width="3" fill="rgba(90,216,255,.12)"/><circle r="2.5" fill="${ICE}" stroke="none"/></g>`;
    }
    if (p.type === 'dumbbell') {
        return `<g ${at}><rect x="-8" y="-3.5" width="16" height="7" rx="1.5" stroke-width="2.5" fill="rgba(90,216,255,.2)"/><line x1="-8" y1="-5" x2="-8" y2="5" stroke-width="3"/><line x1="8" y1="-5" x2="8" y2="5" stroke-width="3"/></g>`;
    }
    return `<g ${at}><circle r="${p.r}" stroke-width="2.5" fill="${VOID}"/></g>`;
}

/** Encart « Prise », sous le sol : une main stylisée (paume en bleu plein) et le libellé. */
function grip(g) {
    const hand = (cx, rotate) => `<g transform="translate(${cx} 12) rotate(${rotate})"><circle r="4.5" fill="${VOID}" stroke="${ICE}" stroke-width="1.5"/><path d="M -4.5 0 A 4.5 4.5 0 0 1 4.5 0 Z" fill="${ICE}" stroke="none"/></g>`;
    const icon = {
        pronation: hand(-98, 0),
        supination: hand(-98, 180),
        curl: hand(-98, 0),
        reverse: hand(-98, 180),
        neutral: hand(-103, -90) + hand(-93, 90),
        arnold: hand(-98, 180 + g.turn),
        diamond: `<path d="M -98 7 L -93 12 L -98 17 L -103 12 Z" fill="none" stroke="${ICE}" stroke-width="1.5"/>`,
        wide: `<line x1="-104" y1="12" x2="-92" y2="12" stroke="${MIST}" stroke-width="1.5"/><circle cx="-107" cy="12" r="2.5" fill="${ICE}"/><circle cx="-89" cy="12" r="2.5" fill="${ICE}"/>`,
    }[g.kind] ?? '';

    return `<g data-part="grip">${icon}<text x="-84" y="14.5" fill="${MIST}" font-size="7" font-family="JetBrains Mono, monospace">Prise : ${esc(g.label)}</text></g>`;
}

/** @param {{chains: object, head: number[], props: array, fixtures: array, guides: array, grip: object|null, gaze: number|null}} scene */
export function sceneMarkup(scene, glowId = 'demo-glow') {
    const guides = (scene.guides ?? []).map((g) =>
        `<line x1="${n(g.from[0])}" y1="${n(g.from[1])}" x2="${n(g.to[0])}" y2="${n(g.to[1])}" stroke="${ICE}" stroke-opacity=".35" stroke-dasharray="3 4"/>`
        + `<text x="${n(g.from[0] + 4)}" y="${n(g.from[1] + 8)}" fill="${MIST}" font-size="7" font-family="JetBrains Mono, monospace">${esc(g.label)}</text>`).join('');

    const fixtures = `<g stroke="${DEEP}" stroke-opacity=".8" stroke-width="2" fill="rgba(61,107,255,.15)">${scene.fixtures.map(fixture).join('')}</g>`;

    const chains = Object.entries(scene.chains).map(([name, list]) =>
        `<polyline data-part="${name}" points="${pts(list)}"${name.startsWith('arm') || name === 'support' ? ' stroke-width="4"' : ''}/>`).join('');
    const hands = Object.entries(scene.chains).filter(([name]) => name.startsWith('arm') || name === 'support')
        .map(([, list]) => `<circle data-part="hand" cx="${n(list.at(-1)[0])}" cy="${n(list.at(-1)[1])}" r="3.4" fill="${ICE}" stroke="none"/>`).join('');

    const [hx, hy] = scene.head;
    const gaze = scene.gaze === null || scene.gaze === undefined ? '' : (() => {
        const dx = Math.cos(scene.gaze * RAD), dy = -Math.sin(scene.gaze * RAD);
        return `<line data-part="gaze" x1="${n(hx + dx * (HEAD_R - 2))}" y1="${n(hy + dy * (HEAD_R - 2))}" x2="${n(hx + dx * (HEAD_R + 4))}" y2="${n(hy + dy * (HEAD_R + 4))}" stroke-width="2.5"/>`;
    })();

    const body = `<g filter="url(#${glowId})" stroke="${ICE}" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" fill="none">`
        + chains + `<circle cx="${n(hx)}" cy="${n(hy)}" r="${HEAD_R}" fill="${VOID}"/>` + gaze + scene.props.map(prop).join('') + hands + '</g>';

    return guides + fixtures + body + (scene.grip ? grip(scene.grip) : '');
}
