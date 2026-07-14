import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: reflection_lab ───────────────────────────────────────────────────
// Bespoke Reflection of Light hero (5054 · 3.2.1). Two modes: LAW — a ray box
// sends a glowing beam onto a plane mirror; the incident and reflected rays,
// the normal, and the angles of incidence and reflection are drawn with live
// degree readouts showing i = r as you drag the angle. IMAGE — an object in
// front of a plane mirror with its virtual image behind, two rays traced to the
// eye and their dashed extensions meeting at the image (same size, same
// distance, virtual, laterally inverted).
//
// Lake-bar immersion + direction-ray convention. config: { mode, angle } ·
// Notes: getState/setState carry {mode, angle}.

export default function ReflectionLab({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(config.mode === 'image' ? 'image' : 'law');
    const [angle, setAngle] = useState(typeof config.angle === 'number' ? config.angle : 40);   // degrees, angle of incidence
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { mode, angle };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, angle: st.current.angle }),
            setState: (s) => {
                if (s?.mode === 'law' || s?.mode === 'image') setMode(s.mode);
                if (typeof s?.angle === 'number') setAngle(Math.max(0, Math.min(80, s.angle)));
            },
        });
    }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf;
        const draw = () => {
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight; if (!w || !h) { raf = requestAnimationFrame(draw); return; }
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const acc = cssVar('--ok', '#34D399'), amber = '#FBBF24', cyan = '#38BDF8';
            const S = st.current;
            // optics-bench backdrop
            const bg = ctx.createLinearGradient(0, 0, 0, h);
            bg.addColorStop(0, '#141a22'); bg.addColorStop(1, '#0e131a');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);

            // glowing light beam (glow underlay + bright core) with an arrowhead
            const beam = (x0, y0, x1, y1, col) => {
                ctx.strokeStyle = col.replace('1)', '.18)'); ctx.lineWidth = 8; ctx.lineCap = 'round';
                ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x1, y1); ctx.stroke();
                ctx.strokeStyle = col; ctx.lineWidth = 2.4;
                ctx.beginPath(); ctx.moveTo(x0, y0); ctx.lineTo(x1, y1); ctx.stroke();
                const ang = Math.atan2(y1 - y0, x1 - x0), mx = (x0 + x1) / 2, my = (y0 + y1) / 2;
                ctx.fillStyle = col;
                ctx.beginPath(); ctx.moveTo(mx + Math.cos(ang) * 8, my + Math.sin(ang) * 8);
                ctx.lineTo(mx - Math.cos(ang - 0.5) * 9, my - Math.sin(ang - 0.5) * 9);
                ctx.lineTo(mx - Math.cos(ang + 0.5) * 9, my - Math.sin(ang + 0.5) * 9); ctx.closePath(); ctx.fill();
            };

            if (S.mode === 'law') {
                const hitX = w * 0.44, my = h * 0.66, L = Math.min(w * 0.32, h * 0.5);
                const iRad = S.angle * Math.PI / 180;
                // mirror (horizontal) with silvered back hatching
                ctx.strokeStyle = '#cfe0ee'; ctx.lineWidth = 3;
                ctx.beginPath(); ctx.moveTo(w * 0.10, my); ctx.lineTo(w * 0.78, my); ctx.stroke();
                ctx.strokeStyle = 'rgba(160,180,200,.5)'; ctx.lineWidth = 1;
                for (let x = w * 0.10; x < w * 0.78; x += 10) { ctx.beginPath(); ctx.moveTo(x, my + 2); ctx.lineTo(x - 6, my + 9); ctx.stroke(); }
                ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'right';
                ctx.fillText('plane mirror', w * 0.77, my + 22);
                // normal (dashed, perpendicular = vertical)
                ctx.strokeStyle = 'rgba(255,255,255,.4)'; ctx.setLineDash([5, 4]); ctx.lineWidth = 1.2;
                ctx.beginPath(); ctx.moveTo(hitX, my - L); ctx.lineTo(hitX, my + 20); ctx.stroke(); ctx.setLineDash([]);
                ctx.fillStyle = faint; ctx.textAlign = 'left'; ctx.font = '600 9px Inter, sans-serif';
                ctx.fillText('normal', hitX + 6, my - L + 12);
                // incident (from upper-left) and reflected (to upper-right)
                const ix = hitX - Math.sin(iRad) * L, iy = my - Math.cos(iRad) * L;
                const rx = hitX + Math.sin(iRad) * L, ry = my - Math.cos(iRad) * L;
                beam(ix, iy, hitX, my, 'rgba(255,236,150,1)');       // incident toward mirror
                beam(hitX, my, rx, ry, 'rgba(255,236,150,1)');       // reflected away
                // ray box at the incident source
                ctx.save(); ctx.translate(ix, iy); ctx.rotate(iRad);
                ctx.fillStyle = '#3a3f47'; ctx.beginPath(); ctx.roundRect(-16, -10, 32, 20, 4); ctx.fill();
                ctx.fillStyle = '#ffec96'; ctx.beginPath(); ctx.arc(16, 0, 3, 0, Math.PI * 2); ctx.fill(); ctx.restore();
                // angle arcs + degree readouts
                ctx.strokeStyle = acc; ctx.lineWidth = 1.5;
                ctx.beginPath(); ctx.arc(hitX, my, 40, -Math.PI / 2 - iRad, -Math.PI / 2); ctx.stroke();
                ctx.strokeStyle = cyan;
                ctx.beginPath(); ctx.arc(hitX, my, 46, -Math.PI / 2, -Math.PI / 2 + iRad); ctx.stroke();
                ctx.fillStyle = acc; ctx.font = '700 11px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
                ctx.fillText(`i = ${S.angle}°`, hitX - Math.sin(iRad / 2) * 66, my - Math.cos(iRad / 2) * 66);
                ctx.fillStyle = cyan;
                ctx.fillText(`r = ${S.angle}°`, hitX + Math.sin(iRad / 2) * 66, my - Math.cos(iRad / 2) * 66);
                ctx.fillStyle = ink; ctx.font = '600 10px Inter, sans-serif';
                ctx.fillText('angle of incidence = angle of reflection', w * 0.44, h - 12);
            } else {
                // ── IMAGE mode ──
                const mx = w * 0.5, cy = h * 0.5, d = w * 0.20, ah = h * 0.22;
                // mirror (vertical) with silvered back
                ctx.strokeStyle = '#cfe0ee'; ctx.lineWidth = 3;
                ctx.beginPath(); ctx.moveTo(mx, h * 0.12); ctx.lineTo(mx, h * 0.88); ctx.stroke();
                ctx.strokeStyle = 'rgba(160,180,200,.5)'; ctx.lineWidth = 1;
                for (let y = h * 0.12; y < h * 0.88; y += 10) { ctx.beginPath(); ctx.moveTo(mx + 2, y); ctx.lineTo(mx + 9, y - 6); ctx.stroke(); }
                // object arrow (solid) and image arrow (dashed, dimmer, laterally inverted)
                const oTip = { x: mx - d, y: cy - ah / 2 }, oBase = { x: mx - d, y: cy + ah / 2 };
                const iTip = { x: mx + d, y: cy - ah / 2 }, iBase = { x: mx + d, y: cy + ah / 2 };
                const arrow = (base, tip, col, dash) => {
                    ctx.strokeStyle = col; ctx.lineWidth = 2.5; ctx.setLineDash(dash ? [5, 4] : []);
                    ctx.beginPath(); ctx.moveTo(base.x, base.y); ctx.lineTo(tip.x, tip.y); ctx.stroke();
                    ctx.beginPath(); ctx.moveTo(tip.x, tip.y); ctx.lineTo(tip.x - 5, tip.y + 9); ctx.lineTo(tip.x + 5, tip.y + 9); ctx.fillStyle = col; ctx.fill(); ctx.setLineDash([]);
                };
                arrow(oBase, oTip, cyan, false);
                arrow(iBase, iTip, 'rgba(120,180,220,.5)', true);
                // eye
                const eye = { x: mx - d * 1.5, y: cy + ah * 0.7 };
                ctx.strokeStyle = ink; ctx.lineWidth = 1.6;
                ctx.beginPath(); ctx.ellipse(eye.x, eye.y, 12, 7, 0, 0, Math.PI * 2); ctx.stroke();
                ctx.fillStyle = ink; ctx.beginPath(); ctx.arc(eye.x, eye.y, 3.5, 0, Math.PI * 2); ctx.fill();
                // two rays from object tip that reflect into the eye; extensions meet at image tip
                [-5, 5].forEach((dy) => {
                    const E = { x: eye.x, y: eye.y + dy };
                    const t = (mx - iTip.x) / (E.x - iTip.x);
                    const M = { x: mx, y: iTip.y + t * (E.y - iTip.y) };
                    beam(oTip.x, oTip.y, M.x, M.y, 'rgba(255,236,150,1)');     // incident
                    beam(M.x, M.y, E.x, E.y, 'rgba(255,236,150,1)');           // reflected to eye
                    ctx.strokeStyle = 'rgba(255,236,150,.35)'; ctx.setLineDash([4, 4]); ctx.lineWidth = 1.2;
                    ctx.beginPath(); ctx.moveTo(M.x, M.y); ctx.lineTo(iTip.x, iTip.y); ctx.stroke(); ctx.setLineDash([]);
                });
                // distance markers
                ctx.strokeStyle = faint; ctx.setLineDash([3, 3]); ctx.lineWidth = 1;
                ctx.beginPath(); ctx.moveTo(mx - d, cy + ah / 2 + 14); ctx.lineTo(mx, cy + ah / 2 + 14); ctx.stroke();
                ctx.beginPath(); ctx.moveTo(mx, cy + ah / 2 + 14); ctx.lineTo(mx + d, cy + ah / 2 + 14); ctx.stroke(); ctx.setLineDash([]);
                ctx.fillStyle = faint; ctx.font = '600 8.5px Inter, sans-serif'; ctx.textAlign = 'center';
                ctx.fillText('same distance', mx - d / 2, cy + ah / 2 + 26);
                ctx.fillText('same distance', mx + d / 2, cy + ah / 2 + 26);
                ctx.fillStyle = cyan; ctx.font = '600 9px Inter, sans-serif';
                ctx.fillText('object', mx - d, cy - ah / 2 - 8);
                ctx.fillStyle = 'rgba(120,180,220,.8)';
                ctx.fillText('virtual image', mx + d, cy - ah / 2 - 8);
                ctx.fillText('(same size, behind the mirror)', mx + d, cy - ah / 2 - 20);
                ctx.fillStyle = ink; ctx.textAlign = 'center'; ctx.font = '600 10px Inter, sans-serif';
                ctx.fillText('the rays only APPEAR to come from behind the mirror — a virtual image', w * 0.5, h - 10);
            }

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">{mode === 'law' ? 'Law of reflection · i = r' : 'Plane mirror · virtual image'}</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'law' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('law')}>📐 Law of reflection</button>
                    <button className={'cw-btn ' + (mode === 'image' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('image')}>🪞 Mirror image</button>
                </div>
                {mode === 'law' ? (
                    <>
                        <Stat label="angle of incidence = angle of reflection" value={`${angle}° = ${angle}°`} tone="acc"
                              sub={<>both angles are measured from the <b>normal</b> (the dashed line at right angles to the mirror), never from the mirror surface</>} />
                        <Slider label="angle of incidence" value={angle} min={0} max={80} step={1} onChange={setAngle} format={(x) => `${x}°`} />
                        <Flag kind="neutral">
                            Drag the incident beam: the reflected beam always leaves at the <b>same angle on the other side of the
                            normal</b> — the law of reflection, <b>angle of incidence = angle of reflection</b>. Both angles are
                            measured from the normal, a line drawn at 90° to the mirror at the point where the ray hits.
                        </Flag>
                    </>
                ) : (
                    <>
                        <Stat label="the image in a plane mirror" value="virtual · upright · same size" tone="acc"
                              sub={<>the image is as far <b>behind</b> the mirror as the object is in front, the <b>same size</b>, and <b>laterally inverted</b> (left–right swapped)</>} />
                        <Flag kind="neutral">
                            Follow the two light rays from the object to your eye. They reflect off the mirror obeying i = r — but
                            traced <b>backwards</b>, their dashed extensions meet <b>behind</b> the mirror. Your brain assumes light
                            travels straight, so you see an image there: a <b>virtual</b> image, the same size and the same distance
                            behind as the object is in front.
                        </Flag>
                    </>
                )}
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, angle })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
