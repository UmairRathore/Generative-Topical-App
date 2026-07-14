import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: fission_fusion_lab ───────────────────────────────────────────────
// Bespoke 5.2.4 hero (immersive 2D). Nuclear fission and fusion.
//   FUSION: two small nuclei (²H + ³H) combine into a larger nucleus (⁴He) + a
//           neutron, releasing energy — the energy source of stars.
//   FISSION: a slow neutron is absorbed by a U-235 nucleus, which becomes unstable
//           and splits into two daughter nuclei + 2–3 neutrons, releasing energy.
//   CHAIN: those neutrons go on to split more U-235 nuclei. A control-rod slider
//           absorbs neutrons; low absorption → the reaction grows (supercritical),
//           high absorption → it dies out (subcritical), in between → steady.
// config: { mode, rods }  (fusion | fission | chain ; rods 0..100 % absorption)

export default function FissionFusionLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['fusion', 'fission', 'chain'].includes(config.mode) ? config.mode : 'fission');
    const [rods, setRods] = useState(Number.isFinite(config.rods) ? config.rods : 55);
    const [rate, setRate] = useState(0);
    const cvRef = useRef(null);
    const sim = useRef({});
    const st = useRef({}); st.current = { mode, rods };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, rods: st.current.rods }),
            setState: (s) => { if (['fusion', 'fission', 'chain'].includes(s?.mode)) setMode(s.mode); if (Number.isFinite(s?.rods)) setRods(s.rods); },
        });
    }, [onReady]); // eslint-disable-line

    // reset the sim whenever the mode changes
    useEffect(() => { sim.current = { neutrons: [], nuclei: [], flashes: [], cycle: 0, fissions: 0, lastFits: [] }; }, [mode]);

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf, prev = performance.now();
        const rand = () => Math.random();

        const draw = (now) => {
            const dt = Math.min(0.05, (now - prev) / 1000); prev = now;
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight;
            if (!w || !h) { raf = requestAnimationFrame(draw); return; }
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const acc = cssVar('--ok', '#34D399'), amber = '#FBBF24', cyan = '#38BDF8', rose = '#FB7185', violet = '#a78bfa';
            const S = st.current, sm = sim.current;
            const bg = ctx.createLinearGradient(0, 0, 0, h);
            bg.addColorStop(0, '#0f1420'); bg.addColorStop(1, '#0a0d14');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);
            ctx.textAlign = 'center';
            const t = now / 1000;

            const blob = (cx, cy, r, np, nn, wobble) => {
                const total = Math.max(1, np + nn);
                for (let i = 0; i < total; i++) {
                    const isP = i % 2 === 0 && i < np * 2;
                    const ang = i * 2.399 + (wobble ? t * 0.8 : 0), rr = r * 0.8 * Math.sqrt(i / total);
                    ctx.fillStyle = isP ? rose : '#9aa4b2';
                    ctx.beginPath(); ctx.arc(cx + Math.cos(ang) * rr, cy + Math.sin(ang) * rr, Math.max(1.6, r * 0.16), 0, 7); ctx.fill();
                }
            };
            const flash = (x, y, a) => { const g = ctx.createRadialGradient(x, y, 0, x, y, 34); g.addColorStop(0, `rgba(251,191,36,${0.7 * a})`); g.addColorStop(0.5, `rgba(251,113,133,${0.3 * a})`); g.addColorStop(1, 'rgba(0,0,0,0)'); ctx.fillStyle = g; ctx.beginPath(); ctx.arc(x, y, 34, 0, 7); ctx.fill(); };
            const neutronDot = (x, y) => { ctx.fillStyle = cyan; ctx.beginPath(); ctx.arc(x, y, 3.2, 0, 7); ctx.fill(); ctx.strokeStyle = 'rgba(56,189,248,0.4)'; ctx.lineWidth = 1; ctx.beginPath(); ctx.arc(x, y, 6, 0, 7); ctx.stroke(); };

            // decay any flashes
            sm.flashes = (sm.flashes || []).filter(f => (f.a -= dt * 1.6) > 0);

            if (S.mode === 'fusion') {
                const cx = w / 2, cy = h * 0.42, period = 3.2, ph = (t % period) / period;
                if (ph < 0.45) {
                    const k = ph / 0.45;
                    blob(cx - 70 * (1 - k), cy, 13, 1, 1, true); // deuterium (1p1n)
                    blob(cx + 70 * (1 - k), cy, 14, 1, 2, true); // tritium (1p2n)
                    ctx.fillStyle = faint; ctx.font = '600 10px Inter';
                    ctx.fillText('²₁H  deuterium', cx - 70 * (1 - k), cy + 30);
                    ctx.fillText('³₁H  tritium', cx + 70 * (1 - k), cy + 30);
                } else {
                    const k = (ph - 0.45) / 0.55;
                    if (k < 0.12) flash(cx, cy, 1 - k / 0.12);
                    blob(cx, cy, 18, 2, 2, true); // He-4
                    neutronDot(cx + 60 * k, cy - 40 * k);
                    ctx.fillStyle = acc; ctx.font = '700 11px Inter'; ctx.fillText('⁴₂He  helium', cx, cy + 34);
                    ctx.fillStyle = cyan; ctx.font = '600 9px Inter'; ctx.fillText('neutron', cx + 60 * k, cy - 40 * k - 10);
                    if (k > 0.2 && k < 0.9) { ctx.fillStyle = amber; ctx.font = '800 13px Inter'; ctx.fillText('+ energy', cx, cy - 44); }
                }
                ctx.fillStyle = ink; ctx.font = '700 12px Inter';
                ctx.fillText('two small nuclei  →  one larger nucleus  +  energy', cx, h - 18);
                setRate(0);
            }

            else if (S.mode === 'fission') {
                const cx = w / 2, cy = h * 0.42, period = 3.4, ph = (t % period) / period;
                if (ph < 0.32) { // neutron approaching
                    const k = ph / 0.32; neutronDot(w * 0.1 + (cx - w * 0.1) * k, cy);
                    blob(cx, cy, 24, 92, 143, false); // U-235-ish
                    ctx.fillStyle = faint; ctx.font = '600 10px Inter'; ctx.fillText('²³⁵₉₂U  uranium-235', cx, cy + 42);
                    ctx.fillStyle = cyan; ctx.font = '600 9px Inter'; ctx.fillText('slow neutron', w * 0.1 + (cx - w * 0.1) * k, cy - 16);
                } else if (ph < 0.42) { // absorbed, wobbling
                    if (ph < 0.35) flash(cx, cy, (0.35 - ph) / 0.03 * 0.5);
                    const wob = Math.sin(t * 40) * 3;
                    blob(cx + wob, cy, 25, 92, 144, true);
                    ctx.fillStyle = amber; ctx.font = '600 10px Inter'; ctx.fillText('unstable — about to split', cx, cy + 44);
                } else { // split
                    const k = (ph - 0.42) / 0.58;
                    if (k < 0.14) flash(cx, cy, 1 - k / 0.14);
                    blob(cx - 90 * k, cy - 14 * k, 17, 56, 85, false); // barium-ish
                    blob(cx + 90 * k, cy + 14 * k, 15, 36, 56, false); // krypton-ish
                    // 3 neutrons fly out
                    [[-0.6, -1], [0.3, -1.1], [0.9, 0.7]].forEach(([dx, dy], i) => neutronDot(cx + dx * 110 * k, cy + dy * 70 * k));
                    ctx.fillStyle = acc; ctx.font = '600 9px Inter';
                    ctx.fillText('daughter nucleus', cx - 90 * k, cy - 14 * k - 24);
                    ctx.fillText('daughter nucleus', cx + 90 * k, cy + 14 * k + 30);
                    if (k > 0.2 && k < 0.95) { ctx.fillStyle = amber; ctx.font = '800 13px Inter'; ctx.fillText('+ energy', cx, cy - 52); }
                }
                ctx.fillStyle = ink; ctx.font = '700 12px Inter';
                ctx.fillText('neutron absorbed  →  nucleus splits  →  2 daughters + 2–3 neutrons + energy', cx, h - 18);
                setRate(0);
            }

            else { // chain reaction
                const absorb = S.rods / 100; // fraction of new neutrons soaked up by control rods
                // lay out a lattice of fuel nuclei once
                if (!sm.nuclei.length) {
                    const cols = 7, rowsN = 4, x0 = w * 0.16, y0 = h * 0.16, dx = (w * 0.68) / (cols - 1), dy = (h * 0.5) / (rowsN - 1);
                    for (let r = 0; r < rowsN; r++) for (let cN = 0; cN < cols; cN++) sm.nuclei.push({ x: x0 + cN * dx, y: y0 + r * dy, alive: true, glow: 0 });
                    sm.neutrons.push({ x: w * 0.02, y: h * 0.4, vx: 150, vy: 20 });
                    sm.window = []; sm.emitTimer = 0;
                }
                // draw control-rod bank descending from the top; more rods = deeper
                const rodDepth = h * (0.06 + 0.5 * absorb);
                for (let i = 0; i < 6; i++) { const rx = w * (0.2 + i * 0.12); ctx.fillStyle = 'rgba(148,163,184,0.55)'; ctx.fillRect(rx - 4, 0, 8, rodDepth); ctx.fillStyle = 'rgba(148,163,184,0.9)'; ctx.fillRect(rx - 6, rodDepth - 6, 12, 8); }
                ctx.fillStyle = faint; ctx.font = '600 9px Inter'; ctx.textAlign = 'left'; ctx.fillText('control rods', w * 0.02, 12); ctx.textAlign = 'center';

                // fuel nuclei
                sm.nuclei.forEach(nuc => {
                    nuc.glow = Math.max(0, nuc.glow - dt * 2);
                    if (nuc.alive) { ctx.fillStyle = `rgba(154,164,178,${0.5})`; ctx.beginPath(); ctx.arc(nuc.x, nuc.y, 7, 0, 7); ctx.fill(); ctx.fillStyle = rose; ctx.beginPath(); ctx.arc(nuc.x, nuc.y, 3, 0, 7); ctx.fill(); }
                    else if (nuc.glow > 0) flash(nuc.x, nuc.y, nuc.glow);
                });

                // advance neutrons; check for hits
                const rega = sm.window;
                sm.neutrons.forEach(nn => {
                    nn.x += nn.vx * dt; nn.y += nn.vy * dt;
                    // rod absorption: a neutron passing through the rod band is absorbed with prob ~absorb
                    if (nn.y < rodDepth && !nn.absChecked) { nn.absChecked = true; if (rand() < absorb) nn.dead = true; }
                    // bounce off floor/ceiling of the core box
                    if (nn.y < 2 || nn.y > h - 2) nn.vy *= -1;
                    if (nn.x < 0 || nn.x > w) nn.dead = true;
                    if (!nn.dead) {
                        for (const nuc of sm.nuclei) {
                            if (nuc.alive && Math.hypot(nn.x - nuc.x, nn.y - nuc.y) < 9) {
                                nuc.alive = false; nuc.glow = 1; nn.dead = true; sm.fissions++;
                                // emit new neutrons (2–3), direction randomised
                                const nNew = 2 + (rand() < 0.5 ? 1 : 0); let caused = 0;
                                for (let e = 0; e < nNew; e++) { const ang = rand() * 7; sm.neutrons.push({ x: nuc.x, y: nuc.y, vx: Math.cos(ang) * 150, vy: Math.sin(ang) * 150 }); caused++; }
                                rega.push({ born: caused }); // record neutrons produced this fission
                                break;
                            }
                        }
                    }
                    if (!nn.dead) neutronDot(nn.x, nn.y);
                });
                sm.neutrons = sm.neutrons.filter(nn => !nn.dead);

                // keep the reaction going / observable: reseed if it dies (subcritical) so students see the die-out repeatedly
                sm.emitTimer += dt;
                if (sm.neutrons.length === 0 && sm.emitTimer > 1.2) { sm.emitTimer = 0; sm.nuclei.forEach(n => { n.alive = true; }); sm.neutrons.push({ x: w * 0.02, y: h * 0.4, vx: 150, vy: 20 }); }
                if (sm.neutrons.length > 90) sm.neutrons = sm.neutrons.slice(0, 90); // cap for perf

                // multiplication estimate: neutrons that survive rods per fission
                const k = (1 - absorb) * 2.5;
                setRate(Math.round(k * 100) / 100);
                const label = k > 1.15 ? 'supercritical — reaction grows' : k < 0.85 ? 'subcritical — reaction dies out' : 'critical — steady, controlled';
                const col = k > 1.15 ? rose : k < 0.85 ? cyan : acc;
                ctx.fillStyle = col; ctx.font = '800 13px Inter'; ctx.fillText(label, w / 2, h - 30);
                ctx.fillStyle = faint; ctx.font = '600 10px Inter'; ctx.fillText(`live neutrons: ${sm.neutrons.length}   ·   fissions: ${sm.fissions}`, w / 2, h - 14);
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const badge = { fusion: 'Nuclear fusion', fission: 'Nuclear fission', chain: 'Chain reaction & control' }[mode];
    const stat = {
        fusion: { v: '²H + ³H → ⁴He + n', tone: 'acc', sub: <>Two <b>small</b> nuclei <b>combine</b> into one <b>larger</b> nucleus, releasing <b>energy</b>. This is the energy source of <b>stars</b> — hydrogen fusing into helium.</> },
        fission: { v: 'n + ²³⁵U → 2 daughters + 2–3 n', tone: 'acc', sub: <>A <b>slow neutron</b> is <b>absorbed</b> by a U-235 nucleus, which becomes unstable and <b>splits</b> into two <b>daughter nuclei</b> plus <b>2–3 neutrons</b>, releasing <b>energy</b>.</> },
        chain: { v: `multiplication ≈ ${rate}`, tone: 'acc', sub: <>Each fission's neutrons can split <b>more</b> nuclei — a <b>chain reaction</b>. <b>Control rods</b> absorb neutrons; a <b>moderator</b> slows them so they are absorbed; <b>coolant</b> carries heat away. Keep it <b>critical</b> (steady).</> },
    }[mode];

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">{badge}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'fusion' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('fusion')}>Fusion</button>
                    <button className={'cw-btn ' + (mode === 'fission' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('fission')}>Fission</button>
                    <button className={'cw-btn ' + (mode === 'chain' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('chain')}>Chain reaction</button>
                </div>
                {mode === 'chain' && (
                    <Slider label="Control rods (neutron absorption)" min={0} max={100} step={5} value={rods} onChange={setRods} suffix="%" />
                )}
                <Stat label={{ fusion: 'Fusion', fission: 'Fission', chain: 'Reactor control' }[mode]} value={stat.v} tone={stat.tone} sub={stat.sub} />
                <Flag kind="neutral">
                    <b>Fusion</b> joins two <b>small</b> nuclei into a <b>larger</b> one and releases energy (it powers the stars). <b>Fission</b> is
                    the opposite direction: a <b>large</b> nucleus such as <b>U-235</b> absorbs a neutron and <b>splits</b> into two daughter nuclei
                    plus <b>2–3 neutrons</b>, releasing energy. Those neutrons can cause further fissions — a <b>chain reaction</b> — which a reactor
                    keeps steady with <b>control rods</b> (absorb neutrons), a <b>moderator</b> (slows neutrons so they are absorbed) and a <b>coolant</b>.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, rods })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
