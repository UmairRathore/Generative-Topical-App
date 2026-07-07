import React, { useEffect, useMemo, useRef, useState } from 'react';
import { SCI, cssVar, mixHex } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: photosynthesis_lake ──────────────────────────────────────────────
// Limiting-factors of photosynthesis. An underwater lake scene (sun → light
// shafts → pondweed bubbling O2) driven by a Blackman limiting-factors model,
// plus a live rate-vs-light-intensity graph. Muddying the water lowers the light
// reaching the plants and makes LIGHT the limiting factor — the exam concept.
//
// Props:
//   config      : { sun, clarity, co2, temp } (0-100, °C) — per-question params
//   onReady     : (api) => void   — receives { getState, setState } for notes
//   onAddToNote : (config) => void — emits the "add to note" intent

const DEFAULTS = { sun: 80, clarity: 90, co2: 70, temp: 22 };
const LIGHT_SAT = 0.55;

function tempFactor(T) {
    const f = Math.exp(-Math.pow((T - 28) / 11, 2));
    return T > 28 ? f * Math.exp(-Math.max(0, T - 32) / 6) : f;   // optimum ~28°C, then denature
}
function model(st) {
    const effLight = (st.sun / 100) * (st.clarity / 100);        // turbidity + depth proxy cuts light
    const co2 = st.co2 / 100, tf = tempFactor(st.temp);
    const plateau = Math.min(co2, tf);                           // non-light ceiling
    const lightFrac = Math.min(1, effLight / LIGHT_SAT);
    const rate = plateau * lightFrac;
    const lim = lightFrac < 0.995 ? 'light' : (co2 <= tf ? 'carbon dioxide' : 'temperature');
    return { effLight, co2, tf, plateau, lightFrac, rate, lim };
}

function drawGraph(cv, st) {
    if (!cv) return;
    const M = model(st);
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const ctx = cv.getContext('2d');
    const w = cv.clientWidth || 300, h = 140;
    cv.width = w * dpr; cv.height = h * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, w, h);
    const pad = { l: 32, r: 12, t: 12, b: 24 }, gw = w - pad.l - pad.r, gh = h - pad.t - pad.b;
    const ink = cssVar('--ink-soft', '#A6C4B3'), line = cssVar('--line', 'rgba(160,200,175,.16)'), acc = cssVar('--ok', '#34D399');
    ctx.strokeStyle = line; ctx.lineWidth = 1;
    ctx.beginPath(); ctx.moveTo(pad.l, pad.t); ctx.lineTo(pad.l, pad.t + gh); ctx.lineTo(pad.l + gw, pad.t + gh); ctx.stroke();
    ctx.fillStyle = ink; ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
    ctx.fillText('light reaching plants →', pad.l + gw / 2, h - 5);
    ctx.save(); ctx.translate(10, pad.t + gh / 2); ctx.rotate(-Math.PI / 2); ctx.fillText('rate →', 0, 0); ctx.restore();
    const X = (L) => pad.l + L * gw, Y = (r) => pad.t + gh - r * gh;
    ctx.strokeStyle = acc; ctx.lineWidth = 2.4; ctx.beginPath();
    for (let i = 0; i <= 60; i++) { const L = i / 60, r = M.plateau * Math.min(1, L / LIGHT_SAT); i ? ctx.lineTo(X(L), Y(r)) : ctx.moveTo(X(L), Y(r)); }
    ctx.stroke();
    ctx.strokeStyle = 'rgba(160,200,175,.35)'; ctx.setLineDash([4, 4]);
    ctx.beginPath(); ctx.moveTo(pad.l, Y(M.plateau)); ctx.lineTo(pad.l + gw, Y(M.plateau)); ctx.stroke();
    ctx.strokeStyle = 'rgba(251,191,36,.4)'; ctx.beginPath(); ctx.moveTo(X(LIGHT_SAT), pad.t); ctx.lineTo(X(LIGHT_SAT), pad.t + gh); ctx.stroke();
    ctx.setLineDash([]);
    const opx = X(Math.min(1, M.effLight)), opy = Y(M.rate);
    ctx.fillStyle = SCI.sun; ctx.strokeStyle = '#fff'; ctx.lineWidth = 2;
    ctx.beginPath(); ctx.arc(opx, opy, 6, 0, 6.283); ctx.fill(); ctx.stroke();
    ctx.strokeStyle = 'rgba(251,191,36,.5)'; ctx.setLineDash([2, 3]);
    ctx.beginPath(); ctx.moveTo(opx, opy); ctx.lineTo(opx, pad.t + gh); ctx.moveTo(opx, opy); ctx.lineTo(pad.l, opy); ctx.stroke(); ctx.setLineDash([]);
}

export default function PhotosynthesisLake({ config = {}, onReady, onAddToNote }) {
    const [st, setSt] = useState({ ...DEFAULTS, ...config });
    const stRef = useRef(st); stRef.current = st;
    const set = (k, v) => setSt((s) => ({ ...s, [k]: +v }));
    const M = useMemo(() => model(st), [st]);

    const lakeRef = useRef(null);
    const graphRef = useRef(null);

    // expose the notes API once
    useEffect(() => {
        onReady && onReady({ getState: () => stRef.current, setState: (s) => setSt((x) => ({ ...x, ...s })) });
    }, [onReady]);

    // lake animation loop
    useEffect(() => {
        const cv = lakeRef.current, host = cv.parentElement;
        const ctx = cv.getContext('2d');
        const W = 1000, H = 688;
        let scale = 1, ox = 0, oy = 0, dpr = 1, raf = 0, last = 0;
        const bubbles = [], mud = [];
        const WEED = [{ x: 300, s: 0 }, { x: 430, s: 1.1 }, { x: 540, s: 2.2 }, { x: 670, s: 0.6 }];
        for (let i = 0; i < 70; i++) mud.push({ x: Math.random() * W, y: 150 + Math.random() * (H - 190), vx: (Math.random() - .5) * .2, vy: (Math.random() - .4) * .3, r: 1 + Math.random() * 2.4, ph: Math.random() * 6.28 });

        const resize = () => {
            const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return;
            dpr = Math.min(window.devicePixelRatio || 1, 2);
            cv.width = w * dpr; cv.height = h * dpr;
            scale = Math.min(w / W, h / H); ox = (w - W * scale) / 2; oy = (h - H * scale) / 2;
        };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const frame = (time) => {
            if (!last) last = time; last = time;
            const st = stRef.current, M = model(st), clar = st.clarity / 100, murk = 1 - clar, surf = 140;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * scale, 0, 0, dpr * scale, ox * dpr, oy * dpr);

            // sky + sun
            const sky = ctx.createLinearGradient(0, 0, 0, surf); sky.addColorStop(0, '#0a2036'); sky.addColorStop(1, '#0f3a55');
            ctx.fillStyle = sky; ctx.fillRect(0, 0, W, surf);
            const sx = 150, sy = 64, sunB = st.sun / 100;
            ctx.save(); ctx.globalAlpha = .35 + sunB * .5; ctx.strokeStyle = SCI.sun; ctx.lineCap = 'round';
            for (let i = 0; i < 10; i++) { const a = i / 10 * 6.283 + time * 3e-4; ctx.lineWidth = 3; ctx.beginPath(); ctx.moveTo(sx + Math.cos(a) * 38, sy + Math.sin(a) * 38); ctx.lineTo(sx + Math.cos(a) * (52 + Math.sin(time * .003 + i) * 6), sy + Math.sin(a) * (52 + Math.sin(time * .003 + i) * 6)); ctx.stroke(); }
            ctx.restore();
            const sg = ctx.createRadialGradient(sx, sy, 4, sx, sy, 34); sg.addColorStop(0, '#fff7dc'); sg.addColorStop(.55, SCI.sun); sg.addColorStop(1, SCI.sunDeep);
            ctx.fillStyle = sg; ctx.beginPath(); ctx.arc(sx, sy, 34, 0, 6.283); ctx.fill();

            // water
            const wg = ctx.createLinearGradient(0, surf, 0, H);
            wg.addColorStop(0, mixHex('#12496b', SCI.mud, murk * .6)); wg.addColorStop(1, mixHex('#06202f', SCI.mud, murk * .75));
            ctx.fillStyle = wg; ctx.fillRect(0, surf, W, H - surf);
            ctx.strokeStyle = 'rgba(255,255,255,.18)'; ctx.lineWidth = 2; ctx.beginPath();
            for (let x = 0; x <= W; x += 12) { const y = surf + Math.sin(x * .04 + time * .002) * 3; x ? ctx.lineTo(x, y) : ctx.moveTo(x, y); } ctx.stroke();

            // light shafts
            const reach = surf + (H - surf) * Math.min(1, .3 + clar * sunB * .95);
            for (let i = 0; i < 5; i++) { const gx = sx - 40 + i * 44; const g = ctx.createLinearGradient(0, surf, 0, reach); g.addColorStop(0, `rgba(255,240,180,${(.18 + sunB * .22) * clar})`); g.addColorStop(1, 'rgba(255,240,180,0)'); ctx.fillStyle = g; ctx.beginPath(); ctx.moveTo(gx, surf); ctx.lineTo(gx + 26, surf); ctx.lineTo(gx + 116, reach); ctx.lineTo(gx - 30, reach); ctx.closePath(); ctx.fill(); }
            ctx.fillStyle = `rgba(255,240,180,${.5 + .5 * clar})`; ctx.font = '600 15px "JetBrains Mono", monospace'; ctx.textAlign = 'left';
            ctx.fillText('light reaching plants: ' + Math.round(M.effLight * 100) + '%', 30, surf + 34);

            // suspended mud
            if (murk > .08) { ctx.fillStyle = SCI.mud; for (const p of mud) { p.x += p.vx; p.y += p.vy; p.ph += .02; if (p.x < 0) p.x = W; if (p.x > W) p.x = 0; if (p.y > H - 30) p.y = surf + 10; if (p.y < surf) p.y = H - 40; ctx.globalAlpha = murk * (.25 + .2 * Math.sin(p.ph)); ctx.beginPath(); ctx.arc(p.x, p.y, p.r, 0, 6.283); ctx.fill(); } ctx.globalAlpha = 1; }

            // bed
            const bed = ctx.createLinearGradient(0, H - 70, 0, H); bed.addColorStop(0, mixHex('#3a2f22', SCI.mud, .5)); bed.addColorStop(1, '#241c14');
            ctx.fillStyle = bed; ctx.beginPath(); ctx.moveTo(0, H - 55); for (let x = 0; x <= W; x += 40) ctx.lineTo(x, H - 55 + Math.sin(x * .03) * 7); ctx.lineTo(W, H); ctx.lineTo(0, H); ctx.closePath(); ctx.fill();

            // pondweed
            const vigor = .5 + M.rate * .7;
            WEED.forEach((w) => {
                const h = 90 + vigor * 90, segs = 7;
                ctx.strokeStyle = mixHex('#1f5a3a', SCI.leaf, vigor); ctx.lineWidth = 5; ctx.lineCap = 'round';
                ctx.beginPath(); ctx.moveTo(w.x, H - 58);
                for (let s = 1; s <= segs; s++) { const t = s / segs, sway = Math.sin(time * .0016 + w.s + t * 2) * (10 + t * 16) * (.5 + vigor * .6); ctx.lineTo(w.x + sway, H - 58 - t * h); } ctx.stroke();
                ctx.fillStyle = mixHex('#2a6b45', SCI.leaf, vigor);
                for (let s = 2; s <= segs; s++) { const t = s / segs, sway = Math.sin(time * .0016 + w.s + t * 2) * (10 + t * 16) * (.5 + vigor * .6), lx = w.x + sway, ly = H - 58 - t * h, dir = (s % 2) ? 1 : -1; ctx.beginPath(); ctx.ellipse(lx + dir * 9, ly, 11, 5, dir * .5, 0, 6.283); ctx.fill(); }
            });

            // O2 bubbles ∝ rate
            if (Math.random() < M.rate * .9) { const w = WEED[(Math.random() * WEED.length) | 0]; bubbles.push({ x: w.x + (Math.random() - .5) * 26, y: H - 70, r: 2 + Math.random() * 4, v: .6 + Math.random() * .8, wob: Math.random() * 6.28 }); }
            ctx.strokeStyle = SCI.o2; ctx.fillStyle = 'rgba(127,227,255,.18)';
            for (let i = bubbles.length - 1; i >= 0; i--) { const b = bubbles[i]; b.y -= b.v * (1 + vigor); b.wob += .1; b.x += Math.sin(b.wob) * .4; ctx.lineWidth = 1.4; ctx.beginPath(); ctx.arc(b.x, b.y, b.r, 0, 6.283); ctx.fill(); ctx.stroke(); if (b.y < surf + 4) bubbles.splice(i, 1); }

            raf = requestAnimationFrame(frame);
        };
        raf = requestAnimationFrame(frame);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, []);

    // graph redraw on state change (+ once on mount)
    useEffect(() => { drawGraph(graphRef.current, st); }, [st]);
    useEffect(() => {
        const onResize = () => drawGraph(graphRef.current, stRef.current);
        window.addEventListener('resize', onResize); return () => window.removeEventListener('resize', onResize);
    }, []);

    const clarityWord = st.clarity > 75 ? 'clear' : st.clarity > 45 ? 'hazy' : st.clarity > 22 ? 'murky' : 'muddy';
    const flag = M.lim === 'light' && st.clarity < 40
        ? { kind: 'warn', node: <>🌧 Murkier / deeper water has lowered the <b>light intensity</b> at the plants — light is now limiting, so the rate falls. That is exam answer <b>C</b>.</> }
        : M.lim === 'light'
            ? { kind: 'warn', node: <>Light is the limiting factor — raise the light (or clear the water) to speed photosynthesis up.</> }
            : { kind: 'ok', node: <>Plenty of light — the rate is now capped by <b>{M.lim}</b>. Stir the mud to make light the limiting factor.</> };

    return (
        <div className="cw-lake">
            <div className="cw-stage-col">
                <div className="cw-stage">
                    <span className="cw-badge">Live · rate ∝ limiting factor</span>
                    <canvas ref={lakeRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label="Relative rate of photosynthesis" value={Math.round(M.rate * 100)} unit="%"
                      sub={<>Limiting factor now: <b>{M.lim}</b></>} />
                <div className="cw-graph"><canvas ref={graphRef} /></div>
                <Slider label="Surface sunlight" value={st.sun} min={0} max={100} tone={SCI.sun} format={(v) => v + '%'} onChange={(v) => set('sun', v)} />
                <Slider label="Water clarity" value={st.clarity} min={5} max={100} tone={SCI.mud} format={() => clarityWord} onChange={(v) => set('clarity', v)} />
                <Slider label="CO₂ available" value={st.co2} min={0} max={100} tone="var(--cw-acc)" format={(v) => v + '%'} onChange={(v) => set('co2', v)} />
                <Slider label="Water temperature" value={st.temp} min={2} max={45} tone={SCI.water} format={(v) => v + '°C'} onChange={(v) => set('temp', v)} />
                <div className="cw-btnrow">
                    <button className="cw-btn cw-btn-ghost" onClick={() => setSt({ sun: 80, clarity: 90, co2: 70, temp: 22 })}>☀ Clear day</button>
                    <button className="cw-btn cw-btn-mud" onClick={() => set('clarity', 18)}>🌧 Stir the mud</button>
                </div>
                <Flag kind={flag.kind}>{flag.node}</Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote(stRef.current)}>📌 Save this diagram to my notes</button>
                )}
            </div>
        </div>
    );
}
