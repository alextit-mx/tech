# Encargo R11: arreglar la subida de fotos y pulir la administración de productos (Vue)

## Objetivo
1. Subir fotos de producto truena con `Call to undefined function App\Http\Controllers\Admin\imagewebp()`
   (`app/Http/Controllers/Admin/AdminController.php:219`). Que nunca vuelva a dar error 500.
2. Que `/admin/products` y el formulario de producto sean una herramienta rápida y agradable para 4 personas que
   capturan cientos de productos importados de la lista de precios (la mayoría aún sin foto).

## Lee primero
- `AGENTS.md`, `../CONTINUAR.md`, `docs/plan.md` (permisos: el vendedor ve costos pero **nunca márgenes**; el dueño sí).
- `app/Http/Controllers/Admin/AdminController.php` (`products`, `quick`, `bulk`, `upload`, `reorderImage`,
  `deleteImage`), `app/Support/WebpImage.php`, `app/Http/Controllers/Admin/BannerController.php`,
  `resources/js/pages/admin/Products.vue`, `resources/js/pages/admin/ProductForm.vue`, `resources/js/components/ui/*`
  (shadcn-vue: sheet, dialog, badge, sonner, skeleton, tooltip…), `../docker/php/Dockerfile`.
- Skill de diseño para pantallas de trabajo: `/mnt/c/Users/tit/.claude/skills/taste-skill/SKILL.md` (sección de app
  screens/dashboards) y `../.claude/skills/playwright-cli/SKILL.md` para capturas.

## 1. Fotos: causa y arreglo
- Causa: el GD de la imagen Docker se compiló sin WebP (`gd_info()` no trae WebP) y `upload()` llama `imagewebp()`
  directo, duplicando lo que ya hace `WebpImage`.
- `../docker/php/Dockerfile`: agrega `libwebp-dev` y `--with-webp` a `docker-php-ext-configure gd`, y un
  `conf.d/uploads.ini` con `upload_max_filesize=20M`, `post_max_size=64M`, `memory_limit=512M`. **No reconstruyas la
  imagen** (lo hace Claude al revisar).
- `WebpImage::store()`: si `imagewebp` no existe o falla, guarda JPEG calidad 85 (PNG si la imagen tiene
  transparencia) con la extensión correcta. Corrige la orientación EXIF de JPEG cuando `exif_read_data` exista.
  Si el archivo no es imagen o GD no puede leerlo, error de validación en español, nunca 500.
- `AdminController::upload()` y `BannerController` usan solo `WebpImage` (sin código GD duplicado). Validación:
  `image|mimes:jpg,jpeg,png,webp|max:20480`, hasta 12 archivos por envío.
- `public/.user.ini` con los mismos límites para el hosting compartido (cPanel).
- Pruebas: subir foto a un producto funciona con y sin WebP (agrega una forma de forzar el respaldo en pruebas, p. ej.
  una propiedad estática o config); archivo no imagen → error de validación.

## 2. Componente `ImageUploader.vue` (fotos de producto)
- Arrastrar y soltar, elegir archivos y **pegar con Ctrl+V** (copiar una imagen del sitio del fabricante y pegarla).
- Antes de subir, reduce en el navegador a 1600 px por lado y conviértela a WebP (JPEG si el navegador no puede) con
  canvas; el servidor la vuelve a validar y procesar.
- Vista previa inmediata, progreso por archivo, reintento si falla, reordenar arrastrando (la primera es la
  principal, marcada como "Principal"), borrar con confirmación. Avisos con sonner.
- Úsalo en el formulario de producto y en la edición rápida del listado (abajo).

## 3. Listado `/admin/products`
- Columna de miniatura (40 px; imagen genérica `/img/producto-sin-foto.svg` si no tiene). Clic en la fila o en la
  miniatura abre un **panel lateral (Sheet) de edición rápida**: fotos (ImageUploader), nombre, categoría, precio con
  IVA, costo, existencias Inmediata y 24 h, condición, publicado, destacado, y enlaces "Abrir completo" y "Ver en
  tienda".
- Filtros rápidos en chips con conteo: Todos, Sin foto, Sin precio (Cotizar), Sin existencias, Ocultos en web,
  Remates/Caja abierta, Destacados. Se combinan con los filtros actuales (categoría, marca…) y viven en la URL.
- Búsqueda con espera de 300 ms por nombre, SKU o marca; la tecla `/` enfoca el buscador.
- Edición en línea de precio y existencias: Enter guarda, Esc cancela, aviso con sonner, sin recargar la página
  (`preserveState`/`preserveScroll`).
- Columnas ordenables (nombre, precio, existencias, actualizado) y 25/50/100 por página.
- Barra de acciones al seleccionar: publicar/ocultar, destacar/quitar destacado, cambiar categoría y el ajuste de
  precios masivo que ya existe.
- En celular, filas como tarjetas. Estados vacíos y de carga (skeleton).
- Costos visibles para todos; **margen solo para el dueño**, como hoy. Toda acción nueva valida permisos en el servidor.

## 4. Formulario de producto
- Secciones claras con navegación lateral fija (General, Precio y existencias, Fotos, Especificaciones, Web/SEO).
- Barra de guardado fija con "Guardar" y "Guardar y ver en tienda"; aviso de cambios sin guardar al salir.
- Vista previa en vivo de la tarjeta como se verá en la tienda (precio con IVA o "Cotizar", etiqueta de existencias).
- Al crear un producto, después del primer guardado queda en edición con la sección de Fotos a la vista.

## Reglas
- Respeta el estilo del código existente y las reglas de `AGENTS.md`. Textos en español de México.
- No cambies la tienda pública ni `/arma-tu-pc` en este encargo.
- Pruebas PHPUnit para lo nuevo del servidor (subida, filtros nuevos, acciones masivas, permisos de margen).
- Verifica con `bash ../.delegate/verify.sh build`, `types`, `check` y `test`. **No uses `migrate` ni `all`** (borran el
  catálogo importado); si agregas migraciones, corre `php artisan migrate` normal.
- Capturas con playwright-cli del listado, el panel lateral y el formulario (1440×900 y 390×844) y corrige lo que se vea
  mal. Entra con un usuario de prueba creado por ti en la base local (y bórralo al terminar).
- Al final, reporta: archivos cambiados, decisiones, pruebas y lo que quede pendiente.
