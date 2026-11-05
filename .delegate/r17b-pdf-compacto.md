# Encargo R17b: PDF de cotización más profesional y compacto (comentarios del dueño)

## Contexto
El dueño probó el PDF de R17 (cotización real COT-2026-0003, 8 partidas) y pidió:
- Ajustar el diseño a los datos que sí tenemos: casi ningún producto tiene modelo y la columna "Modelo" sale vacía.
- Que se vea más profesional y más ajustado: "no cuadrados tan grandes".

Lo que se ve mal hoy:
- Renglones muy altos con cuadrícula gruesa y una columna Modelo vacía.
- La descripción repite la marca ("MSI Fuente MSI…", "ASUS Mother… ASUS TUF").
- Garantía vacía cuando no hay dato.
- Mucho espacio en blanco: la mitad inferior de la hoja está vacía, y las franjas decorativas y la línea roja quedaron
  hasta abajo, en lugar de arriba como en la referencia.
- CONTACTO repite el nombre de CLIENTE, y salen renglones vacíos (CORREO, RFC).

Referencia de estilo, la misma de R17:
`/mnt/c/Users/tit/AppData/Local/Temp/claude/16aba57062ee7798/aa9a046e-edf1-4851-8f67-6a1057555fe1/images/1.png`

## IMPORTANTE
- No ejecutes migraciones, seeders ni `verify.sh migrate`/`all` sobre `database/database.sqlite`.
- No ejecutes `catalogo:*`, `equipos:*` ni `productos:importar`.
- Para generar PDF de prueba usa tu propia base SQLite temporal en `/tmp`.

## Lee primero
- `resources/views/pdf/quote.blade.php` y `pdf/partials/*`, tal como quedaron en R17.
- `/mnt/c/Users/tit/.claude/skills/taste-skill/` (densidad y jerarquía tipográfica).

## Cambios (solo el PDF y lo mínimo que necesite)
1. **Encabezado compacto, arriba**, que no pase de unos 32 mm de alto:
   - **Izquierda:** logo de unos 45 mm de ancho.
   - **Derecha:** título en rojo de unos 13 pt (`location_name`; si está vacío, `business_name`); dirección en
     mayúsculas espaciadas de unos 7.5 pt; línea roja fina con rombo; datos fiscales a unos 7.5 pt (razón social,
     RFC, teléfono, correo) en dos columnas.
   - **Decoración:** las franjas diagonales negra y roja van chicas en la esquina superior derecha. La línea roja con
     escalón va de lado a lado debajo del encabezado. Nada decorativo al final de la página.
2. **Bloque de cliente y folio** compacto, en dos columnas, con renglones de unos 15 px:
   - Etiquetas rojas de 6.5 a 7 pt y valores de 8.5 pt.
   - Muestra solo los datos que existen. CLIENTE siempre. CONTACTO solo si es distinto de CLIENTE. TELÉFONO, CORREO,
     RFC y DIRECCIÓN solo si tienen valor.
   - A la derecha: FECHA, FOLIO, VIGENCIA y VENDEDOR.
   - Si hay `build_name`, muéstralo como "Proyecto: Mi PC" en una línea discreta, no como título.
3. **Tabla de partidas** densa y limpia:
   - Columnas: Cant. · Marca · Descripción · Garantía · P. unitario · Importe. Quita la columna Modelo.
   - En Descripción:
     - primera línea: el nombre sin la marca repetida al inicio (si la descripción empieza con la marca de la
       partida, quítala);
     - segunda línea en gris de 7 pt: "Código <sku>", más "· Modelo <model>" solo cuando haya modelo.
   - Encabezado rojo delgado: unos 7.5 pt en negritas y relleno de unos 4 px.
   - Cuerpo a unos 8 pt, relleno de 3 a 4 px, renglones alternados en gris muy claro y solo una línea fina entre
     renglones (sin cuadrícula gruesa).
   - Números alineados a la derecha.
   - Garantía vacía → "Con fabricante".
   - Quita los renglones de relleno vacíos.
   - Si la tabla pasa de página, repite su encabezado.
4. **Totales**: recuadro angosto a la derecha, de unos 65 mm.
   - SUBTOTAL, DESCUENTO (solo si hay), IVA 16 % y TOTAL. TOTAL va resaltado: fondo rojo y texto blanco, o negritas
     con línea superior gruesa.
   - Debajo, el importe con letra en español de México, por ejemplo "CIENTO NOVENTA Y DOS MIL NOVECIENTOS NOVENTA Y
     SIETE PESOS 85/100 M.N.". Hazlo con una función de soporte y su prueba.
   - Deja la nota "Precios unitarios e importes antes de IVA" en 6.5 pt, junto a los totales.
5. **Pie**:
   - Condiciones comerciales y datos bancarios en dos columnas, a unos 7.5 pt, a la izquierda de los totales o justo
     debajo.
   - "Atendió: X" pequeño.
   - Pie fijo con una línea fina, sitio web y WhatsApp, y "Página X de Y".
   - Una cotización de hasta unas 12 partidas debe caber en una sola hoja.
6. El encabezado compartido sigue sirviendo para los PDF de orden de servicio y garantía. Revisa que se vean bien.
7. **Revisión visual**:
   - Genera ejemplos con 2, 8 y 20 partidas: uno con cliente de mostrador sin correo ni RFC, y otro con cliente
     registrado con dirección y algún producto con modelo.
   - Conviértelos a imagen y revísalos.
   - Déjalos en `sistema/storage/app/r17/` con nombres claros.

## Pruebas
- El PDF no repite la marca en la descripción y no muestra la columna Modelo.
- Muestra "Código" y, cuando existe, "Modelo".
- Oculta los renglones vacíos del cliente.
- El importe con letra es correcto en 3 casos (incluye centavos y miles).
- Los totales no cambian.

## Terminado cuando
`bash .delegate/verify.sh types`, `check`, `test` y `build` pasan. Reporta los archivos y las rutas de los PDF de
ejemplo.

## No hagas
- No cambies cálculos de precios ni totales.
- No toques el resto del sistema.
- No hagas commit.
