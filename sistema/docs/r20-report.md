# R20 · Búsqueda de productos y apariencia del portal

## Resultado

- Búsqueda compartida en cotizaciones y Admin → Productos. Normaliza mayúsculas y acentos y agrega una forma compacta sin espacios, guiones ni puntos. Todas las palabras deben coincidir, en cualquier orden, entre nombre, SKU, modelo, marca y categoría.
- Relevancia: SKU/modelo exacto, prefijo de SKU/modelo, prefijo del nombre, palabras en el nombre y otras coincidencias. Dentro de cada nivel prioriza existencias locales o en 24 h y publicación. La consulta de la cotización devuelve hasta 20 resultados y conserva los campos del editor. El costo se entrega exclusivamente a quien puede ver márgenes.
- `products.search_text` se genera en el evento de guardado del modelo, que también usan la importación y Icecat. Los cambios de nombre de marca/categoría invalidan el índice de sus productos para usar el respaldo con los nombres vigentes. Los índices nulos o vacíos tienen respaldo SQL compatible con SQLite y MySQL.
- `catalogo:indexar` regenera el índice por lotes; admite `--simular`.
- El editor muestra miniatura, marca, SKU, modelo, existencias y precio; resalta coincidencias incluso compactas y sin acentos. Incluye flechas, Enter, Escape, cancelación de peticiones viejas, estado de carga, error y ausencia de resultados.
- Claro, oscuro y sistema comparten control en la barra superior y Configuración → Apariencia. La preferencia se valida por Form Request, se guarda por PATCH en el usuario y en la cookie. La carga inicial usa primero la preferencia del usuario y aplica sistema antes de pintar. El inicio de sesión conserva el tema oscuro de marca.
- Revisión de contraste del portal: textos de advertencia/estado, tarjetas de logos y controles nativos (calendarios y casillas). Los PDF conservan sus estilos claros.
- Icecat conserva nombres cortos y descarta títulos extensos o descriptivos en favor del número de parte. `catalogo:modelos --rehacer` recalcula modelos identificados como Icecat. Para registros antiguos sin procedencia solo considera los que coinciden exactamente con el título de su caché local; conserva los demás. Las ediciones de modelo se marcan como manuales.
- Clasificación de almacenamiento: NVMe/PCIe, SSD/estado sólido, HDD/disco duro/DD/RPM/3.5 y finalmente SATA. Incluye RPM pegado al número, como `7200RPM`.

## Migraciones creadas, sin ejecutar en desarrollo

- `database/migrations/2026_11_04_000005_add_product_search_and_model_source.php`: `products.search_text` y `products.model_source`.
- `database/migrations/2026_11_04_000006_add_user_appearance.php`: `users.appearance`, predeterminado `system`.

Queda a cargo del usuario aplicar las migraciones y, después, ejecutar `catalogo:indexar` y `catalogo:modelos --rehacer` cuando lo considere oportuno. No se ejecutaron esos comandos sobre la base de desarrollo, ni seeders ni migraciones de desarrollo. Las pruebas sí crean su esquema en SQLite en memoria.

## Verificación

- `bash ../.delegate/verify.sh types`: 0.
- `bash ../.delegate/verify.sh check`: 0.
- `bash ../.delegate/verify.sh test`: 147 pruebas PHP, 1595 aserciones; 34 pruebas JavaScript.
- `bash ../.delegate/verify.sh build`: 0.
- Pint: ejecutado sobre los archivos de R20 y también mediante el envoltorio `test`. `pint --dirty` no reconoce Git dentro del contenedor; se ejecutó con rutas explícitas.
- Sin prueba contra MySQL real; la migración y las expresiones usan operaciones disponibles tanto en MySQL como en SQLite.

Las capturas están en `.taste-skill/r20/`, a 1280 px, en claro y oscuro: cotizaciones, editor con búsqueda y partida, Productos y editor, Clientes y formulario/diálogo, fichas pendientes, equipos armados, configuración, servicio técnico, garantías, pólizas, banners y usuarios. `browser-report.json` registra 26 cargas, sin errores JavaScript ni desbordamiento horizontal de la página. Se comprobaron selección con teclado, ausencia de resultados, descarte de una respuesta anterior y los tres botones de tema.

El navegador usa respuestas HTML reales exportadas por `PortalAppearancePreviewTest` y respuestas JSON de sus productos de prueba. Las peticiones del navegador se aíslan mediante fixtures; no acceden a la base de desarrollo. Los listados operativos sin registros muestran su estado vacío. La persistencia real se verifica con pruebas HTTP de Laravel.

El inspector de taste-skill no encontró fallos en el editor en ambos temas, Productos oscuro y Clientes oscuro. Sus advertencias corresponden a las distintas alturas del resumen y las partidas (el resumen conserva su altura y posición fija al desplazar) y al espacio libre de listados con un único registro de prueba. Se conserva el diseño existente del portal.

## Archivos creados

- `app/Support/ProductSearch.php`
- `app/Console/Commands/IndexProducts.php`
- `app/Http/Controllers/Settings/AppearanceController.php`
- `app/Http/Requests/Settings/AppearanceRequest.php`
- Las dos migraciones indicadas arriba.
- `resources/js/components/SearchHighlight.vue`
- `tests/Feature/ProductSearchTest.php`
- `tests/Feature/AppearanceTest.php`
- `tests/Feature/CatalogCorrectionsTest.php`
- `tests/Feature/PortalAppearancePreviewTest.php`
- `tests/fixtures/icecat/model-long.json`
- `docs/r20-report.md`
- Capturas y reportes de `.taste-skill/r20/`.

## Archivos modificados

- `app/Models/Product.php`
- `app/Models/Brand.php`
- `app/Models/Category.php`
- `app/Http/Controllers/Admin/AdminController.php`
- `app/Http/Controllers/Admin/QuoteController.php`
- `app/Console/Commands/EnrichIcecat.php`
- `app/Console/Commands/FillProductModels.php`
- `app/Support/Hardware/Icecat.php`
- `app/Support/Hardware/SpecExtractor.php`
- `app/Http/Middleware/HandleAppearance.php`
- `routes/settings.php`
- `resources/views/app.blade.php`
- `resources/js/composables/useAppearance.ts`
- `resources/js/components/AppearanceTabs.vue`
- `resources/js/components/AppSidebarHeader.vue`
- `resources/js/components/LogoSetting.vue`
- `resources/js/pages/admin/QuoteEditor.vue`
- `resources/js/pages/admin/Products.vue`
- `resources/js/pages/admin/Builds.vue`
- `resources/js/pages/admin/BuildForm.vue`
- `resources/js/pages/admin/Banners.vue`
- `resources/css/portal.css`
- `vite.config.ts`: excluye capturas/reportes generados de taste-skill y Playwright del formateador para evitar reescribir artefactos de otras rondas.

No se borraron archivos ni se hicieron commits. Los cambios concurrentes de R19 no forman parte de este reporte.
