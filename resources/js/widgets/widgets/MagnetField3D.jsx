import React, { useEffect, useRef, useState } from 'react';
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { Stat, Flag } from '../primitives.jsx';

// ── Widget: magnet_field_3d ──────────────────────────────────────────────────
// A genuine 3D (Three.js) magnetic field of a bar magnet. The field lines are
// TRACED from the real two-pole dipole field, so the looping cage that wraps the
// magnet is physically honest — the flat pattern you draw on paper is just one
// slice of it. Orbit to rotate. A "paper slice" toggle drops in the sheet of
// paper (the iron-filings plane) and dims the out-of-plane lines, connecting the
// 3D field to the 2D diagram. Arrows run N → S; lines crowd at the poles
// (strongest field). config: { slice, spin }
//
// 3D by design per the dimensionality doctrine: a magnetic field wraps in space.

const POLE = 1.7;            // half-length of the magnet (pole positions ±POLE on x)
const N3 = new THREE.Vector3(-POLE, 0, 0);
const S3 = new THREE.Vector3(POLE, 0, 0);

function fieldAt(p) {
    const dn = p.clone().sub(N3); const rn = dn.length() + 1e-4;
    const ds = p.clone().sub(S3); const rs = ds.length() + 1e-4;
    return dn.multiplyScalar(1 / (rn * rn * rn)).add(ds.multiplyScalar(-1 / (rs * rs * rs)));
}

function traceLine(seedY) {
    const pts = [];
    let p = new THREE.Vector3(-POLE - 0.05, seedY, 0);
    pts.push(p.clone());
    for (let i = 0; i < 700; i += 1) {
        const f = fieldAt(p); const m = f.length(); if (m < 1e-7) break;
        p = p.clone().add(f.multiplyScalar(0.055 / m));
        if (p.distanceTo(S3) < 0.28) { pts.push(S3.clone().add(new THREE.Vector3(-0.1, seedY > 0 ? 0.05 : -0.05, 0))); break; }
        if (Math.abs(p.x) < POLE + 0.12 && Math.abs(p.y) < 0.5 && Math.abs(p.z) < 0.5) break; // entered magnet
        if (p.length() > 14) break;
        pts.push(p.clone());
    }
    return pts;
}

export default function MagnetField3D({ config = {}, onReady, onAddToNote }) {
    const [slice, setSlice] = useState(!!config.slice);
    const [spin, setSpin] = useState(config.spin !== false);
    const hostRef = useRef(null);
    const simRef = useRef(null);
    const st = useRef({}); st.current = { slice, spin };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, slice: st.current.slice, spin: st.current.spin }),
            setState: (s) => {
                if (typeof s?.slice === 'boolean') setSlice(s.slice);
                if (typeof s?.spin === 'boolean') setSpin(s.spin);
            },
        });
    }, [onReady]); // eslint-disable-line

    useEffect(() => {
        const host = hostRef.current;
        if (!host) return undefined;

        const scene = new THREE.Scene();
        const camera = new THREE.PerspectiveCamera(44, 16 / 10, 0.1, 200);
        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        if ('outputEncoding' in renderer) renderer.outputEncoding = THREE.sRGBEncoding;
        host.appendChild(renderer.domElement);
        renderer.domElement.style.cssText = 'display:block;width:100%;height:100%;touch-action:none;';

        const controls = new OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true; controls.dampingFactor = 0.08; controls.enablePan = false;
        controls.minDistance = 7; controls.maxDistance = 26; controls.rotateSpeed = 0.7;
        const HOME = { r: 13.5, theta: 0.7, phi: 1.15 };
        const applyHome = () => {
            camera.position.set(HOME.r * Math.sin(HOME.phi) * Math.sin(HOME.theta), HOME.r * Math.cos(HOME.phi), HOME.r * Math.sin(HOME.phi) * Math.cos(HOME.theta));
            controls.target.set(0, 0, 0); controls.update();
        };
        applyHome();

        scene.add(new THREE.AmbientLight(0xbcd4ff, 0.6));
        const key = new THREE.DirectionalLight(0xffffff, 1.1); key.position.set(5, 9, 7); scene.add(key);
        const rim = new THREE.DirectionalLight(0x38bdf8, 0.7); rim.position.set(-6, 3, -5); scene.add(rim);

        // ── the bar magnet (red N half, blue S half) ─────────────────────────────
        const magnet = new THREE.Group();
        const redMat = new THREE.MeshStandardMaterial({ color: 0xe5484d, roughness: 0.35, metalness: 0.4, emissive: 0x5a0f12, emissiveIntensity: 0.35 });
        const blueMat = new THREE.MeshStandardMaterial({ color: 0x4d7bff, roughness: 0.35, metalness: 0.4, emissive: 0x101f5a, emissiveIntensity: 0.35 });
        const half = new THREE.BoxGeometry(POLE, 0.7, 0.7);
        const redHalf = new THREE.Mesh(half, redMat); redHalf.position.x = -POLE / 2; magnet.add(redHalf);
        const blueHalf = new THREE.Mesh(half, blueMat); blueHalf.position.x = POLE / 2; magnet.add(blueHalf);
        magnet.add(new THREE.LineSegments(new THREE.EdgesGeometry(new THREE.BoxGeometry(2 * POLE, 0.7, 0.7)), new THREE.LineBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0.3 })));
        scene.add(magnet);

        const sprite = (text, color) => {
            const c = document.createElement('canvas'); c.width = 96; c.height = 96;
            const cx = c.getContext('2d'); cx.fillStyle = color; cx.font = 'bold 72px Inter,sans-serif';
            cx.textAlign = 'center'; cx.textBaseline = 'middle'; cx.fillText(text, 48, 52);
            const sp = new THREE.Sprite(new THREE.SpriteMaterial({ map: new THREE.CanvasTexture(c), transparent: true, depthTest: false }));
            sp.scale.set(0.9, 0.9, 1); return sp;
        };
        const nLbl = sprite('N', '#ffd7d8'); nLbl.position.set(-POLE - 0.55, 0, 0); scene.add(nLbl);
        const sLbl = sprite('S', '#cdd9ff'); sLbl.position.set(POLE + 0.55, 0, 0); scene.add(sLbl);

        // ── field cage: trace planar lines, instance them around the x-axis ──────
        const fieldGroup = new THREE.Group(); scene.add(fieldGroup);
        // 8 azimuths (every 45°) so the field reads clearly as wrapping 360° around the axis
        const azis = [0, Math.PI / 4, Math.PI / 2, 3 * Math.PI / 4, Math.PI, 5 * Math.PI / 4, 3 * Math.PI / 2, 7 * Math.PI / 4];
        const coneGeo = new THREE.ConeGeometry(0.1, 0.3, 12);
        const seeds = [0.4, 1.0, 1.8, 2.9];
        const lines = [];   // {obj, inSlice}
        const cones = [];
        seeds.forEach((y0) => {
            const pts = traceLine(y0);
            if (pts.length < 4) return;
            const geo = new THREE.BufferGeometry().setFromPoints(pts);
            const idx = Math.floor(pts.length * 0.4);
            const dir = pts[idx + 1].clone().sub(pts[idx]).normalize();
            azis.forEach((phi) => {
                const mat = new THREE.LineBasicMaterial({ color: 0x64c8ff, transparent: true, opacity: 0.75 });
                const line = new THREE.Line(geo, mat); line.rotation.x = phi;
                const inSlice = Math.abs(Math.sin(phi)) < 0.01; // φ = 0 or π lie in the x-y (paper) plane
                fieldGroup.add(line); lines.push({ obj: line, mat, inSlice });
                // arrowhead cone (rotated into the same azimuth)
                const cmat = new THREE.MeshStandardMaterial({ color: 0x8fdcff, emissive: 0x2a6f9a, emissiveIntensity: 0.7, roughness: 0.3 });
                const cone = new THREE.Mesh(coneGeo, cmat);
                const holder = new THREE.Group(); holder.rotation.x = phi;
                cone.position.copy(pts[idx]);
                cone.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), dir);
                holder.add(cone); fieldGroup.add(holder); cones.push({ holder, mat: cmat, inSlice });
            });
        });

        // ── the paper (iron-filings) slice plane, hidden until toggled ───────────
        const paper = new THREE.Mesh(
            new THREE.PlaneGeometry(13, 9),
            new THREE.MeshBasicMaterial({ color: 0xdfe8f5, transparent: true, opacity: 0.10, side: THREE.DoubleSide, depthWrite: false }),
        );
        paper.rotation.x = 0; // lies in the x-y plane (z = 0), the plane the φ=0/π lines sit in
        paper.rotation.y = 0;
        paper.visible = false; scene.add(paper);

        let raf = 0;
        const applySlice = () => {
            const on = st.current.slice;
            paper.visible = on;
            lines.forEach((l) => { l.mat.opacity = on ? (l.inSlice ? 0.95 : 0.22) : 0.7; });
            cones.forEach((c) => { c.mat.opacity = on ? (c.inSlice ? 1 : 0.25) : 1; });
        };
        applySlice();

        // regroup the magnet + field under a spinner for a clean auto-rotate
        const spinScene = new THREE.Group();
        scene.remove(magnet, fieldGroup, nLbl, sLbl, paper);
        spinScene.add(magnet, fieldGroup, nLbl, sLbl, paper);
        scene.add(spinScene);
        const loop = () => {
            raf = requestAnimationFrame(loop);
            if (st.current.spin) spinScene.rotation.y += 0.004;
            controls.update();
            renderer.render(scene, camera);
        };

        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; renderer.setSize(w, h, false); camera.aspect = w / h; camera.updateProjectionMatrix(); };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();
        loop();

        simRef.current = { home: applyHome, applySlice };

        return () => {
            cancelAnimationFrame(raf); ro.disconnect();
            controls.dispose(); renderer.dispose();
            host.contains(renderer.domElement) && host.removeChild(renderer.domElement);
            simRef.current = null;
        };
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    // reflect slice/spin changes into the running scene
    useEffect(() => { simRef.current?.applySlice?.(); }, [slice]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10', position: 'relative' }}>
                    <span className="cw-badge">3D magnetic field · drag to rotate</span>
                    <div ref={hostRef} style={{ position: 'absolute', inset: 0, borderRadius: 12, overflow: 'hidden', background: 'linear-gradient(180deg,#0d1420,#070b12)' }} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label="the field wraps the magnet in 3D" value="360° around the axis, N → S" tone="acc"
                      sub={<>this looping cage is the <b>real</b> magnetic field — it wraps <b>all the way around</b> the magnet (the same pattern at every angle). The flat pattern you draw on paper is just <b>one slice</b> of it. Arrows run <b>N → S</b>, and the lines <b>crowd at the poles</b> where the field is strongest.</>} />
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (slice ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setSlice(!slice)}>{slice ? '✓ paper slice shown' : '📄 show the paper slice'}</button>
                    <button className={'cw-btn ' + (spin ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setSpin(!spin)}>{spin ? '⏸ stop spin' : '↻ auto-spin'}</button>
                    <button className="cw-btn cw-btn-ghost" onClick={() => simRef.current?.home?.()}>⤢ reset view</button>
                </div>
                <Flag kind="neutral">
                    A real magnetic field is <b>three-dimensional</b> — it loops out of the <b>north pole</b> and wraps
                    <b> 360° all the way around</b> the magnet (there are field lines at <b>every angle</b>, not just in one plane),
                    returning to the <b>south pole</b>. When you sprinkle <b>iron filings on paper</b>, you only see <b>one flat
                    slice</b> of this field (press <b>show the paper slice</b> to drop it in). The arrows point <b>N → S</b>, and
                    wherever the lines are <b>closest together — at the poles — the field is strongest</b>.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, slice, spin })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
