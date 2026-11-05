# Encargo R20: buscador de productos de la cotización + tema claro/oscuro por usuario + 2 correcciones de datos

## IMPORTANTE
- **Hay otra ronda de Codex corriendo al mismo tiempo (R19)** en el sitio público. No toques
  `resources/views/site/**`, `resources/views/layouts/site.blade.php`,
  `resources/css/{site,shop,site-design,header,modes}.css` ni `resources/js/site/**`. Tu área es el portal
  interno, más los archivos de soporte y comandos que se indican abajo.
- **Hay un proceso escribiendo en la base de desarrollo.**
  - Crea las migraciones que necesites, pero **no las corras** (yo las corro después).
  - No ejecutes seeders, `verify.sh migrate` ni `all`.
  - No ejecutes comandos `catalogo:*`, `equipos:*` ni `productos:importar` sin `--simular`.
  - Las pruebas usan SQLite en memoria: esas sí.
- Si `npm run build` falla porque otra compilación corre al mismo tiempo, espera un minuto y vuelve a intentar.

## Lee primero
- `sistema/AGENTS.md`.
- `app/Http/Controllers/Admin/QuoteController.php`, la ruta `/admin/quotes/products` y
  `resources/js/pages/admin/QuoteEditor.vue`, donde está la búsqueda de productos.
- `AdminController::productQuery()`, la búsqueda de Admin → Productos.
- `app/Http/Middleware/HandleAppearance.php`, `resources/js/composables/useAppearance*`,
  `resources/js/components/AppearanceTabs.vue`, `resources/js/pages/settings/Appearance.vue`,
  `resources/js/components/AppSidebarHeader.vue` y `resources/views/app.blade.php`.
- `app/Console/Commands/FillProductModels.php` y `app/Support/Hardware/SpecExtractor.php` (punto 3).
- Diseño: `/mnt/c/Users/tit/.claude/skills/taste-skill/`, reglas de pantallas de app.

## 1. Buscador de productos de la cotización
Hoy funciona, pero no encuentra bien. Haz que encuentre como lo haría un vendedor:
- Crea `App\Support\ProductSearch` y úsalo en la cotización y en Admin → Productos.
- **Normalización:**
  - minúsculas, sin acentos;
  - además, una forma compacta sin espacios, guiones ni puntos, para que "rtx4060", "RTX 4060" y "rtx-4060" sean
    lo mismo.
- **Dónde busca:** nombre, SKU, `model`, marca y categoría. Cada palabra debe aparecer, en cualquier orden.
  "asus 4060" encuentra "Tarjeta de Video ASUS ... RTX 4060".
- **Índice:**
  - Agrega la columna `products.search_text`, normalizada, con migración (no la corras).
  - Se llena al guardar un producto y en importación, Icecat y `catalogo:modelos`.
  - Comando nuevo `catalogo:indexar` para regenerarla.
  - Si la columna está vacía, la búsqueda debe seguir funcionando con un respaldo.
- **Orden de resultados:**
  1. SKU o modelo exacto;
  2. SKU o modelo que empieza con el término;
  3. nombre que empieza con el término;
  4. todas las palabras en el nombre;
  5. lo demás.
  - Dentro de cada grupo: con existencias primero, luego publicados.
  - Máximo 20 resultados.
- **Respuesta:** id, nombre, marca, SKU, modelo, imagen, precio, existencias locales y en 24 h. Conserva lo que
  ya usa el editor.
- **Editor de cotización:**
  - Lista con miniatura, marca, SKU y modelo, existencias y precio, con el término resaltado.
  - Navegación con flechas y Enter.
  - Cancela las peticiones viejas con `AbortController`, para que no lleguen resultados desordenados.
  - Muestra "Buscando…" mientras carga y un estado "Sin resultados" con sugerencia.
- **Pruebas:** palabras en otro orden, acentos ("camara" encuentra "Cámara"), forma compacta ("rtx4060"), SKU
  parcial, modelo exacto, "marca + modelo" y el orden de los resultados.

## 2. Tema claro/oscuro guardado por usuario
- Columna `users.appearance` (`light`, `dark` o `system`; por defecto `system`), con migración (no la corras).
- **Botón rápido** en la barra superior del portal (`AppSidebarHeader`): claro, oscuro y sistema.
- La página Configuración → Apariencia existente usa lo mismo.
- **Guardado:**
  - Al cambiar, se guarda en el usuario (PATCH) y en la cookie que ya usa `HandleAppearance`.
  - Al iniciar sesión, en cualquier equipo, se aplica lo guardado.
  - El tema se aplica sin parpadeo al cargar.
- **Revisión en oscuro:** revisa que **todas** las pantallas del portal se vean bien.
  - En especial Cotizaciones (lista y editor), Clientes (formulario y diálogo), Productos y su editor, Fichas
    pendientes, Equipos armados, Configuración (tarjetas de logotipos), Servicio técnico, Garantías, Pólizas,
    Banners y Usuarios.
  - No debe haber fondos blancos fijos, textos ilegibles ni bordes invisibles.
- El inicio de sesión sigue siempre oscuro, por la marca. Los PDF no cambian: siempre claros.
- **Pruebas:** se guarda la preferencia, se pone la cookie y se aplica al cargar. Revisa con capturas en claro y
  oscuro, a 1280 px, el editor de cotización, Productos y Clientes, y guárdalas en `sistema/.taste-skill/r20/`.

## 3. Correcciones de datos
1. **`catalogo:modelos`** (`FillProductModels`): a veces guarda el título completo de Icecat, por ejemplo "Radeon
   RX 9060 XT GAMING OC 8G Tarjeta Gráfica - 8GB GDDR6, 128bit, PCI-E 5.0, …".
   - El modelo debe ser corto: usa el número de parte (BrandPartCode o ProductCode) o el nombre corto del modelo.
   - Si el texto pasa de unos 40 caracteres o trae " - ", comas o descripciones, usa el número de parte.
   - Agrega `--rehacer` para recalcular los que vinieron de Icecat, respetando los capturados a mano.
   - Prueba con fixture.
2. **`SpecExtractor`, tipo de disco:** "DD Interno Toshiba 12TB 3.5 MG09ACA12TE SATA Iii" hoy sale "SATA SSD" y
   debe ser **HDD**.
   - Orden de las reglas:
     1. NVMe o PCIe → NVMe;
     2. SSD o "estado sólido" → SATA SSD;
     3. HDD, "disco duro", `\bDD\b`, RPM o 3.5" → HDD;
     4. si solo dice SATA → SATA SSD.
   - Pruebas con ese nombre, con "SSD Stylos STMSSD1B 120GB 2.5 SATA 3" (SATA SSD) y con uno NVMe.

## Terminado cuando
- `bash .delegate/verify.sh types`, `check`, `test` y `build` pasan.
- Textos en español de México.
- Reporta los archivos que cambiaste y las migraciones nuevas, sin correr.

## No hagas
- Lo de la sección IMPORTANTE.
- No toques el sitio público ni el motor de compatibilidad.
- No hagas commit.
