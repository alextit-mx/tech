/*
 * Escena 3D del configurador: gabinete abierto procedural. Ejes: x = frente(+)/atrás(−), y = arriba, z = vidrio(+)/charola(−).
 * Cada pieza llega volando y encaja; los huecos se ven como hologramas en wireframe.
 */
import gsap from 'gsap';
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';
import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js';
import { OutputPass } from 'three/addons/postprocessing/OutputPass.js';
import { RenderPass } from 'three/addons/postprocessing/RenderPass.js';
import { UnrealBloomPass } from 'three/addons/postprocessing/UnrealBloomPass.js';
import type { Part, Slot } from './rules';

const RED = new THREE.Color('#E30613');
const ICE = new THREE.Color('#EAF2FF');

interface Anchor {
    home: THREE.Vector3;
    size: THREE.Vector3;
}

/** Dónde vive cada pieza y el tamaño de su holograma. */
const ANCHORS: Record<Slot, Anchor> = {
    motherboard: {
        home: new THREE.Vector3(-0.45, 0.45, -0.98),
        size: new THREE.Vector3(2.5, 2.9, 0.06),
    },
    cpu: {
        home: new THREE.Vector3(-0.6, 1.15, -0.9),
        size: new THREE.Vector3(0.48, 0.48, 0.1),
    },
    cooler: {
        home: new THREE.Vector3(-0.6, 1.15, -0.5),
        size: new THREE.Vector3(0.95, 1.15, 0.7),
    },
    ram: {
        home: new THREE.Vector3(0.38, 1.12, -0.8),
        size: new THREE.Vector3(0.36, 1.25, 0.3),
    },
    gpu: {
        home: new THREE.Vector3(-0.3, -0.38, -0.5),
        size: new THREE.Vector3(2.9, 0.5, 0.9),
    },
    storage: {
        home: new THREE.Vector3(-0.55, 0.32, -0.92),
        size: new THREE.Vector3(0.8, 0.22, 0.05),
    },
    psu: {
        home: new THREE.Vector3(-1.15, -1.95, -0.1),
        size: new THREE.Vector3(1.5, 0.75, 1.4),
    },
    case: {
        home: new THREE.Vector3(0, 0, 0),
        size: new THREE.Vector3(4.4, 4.8, 2.2),
    },
};

const OVERVIEW = {
    pos: new THREE.Vector3(4.6, 1.5, 8.2),
    target: new THREE.Vector3(0, 0, 0),
};

interface Burst {
    points: THREE.Points;
    velocities: Float32Array;
    born: number;
}

export class PcScene {
    private renderer: THREE.WebGLRenderer;
    private scene = new THREE.Scene();
    private camera = new THREE.PerspectiveCamera(36, 1, 0.1, 100);
    private controls: OrbitControls;
    private composer: EffectComposer | null = null;
    private clock = new THREE.Clock();
    private parts = new Map<Slot, THREE.Group>();
    private ghosts = new Map<Slot, THREE.LineSegments>();
    private fans: THREE.Object3D[] = [];
    private rgb: THREE.MeshStandardMaterial[] = [];
    private bursts: Burst[] = [];
    private emblem: THREE.Points | null = null;
    private lastInput = 0;
    private focused: Slot | null = null;
    private celebrating = false;
    private observer: ResizeObserver;
    private onVisibility = () =>
        this.renderer.setAnimationLoop(
            document.hidden ? null : () => this.frame(),
        );

    constructor(
        private canvas: HTMLCanvasElement,
        private opts: { reduced: boolean; lowPower: boolean },
    ) {
        this.renderer = new THREE.WebGLRenderer({
            canvas,
            antialias: !opts.lowPower,
            alpha: true,
            powerPreference: 'high-performance',
        });
        this.renderer.setPixelRatio(
            Math.min(window.devicePixelRatio, opts.lowPower ? 1 : 1.5),
        );
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.05;

        const pmrem = new THREE.PMREMGenerator(this.renderer);
        this.scene.environment = pmrem.fromScene(
            new RoomEnvironment(),
            0.04,
        ).texture;
        this.scene.fog = new THREE.FogExp2(0x070608, 0.045);

        this.camera.position.copy(OVERVIEW.pos);
        this.controls = new OrbitControls(this.camera, canvas);
        this.controls.enableDamping = true;
        this.controls.enablePan = false;
        this.controls.minDistance = 3.2;
        this.controls.maxDistance = 13;
        this.controls.minPolarAngle = 0.55;
        this.controls.maxPolarAngle = 1.8;
        this.controls.minAzimuthAngle = -1.25;
        this.controls.maxAzimuthAngle = 1.35;
        this.controls.addEventListener(
            'start',
            () => (this.lastInput = performance.now()),
        );

        this.lights();
        this.floor();
        for (const slot of Object.keys(ANCHORS) as Slot[]) this.ghost(slot);

        if (!opts.lowPower) {
            this.composer = new EffectComposer(this.renderer);
            this.composer.addPass(new RenderPass(this.scene, this.camera));
            this.composer.addPass(
                new UnrealBloomPass(
                    new THREE.Vector2(512, 512),
                    0.55,
                    0.45,
                    0.82,
                ),
            );
            this.composer.addPass(new OutputPass());
        }

        this.observer = new ResizeObserver(() => this.resize());
        this.observer.observe(canvas.parentElement ?? canvas);
        this.resize();
        document.addEventListener('visibilitychange', this.onVisibility);
        this.renderer.setAnimationLoop(() => this.frame());
    }

    /** Equipa o quita una pieza. `animate` en falso para restaurar una build guardada sin vuelos. */
    setPart(slot: Slot, part: Part | null, animate = true): void {
        const old = this.parts.get(slot);
        if (old) {
            this.parts.delete(slot);
            this.removeFans(old);
            gsap.killTweensOf(old.position);
            this.scene.remove(old);
            this.dispose(old);
        }
        this.ghosts.get(slot)!.visible = !part;
        if (!part) return;

        const group = this.build(slot, part);
        group.position.copy(ANCHORS[slot].home);
        this.scene.add(group);
        this.parts.set(slot, group);

        if (!animate || this.opts.reduced) return;
        const home = ANCHORS[slot].home;
        group.position.set(home.x + 1.5, home.y + 2.2, home.z + 4.5);
        group.rotation.set(0.6, -0.9, 0.3);
        gsap.to(group.position, {
            x: home.x,
            y: home.y,
            z: home.z,
            duration: 0.9,
            ease: 'back.out(1.5)',
        });
        gsap.to(group.rotation, {
            x: 0,
            y: 0,
            z: 0,
            duration: 0.9,
            ease: 'power3.out',
            onComplete: () => {
                this.scan(group);
                this.burst(home);
            },
        });
    }

    /** Lleva la cámara a una pieza (o a la vista general con null). */
    focus(slot: Slot | null): void {
        this.focused = slot;
        if (this.celebrating) return;
        const target =
            slot && slot !== 'case'
                ? ANCHORS[slot].home.clone()
                : OVERVIEW.target.clone();
        const pos =
            slot && slot !== 'case'
                ? target.clone().add(new THREE.Vector3(1.9, 0.7, 3.6))
                : OVERVIEW.pos.clone();
        if (this.opts.reduced) {
            this.camera.position.copy(pos);
            this.controls.target.copy(target);
            return;
        }
        gsap.to(this.controls.target, {
            x: target.x,
            y: target.y,
            z: target.z,
            duration: 1.1,
            ease: 'power3.inOut',
        });
        gsap.to(this.camera.position, {
            x: pos.x,
            y: pos.y,
            z: pos.z,
            duration: 1.1,
            ease: 'power3.inOut',
        });
    }

    /** Final: giro de 360°, ola RGB que termina en rojo y el emblema ATS hecho de partículas. */
    async celebrate(): Promise<void> {
        this.celebrating = true;
        this.focused = null;
        const t0 = performance.now();
        const wave = (now: number) => {
            const t = (now - t0) / 1000;
            this.rgb.forEach((mat, i) =>
                mat.emissive.setHSL((t * 0.5 + i * 0.07) % 1, 1, 0.5),
            );
            if (t < 2.6) requestAnimationFrame(wave);
            else
                this.rgb.forEach((mat) =>
                    gsap.to(mat.emissive, {
                        r: RED.r,
                        g: RED.g,
                        b: RED.b,
                        duration: 0.8,
                    }),
                );
        };
        if (!this.opts.reduced) requestAnimationFrame(wave);

        const emblem = this.formEmblem();
        if (!this.opts.reduced) {
            this.controls.minAzimuthAngle = -Infinity;
            this.controls.maxAzimuthAngle = Infinity;
            this.controls.target.set(0, 0.2, 0);
            const radius = Math.hypot(OVERVIEW.pos.x, OVERVIEW.pos.z);
            const orbit = {
                a: Math.atan2(this.camera.position.x, this.camera.position.z),
            };
            await new Promise<void>((done) =>
                gsap.to(orbit, {
                    a: orbit.a + Math.PI * 2,
                    duration: 3.4,
                    ease: 'power2.inOut',
                    onUpdate: () => {
                        this.camera.position.set(
                            Math.sin(orbit.a) * radius,
                            1.4,
                            Math.cos(orbit.a) * radius,
                        );
                    },
                    onComplete: done,
                }),
            );
            this.controls.minAzimuthAngle = -1.25;
            this.controls.maxAzimuthAngle = 1.35;
        }
        await emblem;
        this.celebrating = false;
    }

    resize(): void {
        const parent = this.canvas.parentElement;
        const w = parent?.clientWidth ?? window.innerWidth;
        const h = parent?.clientHeight ?? window.innerHeight;
        if (!w || !h) return;
        this.renderer.setSize(w, h, false);
        this.composer?.setSize(w, h);
        this.camera.aspect = w / h;
        // En pantallas angostas se aleja la cámara para que quepa el gabinete.
        this.camera.fov = w / h < 1 ? 48 : 36;
        this.camera.updateProjectionMatrix();
    }

    destroy(): void {
        this.renderer.setAnimationLoop(null);
        document.removeEventListener('visibilitychange', this.onVisibility);
        this.observer.disconnect();
        this.controls.dispose();
        this.dispose(this.scene);
        this.composer?.dispose();
        this.renderer.dispose();
    }

    /* ---------------- Ciclo ---------------- */

    private frame(): void {
        const dt = Math.min(this.clock.getDelta(), 0.05);
        const t = this.clock.elapsedTime;
        for (const fan of this.fans) fan.rotation.z += dt * 9;

        // Hologramas que respiran.
        this.ghosts.forEach(
            (ghost) =>
                ((ghost.material as THREE.LineBasicMaterial).opacity =
                    0.18 + Math.sin(t * 2.4) * 0.1),
        );

        // Reposo: vaivén lento si nadie toca la escena.
        if (
            !this.opts.reduced &&
            !this.celebrating &&
            !this.focused &&
            performance.now() - this.lastInput > 5000
        ) {
            const a = Math.sin(t * 0.18) * 0.38 + 0.5;
            const r = Math.hypot(OVERVIEW.pos.x, OVERVIEW.pos.z);
            this.camera.position.lerp(
                new THREE.Vector3(
                    Math.sin(a) * r,
                    OVERVIEW.pos.y,
                    Math.cos(a) * r,
                ),
                0.01,
            );
        }

        this.bursts = this.bursts.filter((b) => {
            const age = (performance.now() - b.born) / 1000;
            const pos = b.points.geometry.attributes
                .position as THREE.BufferAttribute;
            for (let i = 0; i < pos.count; i++)
                pos.setXYZ(
                    i,
                    pos.getX(i) + b.velocities[i * 3] * dt,
                    pos.getY(i) + b.velocities[i * 3 + 1] * dt,
                    pos.getZ(i) + b.velocities[i * 3 + 2] * dt,
                );
            pos.needsUpdate = true;
            (b.points.material as THREE.PointsMaterial).opacity = Math.max(
                0,
                1 - age / 0.9,
            );
            if (age < 0.9) return true;
            this.scene.remove(b.points);
            this.dispose(b.points);
            return false;
        });

        this.controls.update();
        if (this.composer) this.composer.render();
        else this.renderer.render(this.scene, this.camera);
    }

    /* ---------------- Ambiente ---------------- */

    private lights(): void {
        this.scene.add(new THREE.HemisphereLight(0xb8c4d6, 0x0a0608, 0.35));
        const key = new THREE.DirectionalLight(0xffffff, 1.6);
        key.position.set(4, 6, 6);
        this.scene.add(key);
        const rim = new THREE.PointLight(RED, 28, 9, 2);
        rim.position.set(-3.2, 1.8, -2.6);
        this.scene.add(rim);
        const inner = new THREE.PointLight(RED, 6, 4, 2);
        inner.position.set(0, -0.6, 0.3);
        this.scene.add(inner);
    }

    private floor(): void {
        const grid = new THREE.GridHelper(26, 52, RED, 0x2a2a30);
        const mat = grid.material as THREE.LineBasicMaterial;
        mat.transparent = true;
        mat.opacity = 0.22;
        grid.position.y = -2.5;
        this.scene.add(grid);

        const glow = document.createElement('canvas');
        glow.width = glow.height = 128;
        const ctx = glow.getContext('2d')!;
        const g = ctx.createRadialGradient(64, 64, 0, 64, 64, 64);
        g.addColorStop(0, 'rgba(227,6,19,0.55)');
        g.addColorStop(1, 'rgba(227,6,19,0)');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, 128, 128);
        const plane = new THREE.Mesh(
            new THREE.PlaneGeometry(9, 9),
            new THREE.MeshBasicMaterial({
                map: new THREE.CanvasTexture(glow),
                transparent: true,
                blending: THREE.AdditiveBlending,
                depthWrite: false,
            }),
        );
        plane.rotation.x = -Math.PI / 2;
        plane.position.y = -2.49;
        this.scene.add(plane);
    }

    private ghost(slot: Slot): void {
        const { home, size } = ANCHORS[slot];
        const lines = new THREE.LineSegments(
            new THREE.EdgesGeometry(
                new THREE.BoxGeometry(size.x, size.y, size.z),
            ),
            new THREE.LineBasicMaterial({
                color: slot === 'case' ? 0x6e7480 : ICE,
                transparent: true,
                opacity: 0.25,
            }),
        );
        lines.position.copy(home);
        this.scene.add(lines);
        this.ghosts.set(slot, lines);
    }

    /* ---------------- Piezas ---------------- */

    private build(slot: Slot, part: Part): THREE.Group {
        const g = new THREE.Group();
        const s = part.specs;
        switch (slot) {
            case 'motherboard': {
                const pcb = this.box(2.5, 2.9, 0.06, this.pcbMaterial());
                g.add(pcb);
                // Socket, disipadores de VRM y ranura PCIe.
                g.add(
                    this.at(
                        this.box(0.62, 0.62, 0.05, this.metal(0x2a2a30)),
                        -0.15,
                        0.7,
                        0.05,
                    ),
                );
                g.add(
                    this.at(
                        this.box(0.9, 0.22, 0.16, this.metal(0x3a3d44)),
                        -0.25,
                        1.22,
                        0.1,
                    ),
                );
                g.add(
                    this.at(
                        this.box(0.22, 0.8, 0.16, this.metal(0x3a3d44)),
                        -0.75,
                        0.75,
                        0.1,
                    ),
                );
                g.add(
                    this.at(
                        this.box(1.9, 0.08, 0.1, this.metal(0x15151a)),
                        -0.15,
                        -0.83,
                        0.07,
                    ),
                );
                g.add(
                    this.at(
                        this.box(0.06, 0.9, 0.04, this.glow()),
                        1.18,
                        0.2,
                        0.05,
                    ),
                );
                break;
            }
            case 'cpu': {
                g.add(this.box(0.46, 0.46, 0.05, this.metal(0x18451c, 0.3)));
                g.add(
                    this.at(
                        this.box(0.36, 0.36, 0.06, this.metal(0xb9bec6)),
                        0,
                        0,
                        0.05,
                    ),
                );
                break;
            }
            case 'cooler': {
                if (s.type === 'líquida') {
                    const pump = new THREE.Mesh(
                        new THREE.CylinderGeometry(0.26, 0.26, 0.2, 40),
                        this.metal(0x1c1c22),
                    );
                    pump.rotation.x = Math.PI / 2;
                    g.add(this.at(pump, 0, 0, -0.3));
                    const ring = new THREE.Mesh(
                        new THREE.TorusGeometry(0.2, 0.025, 12, 48),
                        this.glow(),
                    );
                    g.add(this.at(ring, 0, 0, -0.19));
                    // Radiador arriba con tres ventiladores.
                    const rad = new THREE.Group();
                    rad.add(this.box(2.6, 0.12, 0.85, this.metal(0x1d1f24)));
                    for (const x of [-0.85, 0, 0.85])
                        rad.add(this.at(this.fan(0.36, true), x, -0.14, 0));
                    rad.position.set(0.55, 0.98, 0.2);
                    g.add(rad);
                    const curve = new THREE.CatmullRomCurve3([
                        new THREE.Vector3(0.1, 0.1, -0.25),
                        new THREE.Vector3(0.4, 0.6, 0),
                        new THREE.Vector3(0.6, 0.9, 0.1),
                    ]);
                    for (const dz of [0, 0.12]) {
                        const tube = new THREE.Mesh(
                            new THREE.TubeGeometry(curve, 24, 0.035, 8),
                            this.metal(0x111114, 0.2),
                        );
                        tube.position.z = dz;
                        g.add(tube);
                    }
                } else {
                    const fins = new THREE.Group();
                    for (let i = 0; i < 16; i++)
                        fins.add(
                            this.at(
                                this.box(
                                    0.62,
                                    1.05,
                                    0.012,
                                    this.metal(0xc3c8cf),
                                ),
                                0,
                                0,
                                -0.3 + i * 0.04,
                            ),
                        );
                    g.add(fins);
                    g.add(this.at(this.fan(0.42, false, 'x'), 0.36, 0, 0));
                    for (const y of [-0.3, 0, 0.3])
                        g.add(
                            this.at(
                                this.box(
                                    0.7,
                                    0.04,
                                    0.04,
                                    this.metal(0xb87333, 0.3),
                                ),
                                0,
                                y,
                                -0.35,
                            ),
                        );
                }
                break;
            }
            case 'ram': {
                const sticks = Number(s.modules ?? 2) >= 4 ? 4 : 2;
                for (let i = 0; i < sticks; i++) {
                    const stick = new THREE.Group();
                    stick.add(this.box(0.07, 1.22, 0.26, this.metal(0x16161b)));
                    stick.add(
                        this.at(
                            this.box(0.075, 1.18, 0.04, this.glow()),
                            0,
                            0,
                            0.15,
                        ),
                    );
                    stick.position.x =
                        -0.13 + i * (0.26 / Math.max(1, sticks - 1));
                    g.add(stick);
                }
                break;
            }
            case 'gpu': {
                const length = THREE.MathUtils.clamp(
                    Number(s.length_mm ?? 300) / 100,
                    2.1,
                    3.5,
                );
                const shroud = this.box(
                    length,
                    0.48,
                    0.82,
                    this.metal(0x141418),
                );
                g.add(this.at(shroud, (length - 2.9) / 2, 0, 0));
                g.add(
                    this.at(
                        this.box(length, 0.03, 0.84, this.metal(0x2a2c33)),
                        (length - 2.9) / 2,
                        0.25,
                        0,
                    ),
                );
                g.add(
                    this.at(
                        this.box(length * 0.62, 0.05, 0.03, this.glow()),
                        (length - 2.9) / 2,
                        0.06,
                        0.42,
                    ),
                );
                const fans = length > 2.7 ? 3 : 2;
                for (let i = 0; i < fans; i++) {
                    const fan = this.fan(0.36, true);
                    fan.rotation.x = Math.PI / 2;
                    g.add(
                        this.at(
                            fan,
                            (length - 2.9) / 2 +
                                (i - (fans - 1) / 2) * (length / fans),
                            -0.26,
                            0,
                        ),
                    );
                }
                break;
            }
            case 'storage': {
                g.add(this.box(0.8, 0.22, 0.03, this.pcbMaterial()));
                g.add(
                    this.at(
                        this.box(0.7, 0.2, 0.05, this.metal(0x2b2d33)),
                        0,
                        0,
                        0.04,
                    ),
                );
                g.add(
                    this.at(this.box(0.5, 0.03, 0.01, this.glow()), 0, 0, 0.07),
                );
                break;
            }
            case 'psu': {
                g.add(this.box(1.5, 0.75, 1.4, this.metal(0x101013)));
                const grille = new THREE.Mesh(
                    new THREE.CircleGeometry(0.3, 32),
                    this.metal(0x2c2e35),
                );
                grille.rotation.x = -Math.PI / 2;
                g.add(this.at(grille, 0, 0.38, 0));
                g.add(
                    this.at(
                        this.box(0.9, 0.04, 0.02, this.glow()),
                        0,
                        0.1,
                        0.71,
                    ),
                );
                break;
            }
            case 'case': {
                const frame = new THREE.LineSegments(
                    new THREE.EdgesGeometry(
                        new THREE.BoxGeometry(4.4, 4.8, 2.2),
                    ),
                    new THREE.LineBasicMaterial({ color: 0x9aa3ae }),
                );
                g.add(frame);
                const wall = this.metal(0x0d0d11, 0.5);
                g.add(this.at(this.box(4.4, 4.8, 0.05, wall), 0, 0, -1.1));
                g.add(this.at(this.box(4.4, 0.05, 2.2, wall), 0, -2.4, 0));
                g.add(this.at(this.box(4.4, 0.05, 2.2, wall), 0, 2.4, 0));
                g.add(this.at(this.box(0.05, 4.8, 2.2, wall), -2.2, 0, 0));
                // Charola de la fuente.
                g.add(
                    this.at(
                        this.box(4.3, 0.04, 2.1, this.metal(0x16161b)),
                        0,
                        -1.5,
                        0,
                    ),
                );
                g.add(
                    this.at(
                        this.box(4.3, 0.04, 0.03, this.glow()),
                        0,
                        -1.48,
                        1.06,
                    ),
                );
                // Vidrio con borde rojo.
                const glass = new THREE.Mesh(
                    new THREE.PlaneGeometry(4.3, 4.7),
                    new THREE.MeshPhysicalMaterial({
                        color: 0xa8b8cc,
                        transparent: true,
                        opacity: 0.07,
                        roughness: 0.04,
                        metalness: 0,
                        clearcoat: 1,
                        side: THREE.DoubleSide,
                        depthWrite: false,
                    }),
                );
                g.add(this.at(glass, 0, 0, 1.1));
                const edge = new THREE.LineSegments(
                    new THREE.EdgesGeometry(new THREE.PlaneGeometry(4.3, 4.7)),
                    new THREE.LineBasicMaterial({
                        color: RED,
                        transparent: true,
                        opacity: 0.8,
                    }),
                );
                g.add(this.at(edge, 0, 0, 1.11));
                // Tres ventiladores al frente y uno atrás.
                for (const y of [1.25, 0, -1.25])
                    g.add(this.at(this.fan(0.5, true, 'x'), 2.12, y + 0.35, 0));
                g.add(this.at(this.fan(0.42, true, 'x'), -2.12, 1.45, 0));
                break;
            }
        }
        return g;
    }

    /* ---------------- Efectos ---------------- */

    /** Barrido de escaneo: una lámina roja recorre la pieza de abajo hacia arriba. */
    private scan(group: THREE.Group): void {
        const box = new THREE.Box3().setFromObject(group);
        const size = box.getSize(new THREE.Vector3());
        const center = box.getCenter(new THREE.Vector3());
        const sheet = new THREE.Mesh(
            new THREE.BoxGeometry(size.x * 1.08, 0.02, size.z * 1.08 + 0.02),
            new THREE.MeshBasicMaterial({
                color: RED,
                transparent: true,
                opacity: 0.9,
                blending: THREE.AdditiveBlending,
                depthWrite: false,
            }),
        );
        sheet.position.set(center.x, box.min.y, center.z);
        this.scene.add(sheet);
        gsap.to(sheet.position, {
            y: box.max.y,
            duration: 0.55,
            ease: 'power2.inOut',
            onComplete: () => {
                this.scene.remove(sheet);
                this.dispose(sheet);
            },
        });
    }

    private burst(at: THREE.Vector3): void {
        const count = this.opts.lowPower ? 40 : 110;
        const positions = new Float32Array(count * 3);
        const velocities = new Float32Array(count * 3);
        for (let i = 0; i < count; i++) {
            positions.set([at.x, at.y, at.z + 0.3], i * 3);
            const dir = new THREE.Vector3()
                .randomDirection()
                .multiplyScalar(1.5 + Math.random() * 2.5);
            velocities.set([dir.x, dir.y, dir.z], i * 3);
        }
        const geo = new THREE.BufferGeometry();
        geo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        const points = new THREE.Points(
            geo,
            new THREE.PointsMaterial({
                color: RED,
                size: 0.05,
                transparent: true,
                blending: THREE.AdditiveBlending,
                depthWrite: false,
            }),
        );
        this.scene.add(points);
        this.bursts.push({ points, velocities, born: performance.now() });
    }

    /** Las partículas salen del gabinete y forman el emblema ATS frente a la cámara. */
    private async formEmblem(): Promise<void> {
        if (this.emblem) return;
        const img = new Image();
        img.src = '/img/ats-emblema.png';
        try {
            await img.decode();
        } catch {
            return;
        }
        const w = 140;
        const h = Math.round((img.height / img.width) * w);
        const canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext('2d')!;
        ctx.drawImage(img, 0, 0, w, h);
        const data = ctx.getImageData(0, 0, w, h).data;
        const targets: number[] = [];
        const step = this.opts.lowPower ? 3 : 2;
        for (let y = 0; y < h; y += step)
            for (let x = 0; x < w; x += step)
                if (data[(y * w + x) * 4 + 3] > 140)
                    targets.push(
                        (x / w - 0.5) * 3.2,
                        (0.5 - y / h) * 3.2 * (h / w) + 3.4,
                        0.4,
                    );

        const count = targets.length / 3;
        const start = new Float32Array(count * 3);
        for (let i = 0; i < count; i++)
            start.set(
                [
                    (Math.random() - 0.5) * 4,
                    (Math.random() - 0.5) * 4.4,
                    (Math.random() - 0.5) * 2,
                ],
                i * 3,
            );
        const geo = new THREE.BufferGeometry();
        geo.setAttribute(
            'position',
            new THREE.BufferAttribute(start.slice(), 3),
        );
        this.emblem = new THREE.Points(
            geo,
            new THREE.PointsMaterial({
                color: RED,
                size: 0.045,
                transparent: true,
                opacity: 0.95,
                blending: THREE.AdditiveBlending,
                depthWrite: false,
            }),
        );
        this.scene.add(this.emblem);

        const pos = geo.attributes.position as THREE.BufferAttribute;
        const progress = { p: this.opts.reduced ? 1 : 0 };
        const apply = () => {
            for (let i = 0; i < count * 3; i++)
                pos.array[i] = start[i] + (targets[i] - start[i]) * progress.p;
            pos.needsUpdate = true;
        };
        if (this.opts.reduced) return apply();
        await new Promise<void>((done) =>
            gsap.to(progress, {
                p: 1,
                duration: 2.6,
                delay: 0.5,
                ease: 'expo.inOut',
                onUpdate: apply,
                onComplete: done,
            }),
        );
    }

    /* ---------------- Utilidades ---------------- */

    private box(
        w: number,
        h: number,
        d: number,
        mat: THREE.Material,
    ): THREE.Mesh {
        return new THREE.Mesh(new THREE.BoxGeometry(w, h, d), mat);
    }

    private at<T extends THREE.Object3D>(
        obj: T,
        x: number,
        y: number,
        z: number,
    ): T {
        obj.position.set(x, y, z);
        return obj;
    }

    private metal(color: number, roughness = 0.38): THREE.MeshStandardMaterial {
        return new THREE.MeshStandardMaterial({
            color,
            metalness: 0.85,
            roughness,
        });
    }

    private glow(): THREE.MeshStandardMaterial {
        const mat = new THREE.MeshStandardMaterial({
            color: 0x050505,
            emissive: RED.clone(),
            emissiveIntensity: 2.4,
        });
        this.rgb.push(mat);
        return mat;
    }

    private pcbMaterial(): THREE.MeshStandardMaterial {
        const c = document.createElement('canvas');
        c.width = c.height = 256;
        const ctx = c.getContext('2d')!;
        ctx.fillStyle = '#000';
        ctx.fillRect(0, 0, 256, 256);
        ctx.strokeStyle = 'rgba(227,6,19,0.55)';
        ctx.lineWidth = 1.5;
        for (let i = 0; i < 46; i++) {
            let x = Math.random() * 256;
            let y = Math.random() * 256;
            ctx.beginPath();
            ctx.moveTo(x, y);
            for (let k = 0; k < 3; k++) {
                if (k % 2) x += (Math.random() - 0.5) * 120;
                else y += (Math.random() - 0.5) * 120;
                ctx.lineTo(x, y);
            }
            ctx.stroke();
            ctx.fillStyle = 'rgba(227,6,19,0.8)';
            ctx.fillRect(x - 2, y - 2, 4, 4);
        }
        const tex = new THREE.CanvasTexture(c);
        tex.colorSpace = THREE.SRGBColorSpace;
        return new THREE.MeshStandardMaterial({
            color: 0x0e0e12,
            metalness: 0.3,
            roughness: 0.55,
            emissive: 0xffffff,
            emissiveMap: tex,
            emissiveIntensity: 0.9,
        });
    }

    /** Ventilador con aro RGB. `axis` = hacia dónde mira (z por defecto). */
    private fan(
        radius: number,
        ring: boolean,
        axis: 'x' | 'z' = 'z',
    ): THREE.Group {
        const g = new THREE.Group();
        const frame = new THREE.Mesh(
            new THREE.TorusGeometry(radius, radius * 0.07, 10, 48),
            ring ? this.glow() : this.metal(0x1b1b20),
        );
        g.add(frame);
        const blades = new THREE.Group();
        blades.add(
            new THREE.Mesh(
                new THREE.CylinderGeometry(
                    radius * 0.28,
                    radius * 0.28,
                    0.06,
                    24,
                ).rotateX(Math.PI / 2),
                this.metal(0x1d1d22),
            ),
        );
        const bladeMat = new THREE.MeshStandardMaterial({
            color: 0x2a2b31,
            metalness: 0.2,
            roughness: 0.6,
            transparent: true,
            opacity: 0.92,
        });
        for (let i = 0; i < 7; i++) {
            const blade = new THREE.Mesh(
                new THREE.BoxGeometry(radius * 0.75, radius * 0.24, 0.015),
                bladeMat,
            );
            blade.position.x = radius * 0.5;
            const pivot = new THREE.Group();
            pivot.rotation.z = (i / 7) * Math.PI * 2;
            blade.rotation.x = 0.5;
            pivot.add(blade);
            blades.add(pivot);
        }
        g.add(blades);
        this.fans.push(blades);
        if (axis === 'x') g.rotation.y = Math.PI / 2;
        return g;
    }

    private removeFans(root: THREE.Object3D): void {
        const inside = new Set<THREE.Object3D>();
        root.traverse((o) => inside.add(o));
        this.fans = this.fans.filter((f) => !inside.has(f));
        root.traverse((o) => {
            const mat = (o as THREE.Mesh).material;
            if (mat) this.rgb = this.rgb.filter((m) => m !== mat);
        });
    }

    private dispose(root: THREE.Object3D): void {
        root.traverse((o) => {
            const mesh = o as THREE.Mesh;
            mesh.geometry?.dispose();
            const mats = Array.isArray(mesh.material)
                ? mesh.material
                : mesh.material
                  ? [mesh.material]
                  : [];
            for (const m of mats) {
                (m as THREE.MeshStandardMaterial).map?.dispose();
                (m as THREE.MeshStandardMaterial).emissiveMap?.dispose();
                m.dispose();
            }
        });
    }
}

export function webglAvailable(): boolean {
    try {
        const canvas = document.createElement('canvas');
        return Boolean(
            canvas.getContext('webgl2') ?? canvas.getContext('webgl'),
        );
    } catch {
        return false;
    }
}
