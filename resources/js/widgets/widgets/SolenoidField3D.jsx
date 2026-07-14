import React, { useEffect, useRef, useState } from 'react';
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { Stat, Flag } from '../primitives.jsx';

// ── Widget: solenoid_field_3d ────────────────────────────────────────────────
// Genuine 3D (Three.js) magnetic field DUE TO A CURRENT.
//   WIRE     — a straight vertical wire carrying a current. The field is a set of
//              concentric HORIZONTAL circles around it, with direction arrows
//              distributed AROUND each circle (so the circulation is visible) and
//              flowing dots that circulate the way the field points. Reverse the
//              current → every arrow and the flow reverse (right-hand grip rule).
//   SOLENOID — a coil: a strong, uniform field ALONG the axis inside, looping back
//              outside like a bar magnet (a N pole at one end, a S pole at the
//              other). Reverse the current → the poles swap AND every field arrow
//              (external loops + interior) reverses.
// 3D by the dimensionality doctrine (the field wraps in space). config: { mode, reversed }

const POLE = 1.3;
const N3 = () => new THREE.Vector3(-POLE, 0, 0);
const S3 = () => new THREE.Vector3(POLE, 0, 0);
function fieldAt(p) {
    const n = N3(), s = S3();
    const dn = p.clone().sub(n); const rn = dn.length() + 1e-4;
    const ds = p.clone().sub(s); const rs = ds.length() + 1e-4;
    return dn.multiplyScalar(1 / (rn * rn * rn)).add(ds.multiplyScalar(-1 / (rs * rs * rs)));
}
function traceLine(seedY) {
    const pts = []; let p = new THREE.Vector3(-POLE - 0.04, seedY, 0); pts.push(p.clone());
    for (let i = 0; i < 700; i++) {
        const f = fieldAt(p); const m = f.length(); if (m < 1e-7) break;
        p = p.clone().add(f.multiplyScalar(0.05 / m));
        if (p.distanceTo(S3()) < 0.26) break;
        if (Math.abs(p.x) < POLE + 0.1 && Math.abs(p.y) < 0.42 && Math.abs(p.z) < 0.42) break;
        if (p.length() > 11) break; pts.push(p.clone());
    }
    return pts;
}

export default function SolenoidField3D({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['wire', 'solenoid'].includes(config.mode) ? config.mode : 'solenoid');
    const [reversed, setReversed] = useState(!!config.reversed);
    const hostRef = useRef(null);
    const simRef = useRef(null);
    const st = useRef({}); st.current = { mode, reversed };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, reversed: st.current.reversed }),
            setState: (s) => {
                if (['wire', 'solenoid'].includes(s?.mode)) setMode(s.mode);
                if (typeof s?.reversed === 'boolean') setReversed(s.reversed);
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
        controls.minDistance = 6; controls.maxDistance = 24; controls.rotateSpeed = 0.7;
        const HOME = { r: 11, theta: 0.7, phi: 1.0 };
        const applyHome = () => {
            camera.position.set(HOME.r * Math.sin(HOME.phi) * Math.sin(HOME.theta), HOME.r * Math.cos(HOME.phi), HOME.r * Math.sin(HOME.phi) * Math.cos(HOME.theta));
            controls.target.set(0, 0, 0); controls.update();
        };
        applyHome();
        scene.add(new THREE.AmbientLight(0xbcd4ff, 0.65));
        const key = new THREE.DirectionalLight(0xffffff, 1.05); key.position.set(5, 9, 7); scene.add(key);
        const rim = new THREE.DirectionalLight(0x38bdf8, 0.55); rim.position.set(-6, 3, -5); scene.add(rim);

        const sprite = (text, color, s = 0.8) => {
            const c = document.createElement('canvas'); c.width = 96; c.height = 96;
            const cx = c.getContext('2d'); cx.fillStyle = color; cx.font = 'bold 60px Inter,sans-serif';
            cx.textAlign = 'center'; cx.textBaseline = 'middle'; cx.fillText(text, 48, 52);
            const sp = new THREE.Sprite(new THREE.SpriteMaterial({ map: new THREE.CanvasTexture(c), transparent: true, depthTest: false }));
            sp.scale.set(s, s, 1); return sp;
        };
        const coneGeo = new THREE.ConeGeometry(0.1, 0.3, 12);
        const arrowMat = () => new THREE.MeshStandardMaterial({ color: 0x8fdcff, emissive: 0x2a6f9a, emissiveIntensity: 0.7, roughness: 0.3 });
        const lineMat = () => new THREE.LineBasicMaterial({ color: 0x64c8ff, transparent: true, opacity: 0.6 });

        // ── WIRE group: vertical wire + horizontal concentric rings ──────────────
        const wireGroup = new THREE.Group(); scene.add(wireGroup);
        wireGroup.add(new THREE.Mesh(new THREE.CylinderGeometry(0.09, 0.09, 6, 16), new THREE.MeshStandardMaterial({ color: 0xd9a441, metalness: 0.85, roughness: 0.3, emissive: 0x3a2600, emissiveIntensity: 0.4 })));
        const wireArrows = [];   // {cone, a}  — tangent flips with current sign
        const rings = [{ yy: -1.7, r: 1.6 }, { yy: 0, r: 1.15 }, { yy: 0, r: 2.0 }, { yy: 0, r: 2.75 }, { yy: 1.7, r: 1.6 }];
        rings.forEach(({ yy, r }) => {
            const ringPts = [];
            for (let a = 0; a <= Math.PI * 2 + 0.05; a += 0.14) ringPts.push(new THREE.Vector3(r * Math.cos(a), yy, r * Math.sin(a)));
            wireGroup.add(new THREE.Line(new THREE.BufferGeometry().setFromPoints(ringPts), lineMat()));
            // 6 direction arrows distributed AROUND the ring (tangent to it)
            for (let k = 0; k < 6; k++) {
                const a = (k / 6) * Math.PI * 2;
                const cone = new THREE.Mesh(coneGeo, arrowMat());
                cone.position.set(r * Math.cos(a), yy, r * Math.sin(a));
                wireArrows.push({ cone, a }); wireGroup.add(cone);
            }
        });
        // flowing dots that circulate the middle ring (show the field's direction of "flow")
        const flowRing = { yy: 0, r: 2.0 };
        const flowDots = [];
        const dotMat = new THREE.MeshStandardMaterial({ color: 0x9be7ff, emissive: 0x2a6f9a, emissiveIntensity: 1.0 });
        for (let k = 0; k < 12; k++) { const d = new THREE.Mesh(new THREE.SphereGeometry(0.08, 10, 10), dotMat); wireGroup.add(d); flowDots.push({ mesh: d, base: (k / 12) * Math.PI * 2 }); }
        wireGroup.add(sprite('I', '#ffd7a1', 0.6).translateY(3.2));

        // ── SOLENOID group: helix + external dipole loops + interior axial arrows ─
        const solGroup = new THREE.Group(); scene.add(solGroup);
        const turns = 7, rC = 0.55;
        const helixPts = [];
        for (let a = 0; a <= turns * Math.PI * 2; a += 0.22) helixPts.push(new THREE.Vector3(-1.15 + (a / (turns * Math.PI * 2)) * 2.3, rC * Math.cos(a), rC * Math.sin(a)));
        solGroup.add(new THREE.Mesh(new THREE.TubeGeometry(new THREE.CatmullRomCurve3(helixPts), 300, 0.05, 8, false), new THREE.MeshStandardMaterial({ color: 0xd9a441, metalness: 0.8, roughness: 0.35, emissive: 0x3a2600, emissiveIntensity: 0.35 })));
        const azis = [0, Math.PI / 3, 2 * Math.PI / 3, Math.PI, 4 * Math.PI / 3, 5 * Math.PI / 3];
        const solCones = [];   // {cone, dir}  — dir negated on reverse
        [0.35, 0.95, 1.7].forEach((y0) => {
            const pts = traceLine(y0);
            if (pts.length < 12) return;
            const geo = new THREE.BufferGeometry().setFromPoints(pts);
            const idx = Math.floor(pts.length * 0.5);
            const dir = pts[idx + 1].clone().sub(pts[idx]).normalize();
            azis.forEach((phi) => {
                const l = new THREE.Line(geo, lineMat()); l.rotation.x = phi; solGroup.add(l);
                const holder = new THREE.Group(); holder.rotation.x = phi;
                const cone = new THREE.Mesh(coneGeo, arrowMat()); cone.position.copy(pts[idx]);
                holder.add(cone); solGroup.add(holder);
                solCones.push({ cone, dir: dir.clone() });
            });
        });
        const solAxial = [];   // interior arrows (point toward the N end)
        [-0.6, 0, 0.6].forEach((xx) => { const cone = new THREE.Mesh(coneGeo, arrowMat()); cone.position.set(xx, 0, 0); solGroup.add(cone); solAxial.push(cone); });
        const nLbl = sprite('N', '#ffb3b6', 0.7), sLbl = sprite('S', '#b6c7ff', 0.7);
        solGroup.add(nLbl, sLbl);

        const UP = new THREE.Vector3(0, 1, 0);
        const applyMode = () => {
            const S = st.current, sol = S.mode === 'solenoid', rev = S.reversed;
            wireGroup.visible = !sol; solGroup.visible = sol;
            const sgn = rev ? -1 : 1;
            // WIRE: each arrow tangent to its ring; sign flips with the current (right-hand grip)
            wireArrows.forEach(({ cone, a }) => {
                const tang = new THREE.Vector3(-Math.sin(a), 0, Math.cos(a)).multiplyScalar(sgn);
                cone.quaternion.setFromUnitVectors(UP, tang);
            });
            // SOLENOID: poles + interior + external arrows all consistent with the current
            const nAtLeft = !rev;
            nLbl.position.set(nAtLeft ? -1.75 : 1.75, 0, 0); sLbl.position.set(nAtLeft ? 1.75 : -1.75, 0, 0);
            const axDir = new THREE.Vector3(nAtLeft ? -1 : 1, 0, 0);   // inside, field points toward the N end
            solAxial.forEach((c) => c.quaternion.setFromUnitVectors(UP, axDir));
            solCones.forEach(({ cone, dir }) => cone.quaternion.setFromUnitVectors(UP, rev ? dir.clone().negate() : dir));
        };

        const spinScene = new THREE.Group();
        scene.remove(wireGroup, solGroup); spinScene.add(wireGroup, solGroup); scene.add(spinScene);
        applyMode();

        let raf = 0, t = 0;
        const loop = () => {
            raf = requestAnimationFrame(loop); t += 0.016;
            const S = st.current; const sgn = S.reversed ? -1 : 1;
            if (S.mode === 'wire') {
                flowDots.forEach(({ mesh, base }) => {
                    const a = base + sgn * t * 0.9;
                    mesh.position.set(flowRing.r * Math.cos(a), flowRing.yy, flowRing.r * Math.sin(a));
                });
            }
            spinScene.rotation.y += 0.0022;
            controls.update(); renderer.render(scene, camera);
        };
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; renderer.setSize(w, h, false); camera.aspect = w / h; camera.updateProjectionMatrix(); };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();
        simRef.current = { home: applyHome, applyMode };
        loop();
        return () => {
            cancelAnimationFrame(raf); ro.disconnect(); controls.dispose(); renderer.dispose();
            host.contains(renderer.domElement) && host.removeChild(renderer.domElement); simRef.current = null;
        };
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => { simRef.current?.applyMode?.(); }, [mode, reversed]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10', position: 'relative' }}>
                    <span className="cw-badge">{mode === 'solenoid' ? '3D solenoid field · drag to rotate' : '3D field around a wire · drag to rotate'}</span>
                    <div ref={hostRef} style={{ position: 'absolute', inset: 0, borderRadius: 12, overflow: 'hidden', background: 'linear-gradient(180deg,#0d1420,#070b12)' }} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'wire' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('wire')}>│ Straight wire</button>
                    <button className={'cw-btn ' + (mode === 'solenoid' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('solenoid')}>◎ Solenoid</button>
                </div>
                {mode === 'wire' ? (
                    <Stat label="field around a straight wire" value={reversed ? 'circles reversed (clockwise)' : 'concentric circles'} tone="acc"
                          sub={<>a current in a straight wire makes <b>circular</b> field lines <b>around</b> it — closer together near the wire (stronger). Point your <b>right thumb</b> along the current and your fingers curl the way the field goes. <b>Reverse the current → every arrow and the flow reverse.</b></>} />
                ) : (
                    <Stat label="field of a solenoid" value={reversed ? 'poles swapped (S ← → N)' : 'like a bar magnet'} tone="acc"
                          sub={<>a current in a <b>solenoid</b> makes a <b>strong, uniform</b> field <b>along the axis inside</b> and loops back outside just like a <b>bar magnet</b> — one end a <b>N</b> pole, the other <b>S</b>. <b>Reverse the current → the poles swap and every field arrow reverses.</b></>} />
                )}
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (reversed ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setReversed(!reversed)}>⇄ reverse the current</button>
                    <button className="cw-btn cw-btn-ghost" onClick={() => simRef.current?.home?.()}>⤢ reset view</button>
                </div>
                <Flag kind="neutral">
                    A <b>current makes a magnetic field</b>. Around a <b>straight wire</b> the field is a set of <b>circles</b> that wrap around
                    it (use the <b>right-hand grip rule</b>: thumb = current, curled fingers = field) — watch the arrows and the flowing dots
                    circulate. A <b>solenoid</b> (coil) makes a field just like a <b>bar magnet</b>: strong and uniform <b>inside</b>, looping out
                    of a <b>N</b> pole and back to a <b>S</b> pole <b>outside</b>. A <b>bigger current</b> gives a <b>stronger</b> field;
                    <b> reversing</b> the current <b>reverses</b> the whole field (and swaps the solenoid's poles). This magnetic effect is used in
                    <b> relays</b> and <b>loudspeakers</b>.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, reversed })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
