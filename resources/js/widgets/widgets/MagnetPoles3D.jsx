import React, { useEffect, useRef, useState } from 'react';
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { Stat, Flag } from '../primitives.jsx';

// ── Widget: magnet_poles_3d ──────────────────────────────────────────────────
// A genuine 3D (Three.js) attract/repel scene: two bar magnets face each other.
// Flip one and the field between them — traced from the real four-pole field —
// either LINKS across the gap (unlike poles → attract) or CLASHES and pushes
// apart, leaving a neutral gap (like poles → repel). Force arrows and a nudge
// animation reinforce it. The four poles sit on one axis, so the field is
// axially symmetric — traced in a plane and instanced around the axis for a
// full 3D cage. config: { flipped }
//
// 3D per the dimensionality doctrine: two magnets interacting is a spatial field.

const A = 1.15;            // half-length of each magnet
const XL = -2.0, XR = 2.0; // magnet centres on x (tighter gap → clearer link/clash interaction)

export default function MagnetPoles3D({ config = {}, onReady, onAddToNote }) {
    const [flipped, setFlipped] = useState(!!config.flipped);
    const hostRef = useRef(null);
    const simRef = useRef(null);
    const st = useRef({}); st.current = { flipped };

    useEffect(() => {
        onReady?.({
            getState: () => ({ ...config, flipped: st.current.flipped }),
            setState: (s) => { if (typeof s?.flipped === 'boolean') setFlipped(s.flipped); },
        });
    }, [onReady]); // eslint-disable-line

    const attract = flipped;

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
        controls.minDistance = 8; controls.maxDistance = 26; controls.rotateSpeed = 0.7;
        const HOME = { r: 14, theta: 0.62, phi: 1.12 };
        const applyHome = () => {
            camera.position.set(HOME.r * Math.sin(HOME.phi) * Math.sin(HOME.theta), HOME.r * Math.cos(HOME.phi), HOME.r * Math.sin(HOME.phi) * Math.cos(HOME.theta));
            controls.target.set(0, 0, 0); controls.update();
        };
        applyHome();

        scene.add(new THREE.AmbientLight(0xbcd4ff, 0.6));
        const key = new THREE.DirectionalLight(0xffffff, 1.1); key.position.set(5, 9, 7); scene.add(key);
        const rim = new THREE.DirectionalLight(0x38bdf8, 0.7); rim.position.set(-6, 3, -5); scene.add(rim);

        const redMat = new THREE.MeshStandardMaterial({ color: 0xe5484d, roughness: 0.35, metalness: 0.4, emissive: 0x5a0f12, emissiveIntensity: 0.35 });
        const blueMat = new THREE.MeshStandardMaterial({ color: 0x4d7bff, roughness: 0.35, metalness: 0.4, emissive: 0x101f5a, emissiveIntensity: 0.35 });
        const halfGeo = new THREE.BoxGeometry(A, 0.62, 0.62);

        // Build one bar magnet group; nLeft = N pole on the left half. Returns the
        // group plus its two half-meshes so the polarity can be flipped by
        // swapping materials (no rebuild needed).
        const makeMagnet = (nLeft) => {
            const g = new THREE.Group();
            const l = new THREE.Mesh(halfGeo, nLeft ? redMat : blueMat); l.position.x = -A / 2; g.add(l);
            const r = new THREE.Mesh(halfGeo, nLeft ? blueMat : redMat); r.position.x = A / 2; g.add(r);
            g.add(new THREE.LineSegments(new THREE.EdgesGeometry(new THREE.BoxGeometry(2 * A, 0.62, 0.62)), new THREE.LineBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0.25 })));
            return { g, l, r };
        };
        const sprite = (text, color) => {
            const c = document.createElement('canvas'); c.width = 80; c.height = 80;
            const cx = c.getContext('2d'); cx.fillStyle = color; cx.font = 'bold 60px Inter,sans-serif';
            cx.textAlign = 'center'; cx.textBaseline = 'middle'; cx.fillText(text, 40, 44);
            const sp = new THREE.Sprite(new THREE.SpriteMaterial({ map: new THREE.CanvasTexture(c), transparent: true, depthTest: false }));
            sp.scale.set(0.75, 0.75, 1); return sp;
        };

        const world = new THREE.Group(); scene.add(world);
        const magLObj = makeMagnet(true); const magL = magLObj.g; magL.position.x = XL; world.add(magL);   // left: N-left, S faces gap (fixed)
        const magRObj = makeMagnet(false); const magR = magRObj.g; magR.position.x = XR; world.add(magR);  // right: polarity flips via material swap
        // pole labels on the facing ends
        const lblLS = sprite('S', '#cdd9ff'); lblLS.position.set(XL + A + 0.35, 0.55, 0); world.add(lblLS);
        const lblRFace = sprite('S', '#cdd9ff'); lblRFace.position.set(XR - A - 0.35, 0.55, 0); world.add(lblRFace);

        const fieldGroup = new THREE.Group(); world.add(fieldGroup);
        const coneGeo = new THREE.ConeGeometry(0.1, 0.3, 12);
        const azis = [0, Math.PI / 3, 2 * Math.PI / 3, Math.PI, 4 * Math.PI / 3, 5 * Math.PI / 3];

        // force arrows (two 3D cones, one per magnet, pointing in/out)
        const forceMat = new THREE.MeshStandardMaterial({ color: 0x34d399, emissive: 0x0e5a3c, emissiveIntensity: 0.8, roughness: 0.3 });
        const forceMatRep = new THREE.MeshStandardMaterial({ color: 0xfb7185, emissive: 0x5a1020, emissiveIntensity: 0.8, roughness: 0.3 });
        const arrow = (mat) => { const g = new THREE.Group(); const shaft = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.06, 1, 10), mat); const cone = new THREE.Mesh(new THREE.ConeGeometry(0.16, 0.4, 14), mat); g.add(shaft, cone); g.userData = { shaft, cone }; return g; };
        const aL = arrow(forceMat), aR = arrow(forceMat); world.add(aL, aR);

        const setArrow = (g, cx, dir) => {   // dir = +1 points right, -1 left; horizontal at y=1.05
            const { shaft, cone } = g.userData; const len = 0.9;
            g.rotation.z = dir > 0 ? -Math.PI / 2 : Math.PI / 2;
            g.position.set(cx, 1.15, 0);
            shaft.position.set(0, len / 2, 0); shaft.scale.y = len;
            cone.position.set(0, len + 0.2, 0);
        };

        let poles = [];
        const rebuild = () => {
            const flip = st.current.flipped;
            // flip the right magnet's polarity by swapping half-materials:
            //   attract (flip) → N faces the gap → left half red (N), right half blue (S)
            //   repel  (!flip) → S faces the gap → left half blue (S), right half red (N)
            magRObj.l.material = flip ? redMat : blueMat;
            magRObj.r.material = flip ? blueMat : redMat;
            // update the facing-pole label (N when attract, S when repel)
            const c = lblRFace.material.map.image; const cx = c.getContext('2d'); cx.clearRect(0, 0, 80, 80);
            cx.fillStyle = flip ? '#ffd7d8' : '#cdd9ff'; cx.font = 'bold 60px Inter,sans-serif'; cx.textAlign = 'center'; cx.textBaseline = 'middle';
            cx.fillText(flip ? 'N' : 'S', 40, 44); lblRFace.material.map.needsUpdate = true;

            // four poles on the x-axis: left N,S then right two
            poles = [
                { x: XL - A, q: +1 }, { x: XL + A, q: -1 },                          // left: N (outer), S (facing)
                flip ? { x: XR - A, q: +1 } : { x: XR - A, q: -1 },                  // right facing: N(attract) / S(repel)
                flip ? { x: XR + A, q: -1 } : { x: XR + A, q: +1 },                  // right outer
            ];
            drawField();
        };

        const fieldAt = (x, y) => {
            let fx = 0, fy = 0;
            for (const p of poles) { const dx = x - p.x, dy = y; const r = Math.hypot(dx, dy) + 1e-3; const k = p.q / (r * r * r); fx += k * dx; fy += k * dy; }
            return [fx, fy];
        };
        const insideMagnet = (x, y) => Math.abs(y) < 0.42 && ((x > XL - A - 0.05 && x < XL + A + 0.05) || (x > XR - A - 0.05 && x < XR + A + 0.05));

        const traceFrom = (sx, sy) => {
            const pts = [new THREE.Vector3(sx, sy, 0)]; let x = sx, y = sy;
            for (let i = 0; i < 600; i += 1) {
                const [fx, fy] = fieldAt(x, y); const m = Math.hypot(fx, fy); if (m < 1e-6) break;
                x += (fx / m) * 0.05; y += (fy / m) * 0.05;
                if (Math.hypot(x, y) > 12) break;
                // reached an S pole?
                let atS = false; for (const p of poles) if (p.q < 0 && Math.hypot(x - p.x, y) < 0.25) { pts.push(new THREE.Vector3(p.x, 0, 0)); atS = true; break; }
                if (atS) break;
                if (insideMagnet(x, y)) break;
                pts.push(new THREE.Vector3(x, y, 0));
            }
            return pts;
        };

        const drawField = () => {
            fieldGroup.remove(...fieldGroup.children);
            const seeds = [0.2, 0.5, 0.95, 1.5];
            const nPoles = poles.filter((p) => p.q > 0);
            nPoles.forEach((np) => {
                const ox = np.x < 0 ? -0.2 : (np.x < XR ? -0.2 : 0.2); // seed just OUTSIDE the magnet body
                seeds.forEach((dy) => {
                    const pts = traceFrom(np.x + ox, dy);
                    if (pts.length < 4) return;
                    const geo = new THREE.BufferGeometry().setFromPoints(pts);
                    const idx = Math.floor(pts.length * 0.4);
                    const dir = pts[Math.min(idx + 1, pts.length - 1)].clone().sub(pts[idx]).normalize();
                    azis.forEach((phi) => {
                        const mat = new THREE.LineBasicMaterial({ color: 0x64c8ff, transparent: true, opacity: 0.7 });
                        const ln = new THREE.Line(geo, mat); ln.rotation.x = phi; fieldGroup.add(ln);
                        const cmat = new THREE.MeshStandardMaterial({ color: 0x8fdcff, emissive: 0x2a6f9a, emissiveIntensity: 0.7, roughness: 0.3 });
                        const cone = new THREE.Mesh(coneGeo, cmat); cone.position.copy(pts[idx]); cone.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), dir);
                        const holder = new THREE.Group(); holder.rotation.x = phi; holder.add(cone); fieldGroup.add(holder);
                    });
                });
            });
        };
        rebuild();
        simRef.current = { home: applyHome, rebuild };

        let raf = 0; let t0 = performance.now();
        const loop = () => {
            raf = requestAnimationFrame(loop);
            const t = (performance.now() - t0) / 1000;
            const flip = st.current.flipped;
            const nudge = Math.sin(t * 2.2) * 0.12;
            magL.position.x = XL + (flip ? nudge : -nudge);
            magR.position.x = XR - (flip ? nudge : -nudge);
            fieldGroup.position.x = 0;
            setArrow(aL, XL + A + 0.35, flip ? +1 : -1);   // attract → point right (inward); repel → left (outward)
            setArrow(aR, XR - A - 0.35, flip ? -1 : +1);
            aL.userData.shaft.material = aL.userData.cone.material = flip ? forceMat : forceMatRep;
            aR.userData.shaft.material = aR.userData.cone.material = flip ? forceMat : forceMatRep;
            world.rotation.y += 0.0025;
            controls.update();
            renderer.render(scene, camera);
        };
        const resize = () => { const w = host.clientWidth, h = host.clientHeight; if (!w || !h) return; renderer.setSize(w, h, false); camera.aspect = w / h; camera.updateProjectionMatrix(); };
        const ro = new ResizeObserver(resize); ro.observe(host); resize();
        loop();

        return () => {
            cancelAnimationFrame(raf); ro.disconnect(); controls.dispose(); renderer.dispose();
            host.contains(renderer.domElement) && host.removeChild(renderer.domElement); simRef.current = null;
        };
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => { simRef.current?.rebuild?.(); }, [flipped]);

    return (
        <div className="cw-beam">
            <div className="cw-stage-col">
                <div className="cw-stage" style={{ aspectRatio: '16 / 10', position: 'relative' }}>
                    <span className="cw-badge">{attract ? '3D poles · unlike → attract' : '3D poles · like → repel'}</span>
                    <div ref={hostRef} style={{ position: 'absolute', inset: 0, borderRadius: 12, overflow: 'hidden', background: 'linear-gradient(180deg,#0d1420,#070b12)' }} />
                </div>
            </div>
            <div className="cw-controls">
                <Stat label={attract ? 'unlike poles (N–S) face' : 'like poles (S–S) face'} value={attract ? 'ATTRACT' : 'REPEL'} tone={attract ? 'acc' : 'warn'}
                      sub={attract ? <>the field lines <b>link across the gap</b> from one magnet's <b>N</b> to the other's <b>S</b>, pulling them together</> : <>the field lines <b>clash and push apart</b>, leaving a gap — the magnets are forced away from each other</>} />
                <button className="cw-btn cw-btn-ghost" onClick={() => setFlipped(!flipped)}>↺ Flip the right-hand magnet</button>
                <Flag kind="neutral">
                    Two magnets meet, and the rule is simple: <b>like poles repel, unlike poles attract</b>. Rotate the scene to see
                    it in 3D. With <b>unlike poles facing</b>, the field lines <b>join up</b> across the gap (N of one links to S of the
                    other) and pull the magnets together. With <b>like poles facing</b>, the field lines <b>push against each other</b>
                    and can't join, so the magnets are forced apart.
                </Flag>
                {onAddToNote && (
                    <button className="cw-btn cw-btn-save" onClick={() => onAddToNote({ ...config, flipped })}>📌 Save this to my notes</button>
                )}
            </div>
        </div>
    );
}
