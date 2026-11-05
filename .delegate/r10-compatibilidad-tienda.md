# Encargo R10: compatibilidad real en el configurador, banners de la tienda, existencias inmediata/24 h e imagen genérica

El catálogo ya es real: 3,882 productos importados de la lista de precios (`../herramientas/lista_precios.py` →
`php artisan productos:importar`, ver `../CONTINUAR.md`). Lee `AGENTS.md` y `DESIGN.md` antes de empezar.

## 1. Compatibilidad en "Arma tu PC" (lo más importante)
Al elegir una pieza, las siguientes listas deben mostrar **solo lo compatible** (con "Solo compatibles" activado, que es el
default), en cualquier orden de elección:
- CPU ↔ tarjeta madre: mismo socket.
- RAM: mismo tipo que la tarjeta madre; si aún no hay tarjeta madre pero sí CPU, por plataforma (AM5 y LGA1851 → DDR5;
  AM4, LGA1200 y LGA1151 → DDR4; LGA1700 → ambas).
- Enfriamiento: socket del CPU en `sockets` y TDP del CPU ≤ `max_tdp_w`.
- Gabinete: formato de la tarjeta madre y largo de la GPU.
- Fuente: oculta las que no alcanzan el consumo estimado.
- Misma regla en `resources/js/builder/rules.ts` **y** en `app/Support/BuildValidator.php` (con pruebas).
- **Verifica con el catálogo real**: reporta cuántos CPU, tarjetas madre y RAM hay por socket/tipo, y prueba en el navegador
  (playwright-cli) elegir un Ryzen AM5 → solo tarjetas AM5 → solo RAM DDR5. Si la inferencia de specs de
  `../herramientas/lista_precios.py` falla (socket, DDR, formato), corrígela.
- El importador hoy no pisa specs existentes; agrega `--specs` a `productos:importar` para reescribir las specs inferidas
  (sin esa opción se respetan las editadas a mano). Reimporta con `--specs` al terminar.

## 2. Al cambiar de categoría en el selector, la lista vuelve arriba
En `BuildLab.vue`, al cambiar de slot el scroll de la lista de cartas (y del panel en celular) regresa al inicio.

## 3. Existencias: CENTRO = entrega inmediata, 24H = llega al día siguiente
- Migración: `products.stock_24h` (int, default 0). El importador guarda CENTRO en `stock` y 24H en `stock_24h`, y deja de
  escribir "Disponible en 24 h" en `short_description`.
- Etiqueta única en tienda, ficha, carrito, configurador y portal: `stock > 0` → "Entrega inmediata"; si no y
  `stock_24h > 0` → "Llega mañana"; si no → "Sobre pedido". Colores: verde, ámbar, gris.
- Filtros de la tienda: "Entrega inmediata" y "Disponible mañana" en lugar de "Solo en existencia".
- Portal: columnas "Inmediata" y "24 h" en productos; ambas editables en la edición rápida.

## 4. Hero de banners en la tienda (imágenes)
- Tabla `banners` (imagen escritorio, imagen celular opcional, texto alternativo, enlace opcional, orden, activo, vigencia
  opcional desde/hasta). Imágenes al disco `uploads` en WebP (como las fotos de producto).
- Portal: pantalla "Banners" (subir, ordenar, activar/desactivar, vigencia) para dueño y vendedores.
- `/tienda` arriba: carrusel de banners a lo ancho (proporción ~21:7 en escritorio, ~4:5 en celular con su imagen propia),
  avance automático cada 6 s, flechas, puntos, deslizar en celular, pausa al pasar el mouse, respeta movimiento reducido,
  primera imagen sin lazy-load y el resto con lazy-load. Si no hay banners activos, no se muestra nada.
- Pruebas: CRUD con permisos, vigencia, la tienda muestra solo los activos y vigentes.

## 5. Imagen genérica cuando el producto no tiene foto
- Diseña una imagen de producto genérica con la identidad (fondo oscuro con relieve sutil, emblema ATS tenue, puede
  llevar el ícono de la categoría) y úsala en tarjetas, ficha, carrito, cotización pública y cartas del configurador
  cuando no haya foto. Que se vea intencional, no un hueco.

## Terminado cuando
- Capturas en `.design/r10/`: configurador con AM5 elegido (lista de tarjetas filtrada), tienda con banners (sube 2 de
  prueba y bórralos al final), ficha sin foto, tarjetas con las tres etiquetas de existencia.
- `bash ../.delegate/verify.sh all` en verde (ojo: reimporta después, `verify.sh migrate` vacía el catálogo).
- Reporte: conteos por socket/tipo y los cambios a la inferencia de specs.
