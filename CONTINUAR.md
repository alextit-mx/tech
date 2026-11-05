# Cómo continuar Tech Computer en otra computadora

Estado al **31 de octubre de 2026**. Repositorio: `git@github.com:alextit-mx/tech.git` (rama `main`, **público**).

## 1. Qué es
Tech Computer (Tech Computer ATS SA de CV, Plaza de la Tecnología, CDMX) vende, arma y repara PCs con enfoque gamer, y
vende periféricos, accesorios, impresoras y consumibles. El proyecto es **un solo Laravel en `sistema/`** con dos caras:

- **Sitio público** en `/` (Blade + Vite, para SEO): inicio, tienda, fichas de producto, carrito de cotización,
  configurador gamer `/arma-tu-pc`, servicio técnico, asesoría y contacto.
- **Portal interno** en `/admin` (Inertia + Vue + TypeScript) para 4 personas (dueño y 3 vendedores): productos y precios,
  clientes, cotizaciones con PDF y WhatsApp.

La especificación completa está en `sistema/docs/plan.md` y las reglas de código en `sistema/AGENTS.md`.

## 2. Decisiones que ya tomó el dueño (no volver a preguntar)
- **Hosting compartido** (cPanel, PHP 8.3, MySQL, sin Node, sin colas). Por eso: sin SSR, colas `sync`, imágenes en
  `public/uploads`, solo dependencias PHP puras (PDF con dompdf).
- **Vendedores**: ven costos pero **no márgenes**; pueden cambiar precios y dar de alta productos. Solo el dueño ve
  márgenes, hace ajustes masivos de precio y administra usuarios, categorías y configuración.
- Cada producto se publica **con precio** o como **"Cotizar"**. **Los precios incluyen IVA** (16 %).
- Catálogo con **impresoras y consumibles** (tintas, tóner, papel).
- **Pago en línea**: lo quiere, pero queda **pendiente** (el modelo de cotización está listo para agregarlo).
- Pieza estrella: **configurador gamer muy animado y futurista** que termina en una cotización.
- Decisiones de Claude que el usuario no ha objetado: redondeo del ajuste masivo hacia arriba a múltiplos de $10,
  vigencia de cotizaciones de 3 días (editable), el configurador no promete FPS con números, sin registro público
  (el dueño crea los usuarios), con verificación en dos pasos opcional.

## 3. Levantar el entorno (Windows + WSL Ubuntu + Docker Desktop)
```bash
git clone git@github.com:alextit-mx/tech.git ~/Projects/techcomputer
cd ~/Projects/techcomputer
docker build -t techcomputer-php:8.3 docker/php       # PHP 8.3 (en WSL no se instala PHP)

cd sistema
export PATH="$PWD/bin:$PATH"                          # php y composer pasan por Docker
cp .env.example .env
composer install
npm install
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed                             # crea dueño (muestra contraseña si OWNER_PASSWORD está vacío)
SEED_DEMO=1 php artisan db:seed --class=DemoSeeder     # catálogo de prueba y 3 vendedores (contraseña "password")
npm run build
php artisan serve --host=0.0.0.0 --port=8000          # http://localhost:8000 y http://localhost:8000/admin
```
- Node 22 viene de nvm: desde PowerShell usa `wsl -d Ubuntu -e bash -lic "..."` y pon los comandos en un script; las
  comillas en línea a través de PowerShell se rompen.
- El instalador de PHP de php.new fue bloqueado por el sistema de permisos de Claude Code: por eso Docker.

### Codex
- En Ubuntu: `codex login` con una cuenta **Plus o Pro** (la cuenta gratuita llegó a su límite el 31/10 y vuelve el 25/11).
  Si el inicio de sesión no abre el navegador: `codex login --device-auth`.
- `.delegate/run.sh` usa la sesión de WSL; si no existe, usa la de Windows (`/mnt/c/Users/tit/.codex`).
- Prueba rápida: `bash .delegate/run.sh r0-prueba.md low`.

### Verificar
`bash .delegate/verify.sh all` → migración + datos de prueba, build, tipos (`vue-tsc`), lint estricto (`vp check`,
corrige formato solo), pint y pruebas. **Último resultado: todo en verde, 54 pruebas pasan.**

## 4. Qué está hecho
| Ronda | Estado | Qué hay |
|---|---|---|
| Base | ✅ | Laravel 13.39, Inertia 3, Vue, TS, Tailwind 4, Fortify (2FA y confirmación de contraseña; sin registro). |
| R1 catálogo | ✅ (borrador de Codex) | Roles dueño/vendedor, usuarios inactivos (también se cierra su sesión abierta), configuración, categorías con `builder_slot`, marcas, productos con specs por slot (`App\Support\BuilderSpecs`), fotos a WebP, historial de precios, edición rápida, ajuste masivo. Todo en `Admin\AdminController` (compacto). |
| R2 cotizaciones | ✅ | `customers`, `quotes`, `quote_items`, folios `COT-2026-0001` (`App\Support\Folio`), `App\Services\QuoteService` (admin, carrito y configurador; precios siempre de la base), PDF dompdf (`resources/views/pdf/quote.blade.php`), vista pública `/cotizacion/{token}`, WhatsApp, "Tomar" cotizaciones nuevas, inicio del portal con números del mes (margen solo dueño). Páginas: `Dashboard.vue`, `admin/Quotes.vue`, `admin/QuoteEditor.vue`, `admin/Customers.vue`. |
| R3 sitio público | ✅ | Páginas de `sitio/` portadas a Blade (`resources/views/site/pages`), layout `layouts/site.blade.php`, tienda (`Site\ShopController`): departamentos, categoría con filtros, ficha con JSON-LD y Open Graph, búsqueda, carrito en localStorage con precios del servidor (`Site\CartController`), `sitemap.xml`, `robots.txt`. Estilos en `resources/css/shop.css`. |
| R4 configurador | ✅ (sin revisión visual) | `/arma-tu-pc`: `resources/js/builder/` → `rules.ts` (compatibilidad, consumo, nivel de poder, bases recomendadas), `scene.ts` (Three.js procedural: piezas que vuelan y encajan, escaneo, chispas, cámara por slot, final con giro, ola RGB y emblema ATS de partículas), `sfx.ts` (WebAudio), `BuildLab.vue`, `LootCard.vue`, `builder.css`. Servidor: `Site\BuilderController` + `App\Support\BuildValidator` (mismas reglas en PHP). |
| R5 servicio y garantías | ✅ | Órdenes de servicio `OS-…` con historial público/interno, PDF de recepción y WhatsApp; garantías `GAR-…` por partida (desde cotización aceptada, orden entregada o manual), anulación y PDF; pólizas `POL-…` con visitas; consultas públicas `/estado` y `/garantia`; inicio del portal con equipos en el local y vencimientos a 30 días. `sistema/DEPLOY.md` (hosting compartido). |
| Revisión visual | ✅ (3 nov) | Capturas reales con Edge headless: configurador (cámara y cartas corregidas), tienda, ficha, celular. |
| R7 3D premium | ✅ (3 nov, Claude) | `resources/js/builder/scene.ts` reescrito: tone mapping neutral, GTAO, bloom solo del RGB, SMAA, grano, sombras, piso con reflejo, haz de luz y polvo; piezas con biseles, aluminio cepillado, cobre, vidrio con transmisión, ventiladores de 9 aspas, GPU en montaje vertical, heatpipes, cables; RGB que respira y ola arcoíris al final; hover con contorno y clic en una pieza abre su slot. Preset ligero en celular. Llegada épica de cada pieza (haz de luz, impacto con destello, onda expansiva, chispas y sacudida). Se cotiza con las piezas que haya (solo bloquean incompatibilidades; lo que falta se anota en la cotización); "Desde cero" vacía la build guardada. |
| R8 hero con modos | ✅ (3 nov, Claude) | Inicio con 4 modos (ATS, RGB, Frost/Creador, Amber/Servicio) que recolorean todo el sitio (`resources/css/modes.css`, `resources/js/site/theme.js`, `pages/home.js`, `emblem.js` con `setPalette`). Botón de modo en el encabezado (se guarda), `?modo=rgb` en enlaces, login del personal con la marca (`layouts/auth/AuthBrandLayout.vue`) y acceso en encabezado y pie. |
| R11 fotos y admin de productos | ✅ (3 nov, Claude; Codex sin cuota) | Fotos: GD con WebP en el Docker (`docker/php/Dockerfile`, límites de subida en `conf.d/uploads.ini`) y respaldo a JPEG/PNG en `App\Support\WebpImage` si el servidor no trae WebP; `public/.user.ini` sube los límites en cPanel. `components/ImageUploader.vue`: arrastrar, elegir o pegar con Ctrl+V, se reduce en el navegador, progreso, reordenar, principal, borrar (todo por JSON, `lib/http.ts`). `admin/Products.vue`: miniaturas, chips (sin foto, sin precio, sin existencias, ocultos, remates, destacados) con conteo, búsqueda al escribir, orden, edición en línea, panel lateral de edición rápida y acciones masivas. `admin/ProductForm.vue` por secciones con vista previa de la tarjeta. |
| R12 tienda y equipos armados | ✅ (3 nov, Claude) | `builds` + `build_product` (`App\Models\Build`): precio total y disponibilidad en vivo, publicar solo si es compatible y completo. Admin `admin/Builds.vue` y `admin/BuildForm.vue` (selector por slot con compatibilidad, importar el enlace de "Compartir build"); en `/arma-tu-pc` el personal con sesión ve "Guardar como equipo armado". `php artisan equipos:sugeridos` (`App\Support\BuildSuggester`, mismo criterio que `recommend()` de `rules.ts`). Tienda: portada con buscador, equipos por uso, departamentos con conteo y carruseles (`site/shop/home-sections.blade.php`, caché de 5 min **ya renderizada**: Laravel 13 no deja guardar modelos en caché); `/equipos/{slug}` con JSON-LD y sitemap; ficha con "Completa tu equipo", "Equipos que la incluyen" y "Similares"; sugerencias del buscador en Vue (`site/SearchSuggest.vue`, carga bajo demanda) y filtros/paginación de categoría y búsqueda sin recargar (`site/shop.js`). |
| R13 Excel y encabezado gamer | ✅ (3 nov, Claude) | Admin → Productos → "Descargar Excel" (`/admin/products/export`): mismos filtros, chip y orden que la tabla, todas las páginas; margen solo con `view-margins`. El .xlsx lo arma `App\Support\Xlsx` (zip de XML, sin dependencias; probado abriéndolo en Excel). Encabezado nuevo en `resources/css/header.css` (todas las páginas, también el configurador, que reserva 70 px): logo de 60 px, menú en mayúsculas con barra de luz, controles con esquinas cortadas, ícono de carrito para la cotización y el acceso al final ("Entrar" o "Mi panel" con LED). Se quitó el botón de modo de color del encabezado (los modos siguen desde el hero del inicio). |
| R14 compatibilidad y fichas | ✅ | Reglas de hardware compartidas y fichas por campo con origen; extracción por nombre y tablas; bloqueo de armados con fichas pendientes. |
| R14b correcciones | ✅ | Correcciones de compatibilidad y extracción; valores sin origen confirmado siguen pendientes; revisión de armados publicados. |
| R15 Icecat | ✅ | Enriquecimiento por Icecat con caché, imágenes opcionales y conflictos frente al nombre; conserva los datos manuales. |
| R16 fichas pendientes | ✅ | Admin → Fichas pendientes (`/admin/fichas`): pestañas con conteos, búsqueda, existencias, captura crítica por producto, resolución de conflictos y revisión de armados. Tipo de disco por nombre e Icecat. |
| R6 pase de diseño | ❌ pendiente (Codex) | Encargo listo: `.delegate/r6-diseno.md` (taste-skill + awesome-design-md + image-to-code + playwright-cli). |

Pruebas: `tests/Feature/Admin/CatalogTest.php`, `QuoteTest.php`, `SiteTest.php`, `BuilderTest.php`, `ServiceTest.php` + las del kit (60 pasan).

### Skills de diseño instaladas (3 nov)
En `C:\Users\tit\.claude\skills\` (WSL: `/mnt/c/Users/tit/.claude/skills/`), revisadas antes de instalar:
`taste-skill` (Leonxlnx/taste-skill v2, `design-taste-frontend`), `image-to-code` (Leonxlnx/imagegen-skills),
`awesome-design-md` (VoltAgent/awesome-design-md + un `SKILL.md` propio). Playwright CLI oficial de Microsoft:
`npm i -g @playwright/cli` en WSL y su skill en `.claude/skills/playwright-cli` del proyecto. **Falta una vez, con sudo:**
`sudo $(which npx) playwright install-deps chromium` (librerías del sistema para el navegador).
En otra computadora hay que repetir esta instalación (las skills de usuario no están en el repo).

### Servidor local para ver el sitio
`docker run -d --name tc-serve -p 8000:8000 -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app techcomputer-php:8.3 php artisan serve --host=0.0.0.0 --port=8000`
(desde `sistema/`) → http://localhost:8000 en el navegador de Windows. Detener: `docker rm -f tc-serve`.

## Importar la lista de precios (cada mes)
```bash
python3 herramientas/lista_precios.py LP_NOVIEMBRE.xlsx            # -> importacion/LP_NOVIEMBRE_importar.csv
cp importacion/LP_NOVIEMBRE_importar.csv sistema/storage/app/lista.csv
cd sistema && php artisan productos:importar storage/app/lista.csv --simular   # revisar
php artisan productos:importar storage/app/lista.csv [--ocultar-faltantes]
php artisan equipos:sugeridos            # rearma los 6 equipos sugeridos con precios y existencias del día
```
Si cambias el Dockerfile: `docker build -t techcomputer-php:8.3 docker/php` y vuelve a crear `tc-serve`.
`productos:importar` ya no lleva `--specs`; las fichas se derivan y enriquecen por separado. Banners de la tienda: Portal → Banners.

```bash
php artisan catalogo:fichas {--slot=} {--simular}
php artisan catalogo:icecat {--slot=*} {--limit=} {--imagenes} {--simular} {--refrescar}
php artisan equipos:revisar
```
Las llaves muestran opciones disponibles; se pasan como `--slot=case`, por ejemplo.
`ICECAT_USERNAME` va en `sistema/.env`; nunca guardar la contraseña de Icecat.
Admin → **Fichas pendientes** permite confirmar datos estimados y resolver conflictos sin abrir el editor completo.
`equipos:revisar` despublica armados con problemas y reporta sus motivos; no publica borradores.
Pendiente: capturar gabinetes y disipadores de marcas que Icecat gratis no trae.
Mientras corra una carga sobre desarrollo, no ejecutar migraciones, semillas, importaciones ni enriquecimientos simultáneos.
Para verificar sin tocar esa base: desde la raíz, ejecutar `bash .delegate/verify.sh types`, `check`, `test` y `build` por separado
(el script actual solo procesa su primer argumento).
El Excel trae `id | PRODUCTO | PRECIO | CENTRO | 24H` (CENTRO → `stock`, "Entrega inmediata"; 24H → `stock_24h`, "Llega mañana"). Se toma PRECIO como precio público con IVA, CENTRO como existencias del
local y 24H como "Disponible en 24 h"; se publica lo que tiene existencia en alguna de las dos. Del nombre se separan
condición (Remate, Caja abierta, Descontinuado), garantía, marca, categoría y specs del configurador (inferidas; se pueden
corregir en el portal y la siguiente importación las respeta). Las listas y el CSV no se suben a git (repo público).

## 5. Qué sigue (en este orden)
1. Instalar las librerías del navegador de Playwright (comando con sudo arriba) y que Codex tenga sesión con cuenta
   Plus/Pro (`codex login` en Ubuntu).
2. **R6 diseño con Codex**: `bash .delegate/run.sh r6-diseno.md high`. Claude solo revisa capturas y verificación.
   Incluye pulir las pantallas de R1 (`admin/Products.vue`, `ProductForm.vue`).
3. Probar en navegador el portal con sesión (las capturas del 3 nov fueron solo del sitio público) y el final del
   configurador ("BUILD COMPLETO" + cotización) en un navegador real con GPU.
4. Inicio del sitio: la sección "PCs gamer por nivel" y el ejemplo 1440p aún usan el catálogo fijo
   `resources/js/site/catalogo.js`; conectarlos a productos reales.
5. Datos reales: el dueño captura su catálogo (el `DemoSeeder` es solo para desarrollo) y define `OWNER_EMAIL` y
   `OWNER_PASSWORD` en el `.env` de producción.
6. Opcionales: recalcular precios por tipo de cambio (hoy solo ±%), importar listas de mayoristas, pago en línea
   (decisión pendiente del dueño), correr `composer test` (incluye phpstan, aún no se ha corrido).

## 6. Archivos clave
- `CLAUDE.md` (raíz): instrucciones de trabajo para Claude Code.
- `sistema/AGENTS.md`: reglas para Codex. `sistema/docs/plan.md`: especificación por secciones.
- `.delegate/`: encargos `r1`–`r5`, `run.sh` (corre Codex), `verify.sh` (verificación). Los `.log` y `.result.md` no se suben.
- `docker/php/Dockerfile`, `sistema/bin/php`, `sistema/bin/composer`: PHP por Docker.
