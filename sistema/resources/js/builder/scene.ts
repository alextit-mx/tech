/*
 * Escena 3D del configurador: gabinete abierto procedural con acabado de render de producto.
 * Ejes: x = frente(+)/atrás(−), y = arriba, z = vidrio(+)/charola(−). Unidades ≈ 10 cm.
 * Pipeline: HDRI de estudio, ACES, GTAO, bloom del RGB, contorno al pasar el puntero, SMAA, grano.
 */
import gsap from 'gsap';
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
import { RGBELoader } from 'three/addons/loaders/RGBELoader.js';
import { mergeGeometries } from 'three/addons/utils/BufferGeometryUtils.js';
import { BokehPass } from 'three/addons/postprocessing/BokehPass.js';
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js';
import { RectAreaLightUniformsLib } from 'three/addons/lights/RectAreaLightUniformsLib.js';
import { Reflector } from 'three/addons/objects/Reflector.js';
import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js';
import { GTAOPass } from 'three/addons/postprocessing/GTAOPass.js';
import { OutlinePass } from 'three/addons/postprocessing/OutlinePass.js';
import { OutputPass } from 'three/addons/postprocessing/OutputPass.js';
import { RenderPass } from 'three/addons/postprocessing/RenderPass.js';
import { ShaderPass } from 'three/addons/postprocessing/ShaderPass.js';
import { SMAAPass } from 'three/addons/postprocessing/SMAAPass.js';
import { UnrealBloomPass } from 'three/addons/postprocessing/UnrealBloomPass.js';
import type { Part, Slot } from './rules';
import { ledShader, surfaceTexture } from './surfaces';

const RED = new THREE.Color('#E30613');

interface Anchor {
    home: THREE.Vector3;
    size: THREE.Vector3;
}

/** Posición y dimensiones de cada pieza. La GPU va en montaje vertical: ventiladores hacia el vidrio. */
const ANCHORS: Record<Slot, Anchor> = {
    motherboard: {
        home: new THREE.Vector3(-0.75, 0.25, -1.0),
        size: new THREE.Vector3(2.44, 3.05, 0.06),
    },
    cpu: {
        home: new THREE.Vector3(-0.75, 0.95, -0.94),
        size: new THREE.Vector3(0.45, 0.45, 0.08),
    },
    cooler: {
        home: new THREE.Vector3(-0.75, 0.95, -0.3),
        size: new THREE.Vector3(1.0, 1.3, 1.25),
    },
    ram: {
        home: new THREE.Vector3(0.18, 0.95, -0.76),
        size: new THREE.Vector3(0.36, 1.33, 0.42),
    },
    gpu: {
        home: new THREE.Vector3(-0.3, -0.72, 0.35),
        size: new THREE.Vector3(3.0, 1.2, 0.62),
    },
    storage: {
        home: new THREE.Vector3(-0.75, 0.2, -0.93),
        size: new THREE.Vector3(0.8, 0.24, 0.06),
    },
    psu: {
        home: new THREE.Vector3(-1.3, -1.95, -0.05),
        size: new THREE.Vector3(1.5, 0.86, 1.4),
    },
    case: {
        home: new THREE.Vector3(0, 0, 0),
        size: new THREE.Vector3(4.4, 4.8, 2.2),
    },
};

const OVERVIEW = {
    // Lejos para que el gabinete quepa holgado entre el riel y el selector, aun en pantallas grandes;
    // el objetivo a la derecha lo recorre a la izquierda.
    // Más lejos y mirando un poco más arriba: la PC baja en pantalla y no se corta arriba.
    pos: new THREE.Vector3(9.2, 3.4, 13.5),
    target: new THREE.Vector3(0.7, 0.45, 0),
};

const GRAIN_SHADER = {
    uniforms: {
        tDiffuse: { value: null },
        uTime: { value: 0 },
    },
    vertexShader:
        'varying vec2 vUv; void main(){ vUv = uv; gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0); }',
    fragmentShader: `
        uniform sampler2D tDiffuse; uniform float uTime; varying vec2 vUv;
        float rand(vec2 c){ return fract(sin(dot(c, vec2(12.9898, 78.233))) * 43758.5453); }
        void main(){
            vec4 c = texture2D(tDiffuse, vUv);
            float d = distance(vUv, vec2(0.5));
            c.rgb *= mix(1.0, 0.55, smoothstep(0.38, 0.85, d));
            c.rgb += (rand(vUv * (uTime + 1.0)) - 0.5) * 0.007;
            gl_FragColor = c;
        }`,
};

interface Burst {
    points: THREE.Points;
    velocities: Float32Array;
    born: number;
}

interface Glow {
    mat: THREE.MeshStandardMaterial;
    power: { value: number };
}

export class PcScene {
    private renderer: THREE.WebGLRenderer;
    private scene = new THREE.Scene();
    private camera = new THREE.PerspectiveCamera(36, 1, 0.1, 100);
    private controls: OrbitControls;
    private composer: EffectComposer | null = null;
    private bloom: UnrealBloomPass | null = null;
    private outline: OutlinePass | null = null;
    private gtao: GTAOPass | null = null;
    private grain: ShaderPass | null = null;
    private reflector: Reflector | null = null;
    private clock = new THREE.Clock();
    private parts = new Map<Slot, THREE.Group>();
    private ledTime = { value: 0 };
    private ledRainbow = { value: 0 };
    private spill: THREE.PointLight[] = [];
    private dof: BokehPass | null = null;
    private environment: THREE.WebGLRenderTarget | null = null;
    private textures = new Set<THREE.Texture>();
    private destroyed = false;
    private entering = false;
    private degraded = false;
    private sampleStart = 0;
    private sampleFrames = 0;
    private sampleCalls = 0;
    private sampleTriangles = 0;
    private qualitySample = 0;
    private qualityFrames = 0;
    private performanceAt = 0;
    private fans: THREE.Object3D[] = [];
    private glows: Glow[] = [];
    private bursts: Burst[] = [];
    private dust: THREE.Points | null = null;
    private emblem: THREE.Points | null = null;
    private pcb: THREE.MeshStandardMaterial[] = [];
    private shared = new Set<THREE.Material>();
    private bladeGeo = new Map<number, THREE.BufferGeometry>();
    private hubMap: THREE.Texture;
    private m: Record<
        | 'alu'
        | 'anod'
        | 'plastic'
        | 'graphite'
        | 'copper'
        | 'blade'
        | 'glass'
        | 'silver'
        | 'carbon',
        THREE.Material
    >;
    private raycaster = new THREE.Raycaster();
    private pointer = new THREE.Vector2();
    private hovered: Slot | null = null;
    private downAt = { x: 0, y: 0 };
    private lastInput = 0;
    private focused: Slot | null = null;
    private celebrating = false;
    private rainbow = 0;
    /** Animaciones del final (arcoíris, giro, emblema): reset() las corta. */
    private finale: gsap.core.Tween[] = [];
    private observer: ResizeObserver;
    private onVisibility = () => {
        this.sampleStart = this.qualitySample = 0;
        this.sampleFrames =
            this.sampleCalls =
            this.sampleTriangles =
            this.qualityFrames =
                0;
        this.clock.getDelta();
        this.renderer.setAnimationLoop(
            document.hidden ? null : () => this.frame(),
        );
    };

    constructor(
        private canvas: HTMLCanvasElement,
        private opts: {
            reduced: boolean;
            lowPower: boolean;
            onPick?: (slot: Slot) => void;
        },
    ) {
        gsap.ticker.lagSmoothing(0);
        // Fondo opaco del mismo negro del sitio: con alfa, el post-proceso dejaba un velo gris.
        this.renderer = new THREE.WebGLRenderer({
            canvas,
            antialias: false,
            alpha: false,
            powerPreference: 'high-performance',
        });
        this.renderer.setClearColor(0x070608, 1);
        this.scene.background = new THREE.Color(0x070608);
        this.renderer.setPixelRatio(
            Math.min(window.devicePixelRatio, opts.lowPower ? 1.25 : 1.75),
        );
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.0;
        this.renderer.shadowMap.enabled = !opts.lowPower;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        this.renderer.shadowMap.autoUpdate = false;
        RectAreaLightUniformsLib.init();

        this.scene.environmentIntensity = 0.45;
        this.scene.fog = new THREE.FogExp2(0x070608, 0.032);

        this.hubMap = new THREE.TextureLoader().load('/img/ats-emblema.png');
        this.hubMap.colorSpace = THREE.SRGBColorSpace;
        this.m = this.materials();

        this.camera.position.copy(OVERVIEW.pos);
        this.controls = new OrbitControls(this.camera, canvas);
        this.controls.target.copy(OVERVIEW.target);
        this.controls.enableDamping = true;
        this.controls.dampingFactor = 0.06;
        this.controls.enablePan = false;
        this.controls.minDistance = 6;
        this.controls.maxDistance = 22;
        this.controls.minPolarAngle = 0.55;
        this.controls.maxPolarAngle = 1.75;
        this.controls.minAzimuthAngle = -1.25;
        this.controls.maxAzimuthAngle = 1.35;
        this.controls.addEventListener(
            'start',
            () => (this.lastInput = performance.now()),
        );

        this.lights();
        // Sin piso: la PC flota en el espacio entre partículas (preferencia del usuario). Para volver: this.floor();
        this.atmosphere();

        this.postprocessing();
        this.renderer.info.autoReset = false;
        window.addEventListener('build-lab:loaded', this.onLoaded, {
            once: true,
        });
        // Se difiere hasta que exista el primer cuadro; no bloquea el HUD ni el loader.
        requestAnimationFrame(() => {
            if (!this.destroyed) void this.loadEnvironment();
        });

        canvas.addEventListener('pointermove', this.onPointerMove);
        canvas.addEventListener('pointerdown', this.onPointerDown);
        canvas.addEventListener('pointerup', this.onPointerUp);
        canvas.addEventListener('pointerleave', this.onPointerLeave);

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
            this.forget(old);
            (old.userData.arrival as gsap.core.Timeline | undefined)?.kill();
            gsap.killTweensOf([old.position, old.rotation, old.scale]);
            this.scene.remove(old);
            this.renderer.shadowMap.needsUpdate = true;
            this.dispose(old);
        }
        if (!part) return;

        const group = this.build(slot, part);
        group.userData.slot = slot;
        group.position.copy(ANCHORS[slot].home);
        group.traverse((o) => {
            const mesh = o as THREE.Mesh;
            if (!mesh.isMesh) return;
            const materials = Array.isArray(mesh.material)
                ? mesh.material
                : [mesh.material];
            mesh.castShadow =
                !this.opts.lowPower &&
                materials.every(
                    (material) =>
                        !material.transparent &&
                        !(
                            (material as THREE.MeshPhysicalMaterial)
                                .transmission > 0
                        ),
                );
            mesh.receiveShadow = !this.opts.lowPower;
        });
        this.scene.add(group);
        this.parts.set(slot, group);
        group.updateMatrixWorld(true);
        this.registerGlows(group);
        const leds = this.glows.filter((glow) =>
            group.userData.leds.includes(glow),
        );
        for (const led of leds) {
            if (animate && !this.opts.reduced) {
                led.power.value = 0;
                gsap.to(led.power, {
                    value: 1,
                    delay: 0.98,
                    duration: 0.85,
                    ease: 'power2.out',
                });
            }
        }
        this.qualitySample = 0;
        this.qualityFrames = 0;
        this.renderer.shadowMap.needsUpdate = true;

        if (!animate || this.opts.reduced) return;
        // Llegada limpia: baja girando, asienta con un rebote, chispas cortas y pulso en las pistas.
        // Sin haces ni destellos rojos: lavaban toda la escena.
        const home = ANCHORS[slot].home;
        group.position.set(home.x, home.y + 3.4, home.z + 0.4);
        group.rotation.set(0.35, -1.1, 0.18);
        group.scale.setScalar(0.55);
        group.userData.arrival = gsap
            .timeline({ delay: 0.18 })
            .to(group.position, {
                x: home.x,
                y: home.y,
                z: home.z,
                duration: 0.8,
                ease: 'power4.in',
                onUpdate: () => {
                    this.renderer.shadowMap.needsUpdate = true;
                },
            })
            .to(
                group.rotation,
                { x: 0, y: 0, z: 0, duration: 0.8, ease: 'power2.out' },
                0,
            )
            .to(
                group.scale,
                {
                    x: 1.06,
                    y: 1.06,
                    z: 1.06,
                    duration: 0.8,
                    ease: 'power2.out',
                },
                0,
            )
            .add(() => this.impact(home), 0.8)
            .to(
                group.scale,
                {
                    x: 1,
                    y: 1,
                    z: 1,
                    duration: 0.7,
                    ease: 'elastic.out(1, 0.4)',
                    onUpdate: () => {
                        this.renderer.shadowMap.needsUpdate = true;
                    },
                },
                0.8,
            );
    }

    /** Al encajar: chispas cortas y pulso tenue en las pistas de la tarjeta madre. */
    private impact(home: THREE.Vector3): void {
        this.burst(home, this.opts.lowPower ? 12 : 28);
        this.pulse();
    }

    /** Lleva la cámara hacia una pieza (o a la vista general con null). */
    focus(slot: Slot | null): void {
        this.entering = false;
        this.controls.enabled = true;
        if (this.canvas.dataset.intro === 'running')
            this.canvas.dataset.intro = 'interrupted';
        gsap.killTweensOf([this.camera.position, this.controls.target]);
        this.focused = slot;
        if (this.celebrating) return;
        // Se acerca poco y solo se inclina hacia la pieza: el gabinete completo siempre queda a la vista.
        // Sin correrlo de lado: al enfocar el procesador el gabinete se iba a la derecha y los
        // ventiladores del frente quedaban tapados por el selector.
        const piece = slot && slot !== 'case';
        const direction = OVERVIEW.pos.clone().sub(OVERVIEW.target).normalize();
        const target = OVERVIEW.target.clone();
        if (piece) {
            const shift = ANCHORS[slot].home
                .clone()
                .sub(OVERVIEW.target)
                .multiplyScalar(0.6);
            const right = new THREE.Vector3(
                direction.z,
                0,
                -direction.x,
            ).normalize();
            shift.addScaledVector(right, -shift.dot(right));
            target.add(shift);
        }
        const pos = target
            .clone()
            .add(
                direction.multiplyScalar(
                    OVERVIEW.pos.distanceTo(OVERVIEW.target) *
                        (piece ? 0.86 : 1),
                ),
            );
        if (this.opts.reduced) {
            this.camera.position.copy(pos);
            this.controls.target.copy(target);
            return;
        }
        gsap.to(this.controls.target, {
            x: target.x,
            y: target.y,
            z: target.z,
            duration: 1.2,
            ease: 'power3.inOut',
        });
        gsap.to(this.camera.position, {
            x: pos.x,
            y: pos.y,
            z: pos.z,
            duration: 1.2,
            ease: 'power3.inOut',
        });
    }

    /** Final: giro de 360°, ola arcoíris que regresa al rojo y el emblema ATS hecho de partículas. */
    async celebrate(): Promise<void> {
        this.entering = false;
        this.controls.enabled = true;
        if (this.canvas.dataset.intro === 'running')
            this.canvas.dataset.intro = 'interrupted';
        gsap.killTweensOf([this.camera.position, this.controls.target]);
        this.celebrating = true;
        this.focused = null;
        if (!this.opts.reduced)
            this.finale.push(
                gsap.fromTo(
                    this,
                    { rainbow: 0 },
                    {
                        rainbow: 1,
                        duration: 0.6,
                        yoyo: true,
                        repeat: 1,
                        repeatDelay: 3.4,
                    },
                ),
            );

        const emblem = this.formEmblem();
        if (!this.opts.reduced) {
            this.controls.minAzimuthAngle = -Infinity;
            this.controls.maxAzimuthAngle = Infinity;
            const center = OVERVIEW.target;
            this.finale.push(
                gsap.to(this.controls.target, {
                    x: center.x,
                    y: center.y,
                    z: center.z,
                    duration: 1,
                    ease: 'power2.inOut',
                }),
            );
            const radius = Math.hypot(
                OVERVIEW.pos.x - center.x,
                OVERVIEW.pos.z - center.z,
            );
            const orbit = {
                a: Math.atan2(
                    this.camera.position.x - center.x,
                    this.camera.position.z - center.z,
                ),
                radius: Math.hypot(
                    this.camera.position.x - center.x,
                    this.camera.position.z - center.z,
                ),
                y: this.camera.position.y,
            };
            await new Promise<void>((done) => {
                this.finale.push(
                    gsap.to(orbit, {
                        a: orbit.a + Math.PI * 2,
                        radius,
                        y: OVERVIEW.pos.y,
                        duration: 3.6,
                        ease: 'power2.inOut',
                        onUpdate: () => {
                            this.camera.position.set(
                                center.x + Math.sin(orbit.a) * orbit.radius,
                                orbit.y,
                                center.z + Math.cos(orbit.a) * orbit.radius,
                            );
                        },
                        onComplete: done,
                        // reset() lo corta si el cliente empieza de cero a media celebración.
                        onInterrupt: done,
                    }),
                );
            });
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
        this.reflector
            ?.getRenderTarget()
            .setSize(Math.round(w * 0.5), Math.round(h * 0.5));
        this.camera.aspect = w / h;
        // En celular no hay paneles a los lados: se acerca para que el gabinete llene la escena sin cortarse.
        this.camera.zoom =
            w < 1024 ? THREE.MathUtils.clamp((w / h) * 1.4, 1, 1.6) : 1;
        this.camera.updateProjectionMatrix();
    }

    destroy(): void {
        this.destroyed = true;
        this.renderer.setAnimationLoop(null);
        window.removeEventListener('build-lab:loaded', this.onLoaded);
        gsap.killTweensOf([this, this.camera.position, this.controls.target]);
        this.glows.forEach((glow) => gsap.killTweensOf(glow.power));
        this.parts.forEach((part) => {
            (part.userData.arrival as gsap.core.Timeline | undefined)?.kill();
            gsap.killTweensOf([part.position, part.rotation, part.scale]);
        });
        document.removeEventListener('visibilitychange', this.onVisibility);
        this.canvas.removeEventListener('pointermove', this.onPointerMove);
        this.canvas.removeEventListener('pointerdown', this.onPointerDown);
        this.canvas.removeEventListener('pointerup', this.onPointerUp);
        this.canvas.removeEventListener('pointerleave', this.onPointerLeave);
        this.observer.disconnect();
        this.controls.dispose();
        const shared = [...this.shared];
        this.dispose(this.scene);
        shared.forEach((mat) => mat.dispose());
        this.shared.clear();
        this.hubMap.dispose();
        this.bladeGeo.forEach((g) => g.dispose());
        this.environment?.dispose();
        this.reflector?.dispose();
        this.textures.forEach((texture) => texture.dispose());
        this.composer?.passes.forEach((pass) => pass.dispose());
        this.composer?.dispose();
        this.renderer.dispose();
    }

    /* ---------------- Ciclo ---------------- */

    private frame(): void {
        const dt = Math.min(this.clock.getDelta(), 0.05);
        const t = this.clock.elapsedTime;
        if (!this.opts.reduced)
            for (const fan of this.fans) fan.rotation.z += dt * 7;

        this.ledTime.value = this.opts.reduced ? 0 : t;
        this.ledRainbow.value = this.rainbow;
        const powered = this.glows.length > 0;
        for (let i = 0; i < this.spill.length; i++) {
            const light = this.spill[i];
            const color = new THREE.Color().setHSL(
                ((((this.opts.reduced ? 0 : -t) * 0.16 + i * 0.18) % 1) + 1) %
                    1,
                1,
                0.5,
            );
            light.color.copy(RED).lerp(color, this.rainbow);
            const power = powered
                ? Math.max(...this.glows.map((glow) => glow.power.value))
                : 0;
            light.intensity =
                power *
                (i === 0 ? 3.2 : 2.0) *
                (0.86 + Math.sin(t * 1.2) * 0.08);
        }
        if (this.dof) {
            const focus =
                this.focused && this.parts.has(this.focused) && !this.degraded;
            this.dof.enabled = Boolean(focus);
            const target = focus
                ? ANCHORS[this.focused!].home
                : OVERVIEW.target;
            (this.dof.uniforms as Record<string, THREE.IUniform>).focus.value =
                this.camera.position.distanceTo(target);
        }

        if (this.dust && !this.opts.reduced) {
            this.dust.rotation.y = t * 0.012;
            this.dust.position.y = Math.sin(t * 0.2) * 0.15;
        }

        // Reposo: vaivén lento si nadie toca la escena.
        if (
            !this.opts.reduced &&
            !this.celebrating &&
            !this.entering &&
            !this.focused &&
            performance.now() - this.lastInput > 5000
        ) {
            const a = Math.sin(t * 0.16) * 0.14 + 0.58;
            const c = OVERVIEW.target;
            const r = Math.hypot(OVERVIEW.pos.x - c.x, OVERVIEW.pos.z - c.z);
            this.camera.position.lerp(
                new THREE.Vector3(
                    c.x + Math.sin(a) * r,
                    OVERVIEW.pos.y,
                    c.z + Math.cos(a) * r,
                ),
                1 - Math.pow(0.99, dt * 60),
            );
        }

        this.bursts = this.bursts.filter((b) => {
            const age = (performance.now() - b.born) / 1000;
            const pos = b.points.geometry.attributes
                .position as THREE.BufferAttribute;
            for (let i = 0; i < pos.count; i++) {
                b.velocities[i * 3 + 1] -= 3.2 * dt;
                pos.setXYZ(
                    i,
                    pos.getX(i) + b.velocities[i * 3] * dt,
                    pos.getY(i) + b.velocities[i * 3 + 1] * dt,
                    pos.getZ(i) + b.velocities[i * 3 + 2] * dt,
                );
            }
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

        if (this.grain)
            (this.grain.uniforms.uTime as { value: number }).value = t;
        this.controls.update();
        this.renderer.info.reset();
        if (this.composer) this.composer.render();
        else this.renderer.render(this.scene, this.camera);
        this.measure();
    }

    private async loadEnvironment(): Promise<void> {
        const pmrem = new THREE.PMREMGenerator(this.renderer);
        pmrem.compileEquirectangularShader();
        try {
            const hdr = await new RGBELoader().loadAsync(
                '/builder/env/studio_small_03_1k.hdr',
            );
            if (this.destroyed) {
                hdr.dispose();
                return;
            }
            this.environment = pmrem.fromEquirectangular(hdr);
            this.scene.environment = this.environment.texture;
            this.scene.environmentRotation.y = 0.65;
            hdr.dispose();
            this.canvas.dataset.environment = 'studio-small-03';
        } catch {
            // Las luces de estudio mantienen la escena usable si falla la descarga local.
            this.canvas.dataset.environment = 'fallback';
        } finally {
            pmrem.dispose();
        }
    }

    private onLoaded = (): void => {
        this.performanceAt = performance.now() + 3000;
        if (
            this.opts.reduced ||
            this.lastInput ||
            this.focused ||
            !this.parts.size
        )
            return;
        this.intro();
    };

    /** Entrada de cámara: de cerca del ventilador frontal a la vista general. */
    private intro(): void {
        this.entering = true;
        this.canvas.dataset.intro = 'running';
        this.controls.enabled = false;
        this.camera.position.set(5.2, 1.8, 4.9);
        this.controls.target.set(1.9, 1.0, 0);
        gsap.to(this.controls.target, {
            x: OVERVIEW.target.x,
            y: OVERVIEW.target.y,
            z: OVERVIEW.target.z,
            duration: 2.6,
            ease: 'power2.inOut',
        });
        gsap.to(this.camera.position, {
            x: OVERVIEW.pos.x,
            y: OVERVIEW.pos.y,
            z: OVERVIEW.pos.z,
            duration: 2.6,
            ease: 'power2.inOut',
            onComplete: () => {
                this.entering = false;
                this.canvas.dataset.intro = 'complete';
                this.controls.enabled = true;
                this.lastInput = performance.now();
            },
        });
    }

    /** Ventanas de tiempo real: no se usa el delta limitado de las animaciones para medir fps. */
    private measure(): void {
        const now = performance.now();
        if (document.hidden || now < this.performanceAt) {
            this.sampleStart = now;
            return;
        }
        if (!this.sampleStart) this.sampleStart = now;
        this.sampleFrames++;
        this.sampleCalls += this.renderer.info.render.calls;
        this.sampleTriangles += this.renderer.info.render.triangles;
        if (!this.qualitySample) this.qualitySample = now;
        this.qualityFrames++;
        if (
            !this.opts.lowPower &&
            !this.degraded &&
            this.parts.size &&
            now - this.qualitySample > 3000
        ) {
            const fps =
                (this.qualityFrames * 1000) / (now - this.qualitySample);
            if (fps < 45) {
                this.degraded = true;
                if (this.gtao) this.gtao.enabled = false;
                if (this.dof) this.dof.enabled = false;
                if (this.reflector) this.reflector.visible = false;
                this.renderer.shadowMap.enabled = false;
                const glass = this.m.glass as THREE.MeshPhysicalMaterial;
                glass.transmission = 0;
                glass.transparent = true;
                glass.opacity = 0.035;
                glass.envMapIntensity = 0.18;
                glass.specularIntensity = 0.2;
                glass.color.set(0x606975);
                glass.depthWrite = false;
                glass.needsUpdate = true;
                this.renderer.setPixelRatio(
                    Math.min(window.devicePixelRatio, 1.25),
                );
                this.composer?.setPixelRatio(this.renderer.getPixelRatio());
                this.resize();
            }
        }
        const elapsed = now - this.sampleStart;
        if (elapsed < 1500) return;
        this.canvas.dataset.performance = JSON.stringify({
            fps: Math.round((this.sampleFrames * 10000) / elapsed) / 10,
            drawCalls: Math.round(this.sampleCalls / this.sampleFrames),
            triangles: Math.round(this.sampleTriangles / this.sampleFrames),
            preset: this.opts.lowPower
                ? 'light'
                : this.degraded
                  ? 'adaptive'
                  : 'desktop',
            gtao: this.gtao?.enabled ?? false,
            reflection: this.reflector?.visible ?? false,
            dof: this.dof?.enabled ?? false,
            pixelRatio: this.renderer.getPixelRatio(),
        });
        this.sampleStart = now;
        this.sampleFrames = this.sampleCalls = this.sampleTriangles = 0;
    }

    /** Agrupa superficies fijas por material; los rotores y LEDs siguen independientes. */
    private batch(group: THREE.Group): void {
        group.updateMatrixWorld(true);
        const batches = new Map<THREE.Material, THREE.Mesh[]>();
        group.traverse((object) => {
            const mesh = object as THREE.Mesh;
            if (
                !mesh.isMesh ||
                (mesh as THREE.InstancedMesh).isInstancedMesh ||
                Array.isArray(mesh.material) ||
                mesh.material.transparent ||
                mesh.material.userData.led
            )
                return;
            for (
                let parent = mesh.parent;
                parent && parent !== group;
                parent = parent.parent
            )
                if (this.fans.includes(parent)) return;
            const list = batches.get(mesh.material) ?? [];
            list.push(mesh);
            batches.set(mesh.material, list);
        });
        for (const [material, meshes] of batches) {
            if (meshes.length < 2) continue;
            const copies = meshes.map((mesh) => {
                const geometry = mesh.geometry.index
                    ? mesh.geometry.toNonIndexed()
                    : mesh.geometry.clone();
                return geometry.applyMatrix4(mesh.matrixWorld);
            });
            const merged = mergeGeometries(copies);
            copies.forEach((geometry) => geometry.dispose());
            if (!merged) continue;
            for (const mesh of meshes) {
                mesh.removeFromParent();
                mesh.geometry.dispose();
            }
            group.add(new THREE.Mesh(merged, material));
        }
    }

    private boardComponents(): THREE.Group {
        const group = new THREE.Group();
        const capacitors = new THREE.InstancedMesh(
            new THREE.CylinderGeometry(0.035, 0.035, 0.09, 10).rotateX(
                Math.PI / 2,
            ),
            this.m.silver,
            24,
        );
        const coils = new THREE.InstancedMesh(
            new THREE.BoxGeometry(0.12, 0.1, 0.09),
            this.m.graphite,
            12,
        );
        const matrix = new THREE.Matrix4();
        for (let i = 0; i < 24; i++) {
            const row = i < 12;
            const x = row ? -0.48 + (i % 12) * 0.1 : -0.92;
            const y = row ? 1.08 : -1.3 + (i % 12) * 0.17;
            capacitors.setMatrixAt(i, matrix.makeTranslation(x, y, 0.075));
        }
        for (let i = 0; i < 12; i++)
            coils.setMatrixAt(
                i,
                matrix.makeTranslation(
                    -0.43 + (i % 6) * 0.16,
                    i < 6 ? 0.98 : -1.28,
                    0.07,
                ),
            );
        group.add(capacitors, coils);
        return group;
    }

    /* ---------------- Ambiente ---------------- */

    private materials() {
        const own = <T extends THREE.Material>(mat: T): T => {
            this.shared.add(mat);
            return mat;
        };
        const normal = surfaceTexture('brush');
        const carbon = surfaceTexture('carbon');
        this.textures.add(normal);
        this.textures.add(carbon);
        const glass = this.opts.lowPower
            ? new THREE.MeshPhysicalMaterial({
                  color: 0x606975,
                  transparent: true,
                  opacity: 0.035,
                  envMapIntensity: 0.18,
                  specularIntensity: 0.2,
                  roughness: 0.05,
                  metalness: 0,
                  depthWrite: false,
                  side: THREE.DoubleSide,
              })
            : new THREE.MeshPhysicalMaterial({
                  color: 0xffffff,
                  transmission: 1,
                  thickness: 0.05,
                  ior: 1.5,
                  roughness: 0.03,
                  metalness: 0,
                  attenuationColor: new THREE.Color(0xb9c8d8),
                  attenuationDistance: 2.5,
                  specularIntensity: 0.2,
                  envMapIntensity: 0.25,
                  side: THREE.DoubleSide,
              });
        return {
            alu: own(
                new THREE.MeshPhysicalMaterial({
                    color: 0x777f89,
                    metalness: 1,
                    roughness: 0.34,
                    anisotropy: 0.7,
                    normalMap: normal,
                    normalScale: new THREE.Vector2(0.16, 0.16),
                }),
            ),
            anod: own(
                new THREE.MeshStandardMaterial({
                    color: 0x141419,
                    metalness: 0.85,
                    roughness: 0.36,
                }),
            ),
            plastic: own(
                new THREE.MeshStandardMaterial({
                    color: 0x0b0b0e,
                    metalness: 0.05,
                    roughness: 0.55,
                }),
            ),
            graphite: own(
                new THREE.MeshStandardMaterial({
                    color: 0x24262c,
                    metalness: 0.75,
                    roughness: 0.32,
                }),
            ),
            copper: own(
                new THREE.MeshStandardMaterial({
                    color: 0xd08a52,
                    metalness: 1,
                    roughness: 0.22,
                }),
            ),
            carbon: own(
                new THREE.MeshPhysicalMaterial({
                    color: 0xb4b4b4,
                    map: carbon,
                    envMapIntensity: 0.35,
                    roughness: 0.55,
                    metalness: 0.25,
                    clearcoat: 0.2,
                    clearcoatRoughness: 0.4,
                }),
            ),
            blade: own(
                new THREE.MeshStandardMaterial({
                    color: 0xc8c1c4,
                    roughness: 0.5,
                    transparent: true,
                    opacity: 0.46,
                    depthWrite: false,
                    side: THREE.DoubleSide,
                }),
            ),
            silver: own(
                new THREE.MeshStandardMaterial({
                    color: 0xe1e4e8,
                    metalness: 1,
                    roughness: 0.18,
                }),
            ),
            glass: own(glass),
        };
    }

    private lights(): void {
        this.scene.add(new THREE.HemisphereLight(0xc4d0e0, 0x090507, 0.18));
        const key = new THREE.DirectionalLight(0xffffff, 1.7);
        key.position.set(5, 8, 7);
        key.target.position.set(0, -1, 0);
        key.castShadow = true;
        key.shadow.mapSize.set(2048, 2048);
        key.shadow.camera.left = -5;
        key.shadow.camera.right = 5;
        key.shadow.camera.top = 5;
        key.shadow.camera.bottom = -5;
        key.shadow.bias = -0.0004;
        key.shadow.radius = 6;
        this.scene.add(key, key.target);

        // Paneles de estudio: suave blanco de frente, contraluz rojo y relleno frío.
        const soft = new THREE.RectAreaLight(0xffffff, 1.6, 6, 4);
        soft.position.set(2, 3, 7);
        soft.lookAt(0, 0, 0);
        const rim = new THREE.RectAreaLight(0xdce6ff, 3, 4, 6);
        rim.position.set(-5, 2, -4);
        rim.lookAt(0, 0, 0);
        const fill = new THREE.RectAreaLight(0x7fb4ff, 2.5, 4, 4);
        fill.position.set(7, 0, -1);
        fill.lookAt(0, 0, 0);
        this.scene.add(soft, rim, fill);

        // Dentro del gabinete: luz que toma el color del RGB.
        const inner = new THREE.PointLight(RED, 5, 4.5, 2);
        inner.position.set(0.4, -0.2, 0.4);
        const under = new THREE.PointLight(RED, 0, 5, 2);
        under.position.set(0.5, -2.15, 0.85);
        this.spill.push(inner, under);
        this.scene.add(inner, under);
    }

    private floor(): void {
        const y = -2.55;
        if (!this.opts.lowPower) {
            this.reflector = new Reflector(new THREE.PlaneGeometry(40, 40), {
                clipBias: 0.003,
                textureWidth: 512,
                textureHeight: 512,
                color: 0x38383c,
            });
            const reflectionMaterial = this.reflector
                .material as THREE.ShaderMaterial;
            reflectionMaterial.fragmentShader =
                reflectionMaterial.fragmentShader.replace(
                    'vec4 base = texture2DProj( tDiffuse, vUv );',
                    `
                vec4 base = vec4(0.0);
                for (int x = -2; x <= 2; x++) for (int y = -2; y <= 2; y++) {
                    vec4 tap = vUv;
                    tap.xy += vec2(float(x), float(y)) * 0.0038 * vUv.w;
                    base += texture2DProj(tDiffuse, tap) / 25.0;
                }
            `,
                );
            this.reflector.rotation.x = -Math.PI / 2;
            this.reflector.position.y = y - 0.01;
            this.scene.add(this.reflector);
        }
        // Velo oscuro sobre el reflejo: piso de estudio brillante que se funde con el negro.
        const veil = document.createElement('canvas');
        veil.width = veil.height = 256;
        const v = veil.getContext('2d')!;
        const g = v.createRadialGradient(128, 128, 10, 128, 128, 128);
        g.addColorStop(0, 'rgba(7,6,8,0.55)');
        g.addColorStop(0.55, 'rgba(7,6,8,0.86)');
        g.addColorStop(1, 'rgba(7,6,8,1)');
        v.fillStyle = g;
        v.fillRect(0, 0, 256, 256);
        const floorMap = new THREE.CanvasTexture(veil);
        floorMap.colorSpace = THREE.SRGBColorSpace;
        const top = new THREE.Mesh(
            new THREE.PlaneGeometry(40, 40),
            new THREE.MeshStandardMaterial({
                map: floorMap,
                roughness: 0.75,
                metalness: 0.08,
                envMapIntensity: 0.05,
                transparent: true,
                depthWrite: false,
            }),
        );
        top.rotation.x = -Math.PI / 2;
        top.position.y = y;
        this.scene.add(top);

        const shadow = new THREE.Mesh(
            new THREE.PlaneGeometry(40, 40),
            new THREE.ShadowMaterial({ opacity: 0.55 }),
        );
        shadow.rotation.x = -Math.PI / 2;
        shadow.position.y = y + 0.002;
        shadow.receiveShadow = true;
        this.scene.add(shadow);

        const contact = document.createElement('canvas');
        contact.width = contact.height = 128;
        const ctx = contact.getContext('2d')!;
        const falloff = ctx.createRadialGradient(64, 64, 12, 64, 64, 64);
        falloff.addColorStop(0, 'rgba(0,0,0,0.65)');
        falloff.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.fillStyle = falloff;
        ctx.fillRect(0, 0, 128, 128);
        const tex = new THREE.CanvasTexture(contact);
        const mat = new THREE.MeshBasicMaterial({
            map: tex,
            transparent: true,
            depthWrite: false,
        });
        const patch = new THREE.Mesh(new THREE.PlaneGeometry(7, 4.4), mat);
        patch.rotation.x = -Math.PI / 2;
        patch.position.y = y + 0.005;
        this.scene.add(patch);
    }

    private atmosphere(): void {
        // Espacio: estrellas lejanas en una esfera alrededor y partículas cercanas flotando junto a la PC.
        const count = this.opts.lowPower ? 220 : 900;
        const pos = new Float32Array(count * 3);
        const dir = new THREE.Vector3();
        for (let i = 0; i < count; i++) {
            if (i % 4 === 0) {
                pos.set(
                    [
                        (Math.random() - 0.5) * 11,
                        -3.5 + Math.random() * 8,
                        (Math.random() - 0.5) * 7,
                    ],
                    i * 3,
                );
            } else {
                dir.randomDirection().multiplyScalar(14 + Math.random() * 30);
                pos.set([dir.x, dir.y, dir.z], i * 3);
            }
        }
        const geo = new THREE.BufferGeometry();
        geo.setAttribute('position', new THREE.BufferAttribute(pos, 3));
        this.dust = new THREE.Points(
            geo,
            new THREE.PointsMaterial({
                color: 0xffe4e0,
                size: 0.05,
                transparent: true,
                opacity: 0.7,
                depthWrite: false,
                blending: THREE.AdditiveBlending,
            }),
        );
        this.scene.add(this.dust);
    }

    private postprocessing(): void {
        const composer = new EffectComposer(this.renderer);
        composer.addPass(new RenderPass(this.scene, this.camera));
        if (!this.opts.lowPower) {
            this.gtao = new GTAOPass(this.scene, this.camera, 256, 256);
            this.gtao.updateGtaoMaterial({
                radius: 0.35,
                distanceExponent: 1.4,
                thickness: 1.5,
                scale: 1.2,
            });
            this.gtao.blendIntensity = 0.85;
            composer.addPass(this.gtao);
        }
        // Umbral alto: solo brilla lo emisivo (RGB, pistas), no los reflejos del metal o el vidrio.
        this.bloom = new UnrealBloomPass(
            new THREE.Vector2(256, 256),
            this.opts.lowPower ? 0.5 : 0.6,
            0.45,
            // Más alto que cualquier reflejo del aluminio: así las aristas del gabinete no brillan como neón.
            1.4,
        );
        composer.addPass(this.bloom);
        if (!this.opts.lowPower) {
            this.outline = new OutlinePass(
                new THREE.Vector2(256, 256),
                this.scene,
                this.camera,
            );
            this.outline.edgeStrength = 3.5;
            this.outline.edgeGlow = 0.6;
            this.outline.edgeThickness = 1.4;
            this.outline.visibleEdgeColor.set(0xff2a3a);
            this.outline.hiddenEdgeColor.set(0x4a0a10);
            composer.addPass(this.outline);
        }
        if (!this.opts.lowPower) {
            this.dof = new BokehPass(this.scene, this.camera, {
                focus: 14,
                aperture: 0.000035,
                maxblur: 0.004,
            });
            this.dof.enabled = false;
            composer.addPass(this.dof);
        }
        composer.addPass(new OutputPass());
        if (!this.opts.lowPower) composer.addPass(new SMAAPass(256, 256));
        this.grain = new ShaderPass(GRAIN_SHADER);
        composer.addPass(this.grain);
        this.composer = composer;
    }

    /* ---------------- Piezas ---------------- */

    private build(slot: Slot, part: Part): THREE.Group {
        const g = new THREE.Group();
        const s = part.specs;
        const m = this.m;
        switch (slot) {
            case 'motherboard': {
                g.add(this.rbox(2.44, 3.05, 0.05, 0.015, this.pcbMaterial()));
                g.add(this.boardComponents());
                // Socket con su marco, disipadores de VRM con aletas, escudo de I/O con RGB, chipset y ranuras.
                g.add(
                    this.at(
                        this.rbox(0.62, 0.62, 0.04, 0.01, m.graphite),
                        0,
                        0.7,
                        0.045,
                    ),
                );
                g.add(this.at(this.vrm(1.05, 0.26, 0.22), -0.05, 1.22, 0.14));
                g.add(this.at(this.vrm(0.26, 0.9, 0.22), -0.6, 0.62, 0.14));
                g.add(
                    this.at(
                        this.rbox(0.42, 1.0, 0.3, 0.04, m.anod),
                        -1.0,
                        0.95,
                        0.17,
                    ),
                );
                g.add(
                    this.at(
                        this.rbox(0.03, 0.82, 0.02, 0.008, this.glow(2.4)),
                        -0.78,
                        0.95,
                        0.3,
                    ),
                );
                g.add(
                    this.at(
                        this.rbox(0.62, 0.62, 0.12, 0.05, m.anod),
                        0.62,
                        -0.85,
                        0.09,
                    ),
                );
                g.add(
                    this.at(
                        this.rbox(0.5, 0.06, 0.02, 0.01, this.glow(1.8)),
                        0.62,
                        -0.85,
                        0.16,
                    ),
                );
                g.add(
                    this.at(
                        this.rbox(1.95, 0.09, 0.11, 0.02, m.silver),
                        -0.2,
                        -0.42,
                        0.08,
                    ),
                );
                g.add(
                    this.at(
                        this.rbox(1.95, 0.09, 0.11, 0.02, m.plastic),
                        -0.2,
                        -1.05,
                        0.08,
                    ),
                );
                for (const x of [0.86, 0.96, 1.06, 1.16])
                    g.add(
                        this.at(
                            this.rbox(0.05, 1.35, 0.08, 0.01, m.plastic),
                            x,
                            0.7,
                            0.06,
                        ),
                    );
                // Cables forrados de 24 y 8 pines.
                g.add(
                    this.cable(
                        [
                            new THREE.Vector3(1.2, 0.2, 0.06),
                            new THREE.Vector3(1.45, 0.15, 0.4),
                            new THREE.Vector3(1.6, -0.3, 0.5),
                            new THREE.Vector3(1.7, -1.6, 0.3),
                        ],
                        0.07,
                        12,
                    ),
                );
                g.add(
                    this.cable(
                        [
                            new THREE.Vector3(-1.1, 1.45, 0.06),
                            new THREE.Vector3(-1.15, 1.75, 0.35),
                            new THREE.Vector3(-1.3, 1.9, 0.2),
                        ],
                        0.04,
                        4,
                    ),
                );
                break;
            }
            case 'cpu': {
                g.add(
                    this.rbox(
                        0.44,
                        0.44,
                        0.03,
                        0.01,
                        new THREE.MeshStandardMaterial({
                            color: 0x1f3a24,
                            metalness: 0.3,
                            roughness: 0.6,
                        }),
                    ),
                );
                g.add(
                    this.at(
                        this.rbox(0.36, 0.36, 0.05, 0.02, m.silver),
                        0,
                        0,
                        0.035,
                    ),
                );
                break;
            }
            case 'cooler': {
                if (s.type === 'líquida') this.aio(g);
                else this.tower(g);
                break;
            }
            case 'ram': {
                const sticks = Number(s.modules ?? 2) >= 4 ? 4 : 2;
                for (let i = 0; i < sticks; i++) {
                    const stick = new THREE.Group();
                    stick.add(
                        this.at(
                            this.rbox(
                                0.03,
                                1.33,
                                0.3,
                                0.01,
                                this.pcbMaterial(),
                            ),
                            0,
                            0,
                            -0.04,
                        ),
                    );
                    stick.add(this.rbox(0.075, 1.3, 0.34, 0.025, m.anod));
                    stick.add(
                        this.at(
                            this.rbox(0.06, 1.24, 0.08, 0.03, this.glow(2.6)),
                            0,
                            0,
                            0.2,
                        ),
                    );
                    stick.position.x =
                        -0.12 + i * (0.24 / Math.max(1, sticks - 1));
                    g.add(stick);
                }
                break;
            }
            case 'gpu': {
                // Montaje vertical: el largo en x, los ventiladores miran al vidrio (+z).
                const length = THREE.MathUtils.clamp(
                    Number(s.length_mm ?? 300) / 100,
                    2.2,
                    3.5,
                );
                const card = new THREE.Group();
                card.add(this.rbox(length, 1.18, 0.42, 0.06, m.graphite));
                card.add(
                    this.at(
                        this.rbox(length, 1.18, 0.04, 0.02, m.alu),
                        0,
                        0,
                        -0.23,
                    ),
                );
                card.add(this.at(this.shroud(length), 0, 0, 0.2));
                const fans = length > 2.7 ? 3 : 2;
                for (let i = 0; i < fans; i++)
                    card.add(
                        this.at(
                            this.fan(0.44, true),
                            (i - (fans - 1) / 2) * (length / fans) * 0.98,
                            -0.02,
                            0.29,
                        ),
                    );
                card.add(
                    this.at(
                        this.rbox(
                            length * 0.92,
                            0.035,
                            0.05,
                            0.015,
                            this.glow(3),
                        ),
                        0,
                        0.6,
                        0.12,
                    ),
                );
                card.add(
                    this.at(
                        this.label('TECH COMPUTER', 512, 64),
                        length / 2 - 0.85,
                        0.6,
                        0.155,
                    ),
                );
                card.add(
                    this.at(
                        this.rbox(0.08, 1.0, 0.4, 0.02, m.silver),
                        -length / 2 - 0.04,
                        0,
                        0,
                    ),
                );
                card.position.x = (length - 3.0) / 2;
                g.add(card);
                g.add(
                    this.cable(
                        [
                            new THREE.Vector3(0.8, 0.59, 0.03),
                            new THREE.Vector3(1.0, 0.95, 0.53),
                            new THREE.Vector3(1.5, 0.65, 0.75),
                            new THREE.Vector3(1.65, -0.65, 0.2),
                        ],
                        0.045,
                        6,
                    ),
                );
                // Soporte vertical que la sostiene.
                g.add(
                    this.at(
                        this.rbox(0.06, 1.6, 0.06, 0.02, m.anod),
                        -1.95,
                        -0.2,
                        -0.05,
                    ),
                );
                break;
            }
            case 'storage': {
                g.add(this.rbox(0.8, 0.22, 0.02, 0.008, this.pcbMaterial()));
                g.add(
                    this.at(
                        this.rbox(0.78, 0.24, 0.06, 0.02, m.anod),
                        0,
                        0,
                        0.04,
                    ),
                );
                g.add(
                    this.at(
                        this.rbox(0.6, 0.02, 0.01, 0.005, this.glow(2.2)),
                        0,
                        -0.06,
                        0.075,
                    ),
                );
                g.add(this.at(this.label('NVMe', 256, 64), 0.12, 0.05, 0.072));
                break;
            }
            case 'psu': {
                g.add(this.rbox(1.5, 0.86, 1.4, 0.05, m.anod));
                g.add(
                    this.at(
                        this.perforated(1.3, 1.2, m.graphite, 'y'),
                        0,
                        0.432,
                        0,
                    ),
                );
                g.add(
                    this.at(
                        this.label(`${String(s.watts ?? '')} W`, 256, 96),
                        0.25,
                        0,
                        0.705,
                    ),
                );
                g.add(
                    this.at(
                        this.rbox(0.9, 0.03, 0.02, 0.01, this.glow(2)),
                        -0.1,
                        -0.3,
                        0.71,
                    ),
                );
                break;
            }
            case 'case': {
                this.chassis(g);
                break;
            }
        }
        this.batch(g);
        return g;
    }

    private chassis(g: THREE.Group): void {
        const m = this.m;
        const W = 4.4;
        const H = 4.8;
        const D = 2.2;
        // Marco de aluminio cepillado con aristas redondeadas.
        const bar = 0.07;
        for (const [sx, sz] of [
            [1, 1],
            [1, -1],
            [-1, 1],
            [-1, -1],
        ])
            g.add(
                this.at(
                    this.rbox(bar, H, bar, 0.025, m.alu),
                    (sx * (W - bar)) / 2,
                    0,
                    (sz * (D - bar)) / 2,
                ),
            );
        for (const sy of [1, -1]) {
            for (const sz of [1, -1])
                g.add(
                    this.at(
                        this.rbox(W, bar, bar, 0.025, m.alu),
                        0,
                        (sy * (H - bar)) / 2,
                        (sz * (D - bar)) / 2,
                    ),
                );
            for (const sx of [1, -1])
                g.add(
                    this.at(
                        this.rbox(bar, bar, D, 0.025, m.alu),
                        (sx * (W - bar)) / 2,
                        (sy * (H - bar)) / 2,
                        0,
                    ),
                );
        }
        // Paneles anodizados: charola, techo con rejilla, piso y trasero.
        g.add(
            this.at(
                this.rbox(W - 0.1, H - 0.1, 0.04, 0.01, m.anod),
                0,
                0,
                -D / 2 + 0.03,
            ),
        );
        g.add(
            this.at(
                this.perforated(W - 0.2, D - 0.2, m.anod, 'y'),
                0,
                H / 2 - 0.03,
                0,
            ),
        );
        g.add(
            this.at(
                this.rbox(W - 0.1, 0.05, D - 0.1, 0.01, m.anod),
                0,
                -H / 2 + 0.03,
                0,
            ),
        );
        g.add(
            this.at(
                this.rbox(0.04, H - 0.1, D - 0.1, 0.01, m.anod),
                -W / 2 + 0.03,
                0,
                0,
            ),
        );
        // Frente con malla perforada; detrás, tres ventiladores RGB y uno trasero.
        g.add(
            this.at(
                this.perforated(D - 0.2, H - 0.2, m.anod, 'x'),
                W / 2 - 0.02,
                0,
                0,
            ),
        );
        for (const y of [1.3, 0.05, -1.2])
            g.add(this.at(this.fan(0.55, true, 'x'), W / 2 - 0.2, y + 0.3, 0));
        g.add(this.at(this.fan(0.48, true, 'x'), -W / 2 + 0.18, 1.45, -0.05));
        // Cubierta de la fuente, abierta atrás para ver la fuente, con placa y línea RGB.
        g.add(
            this.at(
                this.rbox(2.7, 0.9, D - 0.12, 0.04, m.carbon),
                0.8,
                -1.92,
                0,
            ),
        );
        g.add(
            this.at(
                this.label('TECH COMPUTER', 1024, 96),
                0.5,
                -1.84,
                D / 2 - 0.05,
            ),
        );
        g.add(
            this.at(
                this.rbox(2.6, 0.03, 0.02, 0.01, this.glow(3)),
                0.8,
                -1.48,
                D / 2 - 0.07,
            ),
        );
        // Tira RGB vertical junto al frente y patas.
        g.add(
            this.at(
                this.rbox(0.03, H - 0.4, 0.03, 0.012, this.glow(3.2)),
                W / 2 - 0.12,
                0,
                D / 2 - 0.1,
            ),
        );
        for (const [sx, sz] of [
            [1, 1],
            [1, -1],
            [-1, 1],
            [-1, -1],
        ])
            g.add(
                this.at(
                    this.rbox(0.45, 0.08, 0.3, 0.03, m.alu),
                    sx * 1.7,
                    -H / 2 - 0.05,
                    sz * 0.75,
                ),
            );
        // Vidrio templado del costado (no bloquea el puntero).
        const glass = new THREE.Mesh(
            new RoundedBoxGeometry(W - 0.14, H - 0.14, 0.03, 2, 0.012),
            m.glass,
        );
        glass.position.z = D / 2 + 0.005;
        glass.raycast = () => undefined;
        g.add(glass);
    }

    private tower(g: THREE.Group): void {
        const m = this.m;
        // Base de cobre sobre el procesador, heatpipes y 44 aletas instanciadas.
        g.add(this.at(this.rbox(0.42, 0.42, 0.08, 0.02, m.copper), 0, 0, -0.6));
        const fins = new THREE.InstancedMesh(
            new THREE.BoxGeometry(0.6, 1.18, 0.012),
            m.alu,
            44,
        );
        const mat = new THREE.Matrix4();
        for (let i = 0; i < 44; i++)
            fins.setMatrixAt(
                i,
                mat.makeTranslation(0, 0.04, -0.42 + i * 0.024),
            );
        g.add(fins);
        g.add(
            this.at(this.rbox(0.64, 1.22, 0.06, 0.025, m.anod), 0, 0.04, 0.65),
        );
        g.add(this.at(this.label('ATS', 256, 128), 0, 0.04, 0.683));
        for (const x of [-0.18, -0.06, 0.06, 0.18]) {
            const curve = new THREE.CatmullRomCurve3([
                new THREE.Vector3(x * 0.6, -0.05, -0.6),
                new THREE.Vector3(x, -0.5, -0.45),
                new THREE.Vector3(x, -0.55, 0.1),
                new THREE.Vector3(x, -0.45, 0.7),
                new THREE.Vector3(x, 0.55, 0.72),
            ]);
            g.add(
                new THREE.Mesh(
                    new THREE.TubeGeometry(curve, 40, 0.028, 10),
                    m.copper,
                ),
            );
        }
        g.add(this.at(this.fan(0.5, true, 'x'), 0.38, 0.04, 0.12));
    }

    private aio(g: THREE.Group): void {
        const m = this.m;
        const pump = new THREE.Mesh(
            new THREE.CylinderGeometry(0.3, 0.3, 0.22, 48),
            m.anod,
        );
        pump.rotation.x = Math.PI / 2;
        g.add(this.at(pump, 0, 0, -0.5));
        g.add(
            this.at(
                new THREE.Mesh(
                    this.ring(0.26, 0.022, 14, 64),
                    this.glow(3, true),
                ),
                0,
                0,
                -0.385,
            ),
        );
        const cap = new THREE.Mesh(
            new THREE.CircleGeometry(0.22, 48),
            new THREE.MeshStandardMaterial({
                map: this.hubMap,
                color: 0xffffff,
                metalness: 0.4,
                roughness: 0.35,
                emissive: RED,
                emissiveMap: this.hubMap,
                emissiveIntensity: 1.2,
            }),
        );
        g.add(this.at(cap, 0, 0, -0.388));
        // Radiador arriba con tres ventiladores que miran hacia abajo.
        const rad = new THREE.Group();
        rad.add(this.rbox(2.9, 0.28, 1.0, 0.04, m.anod));
        for (const x of [-0.95, 0, 0.95]) {
            const f = this.fan(0.42, true);
            f.rotation.x = Math.PI / 2;
            rad.add(this.at(f, x, -0.2, 0));
        }
        rad.position.set(1.4, 1.3, 0.3);
        g.add(rad);
        for (const dz of [-0.08, 0.08]) {
            const curve = new THREE.CatmullRomCurve3([
                new THREE.Vector3(0.25, 0.1, -0.5 + dz),
                new THREE.Vector3(0.7, 0.6, -0.1 + dz),
                new THREE.Vector3(1.0, 1.05, 0.2 + dz),
            ]);
            g.add(
                new THREE.Mesh(
                    new THREE.TubeGeometry(curve, 32, 0.05, 12),
                    m.plastic,
                ),
            );
        }
    }

    /* ---------------- Efectos ---------------- */

    private burst(
        at: THREE.Vector3,
        count = this.opts.lowPower ? 40 : 140,
    ): void {
        const positions = new Float32Array(count * 3);
        const velocities = new Float32Array(count * 3);
        for (let i = 0; i < count; i++) {
            positions.set([at.x, at.y, at.z + 0.3], i * 3);
            const dir = new THREE.Vector3()
                .randomDirection()
                .multiplyScalar(1.2 + Math.random() * 2.6);
            velocities.set([dir.x, Math.abs(dir.y) + 0.6, dir.z], i * 3);
        }
        const geo = new THREE.BufferGeometry();
        geo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        const points = new THREE.Points(
            geo,
            new THREE.PointsMaterial({
                color: new THREE.Color(3, 0.35, 0.3),
                size: 0.035,
                transparent: true,
                blending: THREE.AdditiveBlending,
                depthWrite: false,
            }),
        );
        this.scene.add(points);
        this.bursts.push({ points, velocities, born: performance.now() });
    }

    /** Pulso que recorre las pistas de la tarjeta madre al equipar algo. */
    private pulse(): void {
        for (const mat of this.pcb)
            gsap.fromTo(
                mat,
                { emissiveIntensity: 0.06 },
                { emissiveIntensity: 0, duration: 0.65, ease: 'power2.out' },
            );
    }

    /**
     * Las partículas salen de adentro del gabinete y forman el emblema ATS sobre el vidrio lateral,
     * en la esquina inferior del frente, como el logo iluminado de la tapa.
     */
    private async formEmblem(): Promise<void> {
        if (this.emblem) return;
        const img = new Image();
        img.src = '/img/ats-emblema.png';
        try {
            await img.decode();
        } catch {
            return;
        }
        if (this.destroyed) return;
        const w = 150;
        const h = Math.round((img.height / img.width) * w);
        const canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext('2d')!;
        ctx.drawImage(img, 0, 0, w, h);
        const data = ctx.getImageData(0, 0, w, h).data;
        const targets: number[] = [];
        const step = this.opts.lowPower ? 3 : 2;
        // Vidrio lateral, frente a la cubierta de la fuente (opaca): no tapa ninguna pieza ni ventilador.
        const size = 0.68;
        const panel = { x: 1.6, y: -1.84, z: ANCHORS.case.size.z / 2 + 0.05 };
        for (let y = 0; y < h; y += step)
            for (let x = 0; x < w; x += step)
                if (data[(y * w + x) * 4 + 3] > 140)
                    targets.push(
                        (x / w - 0.5) * size + panel.x,
                        (0.5 - y / h) * size * (h / w) + panel.y,
                        panel.z,
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
        const sprite = document.createElement('canvas');
        sprite.width = sprite.height = 32;
        const paint = sprite.getContext('2d')!;
        const falloff = paint.createRadialGradient(16, 16, 0, 16, 16, 16);
        falloff.addColorStop(0, 'rgba(255,255,255,1)');
        falloff.addColorStop(0.45, 'rgba(255,255,255,0.8)');
        falloff.addColorStop(1, 'rgba(255,255,255,0)');
        paint.fillStyle = falloff;
        paint.fillRect(0, 0, 32, 32);
        this.emblem = new THREE.Points(
            geo,
            new THREE.PointsMaterial({
                map: new THREE.CanvasTexture(sprite),
                color: new THREE.Color(1.9, 0.05, 0.12),
                size: 0.019,
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
        await new Promise<void>((done) => {
            this.finale.push(
                gsap.to(progress, {
                    p: 1,
                    duration: 2.6,
                    delay: 0.5,
                    ease: 'expo.inOut',
                    onUpdate: apply,
                    onComplete: done,
                    onInterrupt: done,
                }),
            );
        });
    }

    /** "Desde cero": sin piezas, sin el emblema ni el giro del final, y la cámara vuelve a entrar como al cargar. */
    reset(): void {
        // Borrar de un Map mientras se recorre es seguro: las entradas borradas ya no se visitan.
        for (const slot of this.parts.keys()) this.setPart(slot, null);
        this.finale.splice(0).forEach((tween) => tween.kill());
        this.rainbow = 0;
        if (this.emblem) {
            this.scene.remove(this.emblem);
            this.emblem.geometry.dispose();
            const material = this.emblem.material as THREE.PointsMaterial;
            material.map?.dispose();
            material.dispose();
            this.emblem = null;
        }
        this.celebrating = false;
        this.focused = null;
        this.hovered = null;
        if (this.outline) this.outline.selectedObjects = [];
        this.controls.minAzimuthAngle = -1.25;
        this.controls.maxAzimuthAngle = 1.35;
        gsap.killTweensOf([this.camera.position, this.controls.target]);
        if (this.opts.reduced) {
            this.camera.position.copy(OVERVIEW.pos);
            this.controls.target.copy(OVERVIEW.target);
            this.controls.enabled = true;
            return;
        }
        this.intro();
    }

    /* ---------------- Puntero: resaltar y elegir piezas en la escena ---------------- */

    private onPointerMove = (event: PointerEvent): void => {
        if (event.pointerType !== 'mouse' || this.celebrating) return;
        const r = this.canvas.getBoundingClientRect();
        this.pointer.set(
            ((event.clientX - r.left) / r.width) * 2 - 1,
            -((event.clientY - r.top) / r.height) * 2 + 1,
        );
        this.raycaster.setFromCamera(this.pointer, this.camera);
        const targets = [...this.parts.entries()]
            .filter(([slot]) => slot !== 'case')
            .map(([, g]) => g);
        const hit = this.raycaster.intersectObjects(targets, true)[0];
        let slot: Slot | null = null;
        for (
            let o: THREE.Object3D | null = hit?.object ?? null;
            o;
            o = o.parent
        ) {
            if (o.userData.slot) {
                slot = o.userData.slot as Slot;
                break;
            }
        }
        if (slot === this.hovered) return;
        this.hovered = slot;
        this.canvas.style.cursor = slot ? 'pointer' : '';
        if (this.outline)
            this.outline.selectedObjects = slot ? [this.parts.get(slot)!] : [];
    };

    private onPointerDown = (event: PointerEvent): void => {
        this.downAt = { x: event.clientX, y: event.clientY };
    };

    private onPointerUp = (event: PointerEvent): void => {
        const moved = Math.hypot(
            event.clientX - this.downAt.x,
            event.clientY - this.downAt.y,
        );
        if (moved < 5 && this.hovered) this.opts.onPick?.(this.hovered);
    };

    private onPointerLeave = (): void => {
        this.hovered = null;
        this.canvas.style.cursor = '';
        if (this.outline) this.outline.selectedObjects = [];
    };

    /* ---------------- Utilidades de geometría y materiales ---------------- */

    private ring(
        radius: number,
        tube: number,
        radial: number,
        tubular: number,
    ): THREE.TorusGeometry {
        return new THREE.TorusGeometry(
            radius,
            tube,
            this.opts.lowPower ? 6 : radial,
            this.opts.lowPower ? 32 : tubular,
        );
    }

    private rbox(
        w: number,
        h: number,
        d: number,
        r: number,
        mat: THREE.Material,
    ): THREE.Mesh {
        const radius = Math.min(r, w / 2 - 0.001, h / 2 - 0.001, d / 2 - 0.001);
        if (mat.userData.led) {
            const axis = mat.userData.led.axis.value as THREE.Vector3;
            axis.set(
                w >= h && w >= d ? 1 / w : 0,
                h > w && h >= d ? 1 / h : 0,
                d > w && d > h ? 1 / d : 0,
            );
        }
        return new THREE.Mesh(
            new RoundedBoxGeometry(
                w,
                h,
                d,
                this.opts.lowPower ? 1 : 2,
                Math.max(0.001, radius),
            ),
            mat,
        );
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

    private glow(intensity: number, ring = false): THREE.MeshStandardMaterial {
        return ledShader(this.ledTime, this.ledRainbow, intensity, ring);
    }

    private registerGlows(group: THREE.Group): void {
        const leds: Glow[] = [];
        group.traverse((object) => {
            const mat = (object as THREE.Mesh).material as
                | THREE.MeshStandardMaterial
                | undefined;
            if (
                !mat?.userData.led ||
                this.glows.some((glow) => glow.mat === mat)
            )
                return;
            const glow = { mat, power: mat.userData.led.power };
            this.glows.push(glow);
            leds.push(glow);
        });
        group.userData.leds = leds;
    }

    private forget(root: THREE.Object3D): void {
        const inside = new Set<THREE.Object3D>();
        const mats = new Set<THREE.Material>();
        root.traverse((o) => {
            inside.add(o);
            const mat = (o as THREE.Mesh).material;
            if (mat && !Array.isArray(mat)) mats.add(mat);
        });
        this.fans = this.fans.filter((f) => !inside.has(f));
        this.glows = this.glows.filter((g) => {
            if (!mats.has(g.mat)) return true;
            gsap.killTweensOf(g.power);
            return false;
        });
        this.pcb = this.pcb.filter((p) => !mats.has(p));
        if (this.outline) this.outline.selectedObjects = [];
        this.hovered = null;
    }

    private pcbMaterial(): THREE.MeshStandardMaterial {
        const size = this.opts.lowPower ? 512 : 1024;
        const c = document.createElement('canvas');
        c.width = c.height = size;
        const ctx = c.getContext('2d')!;
        ctx.fillStyle = '#000';
        ctx.fillRect(0, 0, size, size);
        const u = size / 64;
        // Pistas ortogonales con quiebres a 45° y pads, como una tarjeta real.
        for (let i = 0; i < 90; i++) {
            let x = Math.round((Math.random() * size) / u) * u;
            let y = Math.round((Math.random() * size) / u) * u;
            ctx.strokeStyle = `rgba(90,108,102,${(0.25 + Math.random() * 0.3).toFixed(2)})`;
            ctx.lineWidth = Math.random() < 0.2 ? u * 0.5 : u * 0.18;
            ctx.beginPath();
            ctx.moveTo(x, y);
            for (let k = 0; k < 4; k++) {
                const len = (2 + Math.floor(Math.random() * 10)) * u;
                const dir = Math.floor(Math.random() * 4);
                if (dir === 0) x += len;
                else if (dir === 1) y += len;
                else if (dir === 2) {
                    x += len * 0.7;
                    y += len * 0.7;
                } else {
                    x -= len * 0.7;
                    y += len * 0.7;
                }
                ctx.lineTo(x, y);
            }
            ctx.stroke();
            ctx.fillStyle = '#a09c82';
            ctx.beginPath();
            ctx.arc(x, y, u * 0.35, 0, Math.PI * 2);
            ctx.fill();
        }
        ctx.font = `500 ${size / 48}px sans-serif`;
        ctx.fillStyle = '#b8beb4';
        for (let i = 0; i < 16; i++)
            ctx.fillText(
                `${i % 2 ? 'DIMM' : 'SYS'}_${String(i).padStart(2, '0')}`,
                size * (0.1 + (i % 3) * 0.3),
                size * (0.1 + Math.floor(i / 3) * 0.15),
            );
        const tex = new THREE.CanvasTexture(c);
        tex.colorSpace = THREE.SRGBColorSpace;
        tex.anisotropy = 4;
        const mat = new THREE.MeshStandardMaterial({
            color: 0xa5b5ac,
            metalness: 0.35,
            roughness: 0.5,
            emissive: 0xffffff,
            map: tex,
            emissiveIntensity: 0,
        });
        this.pcb.push(mat);
        return mat;
    }

    /** Placa con texto en Chakra Petch (se redibuja cuando la fuente termina de cargar). */
    private label(text: string, w: number, h: number): THREE.Mesh {
        const c = document.createElement('canvas');
        c.width = w;
        c.height = h;
        const ctx = c.getContext('2d')!;
        const tex = new THREE.CanvasTexture(c);
        tex.colorSpace = THREE.SRGBColorSpace;
        const draw = () => {
            ctx.clearRect(0, 0, w, h);
            ctx.font = `700 ${String(Math.round(h * 0.62))}px "Chakra Petch", sans-serif`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = '#e8ecf2';
            ctx.fillText(text, w / 2, h / 2);
            tex.needsUpdate = true;
        };
        draw();
        void document.fonts.ready.then(draw);
        const aspect = w / h;
        const height = aspect > 6 ? 0.1 : 0.14;
        return new THREE.Mesh(
            new THREE.PlaneGeometry(height * aspect, height),
            new THREE.MeshBasicMaterial({
                map: tex,
                transparent: true,
                depthWrite: false,
                toneMapped: false,
            }),
        );
    }

    /** Panel con perforación hexagonal (alphaMap): deja ver lo que hay detrás. `axis` = normal del panel. */
    private perforated(
        a: number,
        b: number,
        base: THREE.Material,
        axis: 'x' | 'y',
    ): THREE.Mesh {
        const c = document.createElement('canvas');
        c.width = c.height = 256;
        const ctx = c.getContext('2d')!;
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, 256, 256);
        ctx.fillStyle = '#000';
        for (let row = 0; row < 22; row++) {
            for (let col = 0; col < 22; col++) {
                ctx.beginPath();
                ctx.arc(
                    col * 12 + (row % 2) * 6 + 4,
                    row * 12 + 4,
                    3.6,
                    0,
                    Math.PI * 2,
                );
                ctx.fill();
            }
        }
        const tex = new THREE.CanvasTexture(c);
        tex.wrapS = tex.wrapT = THREE.RepeatWrapping;
        tex.repeat.set(a * 2.2, b * 2.2);
        const mat = (base as THREE.MeshStandardMaterial).clone();
        mat.alphaMap = tex;
        mat.alphaTest = 0.5;
        mat.side = THREE.DoubleSide;
        const mesh = new THREE.Mesh(new THREE.PlaneGeometry(a, b), mat);
        if (axis === 'x') mesh.rotation.y = Math.PI / 2;
        else mesh.rotation.x = -Math.PI / 2;
        return mesh;
    }

    /** Carcasa facetada de la GPU (contorno con cortes en ángulo, extruido con bisel). */
    private shroud(length: number): THREE.Mesh {
        const L = length / 2;
        const H = 0.57;
        const shape = new THREE.Shape();
        shape.moveTo(-L, -H + 0.12);
        shape.lineTo(-L + 0.12, -H);
        shape.lineTo(L - 0.35, -H);
        shape.lineTo(L, -H + 0.25);
        shape.lineTo(L, H - 0.1);
        shape.lineTo(L - 0.1, H);
        shape.lineTo(-L + 0.4, H);
        shape.lineTo(-L, H - 0.3);
        shape.closePath();
        const geo = new THREE.ExtrudeGeometry(shape, {
            depth: 0.05,
            bevelEnabled: true,
            bevelThickness: 0.015,
            bevelSize: 0.015,
            bevelSegments: 2,
        });
        geo.translate(0, 0, -0.025);
        return new THREE.Mesh(geo, this.m.anod);
    }

    /** Disipador de VRM con aletas. */
    private vrm(w: number, h: number, d: number): THREE.Group {
        const g = new THREE.Group();
        g.add(this.rbox(w, h, d * 0.45, 0.02, this.m.graphite));
        const vertical = h > w;
        const n = Math.round((vertical ? h : w) / 0.06);
        const fins = new THREE.InstancedMesh(
            new THREE.BoxGeometry(
                vertical ? w * 0.95 : 0.025,
                vertical ? 0.025 : h * 0.95,
                d,
            ),
            this.m.graphite,
            n,
        );
        const mat = new THREE.Matrix4();
        for (let i = 0; i < n; i++) {
            const o = -((vertical ? h : w) / 2) + 0.03 + i * 0.06;
            fins.setMatrixAt(
                i,
                mat.makeTranslation(
                    vertical ? 0 : o,
                    vertical ? o : 0,
                    d * 0.25,
                ),
            );
        }
        g.add(fins);
        return g;
    }

    private cable(
        points: THREE.Vector3[],
        radius: number,
        strands = 4,
    ): THREE.Group {
        const group = new THREE.Group();
        const route = new THREE.CatmullRomCurve3(points);
        const mat = new THREE.MeshStandardMaterial({
            color: 0x333037,
            roughness: 0.82,
            metalness: 0.05,
            normalMap: (this.m.alu as THREE.MeshStandardMaterial).normalMap,
            normalScale: new THREE.Vector2(0.45, 0.45),
        });
        for (let i = 0; i < strands; i++) {
            const offset = (i - (strands - 1) / 2) * radius * 0.68;
            const curve = new THREE.CatmullRomCurve3(
                points.map((point) =>
                    point.clone().add(new THREE.Vector3(offset, 0, 0)),
                ),
            );
            group.add(
                new THREE.Mesh(
                    new THREE.TubeGeometry(
                        curve,
                        this.opts.lowPower ? 20 : 40,
                        radius * 0.31,
                        6,
                    ),
                    mat,
                ),
            );
        }
        for (const t of [0.16, 0.4, 0.66]) {
            const comb = this.rbox(
                radius * strands * 0.72,
                radius * 0.85,
                radius * 1.2,
                0.008,
                this.m.graphite,
            );
            comb.position.copy(route.getPoint(t));
            comb.quaternion.setFromUnitVectors(
                new THREE.Vector3(0, 0, 1),
                route.getTangent(t),
            );
            group.add(comb);
        }
        const connector = this.rbox(
            radius * strands * 0.78,
            radius * 1.5,
            radius * 1.8,
            0.012,
            this.m.plastic,
        );
        connector.position.copy(points[0]);
        group.add(connector);
        return group;
    }

    /** Ventilador: marco con difusor RGB, 9 aspas curvas y hub con el emblema. `axis` = hacia dónde mira. */
    private fan(
        radius: number,
        rgb: boolean,
        axis: 'x' | 'z' = 'z',
    ): THREE.Group {
        const g = new THREE.Group();
        const m = this.m;
        const frameW = radius * 2.2;
        const bars: [number, number, number, number][] = [
            [0, radius * 1.05, frameW, radius * 0.1],
            [0, -radius * 1.05, frameW, radius * 0.1],
            [radius * 1.05, 0, radius * 0.1, frameW],
            [-radius * 1.05, 0, radius * 0.1, frameW],
        ];
        for (const [x, y, w, h] of bars)
            g.add(this.at(this.rbox(w, h, 0.22, 0.02, m.plastic), x, y, 0));
        g.add(
            new THREE.Mesh(
                this.ring(radius * 1.0, radius * 0.06, 12, 64),
                m.plastic,
            ),
        );
        if (rgb) {
            g.add(
                this.at(
                    new THREE.Mesh(
                        this.ring(radius * 0.96, radius * 0.03, 10, 64),
                        this.glow(3.2, true),
                    ),
                    0,
                    0,
                    0.1,
                ),
            );
            g.add(
                this.at(
                    new THREE.Mesh(
                        this.ring(radius * 0.96, radius * 0.03, 10, 64),
                        this.glow(2.6, true),
                    ),
                    0,
                    0,
                    -0.1,
                ),
            );
        }
        const blades = new THREE.Group();
        blades.add(
            new THREE.Mesh(
                new THREE.CylinderGeometry(
                    radius * 0.3,
                    radius * 0.3,
                    0.12,
                    32,
                ).rotateX(Math.PI / 2),
                m.anod,
            ),
        );
        const cap = new THREE.Mesh(
            new THREE.CircleGeometry(radius * 0.26, 32),
            new THREE.MeshStandardMaterial({
                map: this.hubMap,
                transparent: true,
                emissive: RED,
                emissiveMap: this.hubMap,
                emissiveIntensity: 0.6,
                metalness: 0.3,
                roughness: 0.4,
            }),
        );
        blades.add(this.at(cap, 0, 0, 0.061));
        const diffuser = this.glow(0.55, true);
        diffuser.color.set(0x414147);
        diffuser.transparent = true;
        diffuser.opacity = 0.6;
        diffuser.depthWrite = false;
        diffuser.side = THREE.DoubleSide;
        const geo = this.blade(radius);
        const rotor = new THREE.InstancedMesh(geo, diffuser, 9);
        const pivot = new THREE.Object3D();
        const pitch = new THREE.Matrix4().makeRotationX(0.45);
        for (let i = 0; i < 9; i++) {
            pivot.rotation.z = (i / 9) * Math.PI * 2;
            pivot.updateMatrix();
            rotor.setMatrixAt(i, pivot.matrix.clone().multiply(pitch));
        }
        blades.add(rotor);
        g.add(blades);
        this.fans.push(blades);
        if (axis === 'x') g.rotation.y = Math.PI / 2;
        return g;
    }

    /** Aspa curva (perfil en hoz) extruida con bisel; se comparte entre ventiladores del mismo tamaño. */
    private blade(radius: number): THREE.BufferGeometry {
        const key = Math.round(radius * 100);
        const cached = this.bladeGeo.get(key);
        if (cached) return cached;
        const r0 = radius * 0.28;
        const r1 = radius * 0.92;
        const s = new THREE.Shape();
        s.moveTo(r0, -radius * 0.1);
        s.quadraticCurveTo(radius * 0.62, -radius * 0.28, r1, -radius * 0.12);
        s.quadraticCurveTo(
            r1 + radius * 0.04,
            radius * 0.06,
            r1 - radius * 0.06,
            radius * 0.2,
        );
        s.quadraticCurveTo(radius * 0.55, radius * 0.12, r0, radius * 0.12);
        s.closePath();
        const geo = new THREE.ExtrudeGeometry(s, {
            depth: 0.012,
            bevelEnabled: true,
            bevelThickness: 0.004,
            bevelSize: 0.004,
            bevelSegments: 1,
            curveSegments: this.opts.lowPower ? 5 : 10,
        });
        this.bladeGeo.set(key, geo);
        return geo;
    }

    private dispose(root: THREE.Object3D): void {
        const blades = new Set(this.bladeGeo.values());
        root.traverse((o) => {
            const mesh = o as THREE.Mesh;
            if ((mesh as THREE.InstancedMesh).isInstancedMesh)
                (mesh as THREE.InstancedMesh).dispose();
            if (mesh.geometry && !blades.has(mesh.geometry))
                mesh.geometry.dispose();
            const mats = Array.isArray(mesh.material)
                ? mesh.material
                : mesh.material
                  ? [mesh.material]
                  : [];
            for (const mat of mats) {
                if (this.shared.has(mat)) continue;
                const std = mat as THREE.MeshStandardMaterial;
                if (std.map && std.map !== this.hubMap) std.map.dispose();
                if (std.emissiveMap && std.emissiveMap !== this.hubMap)
                    std.emissiveMap.dispose();
                std.alphaMap?.dispose();
                if (std.normalMap && !this.textures.has(std.normalMap))
                    std.normalMap.dispose();
                mat.dispose();
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
