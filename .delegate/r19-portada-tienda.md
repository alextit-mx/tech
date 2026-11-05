# Encargo R19: portada y tienda en línea al 100 % (primera impresión) + imágenes de producto

## Objetivo
Peticiones del dueño:
- **Portada:** es la primera impresión y debe ser la combinación perfecta de sitio de empresa y tienda en
  línea.
- **Tienda:** debe verse muchísimo mejor. Hoy hay secciones de texto que desentonan con el estilo visual.
- **Imágenes de producto:** se tienen que ajustar a su cuadro. Hay imágenes chicas que se ven mal.

## IMPORTANTE
- **Hay otra ronda de Codex corriendo al mismo tiempo (R20)**, en el portal interno (Inertia/Vue).
  - No toques `resources/js/pages/admin/`, `resources/js/components/`, `resources/js/layouts/`,
    `app/Http/Controllers/Admin/` ni `database/migrations/`.
  - Tu área es el sitio público: `resources/views/site/**`, `resources/views/layouts/site.blade.php`,
    `resources/css/{site,shop,site-design,header,modes}.css`, `resources/js/site/**` y los controladores
    `app/Http/Controllers/Site/` cuando haga falta.
- **Hay un proceso escribiendo en la base de desarrollo.** No ejecutes migraciones, seeders, `verify.sh migrate`
  ni `all`. Tampoco comandos `catalogo:*`, `equipos:*` o `productos:importar`. Leer el sitio en
  `http://localhost:8000` sí está bien.
- Si `npm run build` falla porque otra compilación corre al mismo tiempo, espera un minuto y vuelve a intentar.

## Lee primero
- `sistema/AGENTS.md` y `sistema/DESIGN.md`, que tienen la identidad y los tokens actuales: rojo #E30613,
  negro, Chakra Petch y Barlow.
- Skills de diseño (obligatorias):
  - `/mnt/c/Users/tit/.claude/skills/taste-skill/`: corre su proceso completo de dirección, inspector y ciclo
    de captura y corrección.
  - `/mnt/c/Users/tit/.claude/skills/awesome-design-md/`: busca referencias de tiendas tech y gamer.
  - `/mnt/c/Users/tit/.claude/skills/image-to-code/`.
  - `.claude/skills/playwright-cli/`.
- Vistas: `resources/views/site/pages/home.blade.php`, `resources/views/site/shop/*.blade.php` y
  `resources/views/site/partials/*` (`product-card`, `build-card`, `rail`, `departments`, `banners`).

## Pasos
1. **Diagnóstico.**
   - Haz capturas de cómo está hoy, a 1440 px, a 1366 × 768 y a 390 px: inicio, `/tienda`, una categoría, una
     búsqueda y una ficha de producto.
   - Escribe en `sistema/docs/r19-diagnostico.md` qué se ve mal en cada sección: texto que desentona, bloques
     flojos, jerarquía, espaciados, consistencia.
2. **Portada.** Esta es la estructura sugerida; ajústala con criterio de diseño. Los textos deben ser breves y sin
   párrafos largos.
   - **Hero:**
     - Propuesta clara: PCs gamer, componentes y servicio técnico en Plaza de la Tecnología, CDMX.
     - Buscador grande del catálogo, que reuse el de sugerencias que ya existe.
     - Botones "Ver tienda" y "Arma tu PC", más un acceso a Servicio técnico.
     - Conserva el emblema 3D y los modos si aportan, pero que cargue rápido. En 1366 × 768 debe verse algo de
       la tienda sin hacer scroll.
   - **Barra de confianza:** entrega inmediata o en 24 h (según existencias reales), garantía, formas de pago y
     tienda física. Ícono y una línea en cada una.
   - **Departamentos:** cuadrícula visual con imagen de producto real, no listas de texto.
   - **Productos:** destacados u ofertas y "Con entrega inmediata", en rieles con precio, existencia y botón para
     agregar a la cotización. Usa los datos que ya existen (`is_featured`, `stock`, `stock_24h`).
   - **Equipos armados** y entrada a Arma tu PC.
   - **Servicio técnico y asesoría:** bloque visual compacto con WhatsApp.
   - **Marcas** reales del catálogo.
   - **Ubicación y contacto:** dirección desde Configuración, enlace a Google Maps (sin API key) y WhatsApp.
   - **No inventes** reseñas, testimonios, cifras, premios ni años de experiencia.
3. **Tienda** (`/tienda`, categorías, búsqueda y ficha):
   - Rehaz los bloques de texto que desentonan, con encabezados y espaciados consistentes.
   - Filtros y orden claros, y un estado vacío bien diseñado.
   - **Tarjetas de producto uniformes:** imagen, marca, nombre en 2 líneas, precio, existencia (local o 24 h) y
     acción.
   - **Ficha de producto:** galería, precio, existencia, especificaciones en una tabla bien diseñada (cuando
     haya), WhatsApp o cotización y productos relacionados.
4. **Imágenes de producto**, en todos lados: tarjetas, rieles, ficha y galería, carrito, sugerencias del
   buscador y equipos armados.
   - Van en un marco de proporción fija, con fondo claro neutro y relleno interno.
   - La imagen va centrada con `object-fit: contain`. Las chicas **no se estiran** más de su tamaño natural
     (`scale-down`).
   - Nada recortado ni pixeleado. El marcador "sin foto" usa el mismo marco.
5. **Revisión:**
   - Repite las capturas después de los cambios y corrige lo que se vea mal: textos cortados, desbordes,
     contraste, alineaciones.
   - Guarda el antes y el después en `sistema/.taste-skill/r19/`.

## Terminado cuando
- `bash .delegate/verify.sh types`, `check`, `test` y `build` pasan.
- Las páginas se ven bien en 1440, 1366 × 768 y 390 px.
- No agregaste librerías pesadas y las imágenes que no están a la vista cargan diferido (`lazy`).
- Textos en español de México.
- Reporta lo que cambiaste por sección y las rutas de las capturas.

## No hagas
- Lo de la sección IMPORTANTE.
- No toques `index.html`, `assets/` ni `sitio/` de la raíz.
- No cambies rutas públicas ni rompas Arma tu PC (`/arma-tu-pc`) ni el carrito de cotización.
- No hagas commit.
