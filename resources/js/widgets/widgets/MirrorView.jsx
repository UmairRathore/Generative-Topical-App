import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Flag, Options } from '../primitives.jsx';

// ── Widget: mirror_view ───────────────────────────────────────────────────────
// Plane-mirror sightline. A viewer's eye and a target object at known heights; a
// mirror of fixed length hangs on the wall with its base at height h. To see the
// object the mirror must cover the reflection point at (eyeH + objectH)/2 — the
// image height is independent of distance. The MCQ Options probe sets h and shows
// whether the reflected ray reaches the eye (green) or misses (red).
// config: {
//   eyeH, objectH, mirrorLen, maxH, unit,
//   optionsLead, hint,
//   options:[{label, h, correct, note}]
// }

const VW = 1000, VH = 560;

export default function MirrorView({ config = {}, onReady, onAddToNote }) {
    const { eyeH = 150, objectH = 0, mirrorLen = 50, maxH = 200, unit = 'cm' } = config;
    const [picked, setPicked] = useState(null);
    const stRef = useRef(picked); stRef.current = picked;
    const cvRef = useRef(null);

    const reflectH = (eyeH + objectH) / 2; // height on the mirror where the object-ray reflects to the eye

    useEffect(() => { onReady && onReady({ getState: () => ({ picked: picked?.label }), setState: () => {} }); }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv.parentElement, ctx = cv.getContext('2d');
        let dpr = 1, raf = 0;
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; dpr = Math.min(window.devicePixelRatio || 1, 2); cv.width = w * dpr; cv.height = h * dpr; const sc = Math.min(w / VW, h / VH); cv.__g = { sc, ox: (w - VW * sc) / 2, oy: (h - VH * sc) / 2 }; };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        const draw = () => {
            const g = cv.__g; if (!g) { raf = requestAnimationFrame(draw); return; }
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A'), acc = cssVar('--ok', '#34D399'), bad = '#fb7185', warn = '#fbbf24';
            const p = stRef.current;
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, cv.width, cv.height);
            ctx.setTransform(dpr * g.sc, 0, 0, dpr * g.sc, g.ox * dpr, g.oy * dpr);
            const bg = ctx.createLinearGradient(0, 0, 0, VH); bg.addColorStop(0, '#0a1622'); bg.addColorStop(1, '#0c1a12'); ctx.fillStyle = bg; ctx.fillRect(0, 0, VW, VH);

            const groundY = VH - 60, topY = 40, wallX = VW - 130, personX = 190;
            const H2Y = (hcm) => groundY - (hcm / maxH) * (groundY - topY); // height(cm) → y

            // ground + wall
            ctx.strokeStyle = faint; ctx.lineWidth = 2; ctx.beginPath(); ctx.moveTo(60, groundY); ctx.lineTo(VW - 40, groundY); ctx.stroke();
            ctx.beginPath(); ctx.moveTo(wallX, topY - 10); ctx.lineTo(wallX, groundY); ctx.stroke();
            // scale ticks
            ctx.fillStyle = faint; ctx.font = '600 12px "JetBrains Mono", monospace'; ctx.textAlign = 'right';
            for (let hh = 0; hh <= maxH; hh += 50) { const y = H2Y(hh); ctx.strokeStyle = 'rgba(166,196,179,.15)'; ctx.beginPath(); ctx.moveTo(70, y); ctx.lineTo(wallX, y); ctx.stroke(); ctx.fillStyle = faint; ctx.fillText(hh + '', 66, y + 4); }

            // person (eye marker)
            const eyeY = H2Y(eyeH);
            ctx.strokeStyle = ink; ctx.lineWidth = 2.4;
            ctx.beginPath(); ctx.arc(personX, eyeY - 16, 16, 0, 6.283); ctx.stroke(); // head
            ctx.beginPath(); ctx.moveTo(personX, eyeY); ctx.lineTo(personX, groundY - 4); ctx.stroke(); // body
            ctx.fillStyle = warn; ctx.beginPath(); ctx.arc(personX + 12, eyeY - 16, 3.5, 0, 6.283); ctx.fill();
            ctx.fillStyle = ink; ctx.font = '600 13px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText(`eye ${eyeH} ${unit}`, personX, eyeY - 40);
            // object (shoes) at objectH near feet
            const objY = H2Y(objectH);
            ctx.fillStyle = '#c084fc'; ctx.beginPath(); ctx.ellipse(personX + 18, objY - 6, 22, 8, 0, 0, 6.283); ctx.fill();
            ctx.fillStyle = '#c084fc'; ctx.fillText('shoes', personX + 58, objY - 4);

            // mirror (from h to h+mirrorLen)
            const h = p ? p.h : reflectH - mirrorLen / 2; // default: centred on reflection point
            const mTop = H2Y(h + mirrorLen), mBot = H2Y(h);
            ctx.strokeStyle = '#7dd3fc'; ctx.lineWidth = 7; ctx.beginPath(); ctx.moveTo(wallX, mBot); ctx.lineTo(wallX, mTop); ctx.stroke();
            ctx.fillStyle = '#7dd3fc'; ctx.font = '600 12px "JetBrains Mono", monospace'; ctx.textAlign = 'left';
            ctx.fillText(`mirror ${mirrorLen}${unit}`, wallX + 12, (mTop + mBot) / 2 - 6);
            ctx.fillText(`base h = ${h}${unit}`, wallX + 12, mBot + 4);

            // reflection point marker on the wall
            const rY = H2Y(reflectH);
            const covered = reflectH >= h && reflectH <= h + mirrorLen;

            // ray path: object → reflection point → eye
            const rayCol = p ? (covered ? acc : bad) : faint;
            ctx.strokeStyle = rayCol; ctx.lineWidth = 2.6; ctx.setLineDash(covered || !p ? [] : [7, 5]);
            ctx.beginPath(); ctx.moveTo(personX + 18, objY - 6); ctx.lineTo(wallX, rY); ctx.lineTo(personX + 12, eyeY - 16); ctx.stroke(); ctx.setLineDash([]);
            // reflection point dot + normal tick
            ctx.fillStyle = rayCol; ctx.beginPath(); ctx.arc(wallX, rY, 5, 0, 6.283); ctx.fill();
            ctx.fillStyle = faint; ctx.textAlign = 'right'; ctx.font = '600 12px "JetBrains Mono", monospace';
            ctx.fillText(`reflect @ ${reflectH}${unit}`, wallX - 8, rY - 8);

            if (p) {
                const col = covered ? acc : bad;
                ctx.fillStyle = 'rgba(0,0,0,.4)'; ctx.fillRect(VW / 2 - 240, 12, 480, 36);
                ctx.strokeStyle = col; ctx.lineWidth = 2; ctx.strokeRect(VW / 2 - 240, 12, 480, 36);
                ctx.fillStyle = col; ctx.font = '700 15px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
                ctx.fillText(covered ? '✓ mirror covers the reflection point — shoes visible' : '✗ reflection point is off the mirror — shoes not visible', VW / 2, 30);
                ctx.textBaseline = 'alphabetic';
            }
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => { cancelAnimationFrame(raf); ro.disconnect(); };
    }, [eyeH, objectH, mirrorLen, maxH, unit, reflectH]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '25 / 14' }}>
                    <span className="cw-badge">Plane-mirror sightline</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                {config.options?.length > 0 && (
                    <Options options={config.options} lead={config.optionsLead || 'Smallest base height h to see the shoes?'} onPick={(o) => setPicked(o)} />
                )}
                <Flag kind="neutral">{config.hint || <>The ray from the shoes reflects at the mirror at height (eye + object)/2. The mirror must reach that point — its top must be at or above it.</>}</Flag>
                {onAddToNote && (<button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config })}>📌 Save this to my notes</button>)}
            </div>
        </div>
    );
}
