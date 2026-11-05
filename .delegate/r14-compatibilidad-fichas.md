# Encargo R14: motor de compatibilidad, fichas técnicas confiables y armados sugeridos que siempre funcionen

## Objetivo
Un armado sugerido de Arma tu PC salió con piezas que no funcionan juntas (caso real abajo). Causas: specs mal leídas
del nombre (fuente "P4600W 600W" guardada como 4600 W; RAM "16GB4X4GB" como 1 módulo; gabinete "C/fuente 500W" sin
marcar; disipador básico con 180 W), reglas que faltan (módulos vs ranuras, chipset vs procesador, calidad de fuente,
conector de 16 pines, gabinete con fuente) y un recomendador que toma "la más barata que pase" y, si nada cumple,
**rellena con la mejor disponible aunque no alcance**. Al terminar:
- Un solo motor de compatibilidad en PHP (fuente de verdad) con 3 niveles, espejado en `rules.ts` y sincronizado
  con casos de prueba compartidos.
- Specs derivadas en PHP con tablas de referencia y con el **origen de cada dato**.
- Armados sugeridos calculados en el servidor: **cero problemas de cualquier nivel y solo piezas con ficha confirmada**;
  si no se puede, se baja un escalón o se dice "no disponible hoy". Nunca se rellena con algo que no alcanza.

## Lee primero
- `AGENTS.md`, `../CONTINUAR.md`.
- `app/Support/BuildValidator.php`, `app/Support/BuildSuggester.php`, `app/Support/BuilderSpecs.php`,
  `app/Models/Build.php`, `app/Models/Product.php`, `app/Console/Commands/ImportProducts.php`,
  `app/Console/Commands/SuggestBuilds.php`, `app/Http/Controllers/Site/BuilderController.php`,
  `app/Http/Controllers/Admin/BuildController.php`, `app/Http/Controllers/Admin/AdminController.php` (chips del listado).
- `resources/js/builder/rules.ts` y `resources/js/builder/BuildLab.vue` (presets `uso`/`presupuesto`, `applyPreset`,
  `startFresh`, arranque con `?uso=&presupuesto=`).
- `resources/js/pages/admin/ProductForm.vue` (sección de specs), `resources/js/pages/admin/Products.vue` (chips),
  `resources/js/pages/admin/BuildForm.vue`.
- `../herramientas/lista_precios.py` y la lista real `../LP_NOVIEMBRE.xlsx` (solo lectura; úsala para saber qué
  modelos de procesador, chipset, tarjeta de video y fuente existen en el catálogo).

## 1. Datos: specs con origen
- Migración: columna `products.spec_sources` (json, nullable): `{campo: origen}` con origen
  `manual` > `icecat` > `tabla` > `nombre` > `supuesto` (`icecat` lo usará R15; déjalo previsto).
- `BuilderSpecs::SCHEMAS` agrega (opcionales, para no invalidar productos existentes) y sus etiquetas en español:
  - cpu: `series` (p. ej. "Ryzen 5000", "Ryzen 7000", "Core 14", "Core Ultra 200"), `power_w` (consumo máximo real
    del paquete: AMD PPT, Intel PL2/MTP). `tdp_w` sigue siendo el TDP oficial.
  - motherboard: `chipset`, `ram_slots`, `max_ram_gb`.
  - gpu: `recommended_psu_w`, `power_connector` (`ninguno`, `8-pin`, `2x8-pin`, `3x8-pin`, `16-pin`).
  - psu: `certification` normalizada a `Sin certificación`, `80+`, `80+ Bronze`, `80+ Silver`, `80+ Gold`,
    `80+ Platinum`, `80+ Titanium`; `pcie_16pin` (bool), `pcie_8pin` (int).
  - case: `includes_psu` (bool).
- `Product::estimatedSpecs()`: campos críticos que faltan o son `supuesto`. `Product::specsVerified()`: ninguno.
  Críticos: cpu socket, series, tdp_w, power_w, igpu, tier · motherboard socket, chipset, memory_type, form_factor,
  ram_slots · ram memory_type, capacity_gb, modules · gpu tdp_w, recommended_psu_w, length_mm, power_connector, tier ·
  psu watts, certification, pcie_16pin · cooler sockets, max_tdp_w · case form_factors, max_gpu_length_mm,
  includes_psu · storage type, capacity_gb.

## 2. Extractor de specs en PHP (`app/Support/Hardware/`)
- `SpecExtractor::extract(string $slot, string $name): array{specs, sources}`. Lo explícito en el nombre es `nombre`;
  lo de tablas es `tabla`; cualquier valor por defecto es `supuesto` y **conservador**.
- Tablas (clases PHP con constantes, comentadas con la fuente oficial). Cubre **todos** los modelos que aparecen en
  `LP_NOVIEMBRE.xlsx` y reporta al final los que no reconociste:
  - `CpuTable`: modelo → socket, cores, threads, tdp_w, power_w, igpu, series, tier. Ej.: Ryzen 9 5900XT = AM4, 16/32,
    105 W, PPT 142 W.
  - `GpuTable`: chip → tdp_w (TBP), recommended_psu_w (fuente recomendada por NVIDIA/AMD/Intel), tier, conector
    típico (como `supuesto`, depende del fabricante). Ej.: RTX 5080 = 360 W, fuente 850 W.
  - `ChipsetTable`: chipset → socket, clase 1–4 (1: A320, A620, H610, H810; 2: A520, B450, H510, B560; 3: B550, B650,
    B760, B860; 4: X570, X670, X870, B850, Z690, Z790, Z890), series soportadas y series que piden BIOS
    (A320: Ryzen 1000–3000, BIOS 5000; serie 600 de Intel: Core 12, BIOS 13–14; etc.).
  - `PsuTable`: líneas conocidas (Corsair RM/RMe/RMx/CX/HX, Cooler Master MWE, XPG, EVGA, Gigabyte, MSI MAG/MPG,
    ASUS Prime/TUF/ROG…) → certificación y si traen 16 pines nativo (ATX 3.x).
- Reglas de nombre que deben quedar: watts = número de 3–4 dígitos seguido de "W" como token propio (no parte de un
  código de modelo), entre 300 y 1600; RAM "NxMGB" → `modules` = N y `capacity_gb` = N×M, velocidad = el número de
  MHz real del nombre; "C/FUENTE", "CON FUENTE" o "INCLUYE FUENTE" → `includes_psu`; chipset y "NxDDR4/NxDDR5" de la
  tarjeta madre → `chipset` y `ram_slots`.
- Supuestos conservadores (siempre `supuesto`): disipador de aire sin dato → 95 W; gabinete mini o micro → 280 mm y
  solo mATX/ITX, media torre → 320 mm; tarjeta de video sin largo → 340 mm; fuente sin 16 pines declarado → false.
- `productos:importar` usa el extractor (deja de leer la columna `specs` del CSV) y mezcla **campo por campo**: solo
  reemplaza campos cuyo origen sea `nombre`, `tabla`, `supuesto` o vacío; nunca `manual` ni `icecat`. Quita la opción
  `--specs`. En `lista_precios.py` quita las funciones de specs y la columna `specs`; el resto queda igual.
- Comando `php artisan catalogo:fichas {--slot=} {--simular}`: vuelve a derivar las specs de los productos existentes
  con la misma mezcla y muestra por slot: cuántos cambian, cuántos quedan con ficha pendiente y ejemplos de cambios.
  No lo corras contra la base local; Claude lo corre.

## 3. Motor de compatibilidad (`app/Support/Hardware/Compatibility.php`)
`Compatibility::check(array $parts): list<array{level, code, message, slots}>`. `$parts` = slot → `['specs' => [...],
'estimated' => [campos supuestos]]`. Niveles: `incompatible` (no funciona), `riesgo` (funciona con condiciones),
`desbalance` (funciona pero está disparejo). Si un problema `incompatible` depende de un campo `estimated`, se reporta
como `riesgo` con "(dato por confirmar)". Mensajes en español de México, concretos, con números. Reglas y códigos:

| Código | Nivel | Regla |
|---|---|---|
| `socket` | incompatible | cpu.socket ≠ motherboard.socket (y lo que ya existe de memoria por plataforma sin tarjeta) |
| `memory_type` | incompatible | ram.memory_type ≠ motherboard.memory_type |
| `form_factor` | incompatible | motherboard.form_factor ∉ case.form_factors |
| `ram_slots` | incompatible | ram.modules > motherboard.ram_slots |
| `ram_max` | incompatible | ram.capacity_gb > motherboard.max_ram_gb |
| `chipset_cpu` | incompatible / riesgo | series no soportada por el chipset; si está en "pide BIOS" → riesgo |
| `board_class` | desbalance | clase del chipset < tier del cpu − 2, o power_w del cpu > 88 W (clase 1), 142 W (clase 2), 200 W (clase 3) |
| `cooler_socket` | incompatible | cpu.socket ∉ cooler.sockets |
| `cooler_power` | incompatible | cooler.max_tdp_w < cpu.power_w (si falta power_w: tdp_w × 1.35) |
| `gpu_length` | incompatible / riesgo | length_mm > max_gpu_length_mm → incompatible; a menos de 10 mm → riesgo |
| `psu_watts` | incompatible / desbalance | watts < max(gpu.recommended_psu_w, cpu.power_w + gpu.tdp_w + 75) → incompatible; < redondeo a 50 de (cpu.power_w + gpu.tdp_w + 75) × 1.25 → desbalance |
| `psu_quality` | incompatible / desbalance | `Sin certificación` con gpu.tdp_w > 150 → incompatible; gpu tier ≥ 4 con menos de 80+ Gold → desbalance |
| `gpu_power_connector` | incompatible / riesgo | 8-pin requeridos (1/2/3) > psu.pcie_8pin → incompatible; 16-pin sin `pcie_16pin`: si pcie_8pin ≥ ceil(gpu.tdp_w / 150) → riesgo "con el adaptador de la tarjeta", si no → incompatible |
| `case_has_psu` | incompatible / desbalance | case.includes_psu con fuente aparte → desbalance (pagas doble); sin fuente aparte y gpu.tdp_w > 75 → incompatible |
| `no_video` | incompatible | sin gpu y cpu.igpu = false |

- `BuildValidator` queda como fachada del motor (errores = `incompatible`, avisos = los demás) para no romper a quien
  lo usa (`BuilderController::submit`, `Build::errors()`, `BuildController::check`). `submit` sigue bloqueando solo lo
  `incompatible`.
- `rules.ts`: espejo del motor (`issues(build)` con los mismos códigos, niveles y textos; `incompatibility(part, build)`
  = el primer `incompatible` que provocaría la pieza; `warnings()` = riesgo y desbalance). El configurador recibe por
  pieza `estimated` (desde `BuilderController::show`). Quita de `rules.ts` `recommend()` y lo que solo usaba él.
- Casos compartidos: `tests/fixtures/compatibility.json` con al menos 14 casos (cada regla y nivel, uno sin problemas
  y el **caso real** de abajo). Los corren PHPUnit y Vitest (agrega `vitest` como dependencia de desarrollo y el script
  `"test:js": "vitest run"`; prueba en `resources/js/builder/rules.test.ts`). Deben dar exactamente los mismos códigos
  y niveles.

Caso real (specs verdaderas) y lo que debe salir como mínimo: Ryzen 9 5900XT (AM4, Ryzen 5000, 105 W, PPT 142 W,
tier 5) · Gigabyte A320M-S2H (AM4, A320, DDR4, mATX, 2 ranuras, 32 GB) · Corsair Vengeance LPX 4×4 GB DDR4 2666 ·
ASUS Prime RTX 5080 (360 W, fuente 850 W, 306 mm, 16-pin, tier 5) · Naceb 600 W sin certificación (sin 16 pines,
2×8-pin) · Royal Talent F120 (aire, AM4/AM5, 95 W) · gabinete Stylos con fuente de 500 W (mATX/ITX, 300 mm) · Kingston
NV3 4 TB NVMe → `ram_slots`, `chipset_cpu` (riesgo), `board_class`, `cooler_power`, `psu_watts`, `psu_quality`,
`gpu_power_connector`, `case_has_psu`.

## 4. Armados sugeridos en el servidor
- `BuildSuggester::suggest(string $use, string $budget): array{parts, stepped, unavailable, message}`. Candidatos:
  publicados, con precio público, con existencia (inmediata o 24 h) y `specsVerified()`.
- Orden: gpu (si el uso la pide o el cpu no tiene gráficos) → cpu → motherboard → ram → psu → cooler → case → storage.
  Gpu y cpu: la más barata que cumpla el tier objetivo (mismos `TARGETS`, `MEMORY` y `STORAGE_GB` que hoy). Demás
  slots: la más barata que, sumada al armado parcial, dé **cero problemas de cualquier nivel**. RAM: primero kits de 2
  módulos con capacidad ≥ objetivo. Almacenamiento: NVMe primero.
- Si un slot no tiene candidato, prueba el siguiente candidato del slot anterior (máximo 8 por slot). Si no se completa,
  baja el tier objetivo de gpu y cpu un escalón (máximo 2, `stepped = true`). Si aun así no, `unavailable = true` con
  mensaje "Este armado no está disponible con las existencias de hoy". Antes de devolver, `Compatibility::check` del
  armado completo debe estar vacío.
- Ruta `GET /arma-tu-pc/sugerido?uso=&presupuesto=` (throttle) → JSON `{parts: {slot: id}, stepped, unavailable,
  message}`. `BuildLab.vue` la usa en `applyPreset` y en el arranque con `?uso=&presupuesto=` (con estado de carga y
  el mensaje si bajó de escalón o no está disponible); conserva la animación y lo de `startFresh` (timers y
  `scene.reset()`).
- Publicar un equipo armado (`BuildController`) exige cero problemas de cualquier nivel y piezas con ficha confirmada;
  el editor muestra los problemas con su nivel. `equipos:sugeridos` usa `suggest()` y despublica (sin borrar) el que no
  se pueda armar.
- Comando `equipos:revisar`: revisa los equipos publicados y despublica los que tengan cualquier problema o piezas
  sin ficha confirmada; imprime cuáles y por qué. Lo llaman al final `productos:importar` y `catalogo:fichas`.

## 5. Admin
- `ProductForm.vue`: los campos nuevos; junto a cada spec, una etiqueta del origen (Manual, Icecat, Tabla, Del nombre,
  Por confirmar). Guardar marca como `manual` solo los campos que cambiaron. Botón "Confirmar ficha" que marca todos
  los campos actuales como `manual`.
- `Products.vue`: chip "Ficha pendiente" (productos de slots del configurador sin `specsVerified()`) con su conteo.

## Terminado cuando
- `bash ../.delegate/verify.sh types`, `check`, `test` y `build` pasan (incluye `test:js`).
- Pruebas PHPUnit del extractor con estos nombres reales: "Fuente Naceb Evotec P4600W 600W ATX 24PIN 12V" → 600 W;
  "RAM Corsair Vengeance Lpxr DDR4 16GB4X4GB 2666 Rojo CMK16GX4M4A2666C16R" → 4 módulos, 16 GB, 2666;
  "Gabinete Stylos Mini-Torre C/fuente 500W Metal Negro STPGAC5B" → includes_psu; "MB Gigabyte A320M S2H Ryzen Gen 1-3
  DDR4 64GB M-ATX GA-A320M-S2H" → chipset A320; "Procesador AMD Ryzen 9 5900XT 4.8 Ghz AM4" → 105 W, PPT 142 W (tabla).
- Pruebas de `suggest()`: con un catálogo de prueba donde la única fuente suficiente no tiene existencia, devuelve
  armado de un escalón menor o `unavailable`, nunca una fuente que no alcance; el resultado siempre pasa `check()` vacío
  y solo usa piezas verificadas. Prueba de la mezcla campo por campo (no pisa `manual`) y de `equipos:revisar`.
- Las pruebas que ya existen siguen pasando (ajusta las que dependan de lo que cambió, sin perder lo que comprueban).

## No hagas
- No corras `verify.sh migrate` ni `all` (borran el catálogo importado); para tu migración usa `php artisan migrate`.
- No corras `catalogo:fichas`, `productos:importar`, `equipos:sugeridos` ni `equipos:revisar` contra la base local.
- No consultes Icecat ni otros servicios externos (eso es R15). No toques `scene.ts`, la portada `/`, el `index.html` de
  la raíz ni `sitio/`.
- Al final reporta: archivos cambiados, decisiones, modelos del catálogo que las tablas no reconocen y pendientes.
