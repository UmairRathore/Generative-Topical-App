import React, { useEffect, useRef, useState } from 'react';
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: dc_motor_3d ──────────────────────────────────────────────────────
// Genuine 3D (Three.js) d.c. motor. A rectangular coil turns between magnet poles.
// The two long sides carry current in opposite directions, so (by the motor effect)
// they feel OPPOSITE forces — a couple that turns the coil. A SPLIT-RING COMMUTATOR
// reverses the current in the coil every half-turn (at the dead points where the
// couple would otherwise reverse), so the coil keeps spinning the SAME way. The
// turning effect grows with the number of turns, the current and the field strength.
// config: { turns, current, field, running }

export default function DcMotor3D({ config = {}, onReady, onAddToNote }) {
    const [turns, setTurns] = useState(typeof config.turns === 'number' ? config.turns : 3);
    const [current, setCurrent] = useState(typeof config.current === 'number' ? config.current : 2);
    const [field, setField] = useState(typeof config.field === 'number' ? config.field : 2);
    const [running, setRunning] = useState(config.running !== false);
    const hostRef = useRef(null);
    const simRef = useRef(null);
    const st = useRef({}); st.current = { turns, current, field, running };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, turns: st.current.turns, current: st.current.current, field: st.current.field, running: st.current.running }),
            setState: (s) => {
                if (typeof s?.turns === 'number') setTurns(Math.max(1, Math.min(6, Math.round(s.turns))));
                if (typeof s?.current === 'number') setCurrent(Math.max(1, Math.min(4, s.current)));
                if (typeof s?.field === 'number') setField(Math.max(1, Math.min(4, s.field)));
                if (typeof s?.running === 'boolean') setRunning(s.running);
            },
        });
    }, [onReady]); // eslint-disable-line

    const turningEffect = (turns * current * field / (6 * 4 * 4) * 100); // % of max, for the readout

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
        controls.minDistance = 6; controls.maxDistance = 22; controls.rotateSpeed = 0.7;
        const HOME = { r: 10.5, theta: 0.78, phi: 1.02 };
        const applyHome = () => {
            camera.position.set(HOME.r * Math.sin(HOME.phi) * Math.sin(HOME.theta), HOME.r * Math.cos(HOME.phi), HOME.r * Math.sin(HOME.phi) * Math.cos(HOME.theta));
            controls.target.set(0, 0, 0); controls.update();
        };
        applyHome();
        scene.add(new THREE.AmbientLight(0xbcd4ff, 0.7));
        const key = new THREE.DirectionalLight(0xffffff, 1.05); key.position.set(5, 9, 7); scene.add(key);
        const rim = new THREE.DirectionalLight(0x38bdf8, 0.5); rim.position.set(-6, 3, -5); scene.add(rim);

        const sprite = (text, color, s = 0.7) => {
            const c = document.createElement('canvas'); c.width = 96; c.height = 96;
            const cx = c.getContext('2d'); cx.fillStyle = color; cx.font = 'bold 58px Inter,sans-serif';
            cx.textAlign = 'center'; cx.textBaseline = 'middle'; cx.fillText(text, 48, 52);
            const sp = new THREE.Sprite(new THREE.SpriteMaterial({ map: new THREE.CanvasTexture(c), transparent: true, depthTest: false }));
            sp.scale.set(s, s, 1); return sp;
        };

        // magnet poles: N (red) at -x, S (blue) at +x → field B points +x
        const poleGeo = new THREE.BoxGeometry(0.55, 2.4, 2.4);
        const nPole = new THREE.Mesh(poleGeo, new THREE.MeshStandardMaterial({ color: 0xe5484d, roughness: 0.5, emissive: 0x5a0f12, emissiveIntensity: 0.3 })); nPole.position.x = -2.4; scene.add(nPole);
        const sPole = new THREE.Mesh(poleGeo, new THREE.MeshStandardMaterial({ color: 0x4d7bff, roughness: 0.5, emissive: 0x101f5a, emissiveIntensity: 0.3 })); sPole.position.x = 2.4; scene.add(sPole);
        scene.add(sprite('N', '#ffd7d8', 0.6).translateX(-2.4).translateY(1.55));
        scene.add(sprite('S', '#cdd9ff', 0.6).translateX(2.4).translateY(1.55));

        // ── the coil (long sides parallel to the rotation axis z) ────────────────
        const coilGroup = new THREE.Group(); scene.add(coilGroup);
        const Rw = 1.35, Lz = 2.4, tube = 0.06;
        const wireCyl = (len) => new THREE.CylinderGeometry(tube, tube, len, 10);
        const greenMat = new THREE.MeshStandardMaterial({ color: 0x37d399, emissive: 0x0c5c3c, emissiveIntensity: 0.5, metalness: 0.6, roughness: 0.3 });
        const redMat = new THREE.MeshStandardMaterial({ color: 0xfb7185, emissive: 0x5c0c22, emissiveIntensity: 0.5, metalness: 0.6, roughness: 0.3 });
        const goldMat = new THREE.MeshStandardMaterial({ color: 0xd9a441, metalness: 0.8, roughness: 0.35, emissive: 0x3a2600, emissiveIntensity: 0.3 });
        const sideA = new THREE.Mesh(wireCyl(Lz), greenMat); sideA.rotation.x = Math.PI / 2; sideA.position.set(Rw, 0, 0); coilGroup.add(sideA);
        const sideB = new THREE.Mesh(wireCyl(Lz), redMat); sideB.rotation.x = Math.PI / 2; sideB.position.set(-Rw, 0, 0); coilGroup.add(sideB);
        const end1 = new THREE.Mesh(wireCyl(2 * Rw), goldMat); end1.rotation.z = Math.PI / 2; end1.position.set(0, 0, Lz / 2); coilGroup.add(end1);
        const end2 = new THREE.Mesh(wireCyl(2 * Rw), goldMat); end2.rotation.z = Math.PI / 2; end2.position.set(0, 0, -Lz / 2); coilGroup.add(end2);

        // force arrows on the two sides (world ±y, flipped by the commutator each half-turn)
        const arrA = new THREE.ArrowHelper(new THREE.Vector3(0, 1, 0), new THREE.Vector3(Rw, 0, 0), 1.1, 0x9be7ff, 0.4, 0.24); scene.add(arrA);
        const arrB = new THREE.ArrowHelper(new THREE.Vector3(0, -1, 0), new THREE.Vector3(-Rw, 0, 0), 1.1, 0x9be7ff, 0.4, 0.24); scene.add(arrB);
        const lblFA = sprite('F', '#c8f0ff', 0.5), lblFB = sprite('F', '#c8f0ff', 0.5); scene.add(lblFA, lblFB);

        // ── split-ring commutator + brushes (at the front z end) ─────────────────
        const commGroup = new THREE.Group(); commGroup.position.set(0, 0, -Lz / 2 - 0.5); scene.add(commGroup);
        const halfA = new THREE.Mesh(new THREE.TorusGeometry(0.34, 0.09, 8, 20, Math.PI), new THREE.MeshStandardMaterial({ color: 0x37d399, metalness: 0.85, roughness: 0.3 }));
        const halfB = new THREE.Mesh(new THREE.TorusGeometry(0.34, 0.09, 8, 20, Math.PI), new THREE.MeshStandardMaterial({ color: 0xfb7185, metalness: 0.85, roughness: 0.3 }));
        halfB.rotation.z = Math.PI; commGroup.add(halfA, halfB);
        const brushMat = new THREE.MeshStandardMaterial({ color: 0x2b2f36, roughness: 0.8 });
        const bTop = new THREE.Mesh(new THREE.BoxGeometry(0.16, 0.32, 0.16), brushMat); bTop.position.set(0, 0.5, -Lz / 2 - 0.5); scene.add(bTop);
        const bBot = new THREE.Mesh(new THREE.BoxGeometry(0.16, 0.32, 0.16), brushMat); bBot.position.set(0, -0.5, -Lz / 2 - 0.5); scene.add(bBot);
        scene.add(sprite('commutator', '#9aa4b2', 0.02));

        let raf = 0, theta = 0;
        const loop = () => {
            raf = requestAnimationFrame(loop);
            const S = st.current;
            const speed = 0.006 * S.turns * S.current * S.field; // turning effect ∝ N·I·B
            if (S.running) theta += speed;
            coilGroup.rotation.z = theta;
            commGroup.rotation.z = theta;
            // commutator: the current (and so the force) reverses every half-turn, at the dead points
            const commSign = Math.cos(theta) >= 0 ? 1 : -1;
            const Fy = commSign * (0.7 + 0.5 * (S.current * S.field) / 16);
            // side A currently at angle θ; force is world ±y
            const ax = Rw * Math.cos(theta), ay = Rw * Math.sin(theta);
            arrA.position.set(ax, ay, 0); arrA.setDirection(new THREE.Vector3(0, Math.sign(Fy), 0)); arrA.setLength(Math.abs(Fy) + 0.5, 0.35, 0.22);
            arrB.position.set(-ax, -ay, 0); arrB.setDirection(new THREE.Vector3(0, -Math.sign(Fy), 0)); arrB.setLength(Math.abs(Fy) + 0.5, 0.35, 0.22);
            lblFA.position.set(ax, ay + Math.sign(Fy) * (Math.abs(Fy) + 0.7), 0);
            lblFB.position.set(-ax, -ay - Math.sign(Fy) * (Math.abs(Fy) + 0.7), 0);
            greenMat.emissiveIntensity = 0.5 + 0.4 * (S.current / 4);
            redMat.emissiveIntensity = 0.5 + 0.4 * (S.current / 4);
            controls.update(); renderer.render(scene, camera);
        };
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; renderer.setSize(w, h, false); camera.aspect = w / h; camera.updateProjectionMatrix(); };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();
        simRef.current = { home: applyHome };
        loop();
        return () => {
            cancelAnimationFrame(raf); ro.disconnect(); controls.dispose(); renderer.dispose();
            host.contains(renderer.domElement) && host.removeChild(renderer.domElement); simRef.current = null;
        };
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10', position: 'relative' }}>
                    <span className="cw-badge">3D d.c. motor · drag to rotate</span>
                    <div ref={hostRef} style={{ position: 'absolute', inset: 0, borderRadius: 12, overflow: 'hidden', background: 'linear-gradient(180deg,#0d1420,#070b12)' }} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label="turning effect on the coil" value={`${Math.round(turningEffect)}% of max`} tone="acc"
                      sub={<>the two sides feel <b>opposite forces</b> (a couple) that <b>turn</b> the coil. The turning effect grows with the <b>number of turns</b>, the <b>current</b> and the <b>field strength</b>. The <b>split-ring commutator</b> reverses the current each half-turn so it keeps spinning the same way.</>} />
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (running ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setRunning(!running)}>{running ? '⏸ stop the motor' : '↻ run the motor'}</button>
                    <button className="cw-btn cw-btn-ghost" onClick={() => simRef.current?.home?.()}>⤢ reset view</button>
                </div>
                <Slider label="number of turns" value={turns} min={1} max={6} step={1} onChange={setTurns} format={(x) => `${x}`} />
                <Slider label="current" value={current} min={1} max={4} step={0.5} onChange={setCurrent} format={(x) => `${x.toFixed(1)} A`} />
                <Slider label="magnetic field strength" value={field} min={1} max={4} step={0.5} onChange={setField} format={(x) => `${x.toFixed(1)}×`} />
                <Flag kind="neutral">
                    In a <b>d.c. motor</b>, a current-carrying <b>coil</b> sits in a magnetic field. Its two sides carry current in
                    <b> opposite</b> directions, so (by the motor effect) they feel <b>opposite forces</b> — a <b>couple</b> that makes the coil
                    <b> turn</b>. Half a turn later the couple would reverse and stop it, so a <b>split-ring commutator</b> (with two <b>brushes</b>)
                    <b> reverses the current</b> in the coil every half-turn — keeping the turning force in the <b>same sense</b>, so it spins
                    continuously. The turning effect is <b>increased</b> by more <b>turns</b>, a bigger <b>current</b>, and a stronger <b>field</b>.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, turns, current, field, running })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
