import * as THREE from 'three';

/** Un solo reloj mantiene en fase tiras, aros, difusores y luz indirecta. */
export const ledShader = (
    clock: { value: number },
    rainbow: { value: number },
    intensity: number,
    ring = false,
) => {
    const material = new THREE.MeshStandardMaterial({
        color: 0x191215,
        emissive: 0xffffff,
        emissiveIntensity: intensity,
        roughness: 0.32,
    });
    const state = {
        time: clock,
        rainbow,
        power: { value: 1 },
        axis: { value: new THREE.Vector3(0, 1, 0) },
        ring: { value: ring ? 1 : 0 },
    };
    material.userData.led = state;
    material.onBeforeCompile = (shader) => {
        Object.assign(shader.uniforms, {
            ledTime: state.time,
            ledRainbow: state.rainbow,
            ledPower: state.power,
            ledAxis: state.axis,
            ledRing: state.ring,
        });
        shader.vertexShader = shader.vertexShader
            .replace(
                '#include <common>',
                '#include <common>\nvarying vec3 ledPosition; varying vec2 ledUv;',
            )
            .replace(
                '#include <begin_vertex>',
                '#include <begin_vertex>\nledPosition = position; ledUv = uv;',
            );
        shader.fragmentShader = shader.fragmentShader
            .replace(
                '#include <common>',
                `#include <common>
            varying vec3 ledPosition; varying vec2 ledUv;
            uniform float ledTime, ledRainbow, ledPower, ledRing; uniform vec3 ledAxis;
            vec3 ledHue(float h) { return clamp(abs(mod(h * 6.0 + vec3(0.0,4.0,2.0),6.0)-3.0)-1.0,0.0,1.0); }
        `,
            )
            .replace(
                '#include <emissivemap_fragment>',
                `#include <emissivemap_fragment>
            float along = mix(dot(ledPosition, ledAxis) + 0.5, ledUv.x, ledRing);
            float phase = fract(along - ledTime * 0.16);
            vec3 ats = mix(vec3(0.77,0.0018,0.0065), vec3(1.0,0.022,0.033), 0.5 + 0.5 * cos(phase * 6.283185));
            vec3 argb = mix(ats, ledHue(phase), ledRainbow);
            float chase = 0.65 + 0.35 * pow(0.5 + 0.5 * cos(phase * 6.283185), 3.0);
            float ignition = smoothstep(along - 0.12, along + 0.12, ledPower * 1.24 - 0.12);
            totalEmissiveRadiance *= argb * chase * ignition;
        `,
            );
    };
    material.customProgramCacheKey = () => 'ats-addressable-v1';
    return material;
};

/** Normal de aluminio y trama de carbono generadas, sin texturas de terceros. */
export function surfaceTexture(kind: 'brush' | 'carbon'): THREE.DataTexture {
    const size = 128;
    const pixels = new Uint8Array(size * size * 4);
    for (let y = 0; y < size; y++)
        for (let x = 0; x < size; x++) {
            const i = (y * size + x) * 4;
            if (kind === 'brush') {
                const groove =
                    Math.sin(y * 2.7) * 13 + Math.sin(y * 0.9 + x * 0.03) * 4;
                pixels.set([128, 128 + groove, 254, 255], i);
            } else {
                const warp = (Math.floor(x / 8) + Math.floor(y / 8)) % 2;
                const thread = Math.sin(((warp ? x : y) * Math.PI) / 2) * 5;
                const v = 22 + warp * 13 + thread;
                pixels.set([v, v + 1, v + 3, 255], i);
            }
        }
    const texture = new THREE.DataTexture(pixels, size, size);
    texture.wrapS = texture.wrapT = THREE.RepeatWrapping;
    texture.magFilter = THREE.LinearFilter;
    texture.minFilter = THREE.LinearMipmapLinearFilter;
    texture.generateMipmaps = true;
    texture.repeat.set(kind === 'carbon' ? 8 : 2, kind === 'carbon' ? 6 : 2);
    if (kind === 'carbon') texture.colorSpace = THREE.SRGBColorSpace;
    texture.needsUpdate = true;
    return texture;
}
