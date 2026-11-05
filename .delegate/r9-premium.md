# Encargo R9: rojo del modo ATS, 3D del configurador nivel 2 y acabado "súper premium"

## Objetivo
1. Corregir que el primer modo del hero (ATS) pierde el rojo.
2. Llevar la escena 3D de `/arma-tu-pc` a nivel render de lanzamiento de producto (que impacte de verdad).
3. Rematar el sitio para que se sienta súper premium, sobre lo que dejó R6.

## Lee primero
- `AGENTS.md`, `../CONTINUAR.md`, `../.delegate/r6-diseno.md` y el `DESIGN.md` que dejó R6 (si existe).
- Skills: `/mnt/c/Users/tit/.claude/skills/awwwards-3d/SKILL.md` + `references/` (POST_PROCESSING, SHADERS,
  PROCEDURAL_GEOMETRY, ANTI_PATTERNS, TRANSITIONS), `threejs-*` (materials, lighting, textures, postprocessing, loaders),
  `/mnt/c/Users/tit/.claude/skills/taste-skill/SKILL.md`, `../.claude/skills/playwright-cli/SKILL.md`.
- `resources/js/builder/scene.ts` (API pública `setPart`, `focus`, `celebrate`, `resize`, `destroy` y `onPick`: **no
  cambies su firma**), `resources/js/site/emblem.js`, `resources/css/modes.css`, `resources/js/site/pages/home.js`.

## 1. Bug: el modo ATS pierde el rojo
- Causa: `emblem.js` arma `palettes.ats.color` con `getComputedStyle(--red)` al montar; si la página carga con otro modo
  guardado (`localStorage tc-mode` o `?modo=`), ATS hereda ese color. Usa `#E30613` fijo para ATS.
- Revisa que nada más del sitio lea `--red` una sola vez al cargar (todo debe seguir la variable en vivo).
- Prueba: cargar `/?modo=frost`, cambiar a la pestaña ATS → emblema, botones y acentos rojos.

## 2. 3D nivel 2 (sin cambiar la API ni el layout del HUD)
- **Quitar los hologramas de los huecos** (las cajas en wireframe de `ghost()` para cada slot, incluido el del gabinete):
  el usuario no quiere ver cuadros donde van las piezas. En la escena solo existen las piezas elegidas. Como mucho, el
  slot activo del selector puede marcarse con un brillo muy tenue en su lugar (sin aristas ni cajas); si no se ve limpio,
  nada.
- **Ambiente**: HDRI de estudio CC0 de Poly Haven (1k, en `public/builder/env/`, carga diferida) en lugar de
  `RoomEnvironment`; reflejos de softbox en vidrio y aluminio.
- **ARGB real**: tiras, ventiladores y RAM con un shader de LEDs direccionables (degradado que corre a lo largo de cada
  tira/aro, no un color plano), sincronizado entre piezas; rojo ATS en reposo, arcoíris en `celebrate()`.
- **Materiales**: aluminio cepillado con normal map procedural, fibra de carbono en la cubierta de la fuente, aspas
  semitranslúcidas (difusor) que toman el color del RGB, PCB con serigrafía, capacitores y bobinas instanciadas.
- **Cables**: 24 pines, 8 pines CPU y 12VHPWR de la GPU, forrados y con peines.
- **Luz**: el RGB interior derrama color sobre el piso y la charola (luces de punto que siguen el color del RGB).
- **Piso**: reflejo difuminado (no espejo), con sombra de contacto suave.
- **Cámara cinematográfica**: al terminar el loader, entrada de 2–3 s (de cerca del ventilador frontal a la vista
  general); profundidad de campo sutil al enfocar una pieza (solo escritorio).
- **Llegada de piezas**: limpia (el usuario rechazó haces y destellos rojos que lavan la escena): caída con giro, rebote,
  chispas cortas y el ARGB de la pieza encendiéndose en secuencia al encajar.
- **Calidad adaptable**: mide fps los primeros segundos; si baja de 45, apaga GTAO/reflejo/DOF. Celular: preset ligero.

## 3. Acabado súper premium del sitio (después de R6)
- Revisa con el inspector de taste-skill y capturas `playwright-cli` (1440×900 y 390×844) el inicio en los 4 modos, la
  tienda, una ficha, el carrito, `/arma-tu-pc` y el login; corrige lo que marque como genérico o mal resuelto.
- Microinteracciones consistentes (hover, foco, transiciones de página suaves), tipografía y espaciado según `DESIGN.md`.

## Terminado cuando
- Capturas en `.design/r9/` dentro de `sistema/` (inicio en cada modo, configurador vacío, build completa, foco en GPU,
  final), como hiciste en R6. Al capturar no dejes el hero pausado en el código: el cambio automático debe seguir activo.
- Reporte de fps y draw calls de la escena en escritorio y en el preset ligero.
- `bash ../.delegate/verify.sh all` en verde.

## No hagas
- No cambies `rules.ts`, backend, rutas ni textos de negocio.
- Nada de haces de luz ni destellos que tiñan toda la pantalla.
- Sin modelos ni texturas que no sean CC0 o generadas por código; sin marcas reales en la escena.
