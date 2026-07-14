import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Flag } from '../primitives.jsx';

// ── Widget: nuclide_lab ──────────────────────────────────────────────────────
// Bespoke 5.1.2 hero (immersive 2D). The nucleus, nuclide notation and isotopes.
//   BUILD    — add or remove protons and neutrons and watch the proton number Z,
//              the nucleon number A = Z + N, the neutron number N = A − Z, the
//              element symbol and the nuclide notation (A over Z, then X) update.
//   ISOTOPES — the same element (same Z) with different numbers of neutrons: three
//              isotopes side by side (same chemistry, different mass).
//   IONS     — an atom loses an electron to form a positive ion, or gains one to
//              form a negative ion.
// config: { mode, z, n, element }

const ELEMENTS = [
    ['n', 'neutron'], ['H', 'hydrogen'], ['He', 'helium'], ['Li', 'lithium'], ['Be', 'beryllium'],
    ['B', 'boron'], ['C', 'carbon'], ['N', 'nitrogen'], ['O', 'oxygen'], ['F', 'fluorine'],
    ['Ne', 'neon'], ['Na', 'sodium'], ['Mg', 'magnesium'], ['Al', 'aluminium'], ['Si', 'silicon'],
    ['P', 'phosphorus'], ['S', 'sulfur'], ['Cl', 'chlorine'], ['Ar', 'argon'], ['K', 'potassium'], ['Ca', 'calcium'],
];
const sym = (z) => (ELEMENTS[z] ? ELEMENTS[z][0] : 'X');
const name = (z) => (ELEMENTS[z] ? ELEMENTS[z][1] : 'unknown');
// isotope sets: [Z, [N1,N2,N3], elementName]
const ISOTOPE_SETS = {
    carbon: [6, [6, 7, 8]], hydrogen: [1, [0, 1, 2]], oxygen: [8, [8, 9, 10]], chlorine: [17, [18, 20, 21]],
};

export default function NuclideLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['build', 'isotopes', 'ions'].includes(config.mode) ? config.mode : 'build');
    const [z, setZ] = useState(typeof config.z === 'number' ? config.z : 6);
    const [n, setN] = useState(typeof config.n === 'number' ? config.n : 6);
    const [iso, setIso] = useState(ISOTOPE_SETS[config.element] ? config.element : 'carbon');
    const [electrons, setElectrons] = useState(6); // ions mode: electron count (neutral = z)
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, z, n, iso, electrons };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, z: st.current.z, n: st.current.n, element: st.current.iso }),
            setState: (s) => {
                if (['build', 'isotopes', 'ions'].includes(s?.mode)) setMode(s.mode);
                if (typeof s?.z === 'number') setZ(Math.max(1, Math.min(20, s.z)));
                if (typeof s?.n === 'number') setN(Math.max(0, Math.min(24, s.n)));
                if (ISOTOPE_SETS[s?.element]) setIso(s.element);
            },
        });
    }, [onReady]); // eslint-disable-line

    const A = z + n;

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf, t0 = performance.now();
        const draw = (now) => {
            const amb = (now - t0) / 1000;
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight;
            if (!w || !h) { raf = requestAnimationFrame(draw); return; }
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const acc = cssVar('--ok', '#34D399'), amber = '#FBBF24', cyan = '#38BDF8', rose = '#FB7185';
            const S = st.current;
            const bg = ctx.createLinearGradient(0, 0, 0, h);
            bg.addColorStop(0, '#12161d'); bg.addColorStop(1, '#0b0e14');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);
            ctx.textAlign = 'center';

            // draw a nucleus of np protons + nn neutrons around (cx,cy), radius scale rs
            const drawNucleus = (cx, cy, np, nn, rs) => {
                const total = np + nn;
                const packR = rs * Math.max(1, Math.sqrt(total) * 0.5);
                const parts = [];
                for (let i = 0; i < total; i++) parts.push(i < np);
                // spiral-ish packing
                parts.forEach((isP, i) => {
                    const ang = i * 2.399, rr = rs * 0.9 * Math.sqrt(i) * 0.9;
                    const px = cx + Math.cos(ang + amb * 0.15) * rr, py = cy + Math.sin(ang + amb * 0.15) * rr;
                    ctx.fillStyle = isP ? rose : '#9aa4b2'; ctx.beginPath(); ctx.arc(px, py, rs, 0, Math.PI * 2); ctx.fill();
                    if (isP) { ctx.fillStyle = '#0b0e14'; ctx.font = `700 ${Math.round(rs)}px Inter`; ctx.fillText('+', px, py + rs * 0.35); }
                });
                return packR;
            };

            if (S.mode === 'build') {
                const cx = w * 0.30, cy = h * 0.44;
                drawNucleus(cx, cy, S.z, S.n, 7);
                // nuclide notation: A over Z, then symbol
                const nx = w * 0.68;
                ctx.textAlign = 'right'; ctx.fillStyle = ink; ctx.font = '800 34px "JetBrains Mono", monospace';
                ctx.fillText(String(S.z + S.n), nx, h * 0.42);       // A on top
                ctx.fillText(String(S.z), nx, h * 0.58);             // Z below
                ctx.textAlign = 'left'; ctx.fillStyle = acc; ctx.font = '800 46px "JetBrains Mono", monospace';
                ctx.fillText(sym(S.z), nx + 10, h * 0.53);
                ctx.textAlign = 'center'; ctx.fillStyle = faint; ctx.font = '600 10px Inter';
                ctx.fillText('nuclide notation:  A (top) = nucleons,  Z (bottom) = protons', w * 0.5, h * 0.72);
                ctx.fillStyle = ink; ctx.font = '700 12px "JetBrains Mono", monospace';
                ctx.fillText(`${name(S.z)}:  Z = ${S.z} protons,  N = ${S.n} neutrons,  A = Z+N = ${S.z + S.n}`, w * 0.5, h - 26);
                ctx.fillStyle = rose; ctx.font = '600 10px Inter'; ctx.textAlign = 'left'; ctx.fillText('● protons (+)', w * 0.06, h - 10);
                ctx.fillStyle = '#9aa4b2'; ctx.fillText('● neutrons', w * 0.30, h - 10);
            } else if (S.mode === 'isotopes') {
                const [zz, ns] = ISOTOPE_SETS[S.iso];
                ns.forEach((nn, k) => {
                    const cx = w * (0.22 + k * 0.28), cy = h * 0.4;
                    drawNucleus(cx, cy, zz, nn, 5);
                    ctx.fillStyle = ink; ctx.font = '700 12px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                    ctx.fillText(`${sym(zz)}-${zz + nn}`, cx, cy + 62);
                    ctx.fillStyle = faint; ctx.font = '600 9px Inter';
                    ctx.fillText(`${zz}p, ${nn}n`, cx, cy + 78);
                });
                ctx.fillStyle = acc; ctx.font = '700 12px Inter'; ctx.textAlign = 'center';
                ctx.fillText(`isotopes of ${name(zz)}: SAME Z = ${zz} (same element), DIFFERENT number of neutrons`, w * 0.5, h * 0.14);
                ctx.fillStyle = faint; ctx.font = '600 10px Inter';
                ctx.fillText('same chemistry (same protons/electrons), different mass (different neutrons)', w * 0.5, h - 12);
            } else {
                // IONS
                const cx = w * 0.4, cy = h * 0.44, ne = S.electrons, zz = S.z;
                drawNucleus(cx, cy, zz, S.n, 6);
                // electron shells
                ctx.strokeStyle = 'rgba(120,180,255,.25)'; ctx.lineWidth = 1;
                [60, 100].forEach((r) => { ctx.beginPath(); ctx.arc(cx, cy, r, 0, Math.PI * 2); ctx.stroke(); });
                for (let i = 0; i < ne; i++) { const a = amb * 0.6 + (i / ne) * Math.PI * 2; const r = i < 2 ? 60 : 100; const ex = cx + Math.cos(a) * r, ey = cy + Math.sin(a) * r; ctx.fillStyle = cyan; ctx.beginPath(); ctx.arc(ex, ey, 5, 0, Math.PI * 2); ctx.fill(); ctx.fillStyle = '#0b0e14'; ctx.font = '700 8px Inter'; ctx.fillText('−', ex, ey + 3); }
                const charge = zz - ne; // +ve if lost electrons
                ctx.fillStyle = charge > 0 ? rose : (charge < 0 ? cyan : acc); ctx.font = '800 15px Inter'; ctx.textAlign = 'center';
                const label = charge === 0 ? `neutral ${name(zz)} atom (${zz}p, ${ne}e)` : charge > 0 ? `POSITIVE ion: lost ${charge} electron${charge > 1 ? 's' : ''}  (charge ${charge > 0 ? '+' : ''}${charge})` : `NEGATIVE ion: gained ${-charge} electron${-charge > 1 ? 's' : ''}  (charge ${charge})`;
                ctx.fillText(label, w * 0.5, h - 26);
                ctx.fillStyle = faint; ctx.font = '600 10px Inter';
                ctx.fillText('lose electrons → positive ion · gain electrons → negative ion (the nucleus is unchanged)', w * 0.5, h - 10);
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const chargeIons = z - electrons;

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">{{ build: 'Nucleus & nuclide notation', isotopes: 'Isotopes', ions: 'Forming ions' }[mode]}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'build' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('build')}>⚛ Build</button>
                    <button className={'cw-btn ' + (mode === 'isotopes' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('isotopes')}>⚖ Isotopes</button>
                    <button className={'cw-btn ' + (mode === 'ions' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('ions')}>± Ions</button>
                </div>

                {mode === 'build' && (
                    <>
                        <Stat label={`${name(z)} nucleus`} value={`${sym(z)}-${A}  (Z=${z}, N=${n}, A=${A})`} tone="acc"
                              sub={<><b>Z</b> (proton number) = <b>{z}</b> — this decides the element. <b>A</b> (nucleon number) = protons + neutrons = <b>{A}</b>. Neutrons <b>N = A − Z = {n}</b>.</>} />
                        <div className="cw-btnrow">
                            <button className="cw-btn cw-btn-ghost" onClick={() => setZ(Math.max(1, z - 1))}>− proton</button>
                            <button className="cw-btn cw-btn-ghost" onClick={() => setZ(Math.min(20, z + 1))}>+ proton</button>
                            <button className="cw-btn cw-btn-ghost" onClick={() => setN(Math.max(0, n - 1))}>− neutron</button>
                            <button className="cw-btn cw-btn-ghost" onClick={() => setN(Math.min(24, n + 1))}>+ neutron</button>
                        </div>
                        <Flag kind="neutral">
                            The nucleus is made of <b>protons</b> (charge +1) and <b>neutrons</b> (no charge). The <b>proton number (atomic number) Z</b>
                            is the number of protons — it fixes which <b>element</b> it is. The <b>nucleon number (mass number) A</b> is the total number
                            of protons and neutrons, so the number of <b>neutrons = A − Z</b>. We write a <b>nuclide</b> (a particular nucleus) as
                            <b> A over Z, then the symbol X</b> — e.g. carbon-12 has A = 12, Z = 6.
                        </Flag>
                    </>
                )}

                {mode === 'isotopes' && (
                    <>
                        <div className="cw-btnrow">
                            {Object.keys(ISOTOPE_SETS).map((k) => (
                                <button key={k} className={'cw-btn ' + (iso === k ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setIso(k)}>{k}</button>
                            ))}
                        </div>
                        <Stat label="isotopes" value={`same Z, different N`} tone="acc"
                              sub={<><b>isotopes</b> are atoms of the <b>same element</b> (same proton number Z) with <b>different numbers of neutrons</b> (so different nucleon number A). They behave the <b>same chemically</b> but have a <b>different mass</b>.</>} />
                        <Flag kind="neutral">
                            An <b>isotope</b> is one of two or more atoms of the <b>same element</b> that have the <b>same number of protons (Z)</b> but
                            <b> different numbers of neutrons</b> — so a different nucleon number A. An element <b>may have more than one isotope</b>
                            (e.g. carbon-12 and carbon-14, both with Z = 6). Because they have the same protons (and electrons), isotopes have the
                            <b> same chemistry</b>; because they have different neutrons, they have a <b>different mass</b>.
                        </Flag>
                    </>
                )}

                {mode === 'ions' && (
                    <>
                        <Stat label="forming an ion" value={chargeIons === 0 ? 'neutral atom' : (chargeIons > 0 ? `positive ion (${chargeIons > 0 ? '+' : ''}${chargeIons})` : `negative ion (${chargeIons})`)} tone="acc"
                              sub={<>a neutral atom has equal protons and electrons. <b>Lose</b> an electron → a <b>positive</b> ion; <b>gain</b> one → a <b>negative</b> ion. The nucleus is <b>not</b> changed.</>} />
                        <div className="cw-btnrow">
                            <button className="cw-btn cw-btn-ghost" onClick={() => setElectrons(Math.max(0, electrons - 1))}>remove an electron (→ +)</button>
                            <button className="cw-btn cw-btn-ghost" onClick={() => setElectrons(Math.min(z + 3, electrons + 1))}>add an electron (→ −)</button>
                            <button className="cw-btn cw-btn-ghost" onClick={() => setElectrons(z)}>reset (neutral)</button>
                        </div>
                        <Flag kind="neutral">
                            A neutral atom has as many <b>electrons</b> (−) as <b>protons</b> (+), so its total charge is zero. If it <b>loses</b> one or more
                            electrons it is left with more protons than electrons — a <b>positive ion</b>. If it <b>gains</b> electrons it has more electrons
                            than protons — a <b>negative ion</b>. Only the <b>electrons</b> move; the <b>nucleus</b> (protons + neutrons) is unchanged, so it
                            is still the same element.
                        </Flag>
                    </>
                )}

                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, z, n, element: iso })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
