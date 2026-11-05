/*
 * El emblema ATS hecho de partículas (el mismo de la página en construcción):
 * se arma al cargar y, al bajar, se separa como una vista explotada.
 */
import * as THREE from "three";
import { EffectComposer } from "three/addons/postprocessing/EffectComposer.js";
import { RenderPass } from "three/addons/postprocessing/RenderPass.js";
import { UnrealBloomPass } from "three/addons/postprocessing/UnrealBloomPass.js";
import { OutputPass } from "three/addons/postprocessing/OutputPass.js";
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";

export async function mountEmblem({ canvas, src, scatterTrigger, reduced, mobile }) {
    const lowPower = mobile || navigator.connection?.saveData || navigator.hardwareConcurrency <= 4 || (navigator.deviceMemory && navigator.deviceMemory <= 4);
    let renderer;
    try {
        renderer = new THREE.WebGLRenderer({ canvas, antialias: false, alpha: true, powerPreference: lowPower ? "low-power" : "high-performance" });
    } catch {
        document.documentElement.classList.add("no-webgl");
        return;
    }

    renderer.setPixelRatio(Math.min(window.devicePixelRatio, lowPower ? 1.25 : 2));
    renderer.setSize(window.innerWidth, window.innerHeight);
    renderer.setClearColor(0x000000, 0);
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 1.1;
    renderer.outputColorSpace = THREE.SRGBColorSpace;

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(35, window.innerWidth / window.innerHeight, 0.1, 100);
    camera.position.set(0, 0, 9);

    const points = await emblemPoints(src, lowPower ? (mobile ? 2800 : 4200) : 11000, mobile);
    canvas.dataset.particleCount = String(points.length / 3);
    canvas.dataset.lowPower = String(Boolean(lowPower));
    const count = points.length / 3;
    const start = new Float32Array(count * 3);
    const scatter = new Float32Array(count * 3);
    const delay = new Float32Array(count);
    const seed = new Float32Array(count);

    for (let i = 0; i < count; i++) {
        const x = points[i * 3], y = points[i * 3 + 1], z = points[i * 3 + 2];
        /* Llegan desde una esfera amplia, como piezas que vuelan a su lugar. */
        const u = Math.random() * 2 - 1, a = Math.random() * Math.PI * 2, r = 7 + Math.random() * 6;
        const s = Math.sqrt(1 - u * u);
        start[i * 3] = r * s * Math.cos(a);
        start[i * 3 + 1] = r * s * Math.sin(a);
        start[i * 3 + 2] = r * u - 2;
        /* La dispersión cubre la ventana y permanece detrás del catálogo. */
        scatter[i * 3] = (Math.random() - 0.5) * 5.8 * camera.aspect;
        scatter[i * 3 + 1] = (Math.random() - 0.5) * 5.8;
        scatter[i * 3 + 2] = (Math.random() - 0.5) * 4;
        delay[i] = Math.random() * 0.55 + (y + 2.2) / 4.4 * 0.25;
        seed[i] = Math.random();
    }

    const geometry = new THREE.BufferGeometry();
    geometry.setAttribute("position", new THREE.BufferAttribute(points, 3));
    geometry.setAttribute("aStart", new THREE.BufferAttribute(start, 3));
    geometry.setAttribute("aScatter", new THREE.BufferAttribute(scatter, 3));
    geometry.setAttribute("aDelay", new THREE.BufferAttribute(delay, 1));
    geometry.setAttribute("aSeed", new THREE.BufferAttribute(seed, 1));

    const uniforms = {
        uTime: { value: 0 },
        uAssemble: { value: reduced ? 1 : 0 },
        uScatter: { value: 0 },
        uSize: { value: mobile ? 22 : 26 },
        uPixelRatio: { value: renderer.getPixelRatio() },
        uColor: { value: new THREE.Color("#E30613") },
        uHot: { value: new THREE.Color("#FFE6E0") },
        uRainbow: { value: 0 },
    };

    const material = new THREE.ShaderMaterial({
        uniforms,
        transparent: true,
        depthWrite: false,
        blending: THREE.AdditiveBlending,
        vertexShader: /* glsl */ `
            precision mediump float;
            uniform float uTime, uAssemble, uScatter, uSize, uPixelRatio;
            attribute vec3 aStart;
            attribute vec3 aScatter;
            attribute float aDelay;
            attribute float aSeed;
            varying float vHeat;
            varying float vAlpha;
            varying float vHue;

            float easeOut(float x) { return 1.0 - pow(1.0 - x, 3.0); }

            void main() {
                float t = clamp((uAssemble - aDelay * 0.55) / 0.45, 0.0, 1.0);
                float e = easeOut(t);
                vec3 pos = mix(aStart, position, e);
                pos += 0.018 * e * vec3(sin(uTime * 1.3 + aSeed * 20.0), cos(uTime * 1.1 + aSeed * 17.0), sin(uTime * 0.9 + aSeed * 13.0));
                pos = mix(pos, aScatter, smoothstep(0.0, 1.0, uScatter));

                /* Modo RGB: el color recorre el emblema en diagonal. */
                vHue = fract((position.x + position.y) * 0.09 - uTime * 0.12);

                vec4 mv = modelViewMatrix * vec4(pos, 1.0);
                gl_Position = projectionMatrix * mv;
                float twinkle = 0.8 + 0.2 * sin(uTime * 3.0 + aSeed * 40.0);
                gl_PointSize = uSize * uPixelRatio * (0.55 + aSeed * 0.75) * twinkle / -mv.z;

                vHeat = step(0.94, aSeed) * 0.9 + (1.0 - e) * 0.6;
                vAlpha = (0.25 + 0.75 * e) * (1.0 - uScatter * 0.35);
            }
        `,
        fragmentShader: /* glsl */ `
            precision mediump float;
            uniform vec3 uColor;
            uniform vec3 uHot;
            uniform float uRainbow;
            varying float vHeat;
            varying float vAlpha;
            varying float vHue;

            vec3 hue(float h) { return clamp(abs(mod(h * 6.0 + vec3(0.0, 4.0, 2.0), 6.0) - 3.0) - 1.0, 0.0, 1.0); }

            void main() {
                float d = length(gl_PointCoord - 0.5);
                float a = smoothstep(0.5, 0.05, d);
                vec3 base = mix(uColor, hue(vHue), uRainbow);
                vec3 color = mix(base, uHot, clamp(vHeat, 0.0, 1.0));
                gl_FragColor = vec4(color, a * vAlpha);
            }
        `,
    });

    const rig = new THREE.Group();
    rig.add(new THREE.Points(geometry, material));
    scene.add(rig);

    /* Brillo neón. */
    const composer = new EffectComposer(renderer);
    composer.addPass(new RenderPass(scene, camera));
    composer.addPass(new UnrealBloomPass(new THREE.Vector2(window.innerWidth, window.innerHeight), mobile ? 0.6 : 0.85, 0.55, 0.1));
    composer.addPass(new OutputPass());

    /* A la derecha en escritorio; arriba y más chico en celular. */
    const origin = new THREE.Vector3();
    let baseScale = 1;
    const layout = () => {
        const wide = window.innerWidth > 860;
        origin.set(wide ? 2.3 * Math.min(1, camera.aspect / 1.6) : 0, wide ? 0.1 : 1.36, 0);
        baseScale = wide ? 1 : 0.46;
        rig.position.copy(origin);
        rig.scale.setScalar(baseScale);
    };
    layout();

    window.addEventListener("resize", () => {
        camera.aspect = window.innerWidth / window.innerHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(window.innerWidth, window.innerHeight);
        composer.setSize(window.innerWidth, window.innerHeight);
        uniforms.uPixelRatio.value = renderer.getPixelRatio();
        layout();
    });

    if (!reduced) gsap.to(uniforms.uAssemble, { value: 1, duration: 3.2, ease: "power2.inOut", delay: 0.2 });

    /* La posición depende del scroll; no se fija ni se desplaza el contenido. */
    const state = { scatter: 0, turn: 0, pulse: 0 };
    if (scatterTrigger) {
        gsap.registerPlugin(ScrollTrigger);
        ScrollTrigger.create({
            trigger: scatterTrigger,
            start: "top bottom",
            end: "top 15%",
            scrub: true,
            onUpdate: (self) => {
                state.scatter = self.progress;
                state.turn = self.progress;
                canvas.style.opacity = String(1 - self.progress * 0.25);
            },
        });
    }

    const pointer = { x: 0, y: 0 };
    window.addEventListener("pointermove", (event) => {
        pointer.x = event.clientX / window.innerWidth - 0.5;
        pointer.y = event.clientY / window.innerHeight - 0.5;
    });

    const clock = new THREE.Clock();
    const render = () => {
        const delta = Math.min(clock.getDelta(), 0.05);
        const k = 1 - Math.pow(1 - 0.06, delta * 60);
        if (!reduced) uniforms.uTime.value += delta;
        uniforms.uScatter.value += (state.scatter + state.pulse - uniforms.uScatter.value) * (reduced ? 1 : k);
        rig.position.copy(origin).multiplyScalar(1 - state.scatter);
        rig.scale.setScalar(baseScale + (1 - baseScale) * state.scatter);
        const targetY = pointer.x * 0.5 + state.turn * 0.9 + (reduced ? 0 : Math.sin(uniforms.uTime.value * 0.25) * 0.12);
        rig.rotation.y += (targetY - rig.rotation.y) * k;
        rig.rotation.x += (pointer.y * 0.3 - rig.rotation.x) * k;
        composer.render();
    };
    const visibility = () => {
        renderer.setAnimationLoop(null);
        canvas.dataset.animationState = document.hidden ? 'paused' : reduced ? 'static' : 'playing';
        clock.getDelta();
        if (!document.hidden) {
            if (reduced) render();
            else renderer.setAnimationLoop(render);
        }
    };
    document.addEventListener('visibilitychange', visibility);
    if (reduced) {
        window.addEventListener('scroll', () => { if (!document.hidden) render(); }, { passive: true });
        window.addEventListener('resize', render);
    }
    visibility();

    /* Paletas de los modos del hero. Al cambiar, el emblema se abre un instante y se vuelve a armar con el color nuevo. */
    const palettes = {
        ats: { color: "#E30613", hot: "#FFE6E0", rainbow: 0 },
        rgb: { color: "#E30613", hot: "#FFFFFF", rainbow: 1 },
        frost: { color: "#2FB4FF", hot: "#E8F8FF", rainbow: 0 },
        amber: { color: "#FF9A1F", hot: "#FFF2DC", rainbow: 0 },
    };
    return {
        setPalette(name) {
            const p = palettes[name] ?? palettes.ats;
            const color = new THREE.Color(p.color);
            const hot = new THREE.Color(p.hot);
            const duration = reduced ? 0 : 0.9;
            gsap.to(uniforms.uColor.value, { r: color.r, g: color.g, b: color.b, duration });
            gsap.to(uniforms.uHot.value, { r: hot.r, g: hot.g, b: hot.b, duration });
            gsap.to(uniforms.uRainbow, { value: p.rainbow, duration });
            if (reduced) requestAnimationFrame(render);
            if (!reduced) gsap.fromTo(state, { pulse: 0.32 }, { pulse: 0, duration: 1.1, ease: "power2.out" });
        },
    };
}

/* Cada píxel rojo del emblema es una partícula (con algo de grosor). */
async function emblemPoints(src, max, mobile) {
    const image = new Image();
    image.src = src;
    await image.decode();

    const height = 4.4;
    const sampleH = mobile ? 150 : 210;
    const sampleW = Math.round(sampleH * (image.width / image.height));
    const canvas = document.createElement("canvas");
    canvas.width = sampleW;
    canvas.height = sampleH;
    const context = canvas.getContext("2d", { willReadFrequently: true });
    context.drawImage(image, 0, 0, sampleW, sampleH);
    const data = context.getImageData(0, 0, sampleW, sampleH).data;

    const filled = [];
    for (let y = 0; y < sampleH; y++) {
        for (let x = 0; x < sampleW; x++) {
            if (data[(y * sampleW + x) * 4 + 3] > 140) filled.push(x, y);
        }
    }

    const total = filled.length / 2;
    const step = Math.max(1, total / max);
    const scale = height / sampleH;
    const out = [];
    for (let i = 0; i < total; i += step) {
        const index = Math.floor(i) * 2;
        out.push(
            (filled[index] + Math.random() - sampleW / 2) * scale,
            -(filled[index + 1] + Math.random() - sampleH / 2) * scale,
            (Math.random() - 0.5) * 0.35,
        );
    }

    return new Float32Array(out);
}
