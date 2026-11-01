# Encargo R2: clientes, cotizaciones, PDF e inicio del portal

## Objetivo
Que los vendedores creen y envíen cotizaciones con folio, PDF y WhatsApp, y que el inicio del portal muestre los números del
mes. Deja lista la creación de cotizaciones desde la web y el configurador (R3/R4 solo la llamarán).

## Lee primero
- `AGENTS.md`, `docs/plan.md` sección 3 (**la especificación; impleméntala completa**) y lo que dejó R1 (modelos, Policies,
  páginas `admin/products`).

## Cambios
1. `composer require barryvdh/laravel-dompdf`. Migraciones, modelos, factories de `customers`, `quotes`, `quote_items`.
2. Servicio `App\Services\QuoteService` (o acciones) con: folio consecutivo por año dentro de transacción con bloqueo,
   copia de precio/costo/descripción del producto, cálculo de totales, y `createFromCart(array $customer, array $items,
   string $source, ?string $buildName)` para web y configurador (precios de la base; ignora lo que mande el navegador).
3. Páginas `admin/customers` y `admin/quotes` (lista, editor, vista) según la sección 3; buscador de productos con
   endpoint JSON paginado. El vendedor ve todas las cotizaciones pero por defecto "solo las mías"; las nuevas de la web
   destacadas con botón "Tomar".
4. PDF con la marca (logo, rojo `#E30613`), vista y PDF públicos por token; enlace de WhatsApp con texto en español.
5. Inicio `/admin` con tarjetas del mes; margen estimado solo para el dueño (y nunca en props del vendedor).
6. Pruebas: folio consecutivo y reinicio por año; totales e IVA; partidas "por confirmar"; copia de precios (cambiar el
   producto no altera la cotización); `createFromCart` ignora precios del cliente y reutiliza cliente por teléfono;
   "Vencida" calculada; PDF responde `application/pdf`; token público; vendedor no recibe márgenes.

## Terminado cuando
- build, types:check, check, pint y pruebas pasan; `DemoSeeder` crea algunas cotizaciones de ejemplo.

## No hagas
- Sitio público, configurador ni servicio técnico.
