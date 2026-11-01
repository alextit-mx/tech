# Encargo R3: sitio público en Blade, tienda y carrito de cotización

## Objetivo
El sitio de `../sitio/` vive dentro de Laravel con el mismo diseño y animaciones, más una tienda conectada al catálogo real
(precios con IVA o "Cotizar"), fichas de producto con buen SEO y un carrito que genera cotizaciones en el portal.

## Lee primero
- `AGENTS.md`, `docs/plan.md` sección 4 (**la especificación; impleméntala completa**).
- Todo `../sitio/` (HTML, `assets/css/site.css`, `assets/js/*.js`) y `../assets/`.
- `/mnt/c/Users/tit/.claude/skills/frontend-design/SKILL.md` — para las páginas nuevas (tienda, categoría, ficha, carrito,
  búsqueda): misma identidad que el sitio (negro, rojo `#E30613`, Chakra Petch + Barlow, motivo BIOS y etiquetas de
  serigrafía), ligeras y rápidas; fichas de producto como "hoja de especificaciones" de hardware.
- `App\Services\QuoteService::createFromCart` y la vista pública de cotización de R2.

## Cambios
1. `npm i three@0.170.0 gsap@3.12.5 lenis@1.1.0`. Layout Blade `layouts/site.blade.php` (header, menú móvil, footer,
   WhatsApp, metadatos) y una entrada Vite por tipo de página para no cargar Three.js donde no hace falta.
2. Rutas y vistas: inicio, servicio técnico, asesoría, contacto (portadas tal cual); tienda, categoría, producto, búsqueda,
   carrito. "Equipos y componentes" del menú pasa a "Tienda". `/arma-tu-pc` queda con el configurador actual portado (R4 lo
   reemplaza).
3. Carrito en localStorage con contador en el header; `POST /cotizar` (honeypot + `throttle`) → `createFromCart(..., 'web')`
   → redirige a `/cotizacion/{token}` con botón de WhatsApp al negocio.
4. SEO: metadatos y Open Graph por página, JSON-LD, `sitemap.xml` y `robots.txt` dinámicos.
5. El inicio muestra productos destacados (`is_featured`) y las categorías principales, incluidas Impresoras y consumibles.
6. Pruebas: páginas públicas responden 200; producto no publicado da 404; precio "Cotizar" no aparece en HTML ni JSON-LD;
   `POST /cotizar` crea cotización `web` con precios de la base, valida teléfono y bloquea honeypot; sitemap incluye productos
   publicados.

## Terminado cuando
- build, types:check, check, pint y pruebas pasan; el sitio se ve igual que `../sitio/` en las páginas portadas.

## No hagas
- No rehagas el configurador (R4). No toques `../sitio/` ni `../index.html`.
