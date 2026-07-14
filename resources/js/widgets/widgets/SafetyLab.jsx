import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: safety_lab ───────────────────────────────────────────────────────
// Bespoke 4.4.2 hero (immersive 2D). Electrical safety.
//   HAZARDS — the four named hazards: damaged insulation, overheating cables,
//             damp conditions, and overloading sockets.
//   PLUG    — the three mains wires (live/neutral/earth) and WHY the switch and
//             fuse go in the LIVE wire.
//   FAULT   — a live wire touching a metal case: earthed (fuse blows, safe),
//             double-insulated (no metal to make live), or unearthed (danger).
//   FUSE    — choosing a fuse rating just above the normal current; a trip
//             switch (circuit breaker) as a resettable alternative.
// config: { mode, hazard, scenario, fuseRating, applianceW }

const HAZARDS = [
    { key: 'insulation', name: 'damaged insulation', why: 'bare conductor exposed → shock or short circuit' },
    { key: 'overheat', name: 'overheating cables', why: 'coiled or overloaded cable cannot lose heat → fire risk' },
    { key: 'damp', name: 'damp conditions', why: 'water conducts → current can pass through you' },
    { key: 'overload', name: 'overloading sockets', why: 'too many appliances → excess current → overheating/fire' },
];
const RATINGS = [3, 5, 13];
const MAINS = 230;

export default function SafetyLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['hazards', 'plug', 'fault', 'fuse'].includes(config.mode) ? config.mode : 'plug');
    const [hazard, setHazard] = useState(() => { const i = HAZARDS.findIndex(x => x.key === config.hazard); return i >= 0 ? i : 0; });
    const [scenario, setScenario] = useState(['earthed', 'insulated', 'unearthed'].includes(config.scenario) ? config.scenario : 'earthed');
    const [applianceW, setApplianceW] = useState(typeof config.applianceW === 'number' ? config.applianceW : 1000);
    const [fuseRating, setFuseRating] = useState(RATINGS.includes(config.fuseRating) ? config.fuseRating : 5);
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, hazard, scenario, applianceW, fuseRating };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, hazard: HAZARDS[st.current.hazard].key, scenario: st.current.scenario, applianceW: st.current.applianceW, fuseRating: st.current.fuseRating }),
            setState: (s) => {
                if (['hazards', 'plug', 'fault', 'fuse'].includes(s?.mode)) setMode(s.mode);
                const i = HAZARDS.findIndex(x => x.key === s?.hazard); if (i >= 0) setHazard(i);
                if (['earthed', 'insulated', 'unearthed'].includes(s?.scenario)) setScenario(s.scenario);
                if (typeof s?.applianceW === 'number') setApplianceW(Math.max(100, Math.min(3000, s.applianceW)));
                if (RATINGS.includes(s?.fuseRating)) setFuseRating(s.fuseRating);
            },
        });
    }, [onReady]); // eslint-disable-line

    const normalI = applianceW / MAINS;               // A
    const idealRating = RATINGS.find(r => r > normalI) ?? 13;
    const fuseOK = fuseRating > normalI;              // won't blow in normal use
    const fuseGood = fuseRating === idealRating;      // just above normal current

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
            const acc = cssVar('--ok', '#34D399'), amber = '#FBBF24', rose = '#FB7185', cyan = '#38BDF8';
            const S = st.current;
            const bg = ctx.createLinearGradient(0, 0, 0, h);
            bg.addColorStop(0, '#12161d'); bg.addColorStop(1, '#0b0e14');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);
            const LIVE = '#c8763c', NEUT = '#5aa9e6', EARTH = '#69c274';
            ctx.lineCap = 'round'; ctx.lineJoin = 'round';
            ctx.textAlign = 'center';

            const caption = (txt, col) => { ctx.fillStyle = col || faint; ctx.font = '600 11px Inter'; ctx.textAlign = 'center'; ctx.fillText(txt, w * 0.5, h - 12); };

            if (S.mode === 'hazards') {
                const hz = HAZARDS[S.hazard], cx = w * 0.5, cy = h * 0.36;
                ctx.strokeStyle = rose; ctx.fillStyle = 'rgba(251,113,133,.08)'; ctx.lineWidth = 2.5;
                if (hz.key === 'insulation') { ctx.beginPath(); ctx.moveTo(cx - 110, cy); ctx.lineTo(cx + 110, cy); ctx.lineWidth = 14; ctx.strokeStyle = '#3a3f47'; ctx.stroke(); ctx.strokeStyle = LIVE; ctx.lineWidth = 5; ctx.beginPath(); ctx.moveTo(cx - 20, cy); ctx.lineTo(cx + 30, cy); ctx.stroke(); for (let i = 0; i < 5; i++) { ctx.strokeStyle = amber; ctx.beginPath(); ctx.moveTo(cx - 15 + i * 11, cy - 6); ctx.lineTo(cx - 10 + i * 11, cy - 16); ctx.stroke(); } }
                else if (hz.key === 'overheat') { for (let i = 0; i < 3; i++) { ctx.strokeStyle = `rgba(${200 + i * 15},${120 - i * 30},60,.9)`; ctx.lineWidth = 10; ctx.beginPath(); ctx.arc(cx, cy, 30 + i * 16, 0.1, Math.PI * 1.9); ctx.stroke(); } for (let i = 0; i < 6; i++) { const a2 = amb * 2 + i; ctx.fillStyle = 'rgba(251,146,60,.5)'; ctx.beginPath(); ctx.arc(cx + Math.cos(a2) * 8, cy - 60 - (amb * 20 + i * 8) % 40, 3, 0, Math.PI * 2); ctx.fill(); } }
                else if (hz.key === 'damp') { ctx.strokeStyle = '#3a3f47'; ctx.lineWidth = 2; ctx.strokeRect(cx - 34, cy - 24, 68, 48); ctx.fillStyle = ink; ctx.beginPath(); ctx.arc(cx - 14, cy, 4, 0, Math.PI * 2); ctx.arc(cx + 14, cy, 4, 0, Math.PI * 2); ctx.fill(); for (let i = 0; i < 7; i++) { ctx.fillStyle = cyan; const dy = (amb * 40 + i * 22) % 90; ctx.beginPath(); ctx.ellipse(cx - 40 + i * 13, cy - 50 + dy, 3, 5, 0, 0, Math.PI * 2); ctx.fill(); } }
                else { ctx.strokeStyle = '#3a3f47'; ctx.lineWidth = 2; ctx.strokeRect(cx - 30, cy - 18, 60, 36); for (let i = 0; i < 5; i++) { const a2 = -1.4 + i * 0.7; ctx.strokeStyle = i > 2 ? rose : NEUT; ctx.lineWidth = 4; ctx.beginPath(); ctx.moveTo(cx, cy); ctx.lineTo(cx + Math.cos(a2) * 70, cy + 22 + Math.abs(Math.sin(a2)) * 40); ctx.stroke(); ctx.fillStyle = i > 2 ? rose : NEUT; ctx.fillRect(cx + Math.cos(a2) * 70 - 6, cy + 22 + Math.abs(Math.sin(a2)) * 40, 12, 10); } }
                ctx.fillStyle = rose; ctx.font = '800 15px Inter'; ctx.textAlign = 'center'; ctx.fillText('⚠ ' + hz.name, cx, h * 0.72);
                ctx.fillStyle = ink; ctx.font = '600 11px Inter'; ctx.fillText(hz.why, cx, h * 0.72 + 22);
                caption(`hazard ${S.hazard + 1} of 4 — a common cause of electric shock or fire`, faint);
            } else if (S.mode === 'plug') {
                // three-core cable into an appliance; switch + fuse in the LIVE wire
                const x0 = w * 0.12, x1 = w * 0.66, ax = w * 0.78;
                const yL = h * 0.3, yN = h * 0.5, yE = h * 0.7;
                const drawWire = (y, col, label) => { ctx.strokeStyle = col; ctx.lineWidth = 5; ctx.beginPath(); ctx.moveTo(x0, y); ctx.lineTo(x1, y); ctx.stroke(); ctx.fillStyle = col; ctx.font = '700 11px Inter'; ctx.textAlign = 'left'; ctx.fillText(label, x0, y - 10); };
                drawWire(yL, LIVE, 'LIVE (line)'); drawWire(yN, NEUT, 'NEUTRAL'); drawWire(yE, EARTH, 'EARTH');
                // switch (gap) in live
                const swx = x0 + (x1 - x0) * 0.38;
                ctx.strokeStyle = '#0b0e14'; ctx.lineWidth = 8; ctx.beginPath(); ctx.moveTo(swx - 12, yL); ctx.lineTo(swx + 12, yL); ctx.stroke();
                ctx.strokeStyle = amber; ctx.lineWidth = 3; ctx.beginPath(); ctx.moveTo(swx - 12, yL); ctx.lineTo(swx + 10, yL - 12); ctx.stroke();
                ctx.fillStyle = amber; ctx.font = '600 9px Inter'; ctx.textAlign = 'center'; ctx.fillText('switch', swx, yL + 18);
                // fuse in live
                const fx = x0 + (x1 - x0) * 0.68;
                ctx.strokeStyle = amber; ctx.lineWidth = 2; ctx.strokeRect(fx - 16, yL - 7, 32, 14); ctx.beginPath(); ctx.moveTo(fx - 16, yL); ctx.lineTo(fx + 16, yL); ctx.stroke();
                ctx.fillStyle = amber; ctx.fillText('fuse', fx, yL + 20);
                // appliance
                ctx.strokeStyle = ink; ctx.lineWidth = 2; ctx.strokeRect(ax, yL - 12, w * 0.12, yE - yL + 24);
                ctx.fillStyle = ink; ctx.font = '700 11px Inter'; ctx.fillText('appliance', ax + w * 0.06, (yL + yE) / 2);
                caption('the SWITCH and FUSE go in the LIVE wire, so switching off isolates the appliance from the live supply', faint);
            } else if (S.mode === 'fault') {
                const cx = w * 0.5, cy = h * 0.4, cw2 = 150, ch2 = 96;
                const metal = S.scenario !== 'insulated';
                const caseCol = S.scenario === 'insulated' ? '#8a8f98' : (S.scenario === 'earthed' ? '#9aa3ad' : '#c0c7cf');
                // case
                ctx.strokeStyle = caseCol; ctx.lineWidth = 3; ctx.fillStyle = 'rgba(255,255,255,.03)';
                ctx.beginPath(); ctx.rect(cx - cw2 / 2, cy - ch2 / 2, cw2, ch2); ctx.fill(); ctx.stroke();
                ctx.fillStyle = faint; ctx.font = '600 10px Inter'; ctx.textAlign = 'center';
                ctx.fillText(metal ? 'metal case' : 'plastic (double-insulated) case', cx, cy - ch2 / 2 - 8);
                // live wire touching the case (fault) for earthed/unearthed
                if (metal) {
                    ctx.strokeStyle = LIVE; ctx.lineWidth = 4; ctx.beginPath(); ctx.moveTo(cx - 60, cy + ch2 / 2); ctx.lineTo(cx - 20, cy - 10); ctx.lineTo(cx - cw2 / 2, cy - 10); ctx.stroke();
                    ctx.fillStyle = rose; ctx.font = '700 10px Inter'; ctx.fillText('live wire touches case', cx - 30, cy + ch2 / 2 + 16);
                }
                if (S.scenario === 'earthed') {
                    ctx.strokeStyle = EARTH; ctx.lineWidth = 4; ctx.beginPath(); ctx.moveTo(cx + cw2 / 2, cy); ctx.lineTo(cx + cw2 / 2 + 70, cy); ctx.lineTo(cx + cw2 / 2 + 70, cy + 70); ctx.stroke();
                    for (let i = 0; i < 3; i++) { ctx.beginPath(); ctx.moveTo(cx + cw2 / 2 + 70 - 14 + i * 10, cy + 70); ctx.lineTo(cx + cw2 / 2 + 70 - 8 + i * 10, cy + 70); ctx.stroke(); }
                    // surge dots to earth
                    for (let i = 0; i < 6; i++) { const p = (amb * 1.5 + i / 6) % 1; ctx.fillStyle = amber; ctx.beginPath(); ctx.arc(cx + cw2 / 2 + p * 70, cy, 3, 0, Math.PI * 2); ctx.fill(); }
                    ctx.fillStyle = acc; ctx.font = '800 13px Inter'; ctx.fillText('✓ SAFE: large current → earth → fuse blows → case dead', cx, cy + ch2 / 2 + 44);
                    ctx.fillStyle = EARTH; ctx.font = '600 9px Inter'; ctx.fillText('earth wire', cx + cw2 / 2 + 70, cy - 6);
                } else if (S.scenario === 'insulated') {
                    ctx.fillStyle = acc; ctx.font = '800 13px Inter'; ctx.fillText('✓ SAFE: no metal outside to become live — no earth wire needed', cx, cy + ch2 / 2 + 30);
                    ctx.strokeStyle = acc; ctx.lineWidth = 1.5; ctx.strokeRect(cx - 14, cy - 8, 28, 16); ctx.strokeRect(cx - 9, cy - 4, 18, 8);
                } else {
                    // unearthed: person touches, shock
                    ctx.strokeStyle = rose; ctx.lineWidth = 3; const px = cx + cw2 / 2 + 40;
                    ctx.beginPath(); ctx.arc(px, cy - 20, 9, 0, Math.PI * 2); ctx.moveTo(px, cy - 11); ctx.lineTo(px, cy + 20); ctx.moveTo(px, cy - 4); ctx.lineTo(cx + cw2 / 2, cy); ctx.moveTo(px, cy + 20); ctx.lineTo(px - 10, cy + 40); ctx.moveTo(px, cy + 20); ctx.lineTo(px + 10, cy + 40); ctx.stroke();
                    if (Math.sin(amb * 12) > 0) { ctx.fillStyle = rose; ctx.font = '800 16px Inter'; ctx.fillText('⚡', (px + cx + cw2 / 2) / 2, cy - 6); }
                    ctx.fillStyle = rose; ctx.font = '800 13px Inter'; ctx.fillText('✗ DANGER: case is LIVE — touching it gives a shock', cx, cy + ch2 / 2 + 44);
                }
            } else {
                // FUSE mode
                const I = S.applianceW / MAINS, rating = S.fuseRating;
                const blown = I > rating;
                const cx = w * 0.32, cy = h * 0.38;
                // fuse cartridge
                ctx.strokeStyle = ink; ctx.lineWidth = 2; ctx.strokeRect(cx - 60, cy - 18, 120, 36);
                ctx.fillStyle = '#2a2f37'; ctx.fillRect(cx - 52, cy - 6, 104, 12);
                if (blown) { ctx.strokeStyle = rose; ctx.lineWidth = 3; ctx.beginPath(); ctx.moveTo(cx - 40, cy); ctx.lineTo(cx - 6, cy); ctx.moveTo(cx + 6, cy); ctx.lineTo(cx + 40, cy); ctx.stroke(); ctx.fillStyle = rose; ctx.font = '800 12px Inter'; ctx.textAlign = 'center'; ctx.fillText('melted!', cx, cy - 26); }
                else { ctx.strokeStyle = amber; ctx.lineWidth = 3; ctx.beginPath(); ctx.moveTo(cx - 40, cy); ctx.lineTo(cx + 40, cy); ctx.stroke(); }
                ctx.fillStyle = ink; ctx.font = '700 12px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                ctx.fillText(`${rating} A fuse`, cx, cy + 40);
                // readouts
                ctx.textAlign = 'left'; ctx.font = '700 12px "JetBrains Mono", monospace';
                ctx.fillStyle = cyan; ctx.fillText(`normal current I = P/V = ${I.toFixed(1)} A`, w * 0.56, cy - 14);
                ctx.fillStyle = fuseGood ? acc : (fuseOK ? amber : rose);
                ctx.fillText(`fuse rating = ${rating} A`, w * 0.56, cy + 8);
                ctx.fillStyle = ink; ctx.font = '600 11px Inter';
                const verdict = blown ? 'too small: blows in normal use' : (fuseGood ? 'good: just above normal current' : 'safe but too high: won\'t protect well');
                ctx.fillText(verdict, w * 0.56, cy + 30);
                caption(`choose a fuse rating JUST ABOVE the normal current (${I.toFixed(1)} A) — here ${idealRating} A. A trip switch (circuit breaker) does the same job but resets.`, faint);
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const badge = { hazards: 'Electrical hazards', plug: 'Live / neutral / earth', fault: 'Fault: live touches case', fuse: 'Fuses & trip switches' }[mode];

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
                    <button className={'cw-btn ' + (mode === 'hazards' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('hazards')}>⚠ Hazards</button>
                    <button className={'cw-btn ' + (mode === 'plug' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('plug')}>🔌 Wiring</button>
                    <button className={'cw-btn ' + (mode === 'fault' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('fault')}>⚡ Fault</button>
                    <button className={'cw-btn ' + (mode === 'fuse' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('fuse')}>🔥 Fuse</button>
                </div>

                {mode === 'hazards' && (
                    <>
                        <div className="cw-btnrow">
                            {HAZARDS.map((hz, i) => (
                                <button key={hz.key} className={'cw-btn ' + (hazard === i ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setHazard(i)}>{hz.name}</button>
                            ))}
                        </div>
                        <Stat label="electrical hazard" value={HAZARDS[hazard].name} tone="warn" sub={HAZARDS[hazard].why} />
                        <Flag kind="neutral">
                            Common mains hazards: <b>damaged insulation</b> (bare wire → shock/short), <b>overheating cables</b>
                            (coiled or overloaded → fire), <b>damp conditions</b> (water conducts → shock) and <b>overloading sockets</b>
                            (too many appliances → excess current → overheating). Each is a route to a <b>shock</b> or a <b>fire</b>.
                        </Flag>
                    </>
                )}

                {mode === 'plug' && (
                    <>
                        <Stat label="the mains supply" value="live · neutral · earth" tone="acc"
                              sub={<>a mains circuit has three wires. The <b>switch</b> and the <b>fuse</b> are both put in the <b>live</b> wire so that switching off (or the fuse blowing) cuts the appliance off from the <b>dangerous live</b> supply.</>} />
                        <Flag kind="neutral">
                            A mains circuit has a <b>live (line)</b> wire, a <b>neutral</b> wire and an <b>earth</b> wire. The <b>switch must be in the
                            live wire</b>: then switching off leaves the appliance at the <b>same (safe) potential</b> as earth. If the switch were in
                            the neutral, the appliance would stay <b>live</b> even when switched off — dangerous to touch.
                        </Flag>
                    </>
                )}

                {mode === 'fault' && (
                    <>
                        <div className="cw-btnrow">
                            <button className={'cw-btn ' + (scenario === 'earthed' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setScenario('earthed')}>Earthed metal</button>
                            <button className={'cw-btn ' + (scenario === 'insulated' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setScenario('insulated')}>Double-insulated</button>
                            <button className={'cw-btn ' + (scenario === 'unearthed' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setScenario('unearthed')}>Unearthed (danger)</button>
                        </div>
                        <Stat label="live wire touches the case" value={scenario === 'earthed' ? 'earthed → safe' : scenario === 'insulated' ? 'insulated → safe' : 'unearthed → DANGER'} tone={scenario === 'unearthed' ? 'warn' : 'acc'}
                              sub={scenario === 'earthed' ? 'the earth wire carries a large current safely to ground, blowing the fuse and cutting the supply.' : scenario === 'insulated' ? 'a non-conducting (plastic) case cannot become live, so no earth wire is needed.' : 'with no earth, the metal case becomes live and anyone touching it gets a shock.'} />
                        <Flag kind="neutral">
                            If a <b>live wire</b> touches an <b>earthed metal case</b>, a <b>large current</b> flows through the low-resistance
                            <b> earth wire</b> to ground; this <b>blows the fuse</b> (or trips the breaker), cutting the supply and leaving the case
                            <b> safe</b>. So an appliance's outer casing must be either <b>earthed</b> (metal) or <b>non-conducting / double-insulated</b>
                            (plastic) — never bare metal with no earth.
                        </Flag>
                    </>
                )}

                {mode === 'fuse' && (
                    <>
                        <Stat label="fuse choice" value={`${fuseRating} A for ${normalI.toFixed(1)} A`} tone={fuseGood ? 'acc' : 'warn'}
                              sub={<>the normal current is <b>I = P/V = {normalI.toFixed(1)} A</b>. Choose the rating <b>just above</b> it — here <b>{idealRating} A</b>. Too small and it blows in normal use; too large and it won't protect the flex.</>} />
                        <Slider label="appliance power" value={applianceW} min={100} max={3000} step={100} onChange={setApplianceW} format={(x) => `${x} W`} />
                        <div className="cw-btnrow">
                            {RATINGS.map((r) => (
                                <button key={r} className={'cw-btn ' + (fuseRating === r ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setFuseRating(r)}>{r} A fuse</button>
                            ))}
                        </div>
                        <Flag kind="neutral">
                            A <b>fuse</b> is a thin wire that <b>melts</b> and breaks the circuit if the current exceeds its rating — protecting the flex
                            from overheating. Choose a rating <b>just above the normal operating current</b> (I = P/V). A <b>trip switch (circuit breaker)</b>
                            does the same job faster and can be <b>reset</b> instead of replaced. Both are placed in the <b>live wire</b> so a fault
                            disconnects the appliance from the live supply.
                        </Flag>
                    </>
                )}

                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, hazard: HAZARDS[hazard].key, scenario, applianceW, fuseRating })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
