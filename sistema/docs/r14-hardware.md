# R14: compatibilidad y fichas técnicas

El servidor decide la compatibilidad y las sugerencias. `Compatibility::check()` devuelve problemas con código, nivel y slots involucrados; `BuildValidator` conserva la interfaz anterior. El espejo TypeScript y la tabla de chipsets se contrastan con PHP en las pruebas. Los 33 casos compartidos fijan también los mensajes completos.

Las sugerencias solo consideran productos publicados, con precio público positivo, existencia inmediata o de 24 horas y ficha confirmada. Buscan hasta ocho alternativas compatibles por slot, retroceden al fallar y permiten bajar hasta dos niveles de CPU/GPU; conservan las metas de RAM y almacenamiento. Nunca sustituyen una pieza por otra insuficiente. GPU primero evita interpretar la falta transitoria de video como un fallo del CPU durante la búsqueda. Para oficina se intentan primero gráficos integrados.

Los campos críticos ausentes, sin origen o con origen distinto de `manual`, `icecat`, `tabla` y `nombre` dejan la ficha pendiente. Se conservan valores existentes sin origen para mantener la compatibilidad indicada con productos anteriores; la nueva derivación asigna el origen de cada dato que reemplaza. Los campos `manual` e `icecat` se protegen individualmente. Confirmar una ficha marca únicamente sus valores presentes, no inventa campos que faltan. Los conectores típicos de GPU, las dimensiones desconocidas y los conectores no declarados de fuente permanecen por confirmar.

Las series de chipset son una matriz conservadora: la actualización de BIOS se trata como riesgo y exige revisión antes de publicar o sugerir. La clase de tarjeta y los niveles de rendimiento son criterios internos de selección, no especificaciones del fabricante. Las variantes de GPU con distinto consumo usan el límite conservador de la familia; se debe confirmar ensamblador, largo y conector.

## Cobertura de la lista de noviembre

Se leyó `LP_NOVIEMBRE.xlsx` sin modificarlo y sin consultar servicios externos. Las tablas reconocen 75 de 80 entradas de CPU, 147 de 148 tarjetas madre, 89 de 93 tarjetas de video y las 104 fuentes. Reconocer el modelo no implica ficha confirmada: medidas, conectores y otros datos pueden seguir pendientes.

Modelos que requieren datos oficiales o aclaración del proveedor antes de ampliar las tablas:

- AMD Ryzen 7 7700X3D y 9850X3D.
- AMD Ryzen 9 9950X3D2 Dual Edition.
- Intel Core Ultra 5 250K Plus y 250KF Plus.
- Radeon RX 9050 (dos productos, Gigabyte y XFX), RX 9060 de 8 GB y RX 9070 GRE de 12 GB.
- Biostar A68N-5600E con A4-3350B integrado: requiere revisar su clasificación para el configurador; no se presupone un socket de CPU intercambiable.

Estos modelos no se confunden con nombres parecidos: por ejemplo, 9950X3D2 no hereda la ficha de 9950X3D y RX 9070 GRE no hereda la de RX 9070.

## Archivos de esta entrega

Creados:

- `app/Support/Hardware/{Compatibility,SpecExtractor,CpuTable,GpuTable,ChipsetTable,PsuTable}.php`.
- `app/Console/Commands/{DeriveSpecs,ReviewBuilds}.php`.
- `app/Http/Requests/Site/SuggestBuildRequest.php`.
- `database/migrations/2026_11_04_000001_add_spec_sources_to_products.php`.
- `resources/js/builder/{chipsets.json,rules.test.ts}`, `vitest.config.ts`.
- `tests/Feature/HardwareTest.php`, `tests/fixtures/compatibility.json` y este reporte.

Modificados:

- `app/Support/{BuildValidator,BuildSuggester,BuilderSpecs}.php`.
- `app/Models/{Product,Build}.php`.
- `app/Console/Commands/{ImportProducts,SuggestBuilds}.php`.
- `app/Http/Controllers/Site/BuilderController.php`.
- `app/Http/Controllers/Admin/{AdminController,BuildController}.php`.
- `app/Http/Requests/Admin/ProductRequest.php` y `routes/site.php`.
- `resources/js/builder/{rules.ts,BuildLab.vue}`.
- `resources/js/pages/admin/{ProductForm,Products,BuildForm}.vue`.
- `package.json`, `package-lock.json`.
- `tests/Feature/{BuilderTest,StoreBuildsTest}.php`.
- `../herramientas/lista_precios.py`: se retiraron el extractor Python y la columna `specs`; el CSV conserva el resto de sus columnas.

No se borraron archivos de la aplicación. No se hicieron commits.

## Aplicación al catálogo y pendientes

Se aplicó solamente la migración aditiva con `php artisan migrate`. Los comandos de importar, derivar fichas, sugerir y revisar equipos se ejecutan exclusivamente dentro de pruebas con SQLite en memoria; no se ejecutaron contra el catálogo local.

Pendiente para Claude: revisar `catalogo:fichas --simular`, aplicar `catalogo:fichas`, confirmar en admin las medidas y conectores pendientes y regenerar sugeridos. Las tablas no sustituyen esa confirmación. Las fuentes antiguas pueden requerir confirmar tanto la ausencia de 16 pines como el número de conectores de 8 pines.

No se realizó revisión visual en navegador. Se conservaron las animaciones y se cancelan tanto peticiones como timers al elegir «Desde cero».

## Verificación final

Pasaron `bash ../.delegate/verify.sh types`, `check`, `test` y `build`: 87 pruebas PHPUnit (663 aserciones) y 34 pruebas Vitest. `test` también ejecutó Pint completo. Se verificaron los nombres reales del extractor, protección de campos manuales/Icecat, búsqueda con retroceso, fuente suficiente sin existencia, exclusión de fichas pendientes, despublicación sin borrado, niveles del editor, cotización con riesgos y ausencia de márgenes en JSON para vendedores. `git diff --check` no encontró problemas.

## R14b

### Correcciones

- `specsVerified()` exige origen confirmado para **cada** campo crítico. Los valores anteriores sin origen siguen disponibles para compatibilidad, pero ya cuentan como pendientes; `merge()` les asigna `supuesto` cuando el extractor no los reemplaza. Se mantienen las protecciones por campo de `manual` e `icecat`.
- `pcie_8pin` es crítico en fuentes. `PsuTable::CONNECTORS` registra cantidad de conectores PCIe 6+2 (no conectores EPS ni cantidad de cables) y presencia del cable de 16 pines. Se distinguen revisiones Corsair por CP/SKU y Gigabyte por PG5; un nombre de línea ambiguo no confirma automáticamente sus conectores de 8 pines. La ASUS ROG-STRIX-850G-AURA-GAMING queda con tres de 8 pines y uno de 16 pines (`tabla`), conforme a la especificación indicada en el encargo. Se conserva el tipo booleano de `pcie_16pin` usado por el configurador.
- Los gabinetes reconocen potencia como fuente incluida, con precedencia de `S/FUENTE`, `SIN FUENTE` y `S/F`. Se reconocen MATX/M-ATX/MICRO ATX/MICRO-ATX/MINI TORRE, ITX/MINI-ITX y ATX/E-ATX como datos del nombre. Los formatos incluyen las tarjetas más pequeñas compatibles. Las dimensiones sin ficha siguen estimadas.
- Los disipadores reconocen AM4, AM5, LGA1700, LGA1851, LGA1200 y LGA1151 explícitos. AMD e Intel genéricos solo producen sockets `supuesto`. `CoolerTable` aporta sockets y, donde está disponible, capacidad térmica declarada; sus comentarios identifican fabricante, familia y apartado de referencia. Las revisiones G2/SE/V2 se separan de las originales. Un AIO de 240/360 mm no recibe un TDP por su tamaño: conserva 95 W `supuesto` cuando no hay cifra tabulada.

### Cobertura

Conteo mediante lectura del Excel y llamadas puras a `SpecExtractor::extract()`, sin ejecutar comandos del catálogo ni leer/modificar la base local. Se cuentan filas de producto, incluyendo caja abierta, remates y descontinuados tras retirar el prefijo de condición, como hace el conversor local. El Excel contiene **134 fuentes y 176 disipadores/enfriamientos**; ese universo no es el de 170 disipadores mencionado para la base local. No se aplicó la derivación a productos persistidos.

| Tabla / dato                               | Filas con origen `tabla` | Pendientes o con otro origen |
| ------------------------------------------ | -----------------------: | ---------------------------: |
| `PsuTable::CONNECTORS`: ambos conectores   |                 83 / 134 |                           51 |
| `CoolerTable`: sockets                     |                106 / 176 |                           70 |
| `CoolerTable`: capacidad térmica y sockets |                 18 / 176 |                          158 |

Por tanto, **106 enfriamientos reciben al menos un campo de `CoolerTable`**; 18 reciben ambos campos críticos de tabla y 88 reciben solo sockets. Esto no confirma automáticamente los otros 158: el TDP permanece pendiente salvo que exista otro origen confirmado. El extractor completo conserva además los reconocimientos por nombre y las tablas de R14 que no cambian en este encargo.

La cobertura de conectores incluye Corsair RM/RMe/RMx/CX/HX/SF por referencia, Cooler Master MWE/G700/G800, XPG Core Reactor II/Pylon, EVGA BQ/SuperNOVA, Gigabyte UD/PG5, MSI MAG/MPG, ASUS Prime/TUF/ROG, DeepCool PL/PM/PX, NZXT C Bronze, Sharkoon SHP y Thermaltake GF3. No se extrapolan conectores a las revisiones no incluidas de HX1500i, RM1200e, MSI PLS/GN/GS/AI/PRO, DeepCool PQ, XPG Probe/Pylon II/Pymcore, Sharkoon Rebel P20, Thermaltake GT/PT/TF3/BX1, InWin, Gamdias, fuentes genéricas y demás referencias sin fila de conectores: sus 8 pines quedan `supuesto`.

`CoolerTable` cubre DeepCool AK/AG/AS500 y AIO LS/LT/LD/LE/LP/LQ; Cooler Master Hyper 212 y MasterLiquid Core II; Corsair H100i Pro XT, iCUE Link, Titan y Nautilus; ASUS Ryuo III/Strix LC III/ProArt; MSI CoreLiquid A12/A13/A15; NZXT T120/Kraken Elite; Thermaltake Astria/LA/TH V3; XPG Levante II, HYTE Q60, Sharkoon S90 e InWin MR. Las familias o revisiones sin fila conservan la extracción del nombre y supuestos. No se consultaron servicios externos ni se descargaron fichas durante esta entrega. Queda pendiente ampliar referencias sin dato tabulado y confirmar los supuestos antes de publicar sugerencias con esas piezas.

### Archivos y verificación

Creado: `app/Support/Hardware/CoolerTable.php`.

Modificados: `app/Models/Product.php`, `app/Support/Hardware/PsuTable.php`, `app/Support/Hardware/SpecExtractor.php`, `tests/Feature/HardwareTest.php`, `tests/Feature/BuilderTest.php`, `tests/Feature/StoreBuildsTest.php` y este documento. No se borraron archivos ni se hicieron commits.

Pasaron `bash ../.delegate/verify.sh types`, `check`, `test` y `build`; `test` ejecutó Pint completo, **91 pruebas PHPUnit (817 aserciones)** y **34 pruebas Vitest**. Las pruebas nuevas cubren datos viejos sin origen, los cuatro orígenes confirmados, mezcla idempotente, conectores críticos y revisiones, la ASUS Aura, una sugerencia con GPU de 8 pines, los tres gabinetes solicitados, abreviaturas negativas, sockets explícitos/genéricos y disipadores con/sin TDP tabulado. Se conservaron las regresiones de Naceb, RAM 4×4, Stylos, 5900XT, A320 y RTX 5080. Los fixtures que representan fichas confirmadas ahora declaran sus orígenes.

No se ejecutaron `verify.sh migrate`/`all`, importaciones, derivaciones ni regeneraciones de equipos contra la base local. Los comandos del catálogo invocados por las pruebas usan SQLite en memoria, según `phpunit.xml`.

## R15

### Fichas e imágenes de Open Icecat

`catalogo:icecat` consulta Open Icecat en español con el usuario de `services.icecat.username` (`ICECAT_USERNAME`). Sin usuario muestra un aviso y termina sin consultas. Retira únicamente las letras iniciales del SKU, conserva ceros iniciales y acepta GTIN de 8 a 14 dígitos. Cuando el GTIN no trae datos, o no es válido, intenta la marca del producto y el último token del nombre como número de parte. Cada petición tiene timeout de 30 segundos; entre consultas reales a la API espera 200 ms.

La caché vive en `storage/app/icecat/`, separada por cuenta y consulta mediante un hash, sin escribir el usuario en los JSON ni en los reportes. Guarda los datos, fecha y código de respuesta. Los aciertos se reutilizan hasta `--refrescar`; las negativas se reintentan al cumplir 30 días. Un timeout queda con código 0. Un HTTP 200 sin ficha se considera negativo, conservando `statusCode` si la API lo proporciona. También se cachea el intento por número de parte. Las imágenes se descargan únicamente cuando se solicita `--imagenes` y el producto no tiene ninguna foto.

El mapeo usa los nombres de las muestras del piloto para tarjeta madre, RAM, tarjeta de video y fuente. Normaliza DDR, sockets, formatos, certificaciones y conectores a los valores del configurador; suma conectores PCIe de 8 y de 6+2 pines, sin confundirlos con EPS. El diseño de RAM determina módulos y capacidad total del kit. Reconoce consumo y TDP de GPU, fuente mínima recomendada y longitud con coma decimal. Gabinetes y disipadores tienen mapeos conservadores de formatos/fuente incluida/largo de GPU y sockets/TDP; al no haber muestras de esos slots, sus pruebas usan features sintéticas identificadas como tales. CPU y almacenamiento se pueden consultar y recibir imágenes; esta entrega no agrega un mapeo de especificaciones para ellos. No se inventan campos oficiales ausentes ni niveles internos de rendimiento.

La mezcla asigna origen `icecat` y conserva siempre cada campo `manual`. Las discrepancias de identidad contra valores `nombre` se guardan en `spec_conflicts` sin reemplazar el valor actual: capacidad/módulos de RAM, watts de fuente, VRAM de GPU y chipset/socket de tarjeta madre. También se protege el socket derivado por tabla desde el chipset del nombre. Los conflictos persisten entre corridas y cuentan como campos pendientes incluso cuando el resto de la ficha está completo. El editor muestra actual e Icecat junto a la ficha; **Confirmar ficha** limpia los conflictos y marca los valores presentes como manuales. Guardar o enviar `spec_conflicts` desde el navegador no los limpia.

`WebpImage::storeContent()` comparte con las subidas la conversión GD, orientación EXIF, reducción a 1600 px y respaldo JPEG/PNG. Se usa el original `Pic` de cada entrada de `Gallery`, en su orden, con respaldo a las reducciones si no hay original. Las descargas fallidas o el contenido inválido se saltan; se guardan hasta seis fotos válidas en `uploads/products` con `sort` consecutivo. Los productos que ya tienen fotos conservan su galería completa y no descargan imágenes.

El extractor del nombre también reconoce `2XDDR4` y `4XDDR5` como `memory_type` con origen `nombre`; la regresión solicitada de ASUS A520M-K devuelve DDR4 y dos ranuras.

### Cómo correrlo (pendiente para el piloto)

Primero aplicar la migración **aditiva** con `php artisan migrate` y comprobar que `ICECAT_USERNAME` esté configurado. Ejemplos para quien corra el piloto, no ejecutados contra la base local en esta entrega:

```bash
export PATH="$PWD/bin:$PATH" npm_config_cache=/tmp/npm-cache
php artisan catalogo:icecat --slot=gpu --slot=ram --limit=40 --simular
php artisan catalogo:icecat --imagenes
php artisan catalogo:icecat --slot=motherboard --refrescar
```

Sin `--slot` procesa todos los slots del configurador, en orden de ID. `--slot` se puede repetir y `--limit` limita el total de productos consultados. El reporte agrupa por slot y marca: productos encontrados por GTIN/número de parte, intentos sin datos por código 403/404/otros, campos actualizados, conflictos e imágenes agregadas. Los negativos incluyen intentos leídos de caché y un producto puede acumular un negativo de GTIN antes de encontrarse por número de parte.

`storage/app/icecat/pendientes.csv` contiene ID, SKU, nombre, slot, marca, campos pendientes y conflictos. Incluye todos los pendientes de los slots seleccionados, también los que quedaron fuera de `--limit`. En simulación proyecta la ficha resultante para los productos consultados: no guarda productos ni fotos ni llama a `equipos:revisar`, pero sí actualiza caché y CSV. Una corrida normal llama a `equipos:revisar` al terminar; no regenera sugerencias por su cuenta.

### Archivos y verificación

Creados: `app/Support/Hardware/{Icecat,IcecatSpecs}.php`, `app/Console/Commands/EnrichIcecat.php`, `database/migrations/2026_11_04_000002_add_spec_conflicts_to_products.php`, `tests/Feature/IcecatTest.php` y seis fixtures recortados en `tests/fixtures/icecat/` (tarjeta madre 1947, RAM 228/2576, GPU 3363/3382 y fuente 277). Cada fixture conserva solamente los features usados, identificación mínima y dos imágenes de Gallery.

Modificados: `.env.example`, `config/services.php`, `app/Models/Product.php`, `app/Support/Hardware/SpecExtractor.php`, `app/Support/WebpImage.php`, `app/Http/Controllers/Admin/AdminController.php`, `resources/js/pages/admin/ProductForm.vue` y este documento. No se borraron archivos, agregaron paquetes ni hicieron commits.

Pasaron `bash ../.delegate/verify.sh types`, `check`, `test` y `build`: **107 pruebas PHPUnit (968 aserciones)** y **34 pruebas Vitest**. `test` ejecutó Pint completo; `git diff --check` pasó. Las 16 pruebas nuevas usan SQLite en memoria, `Http::fake`, discos de prueba y pausas simuladas. Cubren los mapeos, GTIN con prefijos/ceros iniciales, respaldo por número de parte, 403/404, timeout, caché persistente entre instancias, expiración y refresco, protección manual, conflictos de identidad, confirmación desde admin, filtros/límite/CSV, simulación, revisión final y fotos sin duplicar, con máximo seis y tolerancia a errores.

Pendiente: aplicar la migración y ejecutar/revisar el piloto con la cuenta del dueño, además de revisión visual del aviso en el editor. No se consultó Icecat de verdad ni se ejecutaron importaciones, derivaciones o comandos de catálogo/equipos contra la base local. No se usó `verify.sh migrate`/`all`; las muestras originales y la caché permanecen ignoradas por git.
