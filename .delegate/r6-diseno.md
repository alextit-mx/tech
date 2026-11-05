# Encargo R6: pase de diseño del sitio público y del portal con las skills de diseño

## Objetivo
Que el sitio deje de verse "hecho por IA" y se sienta diseñado a propósito para una tienda gamer de Plaza de la
Tecnología, sin perder la identidad actual (negro, rojo ATS `#E30613`, Chakra Petch + Barlow, motivo BIOS, etiquetas de
serigrafía y esquinas cortadas). Todo verificado con capturas reales en escritorio y celular.

## Ya hecho después de escribir este encargo (no lo rompas)
- **Modos del sitio** (ATS, RGB, Frost, Amber): `resources/css/modes.css`, `resources/js/site/theme.js`, hero con modos en
  `resources/views/site/pages/home.blade.php` y `resources/js/site/pages/home.js`, `emblem.js` con `setPalette`. Todo
  color de acento debe salir de `var(--red)`, `var(--red-hot)`, `var(--red-deep)` (o `color-mix` con ellas), nunca de un
  rojo fijo, para que los modos recoloreen la página.
- **Configurador**: escena 3D premium (`resources/js/builder/scene.ts`) y loader con el emblema
  (`resources/views/site/builder.blade.php`). No los toques salvo lo que marque el inspector.
- **Login del personal**: `resources/js/layouts/auth/AuthBrandLayout.vue`.

## Lee primero (en este orden)
1. `AGENTS.md` y `../CONTINUAR.md`.
2. Skills (léelas completas y síguelas):
   - `/mnt/c/Users/tit/.claude/skills/taste-skill/SKILL.md` y sus `references/` — dirección de arte, reglas anti-plantilla,
     inspector (`scripts/inspect.mjs`) y ciclo de mirar y corregir. Para el portal, su parte de *app screens*.
   - `/mnt/c/Users/tit/.claude/skills/awesome-design-md/SKILL.md` y solo estas referencias: `design-md/nvidia`,
     `design-md/playstation`, `design-md/lamborghini`, `design-md/bmw-m` (sitio) y `design-md/linear.app` (portal).
   - `/mnt/c/Users/tit/.claude/skills/image-to-code/SKILL.md` — si puedes generar imágenes, genera primero el diseño de cada
     página y luego impleméntalo; si no puedes, sáltate ese paso y dilo en el reporte.
   - `../.claude/skills/playwright-cli/SKILL.md` — para abrir el sitio y tomar capturas.
3. Lo actual: `resources/css/site.css`, `resources/css/shop.css`, `resources/views/layouts/site.blade.php`,
   `resources/views/site/**`, `resources/js/pages/**` (portal).

## Cambios
1. Escribe `DESIGN.md` en `sistema/` (sistema de diseño del proyecto: tokens de color, tipografía, espaciado, radios,
   componentes, movimiento, tono) a partir de las referencias y la identidad actual. Es la fuente de verdad del resto.
2. Sitio público: inicio, tienda, categoría, ficha, búsqueda, carrito, cotización pública, `/estado`, `/garantia`,
   servicio técnico, asesoría y contacto. Ajusta CSS y Blade según `DESIGN.md` y las reglas de taste-skill.
   El configurador `/arma-tu-pc` ya está pulido: solo corrige lo que el inspector marque.
3. Portal `/admin`: aplica `DESIGN.md` a las pantallas del kit y a las nuestras (inicio, cotizaciones y su editor,
   clientes, productos y su formulario, servicio, garantías, pólizas) usando los componentes `ui` del kit.
4. Verificación visual con `playwright-cli` contra `http://localhost:8000` (servidor: ver `../CONTINUAR.md`): captura cada
   página a 1440×900 y 390×844 en `../.delegate/shots/` (antes y después), y corre el inspector de taste-skill. Para el
   portal entra con el usuario dueño del `DemoSeeder`.

## Terminado cuando
- `bash ../.delegate/verify.sh all` en verde (build, tipos, lint, pint, pruebas).
- Capturas antes/después de todas las páginas listadas y reporte del inspector sin errores graves.
- Reporte: qué decidiste en `DESIGN.md`, archivos cambiados y lo que quedó pendiente.

## No hagas
- No cambies la lógica (controladores, modelos, reglas, rutas) ni los textos de negocio, salvo microcopia.
- No agregues dependencias de producción ni imágenes externas; nada de logos ni marcas de las referencias.
- No toques `../index.html`, `../sitio/` ni `../assets/`.
- Si `playwright-cli` no abre el navegador (faltan librerías del sistema), avisa: el usuario debe correr
  `sudo $(which npx) playwright install-deps chromium`.
