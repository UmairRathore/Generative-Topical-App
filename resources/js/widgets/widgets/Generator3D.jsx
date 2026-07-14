import React, { useEffect, useRef, useState } from 'react';
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { Stat, Slider, Flag } from '../primitives.jsx';

// ── Widget: generator_3d ─────────────────────────────────────────────────────
// Genuine 3D (Three.js) a.c. generator: a rectangular coil spins between the poles
// of a magnet; slip rings + brushes take the output off. As it turns, the e.m.f.
// varies sinusoidally, and a live e.m.f.–time trace (overlaid on the stage) shows
// the a.c. output with the coil's position marked — MAX when the coil is in the
// plane of the field (its sides cut field lines fastest) and ZERO when the coil is
// perpendicular (sides moving along the field, cutting nothing).
// 3D by the dimensionality doctrine (spinning coil in a field).
// config: { speed, running }

export default function Generator3D({ config = {}, onReady, onAddToNote }) {
    const [speed, setSpeed] = useState(typeof config.speed === 'number' ? config.speed : 1);
    const [running, setRunning] = useState(config.running !== false);
    const hostRef = useRef(null);
    const graphRef = useRef(null);
    const simRef = useRef(null);
    const st = useRef({}); st.current = { speed, running };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, speed: st.current.speed, running: st.current.running }),
            setState: (s) => {
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
        controls.minDistance = 6; controls.maxDistance = 22; controls.rotateSpeed = 0.7;
        const HOME = { r: 10.5, theta: 0.8, phi: 1.05 };
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

        // ── magnet poles (N red left, S blue right), field along +x ──────────────
        const poleGeo = new THREE.BoxGeometry(0.6, 2.6, 2.6);
        const nPole = new THREE.Mesh(poleGeo, new THREE.MeshStandardMaterial({ color: 0xe5484d, roughness: 0.5, emissive: 0x5a0f12, emissiveIntensity: 0.3 }));
        nPole.position.x = -2.6; scene.add(nPole);
        const sPole = new THREE.Mesh(poleGeo, new THREE.MeshStandardMaterial({ color: 0x4d7bff, roughness: 0.5, emissive: 0x101f5a, emissiveIntensity: 0.3 }));
        sPole.position.x = 2.6; scene.add(sPole);
        scene.add(sprite('N', '#ffd7d8').translateX(-2.6).translateY(1.7));
        scene.add(sprite('S', '#cdd9ff').translateX(2.6).translateY(1.7));
        // faint field lines between poles
        for (let iy = -1; iy <= 1; iy++) for (let iz = -1; iz <= 1; iz++) {
            const g = new THREE.BufferGeometry().setFromPoints([new THREE.Vector3(-2.3, iy, iz), new THREE.Vector3(2.3, iy, iz)]);
            scene.add(new THREE.Line(g, new THREE.LineBasicMaterial({ color: 0x64c8ff, transparent: true, opacity: 0.16 })));
        }

        // ── the coil: a rectangular loop whose two long "active" sides are PARALLEL
        //    to the rotation axis (z). As the coil turns about z, those sides sweep
        //    round through the field (along x) and genuinely CUT field lines — the
        //    correct generator geometry. e.m.f. peaks when the sides lie on the
        //    x-axis (coil in the plane of the field), and is zero on the y-axis. ─────
        const coilGroup = new THREE.Group();
        const wire = new THREE.MeshStandardMaterial({ color: 0xd9a441, roughness: 0.35, metalness: 0.8, emissive: 0x3a2600, emissiveIntensity: 0.3 });
        const Rw = 1.45, Lz = 2.6, tube = 0.06;
        const wireCyl = (len) => new THREE.CylinderGeometry(tube, tube, len, 10);
        const greenMat = new THREE.MeshStandardMaterial({ color: 0x37d399, emissive: 0x0c5c3c, emissiveIntensity: 0.5, metalness: 0.6, roughness: 0.3 });
        const redMat = new THREE.MeshStandardMaterial({ color: 0xfb7185, emissive: 0x5c0c22, emissiveIntensity: 0.5, metalness: 0.6, roughness: 0.3 });
        // two long active sides, parallel to z, at x = ±Rw (cylinder default axis = y → rotate.x 90° → along z)
        const sideA = new THREE.Mesh(wireCyl(Lz), greenMat); sideA.rotation.x = Math.PI / 2; sideA.position.set(Rw, 0, 0); coilGroup.add(sideA);
        const sideB = new THREE.Mesh(wireCyl(Lz), redMat); sideB.rotation.x = Math.PI / 2; sideB.position.set(-Rw, 0, 0); coilGroup.add(sideB);
        // two short end sides, along x, joining the active sides at z = ±Lz/2
        const end1 = new THREE.Mesh(wireCyl(2 * Rw), wire); end1.rotation.z = Math.PI / 2; end1.position.set(0, 0, Lz / 2); coilGroup.add(end1);
        const end2 = new THREE.Mesh(wireCyl(2 * Rw), wire); end2.rotation.z = Math.PI / 2; end2.position.set(0, 0, -Lz / 2); coilGroup.add(end2);
        // leads from the coil ends down toward the slip rings
        const leadMat = new THREE.LineBasicMaterial({ color: 0x9aa4b2 });
        coilGroup.add(new THREE.Line(new THREE.BufferGeometry().setFromPoints([new THREE.Vector3(Rw, 0, -Lz / 2), new THREE.Vector3(0.28, 0, -Lz / 2 - 0.3)]), leadMat));
        coilGroup.add(new THREE.Line(new THREE.BufferGeometry().setFromPoints([new THREE.Vector3(-Rw, 0, -Lz / 2), new THREE.Vector3(-0.28, 0, -Lz / 2 - 0.3)]), leadMat));
        scene.add(coilGroup);

        // slip rings + brushes at the front (−z axis end)
        const ringMat = new THREE.MeshStandardMaterial({ color: 0xcfd6df, metalness: 0.9, roughness: 0.3 });
        const ring1 = new THREE.Mesh(new THREE.TorusGeometry(0.28, 0.06, 10, 28), ringMat);
        ring1.position.set(0, 0, -2.4); scene.add(ring1);
        const ring2 = new THREE.Mesh(new THREE.TorusGeometry(0.28, 0.06, 10, 28), ringMat);
        ring2.position.set(0, 0, -2.75); scene.add(ring2);
        const brushMat = new THREE.MeshStandardMaterial({ color: 0x2b2f36, roughness: 0.8 });
        const b1 = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.5, 0.14), brushMat); b1.position.set(0, -0.5, -2.4); scene.add(b1);
        const b2 = new THREE.Mesh(new THREE.BoxGeometry(0.18, 0.5, 0.14), brushMat); b2.position.set(0, -0.5, -2.75); scene.add(b2);
        scene.add(sprite('slip rings', '#9aa4b2', 0.05));

        let raf = 0, theta = 0;
        // trace history for the e.m.f.-time graph
        const hist = [];
        const drawGraph = (emf) => {
            const cv = graphRef.current; if (!cv) return;
            const host2 = cv.parentElement, w = host2.clientWidth, h = 92;
            const dpr = Math.min(window.devicePixelRatio || 1, 2);
            cv.width = w * dpr; cv.height = h * dpr; cv.style.width = w + 'px'; cv.style.height = h + 'px';
            const cx = cv.getContext('2d'); cx.setTransform(dpr, 0, 0, dpr, 0, 0); cx.clearRect(0, 0, w, h);
            cx.fillStyle = 'rgba(255,255,255,.03)'; cx.fillRect(0, 0, w, h);
            const mid = h / 2;
            cx.strokeStyle = 'rgba(150,164,178,.35)'; cx.lineWidth = 1; cx.beginPath(); cx.moveTo(0, mid); cx.lineTo(w, mid); cx.stroke();
            cx.strokeStyle = '#37d399'; cx.lineWidth = 2; cx.beginPath();
            hist.forEach((v, i) => { const x = (i / (hist.length - 1 || 1)) * w; const y = mid - v * (mid - 8); if (i === 0) cx.moveTo(x, y); else cx.lineTo(x, y); });
            cx.stroke();
            // current point
            const lx = w - 2, ly = mid - emf * (mid - 8);
            cx.fillStyle = '#fbbf24'; cx.beginPath(); cx.arc(lx - 1, ly, 3.5, 0, Math.PI * 2); cx.fill();
            cx.fillStyle = '#68877a'; cx.font = '600 9px Inter'; cx.textAlign = 'left';
            cx.fillText('e.m.f. →', 4, 12); cx.fillText('+', 4, 20); cx.fillText('−', 4, h - 6);
        };

        const loop = () => {
            raf = requestAnimationFrame(loop);
            const S = st.current;
            if (S.running) theta += 0.03 * S.speed;
            coilGroup.rotation.z = theta;
            // e.m.f. ∝ cos(θ): from the geometry, the active sides (parallel to z) cut
            // field lines fastest when they lie on the x-axis (θ = 0, coil in the plane
            // of the field) and cut none on the y-axis (θ = 90°, coil perpendicular).
            const emf = Math.cos(theta);
            wire.emissiveIntensity = 0.3 + Math.abs(emf) * 0.7;
            greenMat.emissiveIntensity = 0.4 + Math.abs(emf) * 0.9;
            redMat.emissiveIntensity = 0.4 + Math.abs(emf) * 0.9;
            hist.push(emf); if (hist.length > 160) hist.shift();
            drawGraph(emf);
            if (simRef.current) simRef.current.emf = emf;
            controls.update();
            renderer.render(scene, camera);
        };
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; renderer.setSize(w, h, false); camera.aspect = w / h; camera.updateProjectionMatrix(); };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();
        simRef.current = { home: applyHome, emf: 0 };
        loop();

        return () => {
            cancelAnimationFrame(raf); ro.disconnect();
            controls.dispose(); renderer.dispose();
            host.contains(renderer.domElement) && host.removeChild(renderer.domElement);
            simRef.current = null;
        };
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10', position: 'relative' }}>
                    <span className="cw-badge">3D a.c. generator · drag to rotate</span>
                    <div ref={hostRef} style={{ position: 'absolute', inset: 0, borderRadius: 12, overflow: 'hidden', background: 'linear-gradient(180deg,#0d1420,#070b12)' }} />
                    <div style={{ position: 'absolute', left: 8, right: 8, bottom: 8, height: 92, borderRadius: 8, overflow: 'hidden', background: 'rgba(6,10,16,.72)', backdropFilter: 'blur(2px)' }}>
                        <canvas ref={graphRef} style={{ display: 'block', width: '100%', height: '92px' }} />
                    </div>
                </div>
            </div>
            <div className="cw-controls">
                <Stat label="the a.c. generator" value="e.m.f. varies sinusoidally" tone="acc"
                      sub={<>a coil spun in a field gives an <b>alternating</b> e.m.f. It is <b>largest</b> when the coil is <b>in the plane of the field</b> (its sides cut field lines fastest) and <b>zero</b> when the coil is <b>perpendicular</b> to the field (sides moving along the lines, cutting none).</>} />
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (running ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setRunning(!running)}>{running ? '⏸ stop the coil' : '↻ spin the coil'}</button>
                    <button className="cw-btn cw-btn-ghost" onClick={() => simRef.current?.home?.()}>⤢ reset view</button>
                </div>
                <Slider label="speed of rotation" value={speed} min={0.3} max={2.5} step={0.1} onChange={setSpeed} format={(x) => `${x.toFixed(1)}×`} />
                <Flag kind="neutral">
                    In an <b>a.c. generator</b>, a coil is spun in a magnetic field. As it turns, the coil <b>cuts magnetic field lines</b>
                    and an <b>e.m.f. is induced</b> that reverses every half-turn — giving <b>alternating current</b>. The
                    <b> slip rings and brushes</b> connect the spinning coil to the outside circuit. The e.m.f. is at a <b>peak</b> when the
                    coil is moving through the plane of the field (sides cutting fastest) and <b>zero</b> as the coil lies across the field
                    (sides moving parallel to the lines) — trace the graph and watch it match the coil's position. Spinning <b>faster</b>
                    gives a bigger and more frequent e.m.f.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, speed, running })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
