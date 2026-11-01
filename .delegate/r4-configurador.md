# Encargo R4: configurador gamer "Modo armado" en `/arma-tu-pc`

## Objetivo
Una experiencia tipo **pantalla de loadout de videojuego**, futurista y muy animada, para que un gamer joven arme su PC pieza
por pieza con productos reales, vea compatibilidad, consumo, nivel de poder y total en vivo, y al final genere su cotización
(folio, PDF y WhatsApp). Reemplaza el configurador actual de `../sitio/arma-tu-pc.html`. Es la página estrella del sitio:
debe sentirse única, no una plantilla.

## Lee primero
- `AGENTS.md`, `docs/plan.md` secciones 2, 3 y 5.
- Habilidades de diseño (léelas antes de escribir el front y síguelas):
  `/mnt/c/Users/tit/.claude/skills/frontend-design/SKILL.md`,
  `/mnt/c/Users/tit/.claude/skills/awwwards-3d/SKILL.md` y sus `references/` (ANTI_PATTERNS, PATTERNS, POST_PROCESSING,
  PROCEDURAL_GEOMETRY, SHADERS, TRANSITIONS), y `SKILL.md` de `threejs-fundamentals`, `threejs-geometry`,
  `threejs-materials`, `threejs-lighting`, `threejs-postprocessing`, `threejs-interaction`, `threejs-animation` (misma carpeta).
- `../sitio/assets/js/armado.js`, `configurador.js`, `catalogo.js` (lógica de recomendación, etiquetas `silk`),
  `emblem.js` (emblema de partículas), `../sitio/assets/css/site.css` (identidad visual).
- Lo que dejaron R1–R3: modelos `Product`/`Category`/`Quote`, `BuilderSpecs`, creación de cotizaciones y la vista pública.

## Arquitectura
- Página Blade `/arma-tu-pc` con el layout del sitio, que monta una app **Vue 3 + TS sin Inertia** (entrada Vite
  `resources/js/builder/main.ts`, se carga solo en esta página). Catálogo embebido con `@json` (productos publicados de
  categorías con `builder_slot`: id, slot, nombre, marca, miniatura, precio o null, `price_mode`, existencias, specs, tier)
  más periféricos para el paso final.
- Reglas de compatibilidad, consumo y nivel en un módulo puro `resources/js/builder/rules.ts` y **las mismas reglas en PHP**
  (`App\Support\BuildValidator`), que revalida al enviar; precios siempre de la base.
- `POST /arma-tu-pc/cotizar` (honeypot + límite por IP): crea/actualiza cliente por teléfono y cotización `source=builder`
  con `build_name` y partidas → responde folio, enlace público y PDF.
- Three.js r170, GSAP 3.12.5 y Lenis 1.1.0 desde npm (las mismas versiones del sitio). Sin modelos externos: geometría
  procedural.

## Reglas
- Compatibilidad: socket CPU = tarjeta madre; tipo de memoria RAM = tarjeta madre; formato de la tarjeta madre dentro de
  `form_factors` del gabinete; largo de GPU ≤ `max_gpu_length_mm`; socket en `sockets` del disipador y `tdp_w` del CPU ≤
  `max_tdp_w`; sin GPU solo si el CPU tiene `igpu`.
- Consumo estimado = tdp CPU + tdp GPU + 75 W; fuente recomendada = múltiplo de 50 hacia arriba de consumo × 1.4. Aviso si la
  fuente elegida es menor.
- Nivel de poder (0–100) con los `tier` de CPU y GPU (GPU pesa más). "Rinde para" 1080p / 1440p / 4K según el tier de la GPU.
  **No prometas FPS con números.**
- Mínimo para cotizar: cpu, motherboard, ram, storage, psu, case (+ gpu si el CPU no tiene `igpu`). Enfriamiento opcional.
- Piezas en modo "Cotizar" se pueden elegir; cuentan como "por confirmar" en el total.
- Arranque opcional "Base recomendada": uso (1080p, 1440p, 4K, jugar y transmitir, edición e IA, escuela) × nivel (Esencial,
  Equilibrada, Alta, Extrema), como en `armado.js` pero eligiendo productos reales compatibles (el más barato que cumpla el
  tier buscado). Luego todo se puede cambiar.

## Dirección visual (sigue esto)
- **Identidad**: negro `#070608`, rojo de marca `#E30613` para acciones y momentos clave, texto blanco hielo `#EAF2FF`,
  ámbar `#FFB020` solo para avisos. Paneles translúcidos con línea fina y **esquinas cortadas** (chaflán con `clip-path`),
  retícula y *scanlines* muy sutiles. Títulos y HUD en Chakra Petch en mayúsculas con tracking; cuerpo en Barlow; cifras con
  `tabular-nums`. Nada de degradados morados genéricos.
- **Arranque** (≤2.5 s, se puede saltar, una vez por sesión): líneas tipo BIOS como el sitio ("Inicializando modo
  armado… Detectando componentes… OK") y un barrido de luz roja que enciende el gabinete vacío.
- **Escena central (Three.js)**: gabinete abierto procedural flotando en un vacío con niebla: marco de aluminio, panel de
  vidrio con borde fresnel, tarjeta madre con pistas emisivas, y huecos holográficos en *wireframe* donde faltan piezas.
  Al equipar una pieza su malla llega volando y **encaja** (GSAP con *overshoot*), un barrido de escaneo emisivo la recorre
  y salta una ráfaga corta de partículas rojas. Ventiladores de la GPU, del disipador y del gabinete giran. Al elegir un slot,
  la cámara viaja a esa pieza. En reposo: órbita lenta y paralaje con el mouse; arrastre para girar con límites.
  Post: bloom sutil, tone mapping ACES, viñeta.
- **Riel de slots** (izquierda): 8 casillas tipo inventario con etiquetas de serigrafía (`CPU`, `MB`, `DIMM`, `PCIe x16`,
  `M.2`, `CPU FAN`, `ATX 24P`, `CASE`): vacía (borde punteado y "+" pulsando), equipada (miniatura, nombre corto, precio),
  con aviso (ámbar).
- **Selector** (derecha): "cartas de botín" con foto, nombre, 2–3 specs clave ("8 núcleos · AM5 · 65 W"), precio con IVA o
  "Cotizar", existencias e insignia de rareza por tier: 1 Común (gris), 2 Poco común (verde), 3 Raro (azul), 4 Épico (morado),
  5 Legendario (dorado), 6 Mítico (rojo de marca). Orden por precio o poder, búsqueda, "solo compatibles" activado por
  defecto; las incompatibles se ven apagadas con el motivo ("Socket AM5 ≠ LGA1851", "Tarjeta de 340 mm; el gabinete
  admite 320 mm"). Hover con inclinación 3D ligera y brillo.
- **HUD superior**: nombre editable de la build ("Mi PC"), total con conteo animado ("Total estimado con IVA"), contador
  de piezas por confirmar, medidor de energía (consumo vs fuente), indicador radial de nivel de poder, chips 1080p/1440p/4K
  encendidos según rinda, estado "Todo compatible" o "N avisos". Barra inferior: "6/8 piezas", Vaciar, Compartir build
  (URL `?b=` con ids), Generar cotización.
- **Paso "Completa tu setup"** (opcional, antes de cotizar): monitor, teclado, mouse y audífonos como cartas rápidas.
- **Final "BUILD COMPLETO"**: giro de cámara de 360°, ola RGB en ventiladores y RAM que termina en rojo de marca, y las
  partículas forman el **emblema ATS** (reutiliza la técnica de `emblem.js`). Tarjeta de resultados tipo fin de partida
  (nombre, piezas, nivel, total) con formulario: nombre, WhatsApp (10 dígitos), correo opcional, comentario. Al enviar:
  folio enorme en tipografía HUD, "Descargar PDF", "Enviar a Tech Computer por WhatsApp" (mensaje con folio y enlace) y
  "Compartir mi build".
- **Sonido**: efectos sintetizados con WebAudio (hover, equipar, completar), apagados por defecto, botón para activarlos
  (se recuerda en localStorage).
- **Celular (<1024 px)**: escena arriba (~45vh), slots como chips con scroll horizontal, selector como hoja inferior en
  2 columnas, HUD compacto fijo con total y energía. DPR máximo 1.5 y sin bloom en equipos modestos (núcleos ≤4 o prueba de FPS).
- **Accesibilidad y respaldo**: todo usable con teclado (cartas como botones con `aria-pressed`), `aria-live` para total y
  avisos; `prefers-reduced-motion` → sin vuelos ni movimientos de cámara; sin WebGL → esquema 2D en SVG con el mismo HUD.
- **Rendimiento**: 60 fps en laptop media, ≥30 en celular medio; pausa el render si la pestaña está oculta; libera geometrías
  y materiales. La build se guarda en localStorage y se recupera al volver.

## Terminado cuando
- Pruebas PHP: `BuildValidator` (cada regla), `POST /arma-tu-pc/cotizar` crea la cotización con precios de la base,
  rechaza builds incompatibles o incompletas, respeta honeypot y límite.
- `npm run build`, `types:check`, `check`, pint y `php artisan test` pasan; el chunk del configurador no se carga en otras
  páginas.
- Reporte con archivos, decisiones visuales y lo que no alcanzaste.

## No hagas
- No uses modelos 3D ni imágenes externas; no cargues librerías desde CDN.
- No cambies el portal interno salvo lo necesario para mostrar `build_name` y el origen "Configurador" en cotizaciones.
