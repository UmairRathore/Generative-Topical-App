import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: spectrum_lab ─────────────────────────────────────────────────────
// Bespoke Electromagnetic Spectrum hero (5054 · 3.3). A travellable continuum:
// slide across the seven regions (radio → gamma) and each lights up with its
// place in the frequency/wavelength order, its real-world uses, and its hazard.
// A constant speed readout (c = 3.0 x 10^8 m/s, same for all) reinforces that
// every EM wave travels at the same speed in a vacuum.
//
// Lake-bar immersion; qualitative wavelength/frequency (order only, per syllabus).
// config: { region } · Notes: getState/setState carry { region }.

const REGIONS = [
    { name: 'radio', hex: '#C0392B', wl: 'longest wavelength', fr: 'lowest frequency',
      uses: 'radio and television broadcasts; astronomy', ionising: false,
      hazard: 'Non-ionising and low energy — the least harmful region; only extreme intensity causes heating.' },
    { name: 'microwave', hex: '#E67E22', wl: 'long wavelength', fr: 'low frequency',
      uses: 'satellite TV, mobile (cell) phones, Bluetooth, microwave ovens', ionising: false,
      hazard: 'Non-ionising, but absorbed by water — excess causes internal heating of body tissue.' },
    { name: 'infrared', hex: '#E0A83A', wl: 'shorter wavelength', fr: 'higher frequency',
      uses: 'remote controllers, intruder alarms, thermal imaging, electrical appliances, optical fibres', ionising: false,
      hazard: 'Non-ionising, felt as heat — excessive exposure heats soft tissue and causes skin burns.' },
    { name: 'visible', hex: 'rainbow', wl: '~ middle', fr: '~ middle',
      uses: 'vision (seeing) and photography', ionising: false,
      hazard: 'Safe at everyday levels; only very intense light (e.g. lasers) is harmful.' },
    { name: 'ultraviolet', hex: '#7A4FE0', wl: 'short wavelength', fr: 'high frequency',
      uses: 'security marking, detecting counterfeit bank notes, sterilising water', ionising: true,
      hazard: 'IONISING — causes skin cancer and cataracts (damage to the eyes).' },
    { name: 'X-rays', hex: '#3B6FE0', wl: 'shorter wavelength', fr: 'higher frequency',
      uses: 'medical imaging, security scanners, killing cancer cells, detecting cracks in metal', ionising: true,
      hazard: 'IONISING — causes cell mutation and cancer with over-exposure.' },
    { name: 'gamma rays', hex: '#67D5E6', wl: 'shortest wavelength', fr: 'highest frequency',
      uses: 'killing cancer cells, sterilising food and medical equipment, detecting cracks in metal', ionising: true,
      hazard: 'IONISING — causes cell mutation and cancer; the most penetrating region.' },
];

export default function SpectrumLab({ config = {}, onReady, onAddToNote }) {
    const clampR = (r) => Math.max(0, Math.min(REGIONS.length - 1, Math.round(r)));
    const [region, setRegion] = useState(typeof config.region === 'number' ? clampR(config.region) : 0);
    const cvRef = useRef(null);
    const st = useRef({}); st.current = { region };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, region: st.current.region }),
            setState: (s) => { if (typeof s?.region === 'number') setRegion(clampR(s.region)); },
        });
    }, [onReady]); // eslint-disable-line

    const R = REGIONS[region];

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf, t0 = performance.now();
        const VIS = ['#FF4D4D', '#FF9F1C', '#FFE14D', '#48D96A', '#4D9DFF', '#6C6CFF', '#B86CFF'];

        const draw = (now) => {
            const amb = (now - t0) / 1000;
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight;
            if (!w || !h) { raf = requestAnimationFrame(draw); return; }
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);

            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const S = st.current, sel = S.region;
            const bg = ctx.createLinearGradient(0, 0, 0, h);
            bg.addColorStop(0, '#0e131b'); bg.addColorStop(1, '#0a0e14');
            ctx.fillStyle = bg; ctx.fillRect(0, 0, w, h);

            const x0 = w * 0.06, x1 = w * 0.94, yb = h * 0.40, bh = Math.min(h * 0.24, 66);
            const cellW = (x1 - x0) / REGIONS.length;
            const arrowHead = (x, y, dir, col) => {
                ctx.fillStyle = col; ctx.beginPath();
                ctx.moveTo(x + dir * 8, y); ctx.lineTo(x, y - 5); ctx.lineTo(x, y + 5); ctx.closePath(); ctx.fill();
            };

            // wave train drawn along the band, wavelength shrinking left→right (frequency rising)
            ctx.strokeStyle = 'rgba(255,255,255,.10)'; ctx.lineWidth = 1.4;
            ctx.beginPath();
            for (let x = x0; x <= x1; x += 2) {
                const t = (x - x0) / (x1 - x0);
                const k = 0.02 + t * t * 0.5;                 // rising spatial frequency
                const y = yb - 20 + Math.sin((x - x0) * k - amb * 4) * 9;
                x === x0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
            }
            ctx.stroke();

            // the seven region cells
            REGIONS.forEach((rg, i) => {
                const cx = x0 + i * cellW;
                if (rg.hex === 'rainbow') {
                    const g = ctx.createLinearGradient(cx, 0, cx + cellW, 0);
                    VIS.forEach((c, j) => g.addColorStop(j / (VIS.length - 1), c));
                    ctx.fillStyle = g;
                } else {
                    ctx.fillStyle = rg.hex;
                }
                ctx.globalAlpha = i === sel ? 1 : 0.5;
                ctx.fillRect(cx + 1, yb, cellW - 2, bh);
                ctx.globalAlpha = 1;
                if (i === sel) {
                    ctx.strokeStyle = '#fff'; ctx.lineWidth = 2.4; ctx.strokeRect(cx + 1, yb, cellW - 2, bh);
                    // marker triangle above
                    ctx.fillStyle = '#fff'; ctx.beginPath();
                    ctx.moveTo(cx + cellW / 2, yb - 6); ctx.lineTo(cx + cellW / 2 - 6, yb - 15); ctx.lineTo(cx + cellW / 2 + 6, yb - 15); ctx.closePath(); ctx.fill();
                }
                ctx.fillStyle = i === sel ? '#fff' : faint;
                ctx.font = `${i === sel ? '700' : '600'} 8.5px Inter, sans-serif`; ctx.textAlign = 'center';
                const label = rg.name === 'gamma rays' ? 'gamma' : rg.name === 'ultraviolet' ? 'UV' : rg.name === 'infrared' ? 'IR' : rg.name === 'microwave' ? 'micro' : rg.name;
                ctx.fillText(label, cx + cellW / 2, yb + bh + 13);
                if (rg.ionising) { ctx.fillStyle = '#FB7185'; ctx.font = '700 8px Inter'; ctx.fillText('ionising', cx + cellW / 2, yb + bh + 24); }
            });

            // frequency axis (top) increases →
            ctx.strokeStyle = 'rgba(255,255,255,.4)'; ctx.lineWidth = 1.4;
            ctx.beginPath(); ctx.moveTo(x0, yb - 30); ctx.lineTo(x1, yb - 30); ctx.stroke();
            arrowHead(x1, yb - 30, 1, 'rgba(255,255,255,.7)');
            ctx.fillStyle = ink; ctx.font = '600 9.5px Inter, sans-serif'; ctx.textAlign = 'left';
            ctx.fillText('frequency increases  →', x0 + 4, yb - 36);
            // wavelength axis (bottom) increases ←
            ctx.strokeStyle = 'rgba(255,255,255,.4)';
            ctx.beginPath(); ctx.moveTo(x1, yb + bh + 34); ctx.lineTo(x0, yb + bh + 34); ctx.stroke();
            arrowHead(x0, yb + bh + 34, -1, 'rgba(255,255,255,.7)');
            ctx.textAlign = 'right'; ctx.fillStyle = ink;
            ctx.fillText('←  wavelength increases', x1 - 4, yb + bh + 30);

            // constant speed readout
            ctx.textAlign = 'center'; ctx.fillStyle = cssVar('--ok', '#34D399');
            ctx.font = '700 11px "JetBrains Mono", monospace';
            ctx.fillText('c = 3.0 × 10⁸ m/s  —  same speed for every region (in a vacuum)', w * 0.5, h - 12);
            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">Electromagnetic spectrum · radio → gamma</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Slider label="travel across the spectrum" value={region} min={0} max={REGIONS.length - 1} step={1}
                        onChange={(v) => setRegion(clampR(v))} format={() => R.name} />
                <Stat label={`${R.name}  ·  ${R.fr} / ${R.wl}`} value={R.ionising ? 'ionising' : 'non-ionising'} tone={R.ionising ? 'warn' : 'acc'}
                      sub={<><b>Uses:</b> {R.uses}</>} />
                <Flag kind={R.ionising ? 'warn' : 'neutral'}>{R.hazard}</Flag>
                <Flag kind="neutral">
                    All seven regions are the <b>same kind of wave</b> and travel at the <b>same speed, 3.0 × 10⁸ m/s, in a vacuum</b>
                    (and about the same in air). They differ only in <b>frequency and wavelength</b>: from <b>radio</b> (lowest
                    frequency, longest wavelength) to <b>gamma rays</b> (highest frequency, shortest wavelength). Increasing
                    frequency runs radio → gamma; increasing wavelength runs the opposite way.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, region })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
