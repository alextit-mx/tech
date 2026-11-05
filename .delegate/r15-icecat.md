# Encargo R15: fichas técnicas e imágenes oficiales desde Open Icecat

## Objetivo
Llenar las fichas de las piezas del configurador con datos oficiales de Open Icecat (gratis, por código de barras) y
agregar sus imágenes a los productos que no tienen foto, sin pisar lo que el equipo capturó a mano. Así las piezas
pasan de "Ficha pendiente" a confirmadas y los armados sugeridos pueden volver a salir. Ya se probó en piloto: con la
cuenta del dueño, Icecat encontró 18/20 tarjetas de video, 17/20 RAM, 14/20 tarjetas madre y 4/20 fuentes; Intel, AMD,
Corsair y Cooler Master responden 403 (no están en el plan gratuito).

## Lee primero
- `docs/r14-hardware.md` (R14 y R14b), `app/Support/Hardware/SpecExtractor.php`, `app/Models/Product.php`
  (`spec_sources`, `estimatedSpecs()`, `specsVerified()`), `app/Console/Commands/DeriveSpecs.php` (misma mezcla campo
  por campo), `app/Support/WebpImage.php`, `app/Models/ProductImage.php`.
- Respuestas reales de Icecat del piloto (53 JSON, nombre `{slot}-{product_id}.json`, ignoradas por git):
  `storage/app/icecat-muestras/`. Úsalas para sacar los nombres exactos de los campos en español y como base de 4–6
  fixtures recortados para las pruebas (`tests/fixtures/icecat/`, solo los grupos de features que uses y 2 imágenes).

## 0. Corrección del extractor
`memory_type` de tarjetas madre queda `supuesto` en 166 de 182 porque "2XDDR4"/"4XDDR5" no se reconoce (no hay límite
de palabra entre "X" y "DDR"). Debe salir `nombre`. Prueba con "Mother AMD A520M-K ASUS Prime AM4 2XDDR4 64GB M-ATX
90MB1500-M0EAY0" → DDR4, 2 ranuras.

## 1. Cliente y configuración
- `config/services.php`: `icecat.username` = `env('ICECAT_USERNAME')`; agrega `ICECAT_USERNAME=` vacío a
  `.env.example`. Sin usuario, el comando avisa y termina. El `.env` local ya lo tiene; no lo imprimas.
- `app/Support/Hardware/Icecat.php`: `GET https://live.icecat.biz/api/` con `UserName`, `Language=es` y `GTIN`; si no hay
  datos, con `Brand` + `ProductCode` (número de parte: último token del nombre). GTIN = el SKU sin letras de prefijo
  (`OB`, `REM`, etc.) si quedan 8–14 dígitos. Timeout 30 s, pausa de 200 ms entre consultas.
- Caché de respuestas en `storage/app/icecat/` (también las negativas con su código; las negativas se reintentan después
  de 30 días). Una segunda corrida no vuelve a consultar lo que ya está en caché, salvo con `--refrescar`.

## 2. Mapeo a specs (origen `icecat`)
Por slot, con los nombres reales de las muestras (ejemplos vistos): tarjeta madre "Número de ranuras de memoria",
"Memoria interna máxima", "Chipset" ("AMD A520" → A520), "Socket de procesador" ("Zócalo AM4"), "tipos de memoria
compatibles" ("DDR4-SDRAM"), "Factor de forma" ("micro ATX" → mATX), "Número de ranuras M.2 (M)"; RAM "Diseño de
memoria (módulos x tamaño)" ("2 x 8 GB"), "Memoria interna", "Tipo de memoria interna", "Velocidad de memoria del
reloj"; tarjeta de video "Longitud" (mm), "Consumo energético" (W), "Conectores de energia suplementario" ("1x 16-pin",
"2x 8-pin" → `power_connector`), fuente recomendada si viene; fuente "Potencia total", "Certificación 80 PLUS",
"Conectores de poder PCI Express (8 pin)", "Conectores de alimentación PCI Express (16 pines)"; y lo que haya para
gabinete (largo máximo de tarjeta de video, fuente incluida, formatos) y disipador (TDP, sockets). Normaliza a los
valores de `BuilderSpecs` (formatos, certificaciones, conectores).

## 3. Mezcla y conflictos
- `icecat` reemplaza `nombre`, `tabla` y `supuesto`; nunca `manual`.
- Conflicto: si un dato de identidad que vino del nombre no coincide con Icecat (`capacity_gb` o `modules` de RAM,
  `watts` de fuente, `vram_gb` de tarjeta de video, `chipset` o `socket` de tarjeta madre), **no** lo reemplaces: el
  código de barras puede ser de otra variante. Guarda el conflicto (columna nueva `products.spec_conflicts` json:
  `[{field, actual, icecat}]`); un producto con conflictos no tiene `specsVerified()`. En `ProductForm.vue` se ven los
  conflictos junto a la ficha y "Confirmar ficha" los limpia. Ejemplo real: "RAM ADATA D41 DDR4 8GB 2666…" dice 8 GB
  en el nombre e Icecat dice 2 × 8 GB.

## 4. Imágenes
- Solo para productos **sin fotos**: hasta 6 imágenes de `Gallery` (la de mayor resolución de cada una), en el orden de
  Icecat, convertidas con la misma lógica de `WebpImage` (agrega un método que reciba un archivo o contenido descargado,
  sin duplicar la conversión) y registradas como `ProductImage` con `sort`. Descarga con timeout y salta las que fallen.

## 5. Comando
`php artisan catalogo:icecat {--slot=*} {--limit=} {--imagenes} {--simular} {--refrescar}`: por defecto todos los slots
del configurador. Reporte por slot y marca: encontrados (GTIN / número de parte), no encontrados por código (403, 404),
campos llenados, conflictos e imágenes agregadas; al final una lista CSV en `storage/app/icecat/pendientes.csv` con lo
que sigue con ficha pendiente y qué campos faltan. Si no es simulación, al final llama `equipos:revisar`.

## Terminado cuando
- `bash ../.delegate/verify.sh types`, `check`, `test` y `build` pasan (incluye `test:js`).
- Pruebas con `Http::fake` y los fixtures: mapeo por slot, GTIN con prefijo, respaldo por número de parte, 403 y 404,
  caché (la segunda corrida no hace HTTP), no pisa `manual`, conflicto de RAM, imágenes solo sin fotos y máximo 6.
- Reporta en `docs/r14-hardware.md` (sección "R15") qué hiciste y cómo correrlo.

## No hagas
- No consultes Icecat de verdad ni corras `catalogo:icecat`, `catalogo:fichas`, `productos:importar`,
  `equipos:sugeridos` o `equipos:revisar` contra la base local: Claude corre el piloto. No uses `verify.sh migrate`/`all`.
- No subas a git las muestras ni la caché (`storage/` ya está ignorado). No toques lo que no está en esta lista.
