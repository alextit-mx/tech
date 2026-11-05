# Encargo R17: clientes con datos fiscales y dirección + nuevo PDF de cotización

## Objetivo
1. Registrar clientes "con datos" (empresas o personas que facturan), con empresa, contacto, datos fiscales
   (CFDI 4.0) y dirección, y cotizarles desde el editor de cotizaciones. Mostrador sigue igual de rápido.
2. El PDF de cotización debe verse como la imagen de referencia (formato que ya usa el negocio):
   `/mnt/c/Users/tit/AppData/Local/Temp/claude/16aba57062ee7798/aa9a046e-edf1-4851-8f67-6a1057555fe1/images/1.png`

## IMPORTANTE: hay una carga de Icecat corriendo sobre la base de desarrollo
NO ejecutes `php artisan migrate`, seeders, `verify.sh migrate`/`all` ni comandos `catalogo:*`, `equipos:*` o
`productos:importar`. Crea las migraciones pero **no las corras** en desarrollo (yo las corro después). Las pruebas
usan SQLite en memoria: esas sí. Para revisar el PDF usa una base SQLite temporal propia (archivo en `/tmp`), nunca
`database/database.sqlite`.

## Lee primero
- `sistema/AGENTS.md`, `sistema/docs/plan.md` (secciones de clientes, cotizaciones y PDF).
- `app/Models/{Customer,Quote,QuoteItem,Setting}.php`, `app/Http/Requests/Admin/{CustomerRequest,SettingsRequest}.php`,
  `app/Http/Controllers/Admin/CustomerController.php` y el controlador de cotizaciones.
- `resources/js/pages/admin/{Customers,QuoteEditor,Settings}.vue`, `resources/js/components/CustomerPicker.vue`.
- `resources/views/pdf/{quote,service-order,warranty}.blade.php` y `pdf/partials/*` (dompdf vía `App\Support\Pdf`).
- Diseño: `/mnt/c/Users/tit/.claude/skills/image-to-code/` y `taste-skill/` (reglas de pantallas de app).

## Parte 1: clientes
1. **Migración** (nullable todo) en `customers`: `kind` (`mostrador` | `registrado`, por defecto `mostrador`; los
   existentes quedan `mostrador`), `company` (empresa / nombre comercial), `position` (puesto del contacto),
   `legal_name` (razón social), `tax_regime` (clave SAT c_RegimenFiscal), `cfdi_use` (clave c_UsoCFDI), `tax_zip`
   (CP fiscal), `street`, `ext_number`, `int_number`, `neighborhood` (colonia), `city` (municipio/alcaldía), `state`,
   `zip`, `address_notes`. `name` sigue siendo el nombre del contacto (o del cliente de mostrador).
2. **Catálogos SAT** en una clase de soporte (`App\Support\Sat`): regímenes fiscales vigentes con su texto y si
   aplican a persona física, moral o ambas; usos de CFDI vigentes; los 32 estados de México.
3. **Validación** (`CustomerRequest`): para `registrado` son obligatorios `company` o `legal_name`, `rfc`,
   `legal_name`, `tax_regime` y `tax_zip`. RFC con formato válido (12 caracteres persona moral, 13 física, admite
   `XAXX010101000` y `XEXX010101000`), en mayúsculas y único entre clientes registrados. El régimen debe corresponder
   al tipo de persona del RFC. CP de 5 dígitos. Estado del catálogo. Mostrador: como hoy.
4. **Admin → Clientes**:
   - Formulario por secciones: Contacto, Datos fiscales y Dirección. Arriba, selector "Mostrador / Cliente con datos"; con
     Mostrador se ocultan las otras dos secciones.
   - Listas desplegables para régimen (filtrado por tipo de persona según el RFC), uso de CFDI y estado.
   - Botón "Copiar CP fiscal" para llenar el CP de la dirección.
   - Lista: columnas Cliente (empresa o nombre), Contacto, RFC, Tipo (insignia), Teléfono, Cotizaciones. Filtro por
     tipo. El buscador también busca en empresa, razón social y RFC.
5. **Editor de cotización** (`CustomerPicker`):
   - La búsqueda incluye empresa, razón social y RFC, y cada resultado muestra la empresa y el contacto.
   - "Nuevo cliente" abre el formulario completo en un diálogo, sin salir de la cotización, y al guardar lo deja
     seleccionado.
   - Si el cliente seleccionado es registrado, muestra un resumen: RFC, razón social y dirección.

## Parte 2: PDF de cotización (dompdf, carta vertical)
Iguala la imagen de referencia lo más posible:
1. **Encabezado**:
   - **Izquierda:** el logo grande (`public/img/ats-computer.png` o el que corresponda al de la imagen).
   - **Derecha:**
     - un título en rojo y negritas (nuevo ajuste `location_name`, ej. "Plaza de la Tecnología");
     - debajo, la dirección en mayúsculas espaciadas (ajuste `address`, en varias líneas);
     - una línea roja con un rombo al centro;
     - una tabla con RAZÓN SOCIAL (`legal_name`), RFC (`rfc`), TEL. (nuevo ajuste `phone`; si está vacío, usa
       `whatsapp`) y EMAIL (`email`).
   - **Decoración:** franjas diagonales negra y roja en la esquina superior derecha y una línea roja de lado a lado
     con el escalón en ángulo, como en la imagen. Hazlo con una imagen (PNG o SVG que dompdf dibuje bien) en
     `public/img/pdf/`.
   - Agrega `location_name` y `phone` a Configuración, con su validación.
2. **Datos del cliente**:
   - **Izquierda:** etiquetas rojas pequeñas con una línea debajo de cada valor: CLIENTE (empresa, si no razón social,
     si no nombre), CONTACTO, TELÉFONO, CORREO, RFC. Si es registrado, agrega DIRECCIÓN en una línea con calle,
     número, colonia, municipio, estado y CP.
   - **Derecha:** FECHA, FOLIO, VIGENCIA ("15 días", calculada de la fecha y `valid_until`) y VENDEDOR (nombre del
     usuario de la cotización, o "MOSTRADOR").
   - Los renglones vacíos se dejan con su línea, como en la imagen.
3. **Tabla de partidas**:
   - Encabezado rojo con texto blanco: Cantidad, Marca, Modelo, Descripción, Garantía, Precio unitario, Total.
   - Bordes finos grises. Rellena con renglones vacíos hasta tener al menos 5.
   - Si hay varias páginas, el encabezado de la tabla se repite.
   - Columnas nuevas en `quote_items`: `brand`, `model`, `warranty` (texto), con migración.
   - **Al agregar un producto se llenan solas:**
     - `brand`: nombre de la marca;
     - `model`: nuevo campo `products.model` si existe; si no, queda vacío para que el personal lo escriba;
     - `warranty`: sale de `warranty_months` ("1 AÑO", "2 AÑOS", "6 MESES"; vacío si no hay).
   - Las tres columnas se pueden editar en el editor de cotización.
   - Agrega `products.model` (nullable, editable en el editor de producto). Llénalo desde Icecat:
     - toma el nombre del modelo de la ficha en caché (`storage/app/icecat/`; mira los ejemplos en
       `storage/app/icecat-muestras/`);
     - llénalo en `catalogo:icecat` solo cuando esté vacío;
     - agrega el comando `catalogo:modelos {--simular}`, que lo llena solo con la caché, sin pedir nada a Icecat.
4. **Totales**:
   - Recuadro a la derecha con SUBTOTAL, DESCUENTO (solo si hay), IVA 16 % y TOTAL.
   - No cambies cómo se calcula el total: los precios del sistema ya incluyen IVA (revisa `Quote` y el PDF actual).
     Desglosa igual que hoy y muestra los precios unitarios y totales de partida **antes de IVA**, para que la tabla
     cuadre con el subtotal.
5. **Pie de página**:
   - Las notas de la cotización, si hay.
   - "CONDICIONES COMERCIALES", con las líneas del ajuste `quote_terms`; si está vacío, usa las de la imagen:
     vigencia, disponibilidad, tiempo de entrega, forma de pago y garantía.
   - Los datos bancarios (`bank_details`), si hay.
   - "Atendió: <vendedor>".
   - Número de página.
6. **Encabezado compartido**: pon el encabezado en un partial y úsalo también en los PDF de orden de servicio y
   garantía, si se ven bien (revísalos).
7. **Revisión visual**:
   - Genera un PDF de ejemplo con 2 y con 12 partidas y conviértelo a imagen (pdftoppm o lo que tengas) para
     compararlo con la referencia.
   - Deja los PDF de ejemplo en `sistema/storage/app/r17/`.

## Pruebas
- Cliente registrado: válido, RFC inválido, régimen que no corresponde al tipo de persona y RFC repetido.
- Búsqueda por RFC y empresa, y creación desde la cotización.
- El PDF responde 200 y contiene CLIENTE, FOLIO, la marca, el modelo, la garantía y los totales correctos (subtotal +
  IVA = total actual).
- `catalogo:modelos --simular` funciona con un fixture.

## Terminado cuando
- `bash .delegate/verify.sh types`, `check`, `test` y `build` pasan.
- Textos en español de México.
- Reporta los archivos, las migraciones nuevas (sin correr) y la ruta de los PDF de ejemplo.

## No hagas
- Lo de la sección IMPORTANTE.
- No pongas datos reales del negocio (RFC, teléfono, correo) en seeders ni en el código: van en Configuración.
- No toques `index.html`, `assets/` ni `sitio/` de la raíz.
- No hagas commit.
