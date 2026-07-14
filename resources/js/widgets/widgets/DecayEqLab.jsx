import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Flag } from '../primitives.jsx';

// ── Widget: decay_eq_lab ─────────────────────────────────────────────────────
// Bespoke 5.2.3 hero (immersive 2D). Radioactive decay equations.
// A parent nucleus decays into a daughter plus an emitted particle/ray, shown in
// nuclide notation with the nucleon number A (top) and proton number Z (bottom)
// BALANCING on both sides.
//   α decay: A → A−4, Z → Z−2, emitting a helium nucleus.
//   β⁻ decay: A unchanged, Z → Z+1 (a neutron turns into a proton + an electron).
//   γ emission: no change to A or Z — the nucleus just sheds energy.
// config: { mode }  (alpha | beta | gamma)

const CASES = {
    alpha: { p: { s: 'U', A: 238, Z: 92, n: 'uranium-238' }, d: { s: 'Th', A: 234, Z: 90 }, e: { s: 'He', A: 4, Z: 2, lbl: 'α (helium nucleus)' }, rule: 'A → A − 4,  Z → Z − 2' },
    beta: { p: { s: 'C', A: 14, Z: 6, n: 'carbon-14' }, d: { s: 'N', A: 14, Z: 7 }, e: { s: 'e', A: 0, Z: -1, lbl: 'β (electron)' }, rule: 'A unchanged,  Z → Z + 1' },
    gamma: { p: { s: 'Tc', A: 99, Z: 43, n: 'technetium-99m' }, d: { s: 'Tc', A: 99, Z: 43 }, e: { s: 'γ', A: 0, Z: 0, lbl: 'γ (energy)' }, rule: 'A and Z unchanged' },
};

export default function DecayEqLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['alpha', 'beta', 'gamma'].includes(config.mode) ? config.mode : 'alpha');
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode }),
            setState: (s) => { if (['alpha', 'beta', 'gamma'].includes(s?.mode)) setMode(s.mode); },
        });
    }, [onReady]); // eslint-disable-line

    const c = CASES[mode];
    const aOk = c.d.A + c.e.A === c.p.A;
    const zOk = c.d.Z + c.e.Z === c.p.Z;

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
            const S = st.current, cc = CASES[S.mode];
            const bg = ctx.createLinearGradient(0, 0, 0, h);
            bg.addColorStop(0, '#12161d'); bg.addColorStop(1, '#0b0e14');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);
            ctx.textAlign = 'center';

            const nucleus = (cx, cy, np, nn, rs) => {
                const total = Math.max(1, np + nn);
                for (let i = 0; i < total; i++) { const isP = i < np; const ang = i * 2.399, rr = rs * 0.85 * Math.sqrt(i); const px = cx + Math.cos(ang + amb * 0.1) * rr, py = cy + Math.sin(ang + amb * 0.1) * rr; ctx.fillStyle = isP ? rose : '#9aa4b2'; ctx.beginPath(); ctx.arc(px, py, rs, 0, Math.PI * 2); ctx.fill(); }
            };
            // scale nucleus radius down for big nuclei
            const rs = cc.p.A > 60 ? 3 : 5;
            // parent (left), daughter (mid), emitted (right)
            const py = h * 0.34;
            nucleus(w * 0.18, py, cc.p.Z, cc.p.A - cc.p.Z, rs);
            ctx.fillStyle = amber; ctx.font = '800 24px Inter'; ctx.fillText('→', w * 0.36, py + 8);
            nucleus(w * 0.54, py, cc.d.Z, cc.d.A - cc.d.Z, rs);
            ctx.fillStyle = ink; ctx.font = '800 16px Inter'; ctx.fillText('+', w * 0.7, py + 6);
            // emitted particle
            if (S.mode === 'gamma') { ctx.strokeStyle = '#a78bfa'; ctx.lineWidth = 2.4; ctx.beginPath(); for (let x = -18; x <= 18; x++) ctx.lineTo(w * 0.84 + x, py - Math.sin((x + amb * 40) * 0.5) * 6); ctx.stroke(); }
            else nucleus(w * 0.84, py, Math.max(0, cc.e.Z), cc.e.A - Math.max(0, cc.e.Z), 5);
            // moving emitted dot
            const em = ((amb * 0.6) % 1); ctx.fillStyle = S.mode === 'alpha' ? rose : S.mode === 'beta' ? cyan : '#a78bfa';
            ctx.globalAlpha = 0.6; ctx.beginPath(); ctx.arc(w * 0.54 + em * (w * 0.3), py, 3, 0, Math.PI * 2); ctx.fill(); ctx.globalAlpha = 1;
            // labels under nuclei
            ctx.fillStyle = faint; ctx.font = '600 9px Inter';
            ctx.fillText('parent', w * 0.18, py + (cc.p.A > 60 ? 40 : 46));
            ctx.fillText('daughter', w * 0.54, py + (cc.p.A > 60 ? 40 : 46));
            ctx.fillText(cc.e.lbl, w * 0.84, py + 40);

            // ── the equation in nuclide notation ─────────────────────────────────
            const eqY = h * 0.72;
            const term = (x, A, Z, sym, col) => {
                ctx.textAlign = 'right'; ctx.fillStyle = ink; ctx.font = '700 16px "JetBrains Mono", monospace';
                ctx.fillText(String(A), x, eqY - 8); ctx.fillText(Z >= 0 ? String(Z) : String(Z), x, eqY + 12);
                ctx.textAlign = 'left'; ctx.fillStyle = col; ctx.font = '800 24px "JetBrains Mono", monospace'; ctx.fillText(sym, x + 4, eqY + 4);
                ctx.textAlign = 'center';
            };
            term(w * 0.14, cc.p.A, cc.p.Z, cc.p.s, amber);
            ctx.fillStyle = ink; ctx.font = '800 18px Inter'; ctx.fillText('→', w * 0.32, eqY + 2);
            term(w * 0.4, cc.d.A, cc.d.Z, cc.d.s, acc);
            ctx.fillStyle = ink; ctx.fillText('+', w * 0.58, eqY + 2);
            term(w * 0.64, cc.e.A, cc.e.Z, cc.e.s, S.mode === 'alpha' ? rose : S.mode === 'beta' ? cyan : '#a78bfa');
            // balance check
            ctx.fillStyle = aOk ? acc : rose; ctx.font = '700 10px "JetBrains Mono", monospace'; ctx.textAlign = 'left';
            ctx.fillText(`top (A): ${cc.d.A} + ${cc.e.A} = ${cc.p.A} ✓`, w * 0.06, h - 26);
            ctx.fillStyle = zOk ? acc : rose;
            ctx.fillText(`bottom (Z): ${cc.d.Z} + ${cc.e.Z} = ${cc.p.Z} ✓`, w * 0.06, h - 12);
            ctx.fillStyle = faint; ctx.font = '600 10px Inter'; ctx.textAlign = 'right';
            ctx.fillText(cc.rule, w * 0.94, h - 18);
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const info = {
        alpha: { title: 'Alpha decay', sub: <>the nucleus loses an <b>alpha particle</b> (a helium nucleus). The nucleon number falls by <b>4</b> and the proton number by <b>2</b>. Uranium-238 → thorium-234 + α.</> },
        beta: { title: 'Beta-minus decay', sub: <>a <b>neutron turns into a proton</b> and emits a <b>beta particle</b> (an electron). The nucleon number is <b>unchanged</b> and the proton number goes <b>up by 1</b>. Carbon-14 → nitrogen-14 + β.</> },
        gamma: { title: 'Gamma emission', sub: <>the nucleus sheds <b>excess energy</b> as a <b>gamma ray</b>. Neither the nucleon number nor the proton number changes — it is the <b>same nuclide</b>, just lower in energy.</> },
    }[mode];

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">{{ alpha: 'Alpha decay equation', beta: 'Beta decay equation', gamma: 'Gamma emission' }[mode]}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'alpha' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('alpha')}>α decay</button>
                    <button className={'cw-btn ' + (mode === 'beta' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('beta')}>β decay</button>
                    <button className={'cw-btn ' + (mode === 'gamma' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('gamma')}>γ emission</button>
                </div>
                <Stat label={info.title} value={c.rule} tone="acc" sub={info.sub} />
                <Flag kind="neutral">
                    <b>Radioactive decay</b> is a change in an <b>unstable nucleus</b> that emits an alpha or beta particle and/or gamma
                    radiation — <b>spontaneously</b> and <b>randomly</b>. In a <b>decay equation</b> (written in nuclide notation), the
                    <b> nucleon numbers (top) must balance</b> and the <b>proton numbers (bottom) must balance</b> on the two sides.
                    In <b>α decay</b> the nucleus loses a helium nucleus (A − 4, Z − 2); in <b>β⁻ decay</b> a neutron becomes a proton and an
                    electron is emitted (A the same, Z + 1); in <b>γ emission</b> only energy leaves, so A and Z are unchanged.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
