# R19 · diagnóstico y dirección

## Alcance

Sitio público Blade, sin modificar el portal interno, las rutas ni la base de desarrollo. Capturas de inicio, tienda, categoría PCs armadas, búsqueda «ryzen» y una tarjeta de video, a 1440×900, 1366×768 y 390×844 en `.taste-skill/r19/before/`. Inspector adicional a 1440, 768 y 390 en `before/inspector/`.

## Lo que se ve antes

- **Inicio:** el hero ocupa toda la laptop; no se alcanza a ver la tienda en 1366×768. La propuesta tarda en llegar al catálogo, no tiene buscador grande y repite servicios en seis filas, un ejemplo, niveles y un proceso antes de cerrar. No hay productos visibles porque no hay destacados seleccionados en los datos actuales. El emblema y las pestañas sí dan identidad.
- **Tienda:** el banner cubre casi toda la primera pantalla y recorta las palabras de la campaña. Debajo vuelve a empezar otro hero con un titular largo, cuatro promesas explicadas en párrafos y subtítulos editoriales que compiten con productos. Se repite demasiado la misma densidad de secciones. La promesa general de entrega al día siguiente no depende del stock.
- **Departamentos:** índice de texto con largas listas de subcategorías, sin fotografía; comunica navegación secundaria en vez de mercancía. Hay fotografías reales en ocho categorías del catálogo, utilizables sin inventar productos.
- **Tarjetas y rieles:** títulos de cinco a siete líneas, marcas dentro del título, descripción opcional y garantías que producen alturas diferentes. La categoría ocupa una franja entre foto y nombre. Las acciones se parten en celular. El marco oscuro se confunde con las fotos y el marcador de imagen ausente domina las listas.
- **Categoría:** título y margen inicial grandes respecto al catálogo; filtro de precio sin etiquetas persistentes. Falta un acceso directo para limpiar los filtros. En celular la rejilla funciona, pero los nombres extensos y el botón a dos líneas vuelven cada tarjeta demasiado alta.
- **Búsqueda:** conserva un encabezado grande pero no expone orden ni disponibilidad aunque el servidor ya acepta esos filtros. El estado vacío promete conseguir cualquier pieza, sin evidencia.
- **Ficha:** la galería tiene zoom automático que recorta y amplía incluso imágenes chicas. Precio y existencia son claros, pero la marca queda relegada a SKU; la ficha técnica necesita una tabla legible. La imagen principal y miniaturas usan marcos distintos.
- **Imágenes transversales:** `contain` permite ampliar por encima del tamaño natural; equipos usan `cover`; el marcador sin foto también usa `cover`. Sugerencias y carrito no comparten fondo ni relleno. Corregir en una regla común con `scale-down` y eliminar las ampliaciones automáticas.

## Inventario y argumento

Material propio: nombre Tech Computer y emblema ATS; Plaza de la Tecnología; República de Uruguay y locales de Configuración; venta en pesos con IVA; cotización con folio; configurador 3D; disponibilidad local y 24 h separadas; garantía por pieza; marcas y fotos del catálogo; servicio técnico y asesoría; cuatro modos de color.

1. Encuentras una pieza o empiezas una PC → búsqueda con sugerencias y configurador existentes.
2. Sabes cómo comprar → disponibilidad por producto, garantía, transferencia documentada y local físico.
3. Exploras lo que hay → departamentos con fotos reales y acceso al índice completo.
4. Puedes cotizar de inmediato → destacados, selección alternativa si no hay destacados, entrega local comprobada.
5. Puedes partir de un armado → equipos publicados y configurador.
6. Puedes pedir ayuda y visitarnos → servicio, asesoría, WhatsApp y dirección de Configuración.
7. Las marcas están en la tienda → sólo marcas con productos publicados y categoría visible.

## Dirección antes del código

Skills leídas: taste-skill, awesome-design-md, image-to-code y `../.claude/skills/playwright-cli`. Referencias locales: NVIDIA (jerarquía y precisión técnica), DJI (fotografía de producto sobre fondos claros), PlayStation (ritmo de catálogo y capítulos). Se mantienen tokens ATS.

Sorteo taste semilla 795191517: `specimen-catalogue`, elegido entre `pinned-poster`, `contact-sheet` y `specimen-catalogue`: el encuadre repetible permite comparar piezas reales y no retrasa la compra con una escena larga. En esta página, **el mostrador de piezas y cotizaciones del local ATS se convierte en un catálogo visual de piezas disponibles**. Elemento vivo: buscar con sugerencias reales y agregar piezas a la cotización.

Adaptaciones explícitas: se conserva el emblema 3D y los modos por identidad; los productos jamás se recortan ni se agrandan como propone el sorteo. No se usan fotografías de ambiente ajenas ni video porque no prueban inventario o servicio y añaden carga. Las secciones de contacto no inventan formulario ni newsletter: usan dirección y enlaces existentes. La rejilla repetida se reserva al catálogo.

## Referencia image-to-code y textos

`references/hero.png`: borrador generado con la herramienta integrada, sólo para composición, nunca como imagen de producción. Lectura: 58/42 de texto y emblema; titular en dos líneas; búsqueda ancha bajo una sola frase; acciones de 48 px; pestañas compactas; confianza y comienzo del catálogo en la primera pantalla. Se traducen a Chakra Petch, Barlow y tokens actuales. No se copian sus afirmaciones generadas sobre envíos o tarjetas: sólo está documentado pago por transferencia.

Textos guía: «Tu próxima PC / se arma aquí», «PCs gamer, componentes y servicio técnico en Plaza de la Tecnología, CDMX», «Ver tienda», «Arma tu PC», «Explora la tienda», «Destacados» o «Selección de la tienda», «Con entrega inmediata», «Equipos armados», «Tu PC, pieza por pieza», «Servicio y asesoría», «Marcas del catálogo», «Visítanos en la plaza». Sin testimonios, métricas ni promesas nuevas.

## Ronda 0

Visto en un segundo: emblema y titular; catálogo ausente.
Idea: el local gamer ATS, pero el contenido se comporta como folleto de servicios.
Elemento vivo: configurador; búsqueda sólo en encabezado.
Composiciones: hero=split / servicios=filas / armado=split / niveles=rejilla / tienda=índice / proceso=lista.
Puntuación: diseño 6.5 · uso 6.5 · creatividad 7 · contenido 7 → 6.65, limitado a 6.0 por inspector.
Inspector: 1 FAIL (cero fotos reales en inicio), 3 WARN (11 tamaños, sin video, márgenes). Tres cambios prioritarios: catálogo temprano, imágenes consistentes y bloques de texto más breves.

`references/rail.png`: segunda referencia generada con herramienta integrada. Lectura: cuatro fichas de igual ancho, fotos completas en campo claro, marca separada, nombre a dos líneas, precio dominante, existencia en línea propia y una acción. En móvil se traduce a dos tarjetas en rejilla y riel con una tarjeta completa y parte de la siguiente para indicar desplazamiento. Fotos ilustrativas y precios del borrador no se llevan a producción; se conservan datos del catálogo.

## Ronda 1

Visto en un segundo: búsqueda y dos entradas claras, tienda/configurador.
Idea: el mostrador ATS se abre al catálogo.
Elemento vivo: sugerencias y cotización.
Composiciones: hero=split / confianza=franja / departamentos=objetos / productos=riel / equipos=rejilla / configurador=franja / servicio=editorial / marcas=índice / contacto=split.
Puntuación provisional: diseño 7 · uso 7.5 · creatividad 7 · contenido 8 → 7.25.
Tres problemas y correcciones: `round1/inicio-1440-fold.jpg` no muestra emblema en movimiento reducido (activar respaldo estático); encabezados de sección a 80 px frente al margen general de 40 px (quitar padding doble); `round1/tienda-1440-fold.jpg` deja el catálogo debajo del banner (mover promoción después del primer riel y mantener vigencias fuera de la caché).
