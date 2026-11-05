# Encargo R8: inicio con hero de "modos" que cambian el sitio, y login con la marca

## Objetivo
El hero del inicio deja de ser estático: se vuelve un slider de **modos**. Cada modo cambia el tema de la página (colores,
brillos, el emblema de partículas) para que el sitio se sienta vivo y gamer. Además, la pantalla de login del personal
con la identidad de Tech Computer.

## Lee primero
- `AGENTS.md`, `resources/views/site/pages/home.blade.php`, `resources/js/site/pages/home.js`,
  `resources/js/site/emblem.js` (emblema de partículas), `resources/css/site.css` (variables `--red`, etc.) y
  `resources/views/layouts/site.blade.php`.
- Skills: `/mnt/c/Users/tit/.claude/skills/taste-skill/SKILL.md`, `/mnt/c/Users/tit/.claude/skills/awesome-design-md/`
  (`nvidia`, `playstation`), `/mnt/c/Users/tit/.claude/skills/image-to-code/SKILL.md`,
  `/mnt/c/Users/tit/.claude/skills/awwwards-3d/SKILL.md`, `../.claude/skills/playwright-cli/SKILL.md`.

## Modos (textos finales; ajusta solo microcopia)
| # | Tema `data-theme` | Titular | Botones | Carácter |
|---|---|---|---|---|
| 1 | `ats` (actual) | Tu próxima PC gamer se arma aquí. | Arma tu PC · Cotizar por WhatsApp | Negro y rojo ATS, arranque BIOS actual. |
| 2 | `rgb` | Modo RGB: arma tu bestia. | Entrar al modo armado → `/arma-tu-pc` | Acentos que recorren el arcoíris despacio, bordes con degradado cónico animado, emblema con ola de color. |
| 3 | `frost` | Potencia para crear: edición, 3D e IA. | Ver PCs para creadores → `/arma-tu-pc?uso=create&presupuesto=alta` | Azul hielo y plata, sensación de enfriamiento líquido. |
| 4 | `amber` | ¿Se calienta o no enciende? La revisamos hoy. | Servicio técnico · WhatsApp | Ámbar de diagnóstico, líneas tipo POST. |

## Cambios
1. **Temas por variables**: en `site.css` define tokens por `[data-theme]` en `<html>` (`--accent`, `--accent-hot`,
   `--accent-deep`, `--glow`, `--grid`, etc.) y haz que botones, subrayados, malla hexagonal, brillos y bordes los usen.
   Para `rgb` registra `@property --hue` y anímalo (ciclo lento, ≥ 8 s; nada de parpadeos). Transición de color de 600 ms
   al cambiar de modo. Toda la página de inicio cambia, no solo el hero.
2. **Slider**: markup accesible (región con `aria-roledescription="carrusel"`, cada modo con su etiqueta), pestañas tipo
   slot con barra de progreso estilo BIOS, avance automático cada 8 s que se pausa con hover, foco o pestaña oculta;
   flechas del teclado y deslizar en celular. Transición: el texto sale y entra con el mismo tipo de animación de líneas
   que ya tiene el hero.
3. **Emblema**: extiende `emblem.js` con `setPalette(theme)` (rojo, arcoíris animado, hielo, ámbar) y, si cabe, un morph
   de partículas por modo (emblema → silueta de GPU → copo/gota → llave), con la misma técnica de muestreo de imagen.
4. **El modo se queda**: el modo elegido se guarda en localStorage y se aplica en todas las páginas desde un script en
   línea en el `<head>` del layout (sin parpadeo). Agrega en el encabezado un botón pequeño para cambiar de modo.
5. **Movimiento reducido**: sin avance automático, sin animación de `--hue` (degradado fijo), cambios instantáneos.
6. **Login**: `resources/js/layouts/AuthLayout.vue` y páginas `auth/*` con la identidad (fondo tinta, rojo ATS, Chakra
   Petch, emblema), título "Acceso para personal", enlace "Volver al sitio". En el pie del sitio agrega
   "Acceso para personal" → `/login`.

## Terminado cuando
- Capturas `playwright-cli` en `../.delegate/shots/r8-*.png` del inicio en cada modo (1440×900 y 390×844), una página
  interior con modo `rgb` aplicado y el login.
- `bash ../.delegate/verify.sh all` en verde; ninguna prueba existente rota.

## No hagas
- No cambies textos de negocio fuera del hero, ni rutas o controladores.
- No agregues dependencias; reutiliza `three`, `gsap` y `lenis`.
