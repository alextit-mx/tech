# Encargo R14b: correcciones a R14 (fichas confirmadas, fuentes, gabinetes y disipadores)

Revisé R14 contra el catálogo local con `SpecExtractor::extract()` y `catalogo:fichas --simular`. Lo que funciona se
queda (casos de la fuente Naceb, RAM 4×4, Stylos C/fuente, 5900XT, A320, RTX 5080). Corrige solo esto:

## 1. Un campo crítico sin origen NO es ficha confirmada
Hoy `specsVerified()` acepta valores existentes sin `spec_sources`: con la base actual (antes de derivar) da
coolers 170/170 y RAM 73/73 confirmadas, con datos del lector viejo (p. ej. sockets genéricos y 180 W para todos los
disipadores). Regla: un campo crítico cuenta como confirmado solo si su origen es `manual`, `icecat`, `tabla` o
`nombre`. Sin origen = pendiente. Puedes conservar el valor viejo para el configurador, pero marca su origen como
`supuesto` al derivar si el extractor no produce ese campo (hoy 48 disipadores se quedan con `sockets` sin origen).

## 2. Conectores de las fuentes
Las 134 fuentes quedan con `pcie_8pin = 0` (`supuesto`) y la regla `gpu_power_connector` depende de ese dato, así que
ningún armado con tarjeta de 8 pines puede salir sugerido. Agrega `pcie_8pin` a los campos críticos de psu y llena en
`PsuTable`, con valores oficiales del fabricante y origen `tabla`, `pcie_8pin` y `pcie_16pin` por modelo o línea de
las fuentes de `LP_NOVIEMBRE.xlsx` (Corsair RM/RMe/RMx/CX/HX, Cooler Master MWE, XPG, EVGA, Gigabyte, MSI MAG/MPG,
ASUS Prime/TUF/ROG, etc.). Revisa la ASUS ROG Strix 850W Gold Aura (ROG-STRIX-850G-AURA-GAMING): su ficha oficial
dice ATX 3.0, 1 conector de 16 pines y 3 de 8 pines. Donde no haya dato oficial, se queda `supuesto`.

## 3. Gabinetes
- Un token de potencia en el nombre (`500W`, `600W`…) significa que trae fuente: `includes_psu = true` (`nombre`), salvo
  "S/FUENTE", "SIN FUENTE" o "S/F" → `false` (`nombre`).
- `MATX`, `M-ATX`, `MICRO ATX`, `MICRO-ATX` y `MINI TORRE` → `form_factors` mATX/ITX; `ITX` o `MINI-ITX` → ITX;
  `ATX` o `E-ATX` explícito → los que correspondan, con origen `nombre`.
- Pruebas con: "Gabinete Acteck Kiruna II Negro MATX 500W Acero AC-935685" (mATX/ITX, con fuente), "Gabinete Acteck
  Drako GM450 RGB Negro ATX 500W AC-935715" (ATX, con fuente) y "Gabinete ADATA XPG Invader X Btfmt Blanco ARGB ATX
  S/fuente Cristal" (ATX, sin fuente).

## 4. Disipadores
- `sockets` desde el nombre: sockets explícitos (AM4, AM5, LGA1700, LGA1851, LGA1200, LGA1151) → `nombre`; "Socket
  AMD"/"AMD" → AM4 y AM5 `supuesto`; "Intel" → LGA1700, LGA1851 y LGA1200 `supuesto`.
- `CoolerTable` (nueva) con los modelos de enfriamiento de `LP_NOVIEMBRE.xlsx`: `max_tdp_w` y `sockets` con el valor
  oficial del fabricante (p. ej. Deepcool AK620 y AG620, AK400, AG400, AS500; AIO de 240/360 mm de las marcas de la
  lista) con origen `tabla` y comentario de la fuente. Si el fabricante no publica TDP, se queda `supuesto` 95 W.
  Al final reporta cuántos disipadores quedaron con `tabla`.

## Terminado cuando
- `bash ../.delegate/verify.sh types`, `check`, `test` y `build` pasan (incluye `test:js`).
- Pruebas de los puntos 1–4 (incluye: producto con specs viejas sin origen → `specsVerified()` falso).
- Reporta en `docs/r14-hardware.md` (sección nueva "R14b") qué cambió y la cobertura de `PsuTable` y `CoolerTable`.

## No hagas
- No corras `verify.sh migrate`/`all` ni `catalogo:fichas`, `productos:importar`, `equipos:sugeridos` o
  `equipos:revisar` contra la base local. No consultes servicios externos. No toques lo que no está en esta lista.
