import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: lens_lab ─────────────────────────────────────────────────────────
// Bespoke Thin Lenses hero (5054 · 3.2.3). Three modes:
//   FOCUS  — a parallel beam meets a rendered converging lens and is brought to
//            the principal focus F (focal length f marked on the principal
//            axis); switch to a diverging lens and the beam spreads as if from a
//            virtual focus. Defines principal axis / principal focus / f.
//   IMAGE  — a converging lens traces the three standard rays from an object
//            arrow; slide the object across 2F and F and watch the image go from
//            real-inverted-diminished → real-inverted-magnified → (at F) infinity
//            → virtual-upright-magnified (the magnifying glass). Live linear
//            magnification m = image height / object height = v / u.
//   EYE    — a rendered eyeball focusing a distant object; short sight focuses
//            in front of the retina (fixed by a diverging lens), long sight
//            behind it (fixed by a converging lens). Add the correcting lens and
//            the image lands back on the retina.
//
// Lake-bar immersion + direction-ray convention with live readouts.
// config: { mode, objectU, lensType, eyeCase, corrected }
// Notes: getState/setState carry the same keys.

const LENS_TYPES = ['converging', 'diverging'];
const EYE_CASES = ['normal', 'short', 'long'];

export default function LensLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['focus', 'image', 'eye'].includes(config.mode) ? config.mode : 'focus');
    const [objectU, setObjectU] = useState(typeof config.objectU === 'number' ? config.objectU : 1.8); // object distance in units of f
    const [lensType, setLensType] = useState(LENS_TYPES.includes(config.lensType) ? config.lensType : 'converging');
    const [eyeCase, setEyeCase] = useState(EYE_CASES.includes(config.eyeCase) ? config.eyeCase : 'short');
    const [corrected, setCorrected] = useState(!!config.corrected);
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, objectU, lensType, eyeCase, corrected };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, objectU: st.current.objectU, lensType: st.current.lensType, eyeCase: st.current.eyeCase, corrected: st.current.corrected }),
            setState: (s) => {
                if (['focus', 'image', 'eye'].includes(s?.mode)) setMode(s.mode);
                if (typeof s?.objectU === 'number') setObjectU(Math.max(0.4, Math.min(3, s.objectU)));
                if (LENS_TYPES.includes(s?.lensType)) setLensType(s.lensType);
                if (EYE_CASES.includes(s?.eyeCase)) setEyeCase(s.eyeCase);
                if (typeof s?.corrected === 'boolean') setCorrected(s.corrected);
            },
        });
    }, [onReady]); // eslint-disable-line

    // live magnification readout for the IMAGE mode
    const u = objectU;                                   // object distance / f
    const vOverF = Math.abs(u - 1) < 0.02 ? Infinity : u / (u - 1); // v / f  (v = uf/(u-f))
    const mSigned = Number.isFinite(vOverF) ? -vOverF / u : Infinity; // m = -v/u
    const mAbs = Number.isFinite(mSigned) ? Math.abs(mSigned) : Infinity;
    const isVirtual = u < 1;
    const imageDesc = !Number.isFinite(vOverF) ? 'no image (rays emerge parallel)'
        : isVirtual ? 'virtual · upright · magnified'
        : u > 2 ? 'real · inverted · diminished'
        : Math.abs(u - 2) < 0.06 ? 'real · inverted · same size'
        : 'real · inverted · magnified';

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
            bg.addColorStop(0, '#121820'); bg.addColorStop(1, '#0d1219');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);

            // arrowed ray from p0→p1 (glow + core + mid arrowhead); dashed=extension
            const ray = (x0, y0, x1, y1, col, dashed = false, wide = 1) => {
                if (dashed) {
                    ctx.strokeStyle = col.replace('1)', '.5)'); ctx.setLineDash([5, 5]); ctx.lineWidth = 1.3;
                    ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x1, y1); ctx.stroke(); ctx.setLineDash([]);
                    return;
                }
                ctx.strokeStyle = col.replace('1)', '.14)'); ctx.lineWidth = 7 * wide; ctx.lineCap = 'round';
                ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x1, y1); ctx.stroke();
                ctx.strokeStyle = col; ctx.lineWidth = 2.1 * wide;
                ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x1, y1); ctx.stroke();
                const ang = Math.atan2(y1 - y0, x1 - x0), mx = x0 + (x1 - x0) * 0.58, my = y0 + (y1 - y0) * 0.58;
                ctx.fillStyle = col;
                ctx.beginPath(); ctx.moveTo(mx + Math.cos(ang) * 7, my + Math.sin(ang) * 7);
                ctx.lineTo(mx - Math.cos(ang - 0.5) * 8, my - Math.sin(ang - 0.5) * 8);
                ctx.lineTo(mx - Math.cos(ang + 0.5) * 8, my - Math.sin(ang + 0.5) * 8); ctx.closePath(); ctx.fill();
            };
            const arrowObj = (x, ay, tipY, col, dashed = false) => {
                if (dashed) { ctx.setLineDash([4, 4]); }
                ctx.strokeStyle = col; ctx.fillStyle = col; ctx.lineWidth = 2.4;
                ctx.beginPath(); ctx.moveTo(x, ay); ctx.lineTo(x, tipY); ctx.stroke(); ctx.setLineDash([]);
                const dir = tipY < ay ? -1 : 1;
                ctx.beginPath(); ctx.moveTo(x, tipY); ctx.lineTo(x - 5, tipY + dir * 8); ctx.lineTo(x + 5, tipY + dir * 8); ctx.closePath(); ctx.fill();
            };
            const dot = (x, y, col, r = 3.5) => { ctx.fillStyle = col; ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2); ctx.fill(); };
            const axis = (ay) => {
                ctx.strokeStyle = 'rgba(255,255,255,.22)'; ctx.setLineDash([2, 4]); ctx.lineWidth = 1;
                ctx.beginPath(); ctx.moveTo(0, ay); ctx.lineTo(w, ay); ctx.stroke(); ctx.setLineDash([]);
            };
            // vertical lens glyph at cx; converging = biconvex + outward arrows, diverging = biconcave + inward arrows
            const lens = (cx, ay, hh, converging) => {
                const top = ay - hh, bot = ay + hh, bulge = converging ? 11 : -9;
                const g = ctx.createLinearGradient(cx - 14, 0, cx + 14, 0);
                g.addColorStop(0, 'rgba(120,210,220,.10)'); g.addColorStop(0.5, 'rgba(150,225,235,.30)'); g.addColorStop(1, 'rgba(120,210,220,.10)');
                ctx.fillStyle = g;
                ctx.beginPath();
                ctx.moveTo(cx, top);
                ctx.quadraticCurveTo(cx + bulge, ay, cx, bot);
                ctx.quadraticCurveTo(cx - bulge, ay, cx, top);
                ctx.closePath(); ctx.fill();
                ctx.strokeStyle = 'rgba(185,240,245,.8)'; ctx.lineWidth = 1.8; ctx.stroke();
                // convention arrowheads at lens ends
                ctx.fillStyle = 'rgba(185,240,245,.9)';
                const ah = (y, up) => { const d = up ? -1 : 1; const yy = y; const inward = converging ? 1 : -1;
                    // outward (converging) heads point away from centre; inward (diverging) toward centre
                    ctx.beginPath();
                    if (converging) { ctx.moveTo(cx, yy + d * 9); ctx.lineTo(cx - 5, yy + d * 2); ctx.lineTo(cx + 5, yy + d * 2); }
                    else { ctx.moveTo(cx, yy - d * 2); ctx.lineTo(cx - 5, yy - d * 9); ctx.lineTo(cx + 5, yy - d * 9); }
                    ctx.closePath(); ctx.fill(); void inward;
                };
                ah(top, true); ah(bot, false);
            };
            const tick = (x, ay, label, col) => {
                ctx.strokeStyle = col; ctx.lineWidth = 1.4;
                ctx.beginPath(); ctx.moveTo(x, ay - 6); ctx.lineTo(x, ay + 6); ctx.stroke();
                ctx.fillStyle = col; ctx.font = '700 9px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                ctx.fillText(label, x, ay + 18);
            };

            if (S.mode === 'focus') {
                const cx = w * 0.5, ay = h * 0.5, f = w * 0.20, hh = h * 0.34, conv = S.lensType === 'converging';
                axis(ay); lens(cx, ay, hh, conv);
                const Fx = conv ? cx + f : cx - f, Fx2 = conv ? cx - f : cx + f;
                // focal points + f bracket
                dot(Fx, ay, amber); tick(Fx, ay, conv ? 'F' : 'F (virtual)', amber);
                dot(Fx2, ay, faint, 2.5);
                ctx.strokeStyle = amber; ctx.setLineDash([4, 3]); ctx.lineWidth = 1;
                ctx.beginPath(); ctx.moveTo(cx, ay + hh + 8); ctx.lineTo(Fx, ay + hh + 8); ctx.stroke(); ctx.setLineDash([]);
                ctx.fillStyle = amber; ctx.font = '700 10px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                ctx.fillText('f', (cx + Fx) / 2, ay + hh + 22);
                // parallel incoming beam → refracted
                const ys = [-0.72, -0.36, 0, 0.36, 0.72].map((k) => ay + k * hh);
                ys.forEach((y) => {
                    ray(w * 0.04, y, cx, y, 'rgba(255,236,150,1)');            // parallel in
                    if (conv) {
                        // all cross at F on far side
                        const slope = (ay - y) / f;
                        ray(cx, y, w * 0.96, y + slope * (w * 0.96 - cx), 'rgba(255,236,150,1)');
                    } else {
                        // spread as if from virtual focus on the near side
                        const slope = (y - ay) / f;
                        ray(cx, y, w * 0.96, y + slope * (w * 0.96 - cx), 'rgba(255,236,150,1)');
                        ray(cx, y, Fx, ay, 'rgba(255,236,150,1)', true);       // dashed back-extension to virtual F
                    }
                });
                if (conv) dot(Fx, ay, '#fff2c0', 5);
                ctx.fillStyle = ink; ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(conv ? 'parallel rays converge to the principal focus F'
                    : 'parallel rays diverge — they appear to come from the virtual focus F', w * 0.5, h - 12);
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'left';
                ctx.fillText('principal axis', 8, ay - 6);
            } else if (S.mode === 'image') {
                const cx = w * 0.52, ay = h * 0.52, f = w * 0.155, hh = h * 0.30;
                axis(ay); lens(cx, ay, hh, true);
                // principal foci and 2F
                [[cx - f, 'F'], [cx + f, "F'"], [cx - 2 * f, '2F'], [cx + 2 * f, "2F'"]].forEach(([x, l]) =>
                    { dot(x, ay, l.includes('2') ? faint : amber, l.includes('2') ? 2.4 : 3.3); tick(x, ay, l, l.includes('2') ? faint : amber); });

                const uu = S.objectU, px = cx - uu * f, ho = hh * 0.72, py = ay - ho;  // object tip
                arrowObj(px, ay, py, acc);
                ctx.fillStyle = acc; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('object', px, ay + 20);

                const near1 = Math.abs(uu - 1) < 0.02;
                if (near1) {
                    // rays emerge parallel — image at infinity
                    ray(px, py, cx, py, 'rgba(255,236,150,1)');
                    const sl = (ay - py) / f;
                    ray(cx, py, w * 0.99, py + sl * (w * 0.99 - cx), 'rgba(255,236,150,1)');
                    ray(px, py, w * 0.99, ay + (ay - py) / (cx - px) * (w * 0.99 - cx), 'rgba(120,220,255,1)');
                    ctx.fillStyle = rose; ctx.font = '700 11px Inter, sans-serif'; ctx.textAlign = 'center';
                    ctx.fillText('object at F → image at infinity', w * 0.5, h - 12);
                } else {
                    const v = uu * f / (uu - 1);            // signed image distance (px units of f already baked)
                    const xImg = cx + v;
                    const mS = -v / (uu * f);                // = -v/u   (v,u both in px here)
                    const yImg = ay - mS * ho;
                    const virtual = v < 0;
                    // Ray 1: parallel → through F'
                    ray(px, py, cx, py, 'rgba(255,236,150,1)');
                    const r1slope = (ay - py) / f;
                    const r1end = virtual ? w * 0.99 : Math.min(w * 0.99, Math.max(xImg, cx + 4));
                    ray(cx, py, r1end, py + r1slope * (r1end - cx), 'rgba(255,236,150,1)');
                    // Ray 2: straight through optical centre O
                    const r2slope = (ay - py) / (cx - px);
                    const r2end = virtual ? w * 0.99 : r1end;
                    ray(px, py, r2end, py + r2slope * (r2end - px), 'rgba(120,220,255,1)');
                    // Ray 3: through front focus F → emerges parallel
                    const yL3 = py + (ay - py) / (px - (cx - f)) * (cx - px);
                    ray(px, py, cx, yL3, 'rgba(190,255,170,1)');
                    ray(cx, yL3, virtual ? w * 0.99 : r1end, yL3, 'rgba(190,255,170,1)');

                    if (virtual) {
                        // dashed back-extensions to the virtual image (same side as object)
                        ray(cx, py, xImg, yImg, 'rgba(255,236,150,1)', true);
                        ray(px, py, xImg, yImg, 'rgba(120,220,255,1)', true);
                        ray(cx, yL3, xImg, yImg, 'rgba(190,255,170,1)', true);
                        arrowObj(xImg, ay, yImg, amber, true);
                        ctx.fillStyle = amber; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
                        ctx.fillText('virtual image', xImg, ay - Math.abs(yImg - ay) - 8 > 10 ? yImg - 8 : ay + 34);
                    } else {
                        arrowObj(xImg, ay, yImg, amber);
                        dot(xImg, yImg, '#fff2c0', 4);
                        ctx.fillStyle = amber; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'center';
                        ctx.fillText('real image', xImg, yImg + (yImg > ay ? 16 : -8));
                    }
                    ctx.fillStyle = ink; ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
                    ctx.fillText(`m = image / object = ${Math.abs(mS).toFixed(2)}   (${virtual ? 'upright' : 'inverted'})`, w * 0.5, h - 12);
                }
            } else {
                // EYE mode — rendered eyeball focusing a distant object
                const eyeX = w * 0.60, ay = h * 0.5, R = Math.min(w * 0.22, h * 0.40);
                const retinaX = eyeX + R;              // back of the eye
                const corneaX = eyeX - R;              // front (eye lens plane)
                const cse = S.eyeCase, corr = S.corrected && cse !== 'normal';
                // eyeball
                const eg = ctx.createRadialGradient(eyeX - R * 0.3, ay - R * 0.3, R * 0.2, eyeX, ay, R);
                eg.addColorStop(0, 'rgba(230,244,255,.14)'); eg.addColorStop(1, 'rgba(120,150,180,.10)');
                ctx.fillStyle = eg; ctx.beginPath(); ctx.arc(eyeX, ay, R, 0, Math.PI * 2); ctx.fill();
                ctx.strokeStyle = 'rgba(200,225,245,.5)'; ctx.lineWidth = 2; ctx.stroke();
                // retina (back inner wall)
                ctx.strokeStyle = 'rgba(251,113,133,.7)'; ctx.lineWidth = 4;
                ctx.beginPath(); ctx.arc(eyeX, ay, R - 2, -Math.PI / 3, Math.PI / 3); ctx.stroke();
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'left';
                ctx.fillText('retina', retinaX - 6, ay - R + 12);
                // eye lens
                lens(corneaX + 8, ay, R * 0.5, true);
                // correcting spectacle lens in front
                const specX = corneaX - w * 0.13;
                if (corr) lens(specX, ay, R * 0.6, cse === 'long');   // long→converging, short→diverging

                // where the uncorrected eye focuses (short = in front of retina, long = behind)
                const focusShift = cse === 'short' ? -R * 0.5 : cse === 'long' ? R * 0.7 : 0;
                const rawFocusX = retinaX + focusShift;
                // two parallel rays from a distant object, offset above/below axis
                const offs = [-R * 0.34, R * 0.34];
                offs.forEach((o) => {
                    const y0 = ay + o;
                    ray(w * 0.02, y0, corr ? specX : corneaX + 8, y0, 'rgba(255,236,150,1)');
                    let yAtLens = y0, slope;
                    if (corr) {
                        // spectacle lens pre-bends; net effect lands focus on retina
                        ray(specX, y0, corneaX + 8, y0 + (cse === 'long' ? -o * 0.18 : o * 0.18), 'rgba(255,236,150,1)');
                        yAtLens = y0 + (cse === 'long' ? -o * 0.18 : o * 0.18);
                        slope = (ay - yAtLens) / (retinaX - (corneaX + 8));   // → retina
                        ray(corneaX + 8, yAtLens, retinaX, ay, 'rgba(255,236,150,1)');
                        void slope;
                    } else {
                        slope = (ay - yAtLens) / (rawFocusX - (corneaX + 8));
                        const endX = cse === 'long' ? retinaX : rawFocusX;
                        ray(corneaX + 8, yAtLens, endX, yAtLens + slope * (endX - (corneaX + 8)), 'rgba(255,236,150,1)');
                        if (cse === 'short') {
                            // rays cross in front then spread onto retina blurred
                            ray(rawFocusX, ay, retinaX, ay + (ay - yAtLens) * 0.5, 'rgba(255,236,150,1)', false, 0.7);
                        }
                    }
                });
                if (!corr && cse !== 'normal') dot(rawFocusX, ay, rose, 4);
                if (corr || cse === 'normal') dot(retinaX, ay, acc, 4);
                ctx.textAlign = 'center'; ctx.font = '700 11px Inter, sans-serif';
                if (cse === 'normal') { ctx.fillStyle = acc; ctx.fillText('normal eye — distant object focuses on the retina', w * 0.5, h - 12); }
                else if (!corr) { ctx.fillStyle = rose; ctx.fillText(cse === 'short' ? 'short sight — focus falls IN FRONT of the retina' : 'long sight — focus falls BEHIND the retina', w * 0.5, h - 12); }
                else { ctx.fillStyle = acc; ctx.fillText(cse === 'short' ? 'a diverging (concave) lens moves the focus back onto the retina' : 'a converging (convex) lens moves the focus forward onto the retina', w * 0.5, h - 12); }
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    const badge = mode === 'focus'
        ? (lensType === 'converging' ? 'Converging lens · principal focus F' : 'Diverging lens · virtual focus')
        : mode === 'image' ? 'Ray diagram · real ↔ virtual image'
            : 'The eye · focusing & correction';

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
                    <button className={'cw-btn ' + (mode === 'focus' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('focus')}>🔦 Focal point</button>
                    <button className={'cw-btn ' + (mode === 'image' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('image')}>🖼 Ray diagram</button>
                    <button className={'cw-btn ' + (mode === 'eye' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('eye')}>👁 The eye</button>
                </div>

                {mode === 'focus' && (
                    <>
                        <div className="cw-btnrow">
                            <button className={'cw-btn ' + (lensType === 'converging' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setLensType('converging')}>Converging</button>
                            <button className={'cw-btn ' + (lensType === 'diverging' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setLensType('diverging')}>Diverging</button>
                        </div>
                        <Stat label={lensType === 'converging' ? 'converging (convex) lens' : 'diverging (concave) lens'}
                              value={lensType === 'converging' ? 'brings rays TO F' : 'spreads rays FROM F'} tone="acc"
                              sub={<>the <b>principal focus F</b> is where rays parallel to the <b>principal axis</b> meet (converging) or appear to come from (diverging); its distance from the lens is the <b>focal length f</b></>} />
                        <Flag kind="neutral">
                            A <b>converging</b> lens is thickest in the middle: parallel rays are bent inward and cross at the
                            <b> principal focus F</b>. A <b>diverging</b> lens is thinnest in the middle: parallel rays spread out and
                            appear to come from a <b>virtual focus</b> on the same side. The distance from lens to F is the
                            <b> focal length f</b>.
                        </Flag>
                    </>
                )}

                {mode === 'image' && (
                    <>
                        <Stat label="linear magnification  m = image height / object height"
                              value={Number.isFinite(mAbs) ? mAbs.toFixed(2) + '×' : '∞'} tone={isVirtual ? 'warn' : 'acc'}
                              sub={<>{imageDesc}. Slide the object across <b>2F</b> and <b>F</b>: beyond 2F the image is small and inverted; between F and 2F it is magnified and inverted; <b>inside F</b> it becomes an upright, magnified <b>virtual</b> image — the magnifying glass.</>} />
                        <Slider label="object distance u" value={objectU} min={0.4} max={3} step={0.1} onChange={setObjectU}
                                format={(x) => `${x.toFixed(1)} f${Math.abs(x - 1) < 0.05 ? '  (= F)' : Math.abs(x - 2) < 0.05 ? '  (= 2F)' : x < 1 ? '  (inside F)' : ''}`} />
                        <Flag kind="neutral">
                            Three rays fix the image: one <b>parallel to the axis</b> then through F, one <b>straight through the
                            centre</b>, one <b>through the near focus</b> then parallel. Where they cross is the image. With the object
                            <b> inside the focal length</b> the rays leave diverging, so the image is <b>virtual, upright and
                            magnified</b> — exactly how a magnifying glass works.
                        </Flag>
                    </>
                )}

                {mode === 'eye' && (
                    <>
                        <div className="cw-btnrow">
                            <button className={'cw-btn ' + (eyeCase === 'normal' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setEyeCase('normal')}>Normal</button>
                            <button className={'cw-btn ' + (eyeCase === 'short' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setEyeCase('short')}>Short sight</button>
                            <button className={'cw-btn ' + (eyeCase === 'long' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setEyeCase('long')}>Long sight</button>
                        </div>
                        <Stat label={eyeCase === 'normal' ? 'normal vision' : eyeCase === 'short' ? 'short sight (myopia)' : 'long sight (hypermetropia)'}
                              value={eyeCase === 'normal' ? 'on the retina' : corrected ? 'corrected → on the retina' : eyeCase === 'short' ? 'in front of retina' : 'behind retina'}
                              tone={eyeCase === 'normal' || corrected ? 'acc' : 'warn'}
                              sub={eyeCase === 'normal' ? <>a relaxed normal eye focuses distant light exactly on the retina</>
                                  : eyeCase === 'short' ? <>the eye is too powerful, so distant light focuses <b>before</b> the retina — a <b>diverging</b> lens fixes it</>
                                      : <>the eye is too weak, so distant light would focus <b>behind</b> the retina — a <b>converging</b> lens fixes it</>} />
                        {eyeCase !== 'normal' && (
                            <button className={'cw-btn ' + (corrected ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setCorrected(!corrected)}>
                                {corrected ? '✓ correcting lens on' : `add the correcting (${eyeCase === 'short' ? 'diverging' : 'converging'}) lens`}
                            </button>
                        )}
                        <Flag kind="neutral">
                            The eye focuses light onto the <b>retina</b>. In <b>short sight</b> the image of a distant object forms in
                            front of the retina; a <b>diverging (concave)</b> lens spreads the light first so it focuses further back.
                            In <b>long sight</b> the image would form behind the retina; a <b>converging (convex)</b> lens brings it
                            forward. Each correcting lens moves the focus back onto the retina.
                        </Flag>
                    </>
                )}

                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, objectU, lensType, eyeCase, corrected })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
