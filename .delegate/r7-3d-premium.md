# Encargo R7: escena 3D del configurador a nivel premium

## Objetivo
El configurador funciona y la interfaz gusta, pero **la escena 3D se ve "de primaria"**. Debe verse como un render de
producto de marca gamer (nivel lanzamiento de tarjeta de video o gabinete premium), sin perder la fluidez.

## Diagnóstico (capturas del 3 nov)
- Todo son cajas sin bisel y materiales planos: el gabinete parece una caja de cartón con líneas.
- Los aros RGB se ven salmón porque el bloom y el tone mapping lavan el rojo; no hay sombras ni oclusión ambiental.
- Sin reflejos, sin profundidad ni atmósfera: el piso es una rejilla y un degradado.
- Las piezas no tienen detalle reconocible (ventiladores de 7 cajas, GPU como bloque, sin cables ni heatpipes).

## Lee primero
- `AGENTS.md`, `resources/js/builder/scene.ts` (API pública: `setPart`, `focus`, `celebrate`, `resize`, `destroy`;
  **no cambies su firma**, `BuildLab.vue` la usa tal cual), `ANCHORS` y `OVERVIEW`.
- `/mnt/c/Users/tit/.claude/skills/awwwards-3d/SKILL.md` y `references/` (ANTI_PATTERNS, POST_PROCESSING,
  PROCEDURAL_GEOMETRY, SHADERS), y los `SKILL.md` de `threejs-materials`, `threejs-lighting`, `threejs-postprocessing`,
  `threejs-geometry`, `threejs-textures`, `threejs-loaders` (misma carpeta).
- `../.claude/skills/playwright-cli/SKILL.md` para verificar.

## Cambios
1. **Pipeline de render**: `AgXToneMapping` (o ACES bien calibrado), sombras suaves de la luz principal, HDRI de estudio
   CC0 de Poly Haven (1k, guardado en `public/builder/env/`, cargado de forma diferida y sin mostrarse de fondo).
   Post: RenderPass → GTAO → bloom **selectivo** (solo la capa RGB) → SMAA → viñeta y grano sutil → OutputPass.
   Profundidad de campo (Bokeh) solo al enfocar una pieza, solo en escritorio.
2. **Piso y atmósfera**: piso oscuro brillante con reflejo difuso y sombra de contacto (técnica de
   `webgl_shadow_contact`), un haz de luz volumétrico tenue y polvo flotando (≈300 partículas).
3. **Geometría con detalle** (`RoundedBoxGeometry`, `ExtrudeGeometry` con bisel, `InstancedMesh` para aletas y
   perforaciones, `TubeGeometry` para heatpipes y cables):
   - Gabinete: marco de aluminio cepillado con chaflanes, frente perforado, vidrio templado con `MeshPhysicalMaterial`
     (transmission, thickness, ior 1.5) y reflejo de borde, cubierta de fuente con rejilla y placa "TECH COMPUTER".
   - Ventiladores: 9 aspas curvas reales, marco con difusor RGB, hub con logo.
   - GPU: carcasa facetada, backplate, 3 ventiladores, franja RGB y placa lateral; largo según `length_mm`.
   - Disipador de torre con heatpipes de cobre y aletas instanciadas; AIO con bomba, mangueras y radiador.
   - RAM con disipador y difusor RGB; tarjeta madre con disipadores de VRM, ranuras PCIe blindadas, M.2 con disipador y
     pistas emisivas a 1024 px; cables de 24 y 8 pines forrados.
4. **RGB unificado**: un shader/uniforme de tiempo compartido: rojo ATS respirando en reposo, ola arcoíris en
   `celebrate()`, pulso que recorre las pistas al equipar una pieza.
5. **Iluminación de estudio**: luz principal `RectAreaLight`, contraluz rojo, relleno frío; luces internas que toman el
   color del RGB.
6. **Interacción**: al pasar el puntero sobre una pieza en la escena se resalta (contorno o fresnel) y muestra su nombre;
   clic = seleccionar ese slot (emitir un evento que `BuildLab.vue` escuche). Animación de equipar: baja, asienta con
   rebote corto, escaneo, chispas y pulso RGB.
7. **Modelos reales opcionales**: si existe `public/builder/models/<slot>.glb`, cargarlo (GLTFLoader + Draco con
   decodificadores locales) en lugar del procedural, escalado al `ANCHORS[slot].size`. Si no existe, procedural.
8. **Rendimiento**: escritorio 60 fps en GPU media; celular con preset bajo (sin GTAO, sin transmission, sin DOF, menos
   partículas, DPR ≤ 1.5); ≤ 150 draw calls; liberar todo en `destroy()`; respetar `reduced` y `lowPower`.

## Terminado cuando
- Capturas con `playwright-cli` en `../.delegate/shots/r7-*.png` (1440×900 y 390×844) de: vacío, build completa
  (`/arma-tu-pc?b=…`), enfoque en GPU y final; más un reporte de fps y draw calls (`renderer.info`).
- `bash ../.delegate/verify.sh all` en verde.

## No hagas
- No cambies `rules.ts`, el backend ni el layout de paneles del HUD (solo lo que toque la escena).
- No uses modelos o texturas con licencia que no sea CC0 o propia; nada de marcas reales (NVIDIA, AMD, etc.) en la escena.
