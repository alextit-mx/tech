# Plan del sistema Tech Computer

Negocio: Tech Computer ATS SA de CV (RFC TCA240830IM3), Plaza de la Tecnología, CDMX. Vende, arma y da mantenimiento a PCs
(enfoque gamer); vende periféricos, accesorios, impresoras y consumibles. Contacto, dirección, correos y datos de transferencia:
`../index.html` y `../sitio/contacto.html`. WhatsApp del negocio: 52 55 4911 4435.

Un solo proyecto Laravel con dos caras: **portal interno** en `/admin` (Inertia + Vue + TS, 4 usuarios) y **sitio público**
en `/` (Blade + Vite, sin Inertia, para SEO). El portal es la única fuente de productos y precios.

Rondas: R1 base y catálogo · R2 clientes y cotizaciones · R3 sitio público y carrito · R4 configurador gamer ·
R5 servicio técnico, garantías, pólizas y despliegue. **Pago en línea: pendiente (no se construye)**; deja el modelo listo para
agregarlo después a una cotización aceptada.

Paquetes ya decididos: `barryvdh/laravel-dompdf` (PDFs, R2). Front público: `three`, `gsap`, `lenis` desde npm (R3/R4).

---

## 1. Usuarios, roles y configuración (R1)

- `users`: agrega `role` (`owner` | `seller`, etiquetas "Dueño" y "Vendedor") e `is_active` (bool). Un usuario inactivo no
  puede iniciar sesión.
- Sin registro público (ya quitado del kit). El dueño crea usuarios: nombre, correo, rol, contraseña inicial; puede editarlos,
  desactivarlos y cambiarles la contraseña. No puede desactivarse ni quitarse el rol a sí mismo.
- Gates/Policies: `manage-users`, `manage-settings`, `view-margins`, `bulk-pricing`, `manage-categories` → solo `owner`.
- Todo `/admin` exige sesión. El panel del kit (`dashboard`) pasa a `/admin`; login en `/login` como está.
- `settings` (clave/valor con caché y un helper `setting('clave')`), editable solo por el dueño en "Configuración":
  `exchange_rate` (tipo de cambio USD→MXN), `quote_validity_days` (3), `business_name`, `legal_name`, `rfc`, `address`,
  `whatsapp`, `email`, `website`, `bank_details` (texto), `warranty_terms` (texto), `quote_terms` (texto).
  Valores iniciales tomados de `../index.html`.
- Marca del portal: logo `../assets/ats-computer.png` (cópialo a `public/img/`), color principal `#E30613`, títulos en
  "Chakra Petch" y texto en "Barlow" (Google Fonts). Navegación lateral en español: Inicio, Productos, Categorías, Marcas,
  Clientes (R2), Cotizaciones (R2), Servicio (R5), Garantías (R5), Usuarios, Configuración.

## 2. Catálogo (R1)

- `categories`: `parent_id`, `name`, `slug` (único), `description`, `builder_slot` (nullable: `cpu`, `motherboard`, `ram`,
  `gpu`, `storage`, `psu`, `cooler`, `case`), `sort`, `is_visible`. Árbol inicial:
    - PCs armadas
    - Componentes → Procesadores (cpu), Tarjetas madre (motherboard), Memoria RAM (ram), Tarjetas de video (gpu),
      Almacenamiento (storage), Fuentes de poder (psu), Enfriamiento (cooler), Gabinetes (case)
    - Periféricos gamer → Teclados, Mouse, Audífonos, Monitores, Sillas, Controles
    - Accesorios → Cables y adaptadores, Almacenamiento externo y USB, Redes, Energía (no-break y reguladores)
    - Impresoras y consumibles → Impresoras, Tintas, Tóner, Papel
- `brands`: `name`, `slug`. Vendedores y dueño pueden crearlas.
- `products`: `sku` (único), `name`, `slug` (único), `category_id`, `brand_id` (nullable), `short_description`,
  `description` (texto), `cost` (nullable), `cost_currency` (`MXN` | `USD`), `price` (con IVA, nullable),
  `price_mode` (`show` = mostrar precio | `quote` = "Cotizar"), `stock` (int), `is_published` (visible en la web),
  `is_featured`, `specs` (JSON), `warranty_months` (int, nullable), timestamps, soft deletes.
    - Costo en MXN = `cost` × `exchange_rate` si es USD.
    - **Margen** = (precio/1.16 − costo MXN) / (precio/1.16). Solo el dueño lo ve (columna, ficha, reportes). El vendedor sí ve
      el costo.
    - Aviso (no bloqueo) para todos: "El precio sin IVA queda por debajo del costo" cuando aplique.
    - Existencias en la web: >3 "En existencia", 1–3 "Últimas piezas", 0 "Sobre pedido".
- `specs` según `builder_slot` de su categoría; define el esquema en una sola clase (p. ej. `App\Support\BuilderSpecs`)
  que usan el formulario, la validación y el configurador:
    - cpu: `socket`, `cores`, `threads`, `tdp_w`, `igpu` (bool), `tier` (1–6)
    - motherboard: `socket`, `memory_type` (DDR4/DDR5), `form_factor` (ATX/mATX/ITX), `m2_slots`
    - ram: `memory_type`, `capacity_gb`, `modules`, `speed_mhz`
    - gpu: `tdp_w`, `vram_gb`, `length_mm`, `tier` (1–6)
    - storage: `type` (NVMe/SATA SSD/HDD), `capacity_gb`
    - psu: `watts`, `certification`
    - cooler: `type` (aire/líquida), `sockets` (lista), `max_tdp_w`
    - case: `form_factors` (lista), `max_gpu_length_mm`
    - Otras categorías: lista libre de pares nombre/valor (se muestra como ficha técnica en la web).
- `product_images`: `product_id`, `path`, `sort`. Subida jpg/png/webp hasta 8 MB; se convierte con GD a WebP, máximo 1600 px
  por lado, en el disco `uploads` (`public/uploads/products`). Reordenar y borrar.
- `price_changes`: `product_id`, `user_id`, `old_price`, `new_price`, `old_cost`, `new_cost`, `created_at`. Se registra en
  cada cambio de precio o costo (incluido el masivo). Historial visible en la ficha del producto.
- Pantallas:
    - **Productos**: tabla con foto, SKU, nombre, categoría, marca, costo, precio, margen (dueño), existencias, web; búsqueda
      por nombre/SKU; filtros por categoría, marca, publicado, modo de precio y sin existencias. **Edición rápida en la fila de
      precio y existencias** (es lo que más se usa). Selección múltiple: publicar/ocultar (todos), ajustar precio ±% con
      redondeo hacia arriba a múltiplos de $10 (solo dueño, `bulk-pricing`).
    - Formulario de producto con los campos de `specs` según la categoría, fotos e historial de precios.
    - Categorías (dueño) y Marcas (todos): alta, edición y orden simples.
- Seeders: `DatabaseSeeder` crea categorías, configuración y un dueño (correo/contraseña de `.env`: `OWNER_EMAIL`,
  `OWNER_PASSWORD`). `DemoSeeder` (solo desarrollo) crea 3 vendedores y un catálogo de prueba: procesadores y tarjetas de video
  de `../sitio/assets/js/catalogo.js` con sus specs reales, más tarjetas madre, RAM, almacenamiento, fuentes, disipadores y
  gabinetes compatibles, periféricos, accesorios, impresoras, tintas y tóner, con precios de ejemplo realistas en MXN; algunos
  en modo "Cotizar".

## 3. Clientes y cotizaciones (R2)

- `customers`: `name`, `phone` (10 dígitos, WhatsApp; único si existe), `email`, `rfc`, `notes`. Búsqueda por nombre o teléfono.
- `quotes`: `folio` (`COT-2026-0001`, consecutivo por año, a prueba de concurrencia), `customer_id`, `user_id` (vendedor;
  null si viene de la web), `source` (`admin` | `web` | `builder`), `status` (`draft` Borrador, `sent` Enviada, `accepted`
  Aceptada, `rejected` Rechazada), "Vencida" se calcula (enviada y `valid_until` < hoy), `valid_until`, `build_name`
  (nullable, nombre que el gamer le pone a su PC), `discount` (con IVA), `total`, `notes` (las ve el cliente),
  `internal_notes`, `public_token` (aleatorio, 40 caracteres), timestamps.
- `quote_items`: `quote_id`, `product_id` (nullable para partidas libres), `sku`, `description`, `quantity`, `unit_price`
  (con IVA; **null = "Por confirmar"**), `unit_cost` (copia del costo MXN al cotizar, para margen del dueño), `line_total`,
  `sort`. Precios, descripción y costo se copian del producto al agregarlo: cambiar el catálogo no altera cotizaciones hechas.
- Totales: suma de partidas con precio − descuento; subtotal = total/1.16; IVA = total − subtotal. Si hay partidas "Por
  confirmar", mostrar "Total parcial: hay partidas por confirmar".
- Pantallas: lista (folio, cliente, vendedor, origen, total, estado, fecha) con filtros y "solo las mías"; editor con
  buscador de productos (autocompletar por nombre/SKU con precio y existencias), partida libre, cantidades, precio editable,
  descuento, vigencia, notas; alta rápida de cliente desde el editor. Acciones: PDF, enviar por WhatsApp
  (`https://wa.me/52{telefono}?text=` con saludo, folio y enlace público), cambiar estado, duplicar. Las de la web y del
  configurador aparecen como "Nuevas" hasta que un vendedor las toma (se asigna como `user_id`).
- PDF (dompdf): logo, datos del negocio, folio, fecha, vigencia, cliente, partidas, totales con IVA desglosado, notas,
  condiciones (`quote_terms`) y datos de transferencia. Vista pública `/cotizacion/{token}` y `/cotizacion/{token}/pdf`.
- Inicio del portal: cotizaciones del mes (número y monto), aceptadas, nuevas sin atender, por vendedor; el dueño ve además
  margen estimado de las aceptadas. El vendedor solo ve sus números y los totales generales.

## 4. Sitio público (R3)

- Portar `../sitio/` (inicio, equipos, arma-tu-pc, servicio-tecnico, asesoria, contacto) a layouts Blade con el mismo
  diseño, textos y animaciones: CSS de `../sitio/assets/css/site.css`, JS de `../sitio/assets/js/` como módulos de Vite con
  `three`, `gsap` y `lenis` de npm (no CDN). Respetar "reducir movimiento" y la imagen de respaldo sin WebGL.
- Nuevo: **Tienda** `/tienda` (categorías), `/tienda/{categoria}` (listado con filtros por subcategoría, marca, precio y
  "en existencia"; orden), `/producto/{slug}` (fotos, precio con IVA o "Cotizar", existencias, ficha técnica, garantía,
  botones "Agregar a mi cotización" y "Preguntar por WhatsApp"). Búsqueda `/buscar?q=`. Solo productos publicados.
- **Carrito de cotización** (localStorage): lista, cantidades, total estimado. "Enviar cotización": nombre, WhatsApp y correo
  opcional → crea/actualiza cliente por teléfono y una cotización `source=web` (precios tomados de la base, nunca del
  navegador; honeypot y límite por IP) → `/cotizacion/{token}` con botón "Enviar a Tech Computer por WhatsApp" (mensaje con
  folio y enlace).
- SEO: título/descripcion por página, Open Graph con foto del producto, JSON-LD `Product`/`Offer` (MXN, sin precio si es
  "Cotizar") y `LocalBusiness` en inicio y contacto, `sitemap.xml`, `robots.txt`, URLs limpias.
- Rendimiento: el 3D solo en inicio y configurador; tienda y fichas ligeras (imágenes `loading="lazy"` con tamaño fijo).

## 5. Configurador gamer `/arma-tu-pc` (R4)

Ver el encargo de R4 (dirección visual detallada). Resumen: el usuario elige pieza por pieza (cpu, motherboard, ram, gpu,
storage, psu, cooler, case) entre los productos publicados de cada `builder_slot`, con compatibilidad, consumo y total en
vivo; al terminar deja nombre y WhatsApp y se genera una cotización `source=builder` con folio, PDF y WhatsApp.

## 6. Servicio técnico, garantías y pólizas (R5)

- `service_orders`: folio `OS-2026-0001`, cliente, `device_type` (PC de escritorio, laptop, impresora, consola, otro), marca,
  modelo, serie, accesorios recibidos, falla reportada, diagnóstico, trabajo realizado, presupuesto, costo final, `status`
  (Recibido, En diagnóstico, Esperando autorización, En reparación, Listo para entregar, Entregado, Sin reparación),
  fechas de recepción/promesa/entrega, vendedor, `public_token`. `service_order_events` (estado, nota, `is_public`, usuario,
  fecha). PDF "Orden de recepción" con condiciones y líneas de firma. Consulta pública `/estado`: folio + últimos 4 dígitos
  del teléfono → línea de tiempo con notas públicas.
- `warranties`: folio `GAR-2026-0001`, cliente, `quote_id`/`service_order_id` opcionales, partidas (descripción, serie,
  meses), fecha de inicio, condiciones (`warranty_terms`), anulada (bool); vigente/vencida se calcula por partida. Crear desde
  una cotización aceptada (copia partidas y `warranty_months`) o desde una orden entregada. PDF. Consulta pública
  `/garantia`: folio + serie o últimos 4 dígitos del teléfono.
- `service_plans` (pólizas de mantenimiento, sencillo): cliente, nombre del plan, visitas incluidas, precio, vigencia, notas,
  y registro de visitas (fecha, equipo, trabajo, usuario). Avisos de pólizas y garantías por vencer en el inicio del portal.
- `DEPLOY.md`: cómo subir a hosting compartido (cPanel): `npm run build` local, `composer install --no-dev` local, qué carpetas
  subir, raíz del dominio apuntando a `public/` (o alternativa con `public_html`), `.env` de producción con MySQL, migrar sin
  SSH (ruta protegida de una sola vez o importar SQL), cron opcional, respaldos.
