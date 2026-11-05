# Encargo R12: tienda en línea premium con equipos armados y sugerencias (Blade + islas Vue)

## Objetivo
La portada de `/tienda` es muy simple (banners, título, departamentos y "Destacados"). Debe sentirse como una tienda
gamer premium que **muestra muchos productos**, sugiere **equipos armados** y ayuda a comprar. Además, el equipo de
ventas debe poder administrar esos equipos armados.

## Lee primero
- `AGENTS.md`, `../CONTINUAR.md`, `docs/plan.md`, el `DESIGN.md` de R6 si existe.
- `app/Http/Controllers/Site/ShopController.php`, `app/Http/Controllers/Site/BuilderController.php`,
  `app/Support/BuildValidator.php`, `app/Models/Product.php` (`publicPrice`, `stockLabel`, `stockLevel`,
  `imageOrPlaceholder`), `resources/views/site/shop/*`, `resources/views/site/partials/*`, `resources/css/shop.css`,
  `resources/css/modes.css`, `resources/js/site/*` (carrito de cotización en `cart.js`), `resources/js/builder/BuildLab.vue`
  (presets `uso`/`presupuesto`, enlace de compartir `?b=ids&n=nombre`), `resources/js/builder/rules.ts`.
- `resources/js/components/ImageUploader.vue` y `app/Support/WebpImage.php` (de R11; reúsalos).
- Skills: `/mnt/c/Users/tit/.claude/skills/taste-skill/SKILL.md`, `/mnt/c/Users/tit/.claude/skills/awesome-design-md/`,
  `../.claude/skills/playwright-cli/SKILL.md`.

## 1. Equipos armados (servidor y administración)
- Modelo `Build` (tabla `builds`): nombre, slug único, frase corta, uso (`gaming-1080`, `gaming-1440`, `gaming-4k`,
  `creador`, `oficina`), imagen opcional (WebP con `WebpImage`), publicado, orden. Tabla `build_product`
  (build_id, product_id, slot, cantidad) con los mismos slots del configurador.
- Precio total = suma de `publicPrice` × cantidad. Si alguna pieza no tiene precio público, está oculta o borrada, el
  equipo muestra "Cotizar". Disponibilidad = la peor de sus piezas (Entrega inmediata / Llega mañana / Sobre pedido).
- Solo se puede publicar si `BuildValidator::errors()` está vacío.
- Admin `/admin/builds` (dueño y vendedores; liga en `AppSidebar.vue`): lista con orden arrastrable, publicar/ocultar,
  duplicar y borrar. Editor con un selector por slot (búsqueda de productos filtrada por la categoría del slot y por
  compatibilidad), compatibilidad en vivo, total y costo (margen solo para el dueño), foto con `ImageUploader` y
  **"Importar desde Arma tu PC"**: pegar el enlace de compartir y que llene nombre y piezas.
- En `/arma-tu-pc`, si quien navega es personal con sesión iniciada, botón "Guardar como equipo armado" que crea un
  borrador y abre su editor en admin. El visitante normal no ve nada nuevo.
- Comando `php artisan equipos:sugeridos`: crea o actualiza (por slug) 4 equipos publicados con el catálogo actual —
  Gamer de entrada 1080p, Gamer 1440p equilibrada, Ultra 4K, Creador y streaming — con el mismo criterio de los presets
  de `BuildLab.vue` (pórtalo a PHP en `app/Support/BuildSuggester.php`), solo productos publicados con precio y con
  existencia (inmediata o 24 h), y validados con `BuildValidator`. No lo corras contra la base local; Claude lo corre.

## 2. Portada de `/tienda` (Blade para SEO + islas Vue para la interacción)
El contenido y los enlaces deben salir en el HTML de Blade; Vue solo mejora la interacción (montaje como en
`/arma-tu-pc`). En orden:
1. Banners (ya existen).
2. Encabezado con **buscador con sugerencias en vivo** (isla Vue): endpoint JSON público con throttle que regresa hasta 6
   productos con foto, precio y existencias; Enter va a la búsqueda actual; teclado accesible.
3. Departamentos como mosaico visual con conteo de productos.
4. **Equipos armados**: pestañas por uso; tarjeta con imagen (o una composición elegante con las piezas si no hay foto),
   especificaciones clave (CPU, GPU, RAM, almacenamiento), total con IVA, disponibilidad y botones "Personalízalo en Arma
   tu PC" (`/arma-tu-pc?b=ids&n=nombre`) y "Agregar a mi cotización" (agrega todas las piezas al carrito con aviso).
   Página de detalle `/equipos/{slug}` con piezas, precio por pieza, compatibilidad, metadatos SEO, JSON-LD y entrada
   en el sitemap.
5. **Carruseles de productos** (desplazamiento horizontal con flechas y snap, "Ver todo" a la categoría o filtro):
   Destacados, Novedades, Remates y caja abierta, Entrega inmediata en tienda, Tarjetas de video y Procesadores.
6. Marcas con productos publicados.
7. Franja de beneficios **solo con datos reales**: precios con IVA, entrega inmediata en Plaza de la Tecnología, llega
   mañana, garantía por producto, cotización por WhatsApp. Nada inventado (sin reseñas, estrellas, descuentos ni
   "envío gratis").
8. Llamado a Arma tu PC.

## 3. Tarjeta y ficha de producto
- Tarjeta: mejor jerarquía, insignia de condición (Remate, Caja abierta), meses de garantía, punto de existencias,
  botón "Agregar" con respuesta inmediata, segunda foto al pasar el cursor si existe, sin saltos de layout.
- Ficha: secciones renderizadas en el servidor **"Completa tu equipo"** (compatibles por specs: CPU → tarjetas madre
  del mismo socket; tarjeta madre → CPU del socket y RAM del tipo; RAM → tarjetas madre; GPU → fuentes con watts
  suficientes), **"Equipos armados que lo incluyen"** y **"Similares"** (misma categoría, precio ±30 %).
- Revisa también la página de categoría y la búsqueda para que usen el mismo lenguaje visual.

## Reglas
- Colores siempre por variables CSS (`--red`, etc.) para que los modos ATS/RGB/Frost/Amber también tiñan la tienda.
- Rendimiento: consultas con eager loading, caché de 5 min para las secciones de la portada (se limpia al guardar
  productos o equipos), imágenes `loading="lazy"` con tamaño fijo, sin CLS. Respeta `prefers-reduced-motion`.
- No toques la portada `/`, la escena 3D (`scene.ts`) ni el `index.html` de la raíz.
- Pruebas PHPUnit: precio y disponibilidad de equipos, bloqueo de publicación si es incompatible, importación desde
  enlace, comando de sugeridos, endpoint de búsqueda, secciones de la ficha y permisos.
- Verifica con `bash ../.delegate/verify.sh build`, `types`, `check` y `test`. **No uses `migrate` ni `all`** (borran el
  catálogo importado); para tus migraciones corre `php artisan migrate` normal.
- Capturas con playwright-cli de `/tienda`, `/equipos/{slug}`, una ficha, una categoría y el admin de equipos
  (1440×900 y 390×844, modos ATS y RGB) y corrige lo que se vea genérico o roto.
- Al final, reporta: archivos cambiados, decisiones, pruebas y pendientes.
