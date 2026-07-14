import React, { useEffect, useRef, useState } from 'react';
import { cssVar } from '../lib/tokens.js';
import { Stat, Flag } from '../primitives.jsx';

// ── Widget: grid_lab ─────────────────────────────────────────────────────────
// Bespoke Energy Sources hero simulator (5054 · 1.7.3). Nine ways to obtain
// useful energy / generate electrical power, each drawn as its own animated
// generation chain (boiler → turbine → generator where they are used; direct
// turbine chains for moving water and air; panel-direct for solar), with a
// 24-hour availability strip and the three syllabus trade chips: renewable?,
// when/whether available, environmental impact. Qualitative throughout.
//
// config: { source } · Notes contract: getState/setState carry {source, t}.

const SOURCES = [
    { key: 'fossil', label: 'Fossil fuels', chain: 'boiler', store: 'chemical store of the fuel',
      renewable: false, avail: 'whenever needed — fuel burns on demand', availType: 'steady',
      env: 'releases carbon dioxide (and other pollutants) when burned',
      note: 'coal, oil and gas: burned in a boiler to make steam' },
    { key: 'biofuel', label: 'Biofuels', chain: 'boiler', store: 'chemical store of plant matter',
      renewable: true, avail: 'whenever needed — crops can be regrown and burned on demand', availType: 'steady',
      env: 'carbon dioxide released is offset by regrowth, but land use competes with food crops',
      note: 'wood, crop waste, bio-alcohols: burned like a fuel' },
    { key: 'hydro', label: 'Hydroelectric', chain: 'flow', store: 'gravitational potential store of dammed water',
      renewable: true, avail: 'on demand while the reservoir holds water (rain refills it)', availType: 'steady',
      env: 'no combustion gases, but damming a valley floods habitats',
      note: 'falling water spins the turbine directly — no boiler' },
    { key: 'solar', label: 'Solar radiation', chain: 'panel', store: 'sunlight (electromagnetic waves)',
      renewable: true, avail: 'daytime only — and weaker under cloud', availType: 'day',
      env: 'no emissions in use; large farms take land',
      note: 'panels transfer sunlight directly to electrical pathways — no turbine at all' },
    { key: 'nuclear', label: 'Nuclear fuel', chain: 'boiler', store: 'nuclear store of uranium nuclei',
      renewable: false, avail: 'continuous for years on one fuel load', availType: 'steady',
      env: 'no carbon dioxide, but radioactive waste needs long-term storage',
      note: 'fission heats the boiler instead of a flame' },
    { key: 'geothermal', label: 'Geothermal', chain: 'boiler', store: 'internal (thermal) store of hot rocks',
      renewable: true, avail: 'continuous, day and night — the rocks stay hot', availType: 'steady',
      env: 'very low emissions; only worthwhile where hot rock is near the surface',
      note: 'the Earth heats the boiler water' },
    { key: 'wind', label: 'Wind', chain: 'flow', store: 'kinetic store of moving air',
      renewable: true, avail: 'only while the wind blows — gusty and unreliable', availType: 'gusty',
      env: 'no emissions; visual/noise impact and space',
      note: 'moving air spins the blades — the turbine IS the machine' },
    { key: 'tides', label: 'Tides', chain: 'flow', store: 'kinetic + gravitational stores of tidal water',
      renewable: true, avail: 'intermittent but perfectly predictable — twice-daily cycle', availType: 'tidal',
      env: 'no emissions; barrages alter estuary habitats',
      note: 'tidal flow drives the turbines on a timetable the Moon sets' },
    { key: 'waves', label: 'Waves in the sea', chain: 'flow', store: 'kinetic store of sea waves',
      renewable: true, avail: 'only when the sea is rough — irregular', availType: 'gusty',
      env: 'no emissions; hard on equipment, local coastal impact',
      note: 'wave motion drives generators — turbine-and-generator, no boiler' },
];

export default function GridLab({ config = {}, onReady, onAddToNote }) {
    const [srcKey, setSrcKey] = useState(SOURCES.some((s) => s.key === config.source) ? config.source : 'fossil');
    const cvRef = useRef(null);
    const tRef = useRef(0);
    const st = useRef({}); st.current = { srcKey };
    const src = SOURCES.find((s) => s.key === srcKey);

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, source: st.current.srcKey, t: tRef.current }),
            setState: (s) => { if (s?.source && SOURCES.some((x) => x.key === s.source)) setSrcKey(s.source); },
        });
    }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const cv = cvRef.current, host = cv?.parentElement;
        if (!cv || !host) return undefined;
        let raf, last = performance.now();
        const draw = (now) => {
            const dt = Math.min(0.05, (now - last) / 1000); last = now;
            tRef.current += dt;
            const t = tRef.current;
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return;
            cv.width = w * dpr; cv.height = h * dpr;
            const ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0); ctx.clearRect(0, 0, w, h);
            const ink = cssVar('--ink-soft', '#A6C4B3'), faint = cssVar('--ink-faint', '#68877A');
            const line = cssVar('--line', 'rgba(160,200,175,.16)'), acc = cssVar('--ok', '#34D399');
            const cyan = '#38BDF8', amber = '#FBBF24', rose = '#FB7185';
            const S = SOURCES.find((x) => x.key === st.current.srcKey);

            // ── Generation chain (top ~55%) ──
            const cyc = h * 0.30;
            const box = (x, label, col) => {
                ctx.strokeStyle = col; ctx.lineWidth = 2;
                ctx.fillStyle = 'rgba(56,189,248,.10)';
                ctx.beginPath(); ctx.roundRect(x - 44, cyc - 26, 88, 52, 8); ctx.fill(); ctx.stroke();
                ctx.fillStyle = ink; ctx.font = '600 10px Inter, sans-serif'; ctx.textAlign = 'center';
                label.split('\n').forEach((ln, i) => ctx.fillText(ln, x, cyc - 4 + i * 12));
            };
            const flowArrow = (x0, x1) => {
                ctx.strokeStyle = faint; ctx.lineWidth = 1.5;
                ctx.beginPath(); ctx.moveTo(x0, cyc); ctx.lineTo(x1, cyc); ctx.stroke();
                // moving energy dots
                for (let i = 0; i < 3; i++) {
                    const p = ((t * 0.5 + i / 3) % 1);
                    ctx.fillStyle = amber;
                    ctx.beginPath(); ctx.arc(x0 + (x1 - x0) * p, cyc, 3, 0, Math.PI * 2); ctx.fill();
                }
            };
            const stations = S.chain === 'boiler'
                ? [[w * 0.14, `${S.label}\n(source)`], [w * 0.38, 'boiler\n(steam)'], [w * 0.62, 'turbine\n(spins)'], [w * 0.86, 'generator\n(electricity)']]
                : S.chain === 'flow'
                    ? [[w * 0.18, `${S.label}\n(moving)`], [w * 0.5, 'turbine\n(spins)'], [w * 0.82, 'generator\n(electricity)']]
                    : [[w * 0.25, 'sunlight\n(EM waves)'], [w * 0.72, 'solar panel\n(electricity)']];
            stations.forEach(([x, lbl], i) => { if (i) flowArrow(stations[i - 1][0] + 44, x - 44); box(x, lbl, i === stations.length - 1 ? acc : cyan); });
            ctx.fillStyle = faint; ctx.font = '600 9.5px Inter, sans-serif'; ctx.textAlign = 'center';
            ctx.fillText(S.note, w / 2, cyc + 48);
            ctx.fillText(`starts from: ${S.store}`, w / 2, cyc + 64);

            // ── 24-hour availability strip ──
            const ay = h * 0.62, aw = w - 60, ax = 30, ah = h * 0.16;
            ctx.strokeStyle = line; ctx.strokeRect(ax, ay, aw, ah);
            ctx.fillStyle = faint; ctx.font = '600 9px Inter, sans-serif'; ctx.textAlign = 'left';
            ctx.fillText('output over 24 hours', ax, ay - 6);
            ctx.font = '600 8px "JetBrains Mono", monospace'; ctx.textAlign = 'center';
            [['midnight', 0], ['06:00', 0.25], ['noon', 0.5], ['18:00', 0.75], ['midnight', 1]].forEach(([lbl, p]) => {
                ctx.fillText(lbl, ax + aw * p, ay + ah + 12);
            });
            ctx.strokeStyle = acc; ctx.lineWidth = 2; ctx.beginPath();
            for (let p = 0; p <= 1.0001; p += 0.01) {
                let v;
                if (S.availType === 'steady') v = 0.85;
                else if (S.availType === 'day') v = Math.max(0, Math.sin((p - 0.25) * 2 * Math.PI)) * 0.85;
                else if (S.availType === 'tidal') v = 0.45 + 0.4 * Math.sin(p * 4 * Math.PI);
                else v = 0.45 + 0.35 * Math.sin(p * 9 * Math.PI + Math.sin(p * 23));
                const px = ax + aw * p, py = ay + ah - v * ah * 0.92 - 3;
                p === 0 ? ctx.moveTo(px, py) : ctx.lineTo(px, py);
            }
            ctx.stroke();
            // marching now-cursor
            const nowP = (t * 0.04) % 1;
            ctx.strokeStyle = amber; ctx.lineWidth = 1.5; ctx.setLineDash([3, 3]);
            ctx.beginPath(); ctx.moveTo(ax + aw * nowP, ay); ctx.lineTo(ax + aw * nowP, ay + ah); ctx.stroke(); ctx.setLineDash([]);

            // ── trade chips ──
            const chipY = h - 20;
            ctx.font = '700 10px Inter, sans-serif'; ctx.textAlign = 'center';
            ctx.fillStyle = S.renewable ? acc : rose;
            ctx.fillText(S.renewable ? '✓ renewable' : '✗ non-renewable', w * 0.5, chipY);

            raf = requestAnimationFrame(draw);
        };
        raf = requestAnimationFrame(draw);
        return () => cancelAnimationFrame(raf);
    }, []);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10' }}>
                    <span className="cw-badge">Nine sources · one grid</span>
                    <canvas ref={cvRef} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label={src.renewable ? 'renewable source' : 'NON-renewable source'}
                      value={src.label} tone={src.renewable ? 'acc' : 'warn'}
                      sub={<><b>available:</b> {src.avail} · <b>environment:</b> {src.env}</>} />
                <div className="cw-btnrow">
                    {SOURCES.map((s) => (
                        <button key={s.key} className={'cw-btn ' + (s.key === srcKey ? 'cw-btn-save' : 'cw-btn-ghost')}
                                onClick={() => setSrcKey(s.key)}>
                            {s.label}
                        </button>
                    ))}
                </div>
                <Flag kind="neutral">
                    Watch what changes between sources: the <b>chain</b> (boiler → turbine → generator only where something must
                    be boiled; moving water and air spin the turbine directly; solar skips the turbine entirely), the
                    <b> 24-hour strip</b> (steady, daytime-only, tide-timetabled or gusty), and the three trades the syllabus
                    names: renewable, availability, environment.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, source: srcKey })}>📌 Save this source to my notes</button>
                )}
            </div>
        </div>
    );
}
