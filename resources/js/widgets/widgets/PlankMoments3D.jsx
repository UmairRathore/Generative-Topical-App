import React, { useEffect, useMemo, useRef, useState } from 'react';
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: plank_moments_3d ─────────────────────────────────────────────────
// A genuine 3D (Three.js) "principle of moments" simulator: a uniform plank on
// two end supports X and Y, a draggable child, and live force arrows. Taking
// moments about Y gives the reaction at X:
//     F·L = Wp·(L/2) + Wc·(L − d)   ⇒   F = (Wp·L/2 + Wc·(L−d)) / L
//     R   = (Wp + Wc) − F
// Ported from the 9702 m25 Q13 showcase. Follows the notes contract: the saved
// config carries the current child position, so a note re-mounts the exact scene.
//
// config: { length, plankWeight, childWeight, supports:[nameX,nameY], d }

const DEF = { length: 4, plankWeight: 300, childWeight: 600, supports: ['X', 'Y'], d: 0 };

function forces(cfg, d) {
    const { length: L, plankWeight: Wp, childWeight: Wc } = cfg;
    const F = (Wp * (L / 2) + Wc * (L - d)) / L; // reaction at X
    const R = Wp + Wc - F;                        // reaction at Y
    return { F, R, total: Wp + Wc };
}

export default function PlankMoments3D({ config = {}, onReady, onAddToNote }) {
    const cfg = { ...DEF, ...config };
    const { length: L, plankWeight: Wp, childWeight: Wc, supports } = cfg;
    const [nameX, nameY] = supports;

    const hostRef = useRef(null);
    const simRef = useRef(null);              // { setD, home, dispose }
    const [d, setDState] = useState(() => Math.max(0, Math.min(L, cfg.d ?? 0)));
    const dRef = useRef(d); dRef.current = d;

    const { F, R, total } = useMemo(() => forces(cfg, d), [cfg, d]);

    // Keep both the React UI and the 3D scene in step.
    const setD = (nd) => {
        const v = Math.max(0, Math.min(L, +nd));
        setDState(v);
        simRef.current?.setD(v);
    };

    // ── Notes contract: expose state + a save handler ─────────────────────────
    useEffect(() => {
        onReady?.({
            getState: () => ({ ...cfg, d: dRef.current }),
            setState: (s) => { if (s && typeof s.d === 'number') setD(s.d); },
        });
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    // ── Build the Three.js scene once ─────────────────────────────────────────
    useEffect(() => {
        const host = hostRef.current;
        if (!host) return undefined;

        const cvar = (n, f) => {
            const v = getComputedStyle(document.documentElement).getPropertyValue(n).trim();
            return v || f;
        };

        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(42, 16 / 11, 0.1, 200);
        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        if ('outputEncoding' in renderer) renderer.outputEncoding = THREE.sRGBEncoding;
        host.appendChild(renderer.domElement);
        renderer.domElement.style.cssText = 'display:block;width:100%;height:100%;touch-action:none;';

        const controls = new OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true; controls.dampingFactor = 0.08;
        controls.enablePan = false; controls.minDistance = 11; controls.maxDistance = 26;
        controls.minPolarAngle = 0.6; controls.maxPolarAngle = 1.42; controls.rotateSpeed = 0.65;
        const HOME = { theta: 0.62, phi: 1.04, r: 18.5 };
        const applyHome = () => {
            const s = HOME;
            camera.position.set(s.r * Math.sin(s.phi) * Math.sin(s.theta), s.r * Math.cos(s.phi), s.r * Math.sin(s.phi) * Math.cos(s.theta));
            controls.target.set(0, 1.5, 0); controls.update();
        };
        applyHome();

        scene.add(new THREE.AmbientLight(0xbcd4ff, 0.55));
        const key = new THREE.DirectionalLight(0xffffff, 1.15); key.position.set(6, 12, 8); scene.add(key);
        const rim = new THREE.DirectionalLight(0x38bdf8, 0.9); rim.position.set(-8, 4, -6); scene.add(rim);
        const fill = new THREE.PointLight(0x22d3ee, 0.5, 60); fill.position.set(0, 6, 10); scene.add(fill);

        const grid = new THREE.GridHelper(40, 40, 0x2b415f, 0x18263c); grid.position.y = -2.55; scene.add(grid);
        const floor = new THREE.Mesh(new THREE.PlaneGeometry(60, 60), new THREE.MeshStandardMaterial({ color: 0x0a1220, roughness: 1, transparent: true, opacity: 0.55 }));
        floor.rotation.x = -Math.PI / 2; floor.position.y = -2.56; scene.add(floor);

        const woodMat = new THREE.MeshStandardMaterial({ color: 0xc39a5f, roughness: 0.62, metalness: 0.05 });
        const supMat = new THREE.MeshStandardMaterial({ color: 0x243651, roughness: 0.4, metalness: 0.5, emissive: 0x0a1a2e, emissiveIntensity: 0.4 });
        const childMat = new THREE.MeshStandardMaterial({ color: 0x38bdf8, roughness: 0.35, metalness: 0.1, emissive: 0x0e5a86, emissiveIntensity: 0.55 });

        const PSPAN = 8, x0 = -PSPAN / 2;
        const mToX = (m) => x0 + (m / L) * PSPAN;

        const plank = new THREE.Mesh(new THREE.BoxGeometry(PSPAN + 0.6, 0.42, 1.7), woodMat);
        scene.add(plank);
        plank.add(new THREE.LineSegments(new THREE.EdgesGeometry(plank.geometry), new THREE.LineBasicMaterial({ color: 0x5b422a })));

        const support = (x, color) => {
            const shape = new THREE.Shape();
            shape.moveTo(-0.9, 0); shape.lineTo(0.9, 0); shape.lineTo(0, 1.85); shape.lineTo(-0.9, 0);
            const g = new THREE.ExtrudeGeometry(shape, { depth: 1.3, bevelEnabled: false }); g.center();
            const m = new THREE.Mesh(g, supMat.clone());
            m.material.emissive = new THREE.Color(color); m.material.emissiveIntensity = 0.35;
            m.position.set(x, -1.15, 0); m.scale.set(1, 1.25, 1); scene.add(m);
        };
        support(mToX(0), 0x38bdf8); support(mToX(L), 0x4ade80);

        const label = (text, color) => {
            const c = document.createElement('canvas'); c.width = 128; c.height = 128;
            const cx = c.getContext('2d'); cx.fillStyle = color; cx.font = 'bold 84px Inter,sans-serif';
            cx.textAlign = 'center'; cx.textBaseline = 'middle'; cx.fillText(text, 64, 68);
            const sp = new THREE.Sprite(new THREE.SpriteMaterial({ map: new THREE.CanvasTexture(c), transparent: true, depthTest: false }));
            sp.scale.set(1.3, 1.3, 1); return sp;
        };
        const lblX = label(nameX, '#EAF1FB'); lblX.position.set(mToX(0), 1.2, 0); scene.add(lblX);
        const lblY = label(nameY, '#EAF1FB'); lblY.position.set(mToX(L), 1.2, 0); scene.add(lblY);

        const childG = new THREE.Group();
        const body = new THREE.Mesh(new THREE.CylinderGeometry(0.3, 0.4, 1.15, 20), childMat); body.position.y = 0.72; childG.add(body);
        const shoulder = new THREE.Mesh(new THREE.SphereGeometry(0.3, 18, 14), childMat); shoulder.position.y = 1.22; shoulder.scale.set(1, 0.6, 1); childG.add(shoulder);
        const head = new THREE.Mesh(new THREE.SphereGeometry(0.32, 20, 16), childMat); head.position.y = 1.5; childG.add(head);
        const ring = new THREE.Mesh(new THREE.TorusGeometry(0.6, 0.05, 10, 32), new THREE.MeshBasicMaterial({ color: 0x7fe0ff })); ring.rotation.x = Math.PI / 2; ring.position.y = 0.24; childG.add(ring);
        childG.position.set(mToX(0), 0.21, 0); scene.add(childG);

        const makeArrow = (color) => {
            const g = new THREE.Group();
            const mat = new THREE.MeshStandardMaterial({ color, emissive: color, emissiveIntensity: 0.9, roughness: 0.3 });
            const shaft = new THREE.Mesh(new THREE.CylinderGeometry(0.07, 0.07, 1, 12), mat);
            const cone = new THREE.Mesh(new THREE.ConeGeometry(0.2, 0.5, 16), mat);
            g.add(shaft, cone); g.userData = { shaft, cone }; return g;
        };
        const setArrow = (g, x, baseY, len, up) => {
            const { shaft, cone } = g.userData; len = Math.max(0.4, len);
            shaft.scale.y = len; shaft.position.set(0, up ? baseY + len / 2 : baseY - len / 2, 0);
            cone.position.set(0, up ? baseY + len + 0.25 : baseY - len - 0.25, 0); cone.rotation.z = up ? 0 : Math.PI;
            g.position.x = x;
        };
        const aFx = makeArrow(0x38bdf8), aRy = makeArrow(0x4ade80), aWp = makeArrow(0xfb7185), aWc = makeArrow(0xfbbf24);
        scene.add(aFx, aRy, aWp, aWc);

        const lay = document.createElement('div'); lay.style.cssText = 'position:absolute;inset:0;pointer-events:none;z-index:4;'; host.appendChild(lay);
        const tag = (color) => { const el = document.createElement('div'); el.style.cssText = `position:absolute;transform:translate(-50%,-50%);font:600 12px 'JetBrains Mono',monospace;color:${color};background:rgba(6,11,20,.72);border:1px solid ${color}55;padding:2px 7px;border-radius:7px;white-space:nowrap;`; lay.appendChild(el); return el; };
        const tFx = tag('#38BDF8'), tRy = tag('#4ADE80'), tWp = tag('#FB7185'), tWc = tag('#FBBF24');
        const project = (x, y, z) => { const v = new THREE.Vector3(x, y, z).project(camera); return { x: (v.x * 0.5 + 0.5) * host.clientWidth, y: (-v.y * 0.5 + 0.5) * host.clientHeight, vis: v.z < 1 }; };

        // Drag the child along the plank.
        const ray = new THREE.Raycaster(); const dragPlane = new THREE.Plane(new THREE.Vector3(0, 1, 0), -0.21);
        const ndc = new THREE.Vector2(); let dragging = false;
        const pointerNDC = (e) => { const r = renderer.domElement.getBoundingClientRect(); ndc.x = ((e.clientX - r.left) / r.width) * 2 - 1; ndc.y = -((e.clientY - r.top) / r.height) * 2 + 1; };
        const hitChild = (e) => { pointerNDC(e); ray.setFromCamera(ndc, camera); return ray.intersectObject(childG, true).length > 0; };
        const dragTo = (e) => { pointerNDC(e); ray.setFromCamera(ndc, camera); const p = new THREE.Vector3(); if (ray.ray.intersectPlane(dragPlane, p)) { let m = ((p.x - x0) / PSPAN) * L; m = Math.max(0, Math.min(L, m)); setD(+m.toFixed(2)); } };
        const onDown = (e) => { if (hitChild(e)) { dragging = true; controls.enabled = false; ring.material.color.set(0xffffff); renderer.domElement.setPointerCapture(e.pointerId); } };
        const onMove = (e) => { if (dragging) dragTo(e); };
        const endDrag = () => { dragging = false; controls.enabled = true; ring.material.color.set(0x7fe0ff); };
        renderer.domElement.addEventListener('pointerdown', onDown);
        renderer.domElement.addEventListener('pointermove', onMove);
        renderer.domElement.addEventListener('pointerup', endDrag);
        renderer.domElement.addEventListener('pointercancel', endDrag);

        let curD = dRef.current;
        const draw = (m) => {
            const cx = mToX(m); childG.position.x = cx;
            const { F: f, R: r } = forces(cfg, m);
            const sc = (v) => 0.5 + (v / (Wp + Wc)) * 2.7;
            setArrow(aFx, mToX(0), 0.22, sc(f), true);
            setArrow(aRy, mToX(L), 0.22, sc(r), true);
            setArrow(aWp, mToX(L / 2), -0.22, sc(Wp), false);
            setArrow(aWc, cx, -0.22, sc(Wc), false);
            const put = (t, x, y, z, txt) => { const p = project(x, y, z); t.textContent = txt; t.style.left = `${p.x}px`; t.style.top = `${p.y}px`; t.style.display = p.vis ? 'block' : 'none'; };
            put(tFx, mToX(0), 0.22 + sc(f) + 0.6, 0, `${Math.round(f)} N`);
            put(tRy, mToX(L), 0.22 + sc(r) + 0.6, 0, `${Math.round(r)} N`);
            put(tWp, mToX(L / 2), -0.22 - sc(Wp) - 0.6, 0, `${Wp} N`);
            put(tWc, cx, -0.22 - sc(Wc) - 0.6, 0, `${Wc} N`);
            curD = m;
        };

        let raf = 0;
        const loop = () => { raf = requestAnimationFrame(loop); controls.update(); draw(curD); renderer.render(scene, camera); };
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; renderer.setSize(w, h, false); camera.aspect = w / h; camera.updateProjectionMatrix(); };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();
        draw(dRef.current); loop();

        simRef.current = { setD: (m) => { curD = m; }, home: applyHome };

        return () => {
            cancelAnimationFrame(raf); ro.disconnect();
            renderer.domElement.removeEventListener('pointerdown', onDown);
            renderer.domElement.removeEventListener('pointermove', onMove);
            renderer.domElement.removeEventListener('pointerup', endDrag);
            renderer.domElement.removeEventListener('pointercancel', endDrag);
            controls.dispose(); renderer.dispose();
            host.contains(renderer.domElement) && host.removeChild(renderer.domElement);
            host.contains(lay) && host.removeChild(lay);
            simRef.current = null;
        };
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    const atX = Math.abs(d) < 0.03, atY = Math.abs(d - L) < 0.03, atMid = Math.abs(d - L / 2) < 0.03;
    const flag = atX
        ? { kind: 'ok', text: <>✓ Child at <b>{nameX}</b> → F = {Math.round(F)} N, R = {Math.round(R)} N.</> }
        : atY
            ? { kind: 'ok', text: <>✓ Child at <b>{nameY}</b> → F = {Math.round(F)} N, R = {Math.round(R)} N.</> }
            : atMid
                ? { kind: 'warn', text: <>Child at the centre → supports share equally: F = R = {Math.round(F)} N.</> }
                : { kind: 'neutral', text: <>Child {d.toFixed(1)} m from {nameX} → F = {Math.round(F)} N. Slide to <b>0</b> or <b>{L} m</b> for the exam answer.</> };

    return (
        <div className="cw-root">
            <div ref={hostRef} style={{ position: 'relative', width: '100%', height: 340, borderRadius: 14, overflow: 'hidden', background: 'linear-gradient(180deg,#0a1220,#060b14)' }} />
            <div className="cw-row" style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 10, marginTop: 12 }}>
                <Stat label={`Reaction at ${nameX}`} value={Math.round(F)} unit="N" tone="acc" />
                <Stat label={`Reaction at ${nameY}`} value={Math.round(R)} unit="N" tone="ok" />
                <Stat label="Supports total" value={Math.round(total)} unit="N" sub="= total weight" />
            </div>
            <div style={{ marginTop: 12 }}>
                <Slider
                    label={`Child position (from ${nameX})`}
                    value={d} min={0} max={L} step={0.1}
                    onChange={setD} format={(v) => `${(+v).toFixed(1)} m`}
                />
            </div>
            <Flag kind={flag.kind}>{flag.text}</Flag>
            <div style={{ display: 'flex', gap: 8, marginTop: 12, flexWrap: 'wrap' }}>
                <button type="button" className="cw-btn cw-btn-ghost" onClick={() => setD(0)}>Child at {nameX}</button>
                <button type="button" className="cw-btn cw-btn-ghost" onClick={() => setD(L)}>Child at {nameY}</button>
                <button type="button" className="cw-btn cw-btn-ghost" onClick={() => simRef.current?.home()}>Reset view</button>
                {onAddToNote && (
                    <button type="button" className="cw-btn cw-btn-solid" style={{ marginLeft: 'auto' }} onClick={() => onAddToNote({ ...cfg, d: dRef.current })}>
                        ＋ Save this setup to my notes
                    </button>
                )}
            </div>
        </div>
    );
}
