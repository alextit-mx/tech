# R16 · Fichas pendientes

Se agregó `/admin/fichas` con pestañas y conteos por tipo, búsqueda por nombre/SKU/marca, filtro de existencias,
orden por publicación/existencias/nombre y paginación de 25. Cada producto muestra únicamente campos críticos
pendientes, valores estimados, origen, conflictos seleccionables, búsqueda externa y enlace al editor.
El formulario guarda por fila y acepta captura parcial; los campos confirmados pasan a manual y se limpian
solo sus conflictos. Se conservan los demás datos. No se envían costos ni márgenes en estas props o respuestas.

`BuilderSpecs` concentra tipos, etiquetas y opciones para el editor y la pantalla nueva; ambos usan `SpecInput`.
El permiso `edit-products` corresponde al dueño y vendedores activos y también lo usa `ProductRequest`.
No existe una bitácora general de productos: solo `PriceChange`, que se reserva para cambios de precio/costo.

`BuildReviewer` conserva la lógica de `equipos:revisar`: revisa publicados, mantiene los válidos y despublica
los que tienen problemas. El botón reporta conteos y motivos; no publica borradores automáticamente.
Se agregó extracción de tipo de disco por nombre y por atributos Icecat, con pruebas y fixtures de almacenamiento.

## Verificación

- `bash ../.delegate/verify.sh types`: correcto.
- `bash ../.delegate/verify.sh check`: correcto.
- `bash ../.delegate/verify.sh test`: Pint, 116 pruebas PHP y 34 pruebas JavaScript correctas.
- `bash ../.delegate/verify.sh build`: correcto. Advertencia de tamaño del bundle 3D existente.
- `git diff --check`: correcto.
- Navegador real con datos generados en pruebas SQLite en memoria, sin usar la base de desarrollo:
  inspección a 1280, 768 y 390 px (0 FAIL, 0 WARN), sin desbordamiento horizontal.
  Revisión manual de capturas de laptop/celular y comprobación de selección de conflictos, pestañas,
  casillas, opciones de almacenamiento, enlace externo y presentación del resultado de revisión.
  El botón de revisión se probó contra una respuesta de ejemplo en navegador y contra el servicio real en PHPUnit.
- Evidencia visual: `.taste-skill/r16/` (6 imágenes y `report.json`). Se respetó el diseño existente de Admin.

El script actual procesa solo el primer argumento: se corrieron los cuatro pasos por separado, sin `all` ni `migrate`.
No se crearon migraciones, no se ejecutaron comandos de carga/revisión sobre desarrollo, no se escribió desde esta
sesión en `database/database.sqlite` y no se hicieron commits. Los cambios previos del usuario se conservaron.

## Pendiente por restricción del entorno

`../CONTINUAR.md` está montado como solo lectura y fuera de las raíces de escritura autorizadas.
La actualización completa está preparada en `docs/r16-continuar.patch`: agrega R14, R14b, R15 y R16,
comandos, configuración de Icecat, nueva pantalla y captura pendiente de gabinetes/disipadores.
Se puede aplicar desde la raíz del repositorio con:

```bash
git apply sistema/docs/r16-continuar.patch
```

Sigue pendiente la captura de gabinetes y disipadores de marcas que Icecat gratis no trae.

## Archivos de R16

Modificados (respecto al estado al iniciar este encargo):

- `app/Console/Commands/ReviewBuilds.php`
- `app/Http/Controllers/Admin/AdminController.php`
- `app/Http/Middleware/HandleInertiaRequests.php`
- `app/Http/Requests/Admin/ProductRequest.php`
- `app/Providers/AppServiceProvider.php`
- `app/Support/BuilderSpecs.php`
- `app/Support/Hardware/IcecatSpecs.php`
- `app/Support/Hardware/SpecExtractor.php`
- `resources/js/components/AppSidebar.vue`
- `resources/js/pages/admin/ProductForm.vue`
- `routes/web.php`
- `tests/Feature/IcecatTest.php`

Creados:

- `app/Http/Controllers/Admin/PendingSpecsController.php`
- `app/Http/Requests/Admin/PendingSpecsRequest.php`
- `app/Support/BuildReviewer.php`
- `app/Support/PendingSpecs.php`
- `docs/r16-continuar.patch`
- `resources/js/components/PendingSpecRow.vue`
- `resources/js/components/SpecInput.vue`
- `resources/js/lib/specs.ts`
- `resources/js/pages/admin/PendingSpecs.vue`
- `tests/Feature/PendingSpecsTest.php`
- `tests/fixtures/icecat/storage-hdd.json`
- `tests/fixtures/icecat/storage-nvme.json`
- `tests/fixtures/icecat/storage-sata.json`
- `docs/r16-report.md`
- `.taste-skill/r16/` (capturas e informe de inspección).

Borrados: ninguno.
