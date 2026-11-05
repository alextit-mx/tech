# Encargo R16: pantalla "Fichas pendientes" en Admin + tipo de disco + CONTINUAR.md

## Objetivo
El personal debe poder completar rápido las fichas técnicas que bloquean los armados sugeridos (sobre todo gabinetes
y disipadores, que Icecat gratis no trae). Una pantalla en Admin muestra, por tipo de pieza, solo los productos con
ficha pendiente y solo los campos críticos que les faltan; al guardar quedan como `manual` (confirmados).

## IMPORTANTE: hay una carga de Icecat corriendo sobre la base de desarrollo
- NO ejecutes `php artisan migrate`, `db:seed`, `verify.sh migrate` ni `verify.sh all`, ni nada que escriba en
  `database/database.sqlite`. Tampoco `catalogo:icecat`, `catalogo:fichas` (sin `--simular`), `equipos:*`
  ni `productos:importar`. Las pruebas usan SQLite en memoria (phpunit.xml): esas sí.
- No hace falta migración (todo vive en `specs`, `spec_sources`, `spec_conflicts`). Si de verdad necesitas una,
  créala pero no la ejecutes y avísalo en tu reporte.

## Lee primero
- `sistema/AGENTS.md` — reglas de código.
- `sistema/app/Models/Product.php` — `CRITICAL_SPECS`, `estimatedSpecs()`, `specsVerified()`.
- `sistema/app/Http/Controllers/Admin/AdminController.php` — `productQuery()` y el chip `pending_specs`, el
  "Confirmar ficha" del editor y cómo se guardan `spec_sources`.
- `sistema/resources/js/pages/admin/Products.vue` y el editor de producto — estilo de Admin, insignias de origen y el
  esquema de campos por tipo (reúsalo para saber tipo, unidades y opciones de cada campo; no lo dupliques).
- `sistema/app/Support/Hardware/SpecExtractor.php` y `IcecatSpecs.php` — para el punto 3.
- `sistema/app/Console/Commands/ReviewBuilds.php` — para el botón "Revisar armados".
- Diseño: `/mnt/c/Users/tit/.claude/skills/taste-skill/` (reglas de pantallas de app). Que se vea como el resto
  del Admin, no inventes otro estilo.

## Cambios
1. **Ruta y menú.** `GET /admin/fichas` (nombre `admin.specs.pending`) y `PATCH /admin/fichas/{product}`
   (`admin.specs.update`), con el mismo permiso que editar productos. Entrada "Fichas pendientes" en el menú de Admin,
   junto a Productos, con el total pendiente como contador.
2. **Página `resources/js/pages/admin/PendingSpecs.vue`.**
   - Pestañas por tipo con su número de pendientes: Gabinetes, Disipadores, Fuentes, Tarjetas de video,
     Procesadores, Memoria RAM, Tarjetas madre, Almacenamiento. Abre por defecto la de más pendientes.
   - Lista solo productos del tipo con `estimatedSpecs() !== []`. Orden: publicados primero, luego por existencias
     (`stock + stock_24h`) de mayor a menor, luego nombre. 25 por página. Buscador por nombre/SKU/marca.
     Filtro rápido "Solo con existencias".
   - Cada fila: miniatura, nombre, SKU, marca, existencias, enlace "Buscar ficha" (nueva pestaña a una búsqueda web
     de `marca + modelo/SKU + especificaciones`) y enlace al editor completo.
   - Campos: solo los de `CRITICAL_SPECS` de ese tipo que estén pendientes, precargados con el valor estimado y la
     insignia de origen actual ("estimado", "por nombre"…). Si hay conflicto en `spec_conflicts`, muestra los dos
     valores (ej. "Icecat: 320 mm · Nombre: 360 mm") para elegir uno o escribir otro.
   - Controles según el esquema: lista para valores cerrados (socket, tipo de memoria, formato, certificación,
     conector de poder, tipo de disco), número con unidad (mm, W, GB, ranuras), casillas para listas
     (`sockets`, `form_factors`), sí/no para `includes_psu`.
   - Botón "Guardar" por fila (Enter también guarda). Al guardar, si la ficha queda completa la fila desaparece con
     un aviso breve; si no, se queda mostrando lo que aún falta. Errores de validación junto al campo.
   - Arriba, botón "Revisar armados" que corre la misma lógica de `equipos:revisar` (extráela a un servicio si hace
     falta, y que el comando la use) y muestra el resultado (cuántos quedaron publicados y cuántos no, con motivo).
3. **Guardado (`PATCH`).** Acepta solo campos de `CRITICAL_SPECS` del tipo del producto; valida tipo, rango y
   opciones. Escribe el valor en `specs`, marca `spec_sources[campo] = 'manual'` y quita de `spec_conflicts` los
   conflictos de esos campos. No toca otros campos. Usa el registro de actividad de productos si ya existe.
4. **Tipo de disco.** Que `storage.type` se llene: en `IcecatSpecs` (interfaz/tipo de unidad: NVMe, PCIe, M.2,
   SATA, HDD) y en `SpecExtractor` desde el nombre (`NVME`, `M.2 NVMe`, `PCIE` → NVMe; `SATA`, `2.5"` en SSD → SATA;
   `HDD`, `DISCO DURO`, `7200RPM`, `3.5"` → HDD). Usa los valores que ya define el esquema. Agrega casos a las pruebas
   y a los fixtures existentes; no rompas los actuales.
5. **`CONTINUAR.md`** (raíz). Agrega a la tabla de rondas R14, R14b, R15 y R16 (una línea cada una) y en la parte de
   comandos: `catalogo:fichas {--slot=} {--simular}`, `catalogo:icecat {--slot=*} {--limit=} {--imagenes}
   {--simular} {--refrescar}`, `equipos:revisar`, que `productos:importar` ya no lleva `--specs`, que
   `ICECAT_USERNAME` va en `sistema/.env` (nunca la contraseña) y la nueva pantalla "Fichas pendientes".
   Cosas que siguen pendientes: capturar gabinetes y disipadores (marcas que Icecat gratis no trae).
6. **Pruebas** `tests/Feature/PendingSpecsTest.php`: lista solo pendientes del tipo y en el orden pedido; guardar
   pone `manual`, limpia conflictos y saca el producto de la lista; rechaza valores inválidos y campos que no son del
   tipo; sin permiso da 403; "Revisar armados" responde bien. Más los casos de tipo de disco.

## Terminado cuando
- `bash .delegate/verify.sh types check test build` pasa completo (incluye `test:js`).
- Textos en español de México. Funciona en pantalla de laptop (1280 px) y no se rompe en celular.

## No hagas
- Lo de la sección IMPORTANTE (nada que escriba en la base de desarrollo).
- No toques `index.html`, `assets/` ni `sitio/` de la raíz, ni el motor de compatibilidad (`Compatibility`,
  `rules.ts`) salvo lo del tipo de disco.
- No hagas commit.
