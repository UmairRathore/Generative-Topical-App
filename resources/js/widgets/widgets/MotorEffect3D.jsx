import React, { useEffect, useRef, useState } from 'react';
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { Stat, Flag } from '../primitives.jsx';

// ── Widget: motor_effect_3d ──────────────────────────────────────────────────
// Genuine 3D (Three.js) motor effect. This is the section that MUST be 3D: the
// force on a current-carrying conductor is mutually PERPENDICULAR to the field
// and the current (Fleming's left-hand rule), which the flat dots/crosses of a
// 2D diagram hide.
//   FORCE    — a straight conductor (current I) between magnet poles (field B).
//              The force F is drawn as the third perpendicular arrow, computed
//              live as F ∝ I × B, so reversing EITHER the current OR the field
//              reverses F. The wire visibly kicks in the F direction.
//   PARALLEL — two parallel conductors: same-direction currents ATTRACT (their
//              fields cancel between them), opposite-direction currents REPEL.
// config: { mode, currentRev, fieldRev, opposite }

export default function MotorEffect3D({ config = {}, onReady, onAddToNote }) {
    const [mode, setMode] = useState(['force', 'parallel'].includes(config.mode) ? config.mode : 'force');
    const [currentRev, setCurrentRev] = useState(!!config.currentRev);
    const [fieldRev, setFieldRev] = useState(!!config.fieldRev);
    const [opposite, setOpposite] = useState(!!config.opposite);
    const hostRef = useRef(null);
    const simRef = useRef(null);
    const st = useRef({}); st.current = { mode, currentRev, fieldRev, opposite };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, mode: st.current.mode, currentRev: st.current.currentRev, fieldRev: st.current.fieldRev, opposite: st.current.opposite }),
            setState: (s) => {
                if (['force', 'parallel'].includes(s?.mode)) setMode(s.mode);
                if (typeof s?.currentRev === 'boolean') setCurrentRev(s.currentRev);
                if (typeof s?.fieldRev === 'boolean') setFieldRev(s.fieldRev);
                if (typeof s?.opposite === 'boolean') setOpposite(s.opposite);
            },
        });
    }, [onReady]); // eslint-disable-line

    // the force direction, computed live from the physics (F ∝ I × B) for the readout
    const Idir = currentRev ? '−z (into the diagram)' : '+z (out of the diagram)';
    const Bdir = fieldRev ? '−x (S → N)' : '+x (N → S)';

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
        const HOME = { r: 10.5, theta: 0.72, phi: 1.05 };
        const applyHome = () => {
            camera.position.set(HOME.r * Math.sin(HOME.phi) * Math.sin(HOME.theta), HOME.r * Math.cos(HOME.phi), HOME.r * Math.sin(HOME.phi) * Math.cos(HOME.theta));
            controls.target.set(0, 0, 0); controls.update();
        };
        applyHome();
        scene.add(new THREE.AmbientLight(0xbcd4ff, 0.7));
        const key = new THREE.DirectionalLight(0xffffff, 1.05); key.position.set(5, 9, 7); scene.add(key);
        const rim = new THREE.DirectionalLight(0x38bdf8, 0.5); rim.position.set(-6, 3, -5); scene.add(rim);

        const sprite = (text, color, s = 0.7) => {
            const c = document.createElement('canvas'); c.width = 128; c.height = 64;
            const cx = c.getContext('2d'); cx.fillStyle = color; cx.font = 'bold 40px Inter,sans-serif';
            cx.textAlign = 'center'; cx.textBaseline = 'middle'; cx.fillText(text, 64, 34);
            const sp = new THREE.Sprite(new THREE.SpriteMaterial({ map: new THREE.CanvasTexture(c), transparent: true, depthTest: false }));
            sp.scale.set(s * 2, s, 1); return sp;
        };

        // ── FORCE group ──────────────────────────────────────────────────────────
        const forceGroup = new THREE.Group(); scene.add(forceGroup);
        // magnet poles: N (red) at -x, S (blue) at +x → field B points +x (N to S)
        const poleGeo = new THREE.BoxGeometry(0.55, 2.4, 2.4);
        const nPole = new THREE.Mesh(poleGeo, new THREE.MeshStandardMaterial({ color: 0xe5484d, roughness: 0.5, emissive: 0x5a0f12, emissiveIntensity: 0.3 }));
        nPole.position.x = -2.4; forceGroup.add(nPole);
        const sPole = new THREE.Mesh(poleGeo, new THREE.MeshStandardMaterial({ color: 0x4d7bff, roughness: 0.5, emissive: 0x101f5a, emissiveIntensity: 0.3 }));
        sPole.position.x = 2.4; forceGroup.add(sPole);
        forceGroup.add(sprite('N', '#ffd7d8', 0.6).translateX(-2.4).translateY(1.55));
        forceGroup.add(sprite('S', '#cdd9ff', 0.6).translateX(2.4).translateY(1.55));
        // conductor along z
        const wireGroup = new THREE.Group(); forceGroup.add(wireGroup);
        const wire = new THREE.Mesh(new THREE.CylinderGeometry(0.1, 0.1, 4.2, 16), new THREE.MeshStandardMaterial({ color: 0xd9a441, metalness: 0.85, roughness: 0.3, emissive: 0x3a2600, emissiveIntensity: 0.5 }));
        wire.rotation.x = Math.PI / 2; wireGroup.add(wire);
        // three perpendicular arrows (I blue, B orange, F green) from the origin
        const O = new THREE.Vector3(0, 0, 0);
        const arrI = new THREE.ArrowHelper(new THREE.Vector3(0, 0, 1), O, 2, 0x38bdf8, 0.5, 0.28); forceGroup.add(arrI);
        const arrB = new THREE.ArrowHelper(new THREE.Vector3(1, 0, 0), O, 2, 0xfb923c, 0.5, 0.28); forceGroup.add(arrB);
        const arrF = new THREE.ArrowHelper(new THREE.Vector3(0, 1, 0), O, 2, 0x37d399, 0.55, 0.3); forceGroup.add(arrF);
        const lblI = sprite('I', '#8fd6ff', 0.55), lblB = sprite('B', '#ffbf87', 0.55), lblF = sprite('F', '#7bf0c2', 0.6);
        forceGroup.add(lblI, lblB, lblF);
        // current dots along the wire
        const dotGeo = new THREE.SphereGeometry(0.09, 10, 10);
        const dotMat = new THREE.MeshStandardMaterial({ color: 0x9be7ff, emissive: 0x2a6f9a, emissiveIntensity: 0.9 });
        const dots = []; for (let i = 0; i < 8; i++) { const d = new THREE.Mesh(dotGeo, dotMat); wireGroup.add(d); dots.push(d); }

        // ── PARALLEL group ───────────────────────────────────────────────────────
        const parGroup = new THREE.Group(); scene.add(parGroup);
        const mkWire = (x, col) => {
            const g = new THREE.Group();
            const w = new THREE.Mesh(new THREE.CylinderGeometry(0.1, 0.1, 4.4, 16), new THREE.MeshStandardMaterial({ color: 0xd9a441, metalness: 0.85, roughness: 0.3, emissive: 0x3a2600, emissiveIntensity: 0.5 }));
            g.add(w); g.position.x = x; parGroup.add(g); return g;
        };
        const wL = mkWire(-1.3), wR = mkWire(1.3);
        const arrL = new THREE.ArrowHelper(new THREE.Vector3(1, 0, 0), new THREE.Vector3(-1.3, 0, 0), 0.9, 0x37d399, 0.35, 0.22); parGroup.add(arrL);
        const arrR = new THREE.ArrowHelper(new THREE.Vector3(-1, 0, 0), new THREE.Vector3(1.3, 0, 0), 0.9, 0x37d399, 0.35, 0.22); parGroup.add(arrR);
        const curL = new THREE.ArrowHelper(new THREE.Vector3(0, 1, 0), new THREE.Vector3(-1.3, -2.4, 0), 1.4, 0x38bdf8, 0.4, 0.24); parGroup.add(curL);
        const curR = new THREE.ArrowHelper(new THREE.Vector3(0, 1, 0), new THREE.Vector3(1.3, -2.4, 0), 1.4, 0x38bdf8, 0.4, 0.24); parGroup.add(curR);
        const parLbl = sprite('', '#7bf0c2', 0.7); parLbl.position.set(0, 2.4, 0); parGroup.add(parLbl);

        const applyMode = () => {
            const S = st.current;
            forceGroup.visible = S.mode === 'force';
            parGroup.visible = S.mode === 'parallel';
            if (S.mode === 'force') {
                const Iv = new THREE.Vector3(0, 0, S.currentRev ? -1 : 1);
                const Bv = new THREE.Vector3(S.fieldRev ? -1 : 1, 0, 0);
                const Fv = Iv.clone().cross(Bv).normalize(); // F ∝ I × B (conventional current)
                arrI.setDirection(Iv); arrB.setDirection(Bv); arrF.setDirection(Fv);
                lblI.position.copy(Iv.clone().multiplyScalar(2.3));
                lblB.position.copy(Bv.clone().multiplyScalar(2.3));
                lblF.position.copy(Fv.clone().multiplyScalar(2.4));
                simRef.current && (simRef.current.Fv = Fv, simRef.current.Iv = Iv);
            } else {
                const same = !S.opposite;
                // same-direction currents attract; opposite repel
                curL.setDirection(new THREE.Vector3(0, 1, 0));
                curR.setDirection(new THREE.Vector3(0, same ? 1 : -1, 0));
                curR.position.set(1.3, same ? -2.4 : 2.4, 0);
                arrL.setDirection(new THREE.Vector3(same ? 1 : -1, 0, 0));
                arrR.setDirection(new THREE.Vector3(same ? -1 : 1, 0, 0));
                parLbl.material.map = sprite(same ? 'ATTRACT' : 'REPEL', same ? '#7bf0c2' : '#fca5a5', 0.7).material.map;
            }
        };

        let raf = 0, t = 0;
        const loop = () => {
            raf = requestAnimationFrame(loop); t += 0.016;
            const S = st.current;
            if (S.mode === 'force') {
                const Iv = simRef.current?.Iv || new THREE.Vector3(0, 0, 1);
                const Fv = simRef.current?.Fv || new THREE.Vector3(0, 1, 0);
                // current dots drift along the wire in the current direction
                dots.forEach((d, i) => { let u = ((i / 8) + t * 0.15) % 1; d.position.copy(Iv.clone().multiplyScalar((u - 0.5) * 4.2)); });
                // the wire bobs in the F direction
                wireGroup.position.copy(Fv.clone().multiplyScalar(0.28 + 0.12 * Math.sin(t * 2)));
            }
            controls.update(); renderer.render(scene, camera);
        };
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; renderer.setSize(w, h, false); camera.aspect = w / h; camera.updateProjectionMatrix(); };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();
        simRef.current = { home: applyHome, applyMode, Fv: new THREE.Vector3(0, 1, 0), Iv: new THREE.Vector3(0, 0, 1) };
        applyMode(); loop();
        return () => {
            cancelAnimationFrame(raf); ro.disconnect(); controls.dispose(); renderer.dispose();
            host.contains(renderer.domElement) && host.removeChild(renderer.domElement); simRef.current = null;
        };
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => { simRef.current?.applyMode?.(); }, [mode, currentRev, fieldRev, opposite]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10', position: 'relative' }}>
                    <span className="cw-badge">{mode === 'force' ? '3D motor effect · drag to rotate' : '3D parallel wires · drag to rotate'}</span>
                    <div ref={hostRef} style={{ position: 'absolute', inset: 0, borderRadius: 12, overflow: 'hidden', background: 'linear-gradient(180deg,#0d1420,#070b12)' }} />
                </div>
            </div>
            <div className="cw-controls">
                <div className="cw-btnrow">
                    <button className={'cw-btn ' + (mode === 'force' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('force')}>⊥ Force on a wire</button>
                    <button className={'cw-btn ' + (mode === 'parallel' ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setMode('parallel')}>∥ Parallel wires</button>
                </div>
                {mode === 'force' ? (
                    <>
                        <Stat label="force on a current in a field" value="F ⊥ B ⊥ I" tone="acc"
                              sub={<>the force <b style={{ color: '#7bf0c2' }}>F</b> is at <b>right angles to both</b> the field <b style={{ color: '#ffbf87' }}>B</b> and the current <b style={{ color: '#8fd6ff' }}>I</b> — <b>Fleming's left-hand rule</b>. Reverse <b>either</b> the current or the field and F reverses.</>} />
                        <div className="cw-btnrow">
                            <button className={'cw-btn ' + (currentRev ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setCurrentRev(!currentRev)}>⇄ reverse current (I now {Idir})</button>
                        </div>
                        <div className="cw-btnrow">
                            <button className={'cw-btn ' + (fieldRev ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setFieldRev(!fieldRev)}>⇄ reverse field (B now {Bdir})</button>
                            <button className="cw-btn cw-btn-ghost" onClick={() => simRef.current?.home?.()}>⤢ reset view</button>
                        </div>
                        <Flag kind="neutral">
                            A <b>current-carrying wire in a magnetic field feels a force</b> (the motor effect). Crucially, the force is
                            <b> perpendicular to BOTH the field and the current</b> — that is why this needs 3D. Use <b>Fleming's left-hand rule</b>:
                            hold the thumb and first two fingers of your <b>left hand</b> at right angles — <b>F</b>irst finger = <b>F</b>ield (N→S),
                            se<b>C</b>ond finger = <b>C</b>urrent, thu<b>M</b>b = <b>M</b>otion (force). Reversing <b>either</b> the current or the
                            field reverses the force; reversing <b>both</b> leaves it unchanged.
                        </Flag>
                    </>
                ) : (
                    <>
                        <Stat label="two parallel currents" value={opposite ? 'REPEL' : 'ATTRACT'} tone="acc"
                              sub={opposite ? <>currents in <b>opposite</b> directions <b>repel</b> — their fields <b>add</b> in the gap, pushing the wires apart.</> : <>currents in the <b>same</b> direction <b>attract</b> — their fields <b>cancel</b> in the gap between them, and the wires are pushed together.</>} />
                        <div className="cw-btnrow">
                            <button className={'cw-btn ' + (!opposite ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setOpposite(false)}>same direction</button>
                            <button className={'cw-btn ' + (opposite ? 'cw-btn-save' : 'cw-btn-ghost')} onClick={() => setOpposite(true)}>opposite directions</button>
                            <button className="cw-btn cw-btn-ghost" onClick={() => simRef.current?.home?.()}>⤢ reset view</button>
                        </div>
                        <Flag kind="neutral">
                            Each current-carrying wire makes its own magnetic field, and each sits in the other's field — so each feels a force.
                            <b> Same-direction</b> currents <b>attract</b> (between the wires the two fields point opposite ways and partly
                            <b> cancel</b>, so the field there is weak and the wires are drawn together). <b>Opposite-direction</b> currents
                            <b> repel</b> (the fields <b>add</b> in the gap, pushing the wires apart). (The Earth's field is ignored here.)
                        </Flag>
                    </>
                )}
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, mode, currentRev, fieldRev, opposite })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
