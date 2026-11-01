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
| R5 servicio y garantías | ❌ pendiente | Encargo listo: `.delegate/r5-servicio-garantias.md`. Los enlaces "Estado de mi equipo" (`/estado`) y "Consultar garantía" (`/garantia`) del pie dan 404 hasta entonces. |

Pruebas: `tests/Feature/Admin/CatalogTest.php`, `QuoteTest.php`, `SiteTest.php`, `BuilderTest.php` + las del kit.

## 5. Qué sigue (en este orden)
1. **Revisión visual en navegador** (escritorio y celular) de `/`, `/tienda`, una categoría, una ficha, `/carrito`,
   **`/arma-tu-pc`** y `/admin` (inicio, cotizaciones, editor, clientes, productos). Nada de esto se ha visto en navegador
   todavía: solo compila, pasa tipos, lint y pruebas. En el configurador revisar rendimiento, que el gabinete quepa en
   pantalla, el final y el flujo en celular.
2. **R5**: correr `.delegate/r5-servicio-garantias.md` (órdenes de servicio, garantías, pólizas, `/estado`, `/garantia`
   y `DEPLOY.md` para hosting compartido).
3. Pulir pantallas de R1 (`admin/Products.vue`, `ProductForm.vue`, etc.): funcionan pero son básicas.
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
