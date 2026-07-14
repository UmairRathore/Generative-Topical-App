import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: half_life_lab ────────────────────────────────────────────────────
// Bespoke 5.2.5 hero (immersive 2D). Half-life, carbon dating and applications.
//   DECAY: a sample of nuclei halving every half-life, drawn as a live decay curve
//          (count vs time) + a grid of parent/daughter dots; a time slider in
//          half-lives reads off count = N·(½)^(t/T).
//   DATING: carbon-14 dating — the fraction of C-14 remaining vs age (T½ = 5730 yr),
//          reading an object's age from the measured fraction.
//   USES: matching the radiation TYPE and HALF-LIFE to an application.
// config: { mode, halves }  (decay | dating | uses ; halves 0..5)

const USES = [
    { key: 'smoke', name: 'Smoke alarm', type: 'alpha (α)', half: 'long (americium-241 ≈ 432 yr)', why: 'α strongly ionises the air so a current flows; smoke absorbs the α and the current drops. A long half-life means the source lasts for years without needing replacing. α cannot escape the casing, so it is safe.' },
    { key: 'food', name: 'Irradiating food / sterilising equipment', type: 'gamma (γ)', half: 'from a long-lived source', why: 'γ is very penetrating, so it passes through the packaging/wrapping and kills bacteria inside without opening it. The item itself does not become radioactive.' },
    { key: 'thick', name: 'Controlling thickness of sheet material', type: 'beta (β)', half: 'long (steady source)', why: 'β is partly absorbed by the sheet. If the sheet gets thicker, fewer β get through and the detector count drops — so the count controls the rollers. α would be stopped completely; γ would pass straight through unchanged.' },
    { key: 'medical', name: 'Medical diagnosis (tracer)', type: 'gamma (γ)', half: 'short (technetium-99m ≈ 6 h)', why: 'γ escapes the body to be detected outside. A short half-life means the activity falls quickly, so the patient is not exposed for long after the scan.' },
    { key: 'cancer', name: 'Treating cancer', type: 'gamma (γ)', half: 'long-lived source (e.g. cobalt-60)', why: 'penetrating γ is aimed at the tumour from outside to kill the cancer cells. Beams from several directions concentrate the dose on the tumour while sparing healthy tissue.' },
];

export default function HalfLifeLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['decay', 'dating', 'uses'].includes(config.mode) ? config.mode : 'decay');
    const [halves, setHalves] = useState(Number.isFinite(config.halves) ? config.halves : 2);
    const [age, setAge] = useState(11460); // years, for dating
    const [use, setUse] = useState(config.use && USES.find(u => u.key === config.use) ? config.use : 'smoke');
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, halves, age };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, halves: st.current.halves, age: st.current.age, use }),
            setState: (s) => { if (['decay', 'dating', 'uses'].includes(s?.mode)) setMode(s.mode); if (Number.isFinite(s?.halves)) setHalves(s.halves); if (Number.isFinite(s?.age)) setAge(s.age); if (s?.use) setUse(s.use); },
        });
    }, [onReady]); // eslint-disable-line

    const T = 5730; // carbon-14 half-life, years
    const N0 = 1000;

    useEffect(() => {
        if (mode === 'uses') return undefined;
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf;
        const draw = (now) => {
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight;
            if (!w || !h) { raf = requestAnimationFrame(draw); return; }
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const faint = cssVar('--ink-faint', '#68877A'), ink = cssVar('--ink-soft', '#A6C4B3');
            const acc = cssVar('--ok', '#34D399'), amber = '#FBBF24', rose = '#FB7185', cyan = '#38BDF8';
            const S = st.current;
            const bg = ctx.createLinearGradient(0, 0, 0, h); bg.addColorStop(0, '#0f1420'); bg.addColorStop(1, '#0a0d14');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);

            // graph frame (right ~60%), dot sample (left ~36%)
            const gx0 = w * 0.42, gy0 = h * 0.12, gx1 = w * 0.94, gy1 = h * 0.8;
            const maxHalf = 5;
            const frac = (S.mode === 'dating') ? Math.pow(0.5, S.age / T) : Math.pow(0.5, S.halves);
            const tNow = (S.mode === 'dating') ? (S.age / T) : S.halves; // in half-lives

            // axes
            ctx.strokeStyle = faint; ctx.lineWidth = 1;
            ctx.beginPath(); ctx.moveTo(gx0, gy0); ctx.lineTo(gx0, gy1); ctx.lineTo(gx1, gy1); ctx.stroke();
            ctx.fillStyle = faint; ctx.font = '600 9px Inter'; ctx.textAlign = 'center';
            for (let k = 0; k <= maxHalf; k++) { const x = gx0 + (gx1 - gx0) * k / maxHalf; ctx.fillStyle = faint; ctx.fillText(String(k), x, gy1 + 12); if (k > 0) { ctx.strokeStyle = 'rgba(104,135,122,0.15)'; ctx.beginPath(); ctx.moveTo(x, gy0); ctx.lineTo(x, gy1); ctx.stroke(); } }
            ctx.save(); ctx.translate(gx0 - 26, (gy0 + gy1) / 2); ctx.rotate(-Math.PI / 2); ctx.fillText('count remaining', 0, 0); ctx.restore();
            ctx.fillText('time (half-lives)', (gx0 + gx1) / 2, gy1 + 26);
            // half fraction gridlines
            ctx.textAlign = 'right';
            [1, 0.5, 0.25, 0.125].forEach(f => { const y = gy1 - (gy1 - gy0) * f; ctx.strokeStyle = 'rgba(104,135,122,0.12)'; ctx.beginPath(); ctx.moveTo(gx0, y); ctx.lineTo(gx1, y); ctx.stroke(); ctx.fillStyle = faint; ctx.fillText(Math.round(N0 * f), gx0 - 4, y + 3); });

            // decay curve
            ctx.strokeStyle = acc; ctx.lineWidth = 2.4; ctx.beginPath();
            for (let px = 0; px <= 1; px += 0.01) { const th = px * maxHalf; const y = gy1 - (gy1 - gy0) * Math.pow(0.5, th); const x = gx0 + (gx1 - gx0) * px; if (px === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y); }
            ctx.stroke();

            // half-life step markers (dashed drop lines at 1,2,3 half-lives)
            ctx.setLineDash([3, 3]); ctx.strokeStyle = 'rgba(251,191,36,0.5)';
            for (let k = 1; k <= 3; k++) { const x = gx0 + (gx1 - gx0) * k / maxHalf; const y = gy1 - (gy1 - gy0) * Math.pow(0.5, k); ctx.beginPath(); ctx.moveTo(x, gy1); ctx.lineTo(x, y); ctx.lineTo(gx0, y); ctx.stroke(); }
            ctx.setLineDash([]);

            // current-time cursor
            const cx = gx0 + (gx1 - gx0) * Math.min(1, tNow / maxHalf); const cy = gy1 - (gy1 - gy0) * frac;
            ctx.strokeStyle = cyan; ctx.lineWidth = 1.5; ctx.beginPath(); ctx.moveTo(cx, gy0); ctx.lineTo(cx, gy1); ctx.stroke();
            ctx.fillStyle = cyan; ctx.beginPath(); ctx.arc(cx, cy, 5, 0, 7); ctx.fill();
            ctx.fillStyle = ink; ctx.font = '700 11px Inter'; ctx.textAlign = 'left';
            ctx.fillText(`${Math.round(N0 * frac)} left`, Math.min(cx + 8, gx1 - 44), Math.max(gy0 + 10, cy - 8));

            // dot sample (left)
            const cols = 10, rows = 10, dx0 = w * 0.05, dy0 = h * 0.14, cell = Math.min((w * 0.32) / cols, (h * 0.5) / rows);
            const aliveN = Math.round(cols * rows * frac);
            ctx.textAlign = 'center'; ctx.fillStyle = faint; ctx.font = '600 9px Inter';
            ctx.fillText('the sample (100 nuclei)', dx0 + cols * cell / 2, dy0 - 6);
            for (let i = 0; i < cols * rows; i++) { const r = Math.floor(i / cols), cN = i % cols; const alive = i < aliveN; ctx.fillStyle = alive ? rose : 'rgba(120,130,145,0.45)'; ctx.beginPath(); ctx.arc(dx0 + cN * cell + cell / 2, dy0 + r * cell + cell / 2, cell * 0.3, 0, 7); ctx.fill(); }
            ctx.fillStyle = rose; ctx.font = '600 9px Inter'; ctx.textAlign = 'left'; ctx.fillText('● undecayed', dx0, dy0 + rows * cell + 14);
            ctx.fillStyle = 'rgba(120,130,145,0.7)'; ctx.fillText('● decayed', dx0 + cols * cell * 0.55, dy0 + rows * cell + 14);

            // caption
            ctx.textAlign = 'center'; ctx.fillStyle = amber; ctx.font = '700 12px Inter';
            if (S.mode === 'dating') ctx.fillText(`age ${Math.round(S.age)} yr = ${(S.age / T).toFixed(2)} half-lives → ${(frac * 100).toFixed(1)}% C-14 left`, w / 2, h - 12);
            else ctx.fillText(`after ${S.halves.toFixed(2)} half-lives, ${(frac * 100).toFixed(1)}% remains`, w / 2, h - 12);
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, [mode]);

    const u = USES.find(x => x.key === use) || USES[0];
    const frac = Math.pow(0.5, halves);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">{{ decay: 'Half-life & the decay curve', dating: 'Carbon-14 dating', uses: 'Choosing an isotope' }[mode]}</span>
                    {mode === 'uses' ? (
                        <div style={{ position: 'absolute', inset: 0, padding: '30px 14px 12px', overflowY: 'auto' }}>
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginBottom: 8 }}>
                                {USES.map(x => (
                                    <button key={x.key} className={'cw-btn ' + (use === x.key ? 'cw-btn-save' : 'cw-btn-ghost')} style={{ fontSize: 11 }} onClick={() => setUse(x.key)}>{x.name}</button>
                                ))}
                            </div>
                            <div style={{ fontSize: 13, lineHeight: 1.5, color: cssVar('--ink-soft', '#A6C4B3') }}>
                                <div style={{ fontWeight: 800, color: cssVar('--ok', '#34D399'), marginBottom: 4 }}>{u.name}</div>
                                <div><b>Radiation:</b> {u.type}</div>
                                <div><b>Half-life needed:</b> {u.half}</div>
                                <div style={{ marginTop: 6 }}><b>Why this choice:</b> {u.why}</div>
                            </div>
                        </div>
                    ) : <canvas ref={cvRef} />}
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'decay' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('decay')}>Half-life</button>
                    <button className={'cw-btn ' + (mode === 'dating' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('dating')}>C-14 dating</button>
                    <button className={'cw-btn ' + (mode === 'uses' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('uses')}>Choosing isotope</button>
                </div>
                {mode === 'decay' && <Slider label="Time elapsed" min={0} max={5} step={0.25} value={halves} onChange={setHalves} suffix=" half-lives" />}
                {mode === 'dating' && <Slider label="Age of the object" min={0} max={28650} step={573} value={age} onChange={setAge} suffix=" yr" />}
                {mode === 'decay' && <Stat label="Fraction remaining" value={`${(frac * 100).toFixed(1)}%  (½ ^ ${halves})`} tone="acc" sub={<>Every <b>half-life</b>, half of the remaining nuclei decay: 100% → 50% → 25% → 12.5% → … . The <b>half-life</b> is the time for <b>half</b> the nuclei in the sample to decay — a fixed value for each isotope.</>} />}
                {mode === 'dating' && <Stat label="Carbon-14 dating" value={`${(Math.pow(0.5, age / T) * 100).toFixed(1)}% left → ${Math.round(age)} yr`} tone="acc" sub={<>Living things take in <b>carbon-14</b> (half-life <b>5730 yr</b>). When they die, no more is taken in and the C-14 <b>decays</b>. Measuring the fraction left gives the <b>age</b>.</>} />}
                {mode === 'uses' && <Stat label={u.name} value={u.type} tone="acc" sub={<>The <b>type of radiation</b> and the <b>half-life</b> are chosen to suit the job — penetration for reaching the target, and a half-life long enough to be useful but not so long it stays dangerous.</>} />}
                <Flag kind="neutral">
                    The <b>half-life</b> of an isotope is the time taken for <b>half</b> the nuclei in a sample to decay. It is <b>constant</b> for a
                    given isotope, so the count/activity <b>halves</b> each half-life (100% → 50% → 25% → …). <b>Carbon-14 dating</b> uses this: a
                    dead sample's C-14 fraction gives its age. For <b>applications</b>, the <b>radiation type</b> (α/β/γ, by penetration) and the
                    <b> half-life</b> are matched to the task — e.g. penetrating γ with a short half-life for medical tracers.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, halves, age, use })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
