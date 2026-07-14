import React, { useEffect, useRef, useState } from 'react';
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: induction_3d ─────────────────────────────────────────────────────
// Genuine 3D (Three.js) electromagnetic induction. A bar magnet moves in and out
// of a coil connected to a centre-zero galvanometer. While the magnet MOVES, the
// changing flux induces an e.m.f. and the needle deflects; the faster it moves and
// the more turns, the bigger the deflection. The DIRECTION reverses between pushing
// in and pulling out (Lenz's law): the coil's near face becomes the pole that
// OPPOSES the magnet's motion. Stationary magnet → no deflection.
// 3D by the dimensionality doctrine (the coil and moving magnet are spatial).
// config: { turns, speed, running }

const COIL_R = 1.15;

function helixTube(turns) {
    const pts = [];
    const pitch = 2.6 / Math.max(turns, 1);
    const total = turns * Math.PI * 2;
    for (let a = 0; a <= total; a += 0.25) {
        const x = -1.3 + (a / total) * 2.6;
        pts.push(new THREE.Vector3(x, COIL_R * Math.cos(a), COIL_R * Math.sin(a)));
    }
    const curve = new THREE.CatmullRomCurve3(pts);
    return new THREE.TubeGeometry(curve, 260, 0.05, 8, false);
}

export default function Induction3D({ config = {}, onReady, onAddToNote }) {
    const [turns, setTurns] = useState(typeof config.turns === 'number' ? config.turns : 6);
    const [speed, setSpeed] = useState(typeof config.speed === 'number' ? config.speed : 1);
    const [running, setRunning] = useState(config.running !== false);
    const hostRef = useRef(null);
    const simRef = useRef(null);
    const st = useRef({}); st.current = { turns, speed, running };
    const [emf, setEmf] = useState(0); // for the readout (state mirror; refs don't re-render)

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, turns: st.current.turns, speed: st.current.speed, running: st.current.running }),
            setState: (s) => {
                if (typeof s?.turns === 'number') setTurns(Math.max(2, Math.min(12, Math.round(s.turns))));
                if (typeof s?.speed === 'number') setSpeed(Math.max(0.3, Math.min(2.5, s.speed)));
                if (typeof s?.running === 'boolean') setRunning(s.running);
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
        const HOME = { r: 11.5, theta: 0.72, phi: 1.12 };
        const applyHome = () => {
            camera.position.set(HOME.r * Math.sin(HOME.phi) * Math.sin(HOME.theta), HOME.r * Math.cos(HOME.phi), HOME.r * Math.sin(HOME.phi) * Math.cos(HOME.theta));
            controls.target.set(0.4, 0, 0); controls.update();
        };
        applyHome();

        scene.add(new THREE.AmbientLight(0xbcd4ff, 0.65));
        const key = new THREE.DirectionalLight(0xffffff, 1.1); key.position.set(5, 9, 7); scene.add(key);
        const rim = new THREE.DirectionalLight(0x38bdf8, 0.6); rim.position.set(-6, 3, -5); scene.add(rim);

        const sprite = (text, color, s = 0.9) => {
            const c = document.createElement('canvas'); c.width = 96; c.height = 96;
            const cx = c.getContext('2d'); cx.fillStyle = color; cx.font = 'bold 60px Inter,sans-serif';
            cx.textAlign = 'center'; cx.textBaseline = 'middle'; cx.fillText(text, 48, 52);
            const sp = new THREE.Sprite(new THREE.SpriteMaterial({ map: new THREE.CanvasTexture(c), transparent: true, depthTest: false }));
            sp.scale.set(s, s, 1); return sp;
        };

        // ── the coil ─────────────────────────────────────────────────────────────
        const coilMat = new THREE.MeshStandardMaterial({ color: 0xd9a441, roughness: 0.35, metalness: 0.8, emissive: 0x3a2600, emissiveIntensity: 0.3 });
        let coil = new THREE.Mesh(helixTube(config.turns ?? 6), coilMat); scene.add(coil);
        // leads down to the meter
        const leadMat = new THREE.LineBasicMaterial({ color: 0x9aa4b2 });
        const leads = new THREE.Line(new THREE.BufferGeometry().setFromPoints([
            new THREE.Vector3(1.3, -COIL_R, 0), new THREE.Vector3(2.6, -2.2, 0),
            new THREE.Vector3(3.4, -2.2, 0),
        ]), leadMat); scene.add(leads);
        const leads2 = new THREE.Line(new THREE.BufferGeometry().setFromPoints([
            new THREE.Vector3(-1.3, -COIL_R, 0), new THREE.Vector3(1.6, -2.6, 0),
            new THREE.Vector3(2.6, -2.6, 0),
        ]), leadMat); scene.add(leads2);

        // induced-current ring arrows around the middle turn
        const arrowGroup = new THREE.Group(); scene.add(arrowGroup);
        const coneGeo = new THREE.ConeGeometry(0.11, 0.34, 12);
        const arrows = [];
        for (let k = 0; k < 6; k++) {
            const a = (k / 6) * Math.PI * 2;
            const mat = new THREE.MeshStandardMaterial({ color: 0x37d399, emissive: 0x0c5c3c, emissiveIntensity: 0.8, roughness: 0.3, transparent: true, opacity: 0 });
            const cone = new THREE.Mesh(coneGeo, mat);
            cone.position.set(0.05, (COIL_R + 0.02) * Math.cos(a), (COIL_R + 0.02) * Math.sin(a));
            arrows.push({ cone, mat, a });
            arrowGroup.add(cone);
        }
        // induced pole label on the near (−x, magnet-facing) face
        const poleLbl = sprite('', '#ffffff', 0.8); poleLbl.position.set(-1.55, 0, 0); scene.add(poleLbl);

        // ── the bar magnet ───────────────────────────────────────────────────────
        const magnet = new THREE.Group();
        const redMat = new THREE.MeshStandardMaterial({ color: 0xe5484d, roughness: 0.35, metalness: 0.4, emissive: 0x5a0f12, emissiveIntensity: 0.35 });
        const blueMat = new THREE.MeshStandardMaterial({ color: 0x4d7bff, roughness: 0.35, metalness: 0.4, emissive: 0x101f5a, emissiveIntensity: 0.35 });
        const half = new THREE.BoxGeometry(0.75, 0.42, 0.42);
        const nHalf = new THREE.Mesh(half, redMat); nHalf.position.x = 0.375; magnet.add(nHalf); // N faces +x (toward coil)
        const sHalf = new THREE.Mesh(half, blueMat); sHalf.position.x = -0.375; magnet.add(sHalf);
        magnet.add(sprite('N', '#ffd7d8', 0.5).translateX(0.85));
        scene.add(magnet);

        // ── the galvanometer (centre-zero dial + needle) ─────────────────────────
        const meter = new THREE.Group(); meter.position.set(3.6, -2.4, 0);
        const dial = new THREE.Mesh(new THREE.CircleGeometry(0.9, 40), new THREE.MeshBasicMaterial({ color: 0x0e141d }));
        const ring = new THREE.Mesh(new THREE.RingGeometry(0.88, 0.96, 40), new THREE.MeshBasicMaterial({ color: 0x9aa4b2, side: THREE.DoubleSide }));
        meter.add(dial, ring);
        meter.add(sprite('0', '#68877a', 0.35).translateY(0.62));
        const needle = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.8, 0.03), new THREE.MeshBasicMaterial({ color: 0xfb7185 }));
        needle.geometry.translate(0, 0.35, 0); needle.position.z = 0.02; meter.add(needle);
        meter.lookAt(camera.position);
        scene.add(meter);

        // regroup coil + arrows + poleLbl + leads for auto-spin (magnet & meter stay put)
        const spinScene = new THREE.Group();
        scene.remove(coil, arrowGroup, poleLbl, leads, leads2);
        spinScene.add(coil, arrowGroup, poleLbl, leads, leads2);
        scene.add(spinScene);

        let raf = 0, t = 0, lastX = -3, manualX = null;
        const loop = () => {
            raf = requestAnimationFrame(loop);
            const S = st.current;
            // magnet position: auto-oscillate between out (-5) and part-way in (-0.2)
            let mx;
            if (S.running) { t += 0.016 * S.speed; mx = -2.6 + 2.4 * Math.sin(t); }
            else { mx = manualX ?? -2.6; }
            magnet.position.x = mx;
            const vel = (mx - lastX); lastX = mx;
            // e.m.f. ∝ turns × velocity (rate of flux change)
            const rawEmf = vel * S.turns * 8;
            const clamped = Math.max(-1, Math.min(1, rawEmf / 6));
            // needle deflect (±60°), centre zero
            needle.rotation.z = -clamped * (Math.PI / 3);
            meter.lookAt(camera.position);
            // current arrows: opacity ∝ |emf|, tangential direction flips with sign
            const dir = Math.sign(clamped || 0.0001);
            arrows.forEach((ar) => {
                ar.mat.opacity = Math.min(0.95, Math.abs(clamped) * 1.3);
                // tangent around the x-axis ring
                const tang = new THREE.Vector3(0, -Math.sin(ar.a), Math.cos(ar.a)).multiplyScalar(dir);
                ar.cone.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), tang);
            });
            // coil glow with current
            coilMat.emissiveIntensity = 0.3 + Math.abs(clamped) * 0.9;
            // induced pole (Lenz): approaching (vel>0, moving +x toward coil) → near face N (repel);
            // receding (vel<0) → near face S (attract). Only meaningful while moving.
            if (Math.abs(clamped) > 0.06) {
                const nearIsN = vel > 0;
                poleLbl.material.map = sprite(nearIsN ? 'N' : 'S', nearIsN ? '#ffb3b6' : '#b6c7ff', 0.8).material.map;
                poleLbl.material.opacity = 1;
            } else { poleLbl.material.opacity = 0; }
            // throttle React state updates
            if (Math.abs(clamped * 100 - (simRef.current?._e ?? 0)) > 4) { simRef.current._e = clamped * 100; setEmf(Math.round(clamped * 100)); }
            controls.update();
            renderer.render(scene, camera);
        };
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; renderer.setSize(w, h, false); camera.aspect = w / h; camera.updateProjectionMatrix(); };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();

        simRef.current = { home: applyHome, _e: 0, rebuildCoil: (n) => { const g = helixTube(n); coil.geometry.dispose(); coil.geometry = g; } };
        loop();

        return () => {
            cancelAnimationFrame(raf); ro.disconnect();
            controls.dispose(); renderer.dispose();
            host.contains(renderer.domElement) && host.removeChild(renderer.domElement);
            simRef.current = null;
        };
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => { simRef.current?.rebuildCoil?.(turns); }, [turns]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10', position: 'relative' }}>
                    <span className="cw-badge">3D induction · drag to rotate</span>
                    <div ref={hostRef} style={{ position: 'absolute', inset: 0, borderRadius: 12, overflow: 'hidden', background: 'linear-gradient(180deg,#0d1420,#070b12)' }} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label="induced e.m.f. (galvanometer)" value={`${emf === 0 ? 'zero' : (emf > 0 ? '→ ' : '← ') + Math.abs(emf)}`} tone="acc"
                      sub={<>the needle deflects only while the magnet <b>moves</b> (the flux <b>changes</b>). Move it <b>faster</b> or use <b>more turns</b> for a bigger e.m.f.; the direction <b>reverses</b> between pushing in and pulling out (<b>Lenz's law</b>).</>} />
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (running ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setRunning(!running)}>{running ? '⏸ hold magnet still' : '↻ move the magnet'}</button>
                    <button className="cw-btn cw-btn-ghost" onClick={() => simRef.current?.home?.()}>⤢ reset view</button>
                </div>
                <Slider label="turns on the coil" value={turns} min={2} max={12} step={1} onChange={setTurns} format={(x) => `${x}`} />
                <Slider label="speed of the magnet" value={speed} min={0.3} max={2.5} step={0.1} onChange={setSpeed} format={(x) => `${x.toFixed(1)}×`} />
                <Flag kind="neutral">
                    Moving a magnet into a coil makes the <b>magnetic flux through the coil change</b>, which <b>induces an e.m.f.</b> and
                    drives a current (the needle deflects). The e.m.f. is bigger when the <b>flux changes faster</b> (move the magnet quicker,
                    or cut more field lines) and when there are <b>more turns</b>. Hold the magnet <b>still</b> and there is <b>no change of
                    flux → no e.m.f.</b> The induced current always <b>opposes the change</b> that produced it (<b>Lenz's law</b>): as the
                    magnet approaches, the coil's near face becomes the <b>same pole</b>, pushing back.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, turns, speed, running })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
