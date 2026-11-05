# Encargo R19e: el slider de la tienda como hero de pantalla completa

## Petición del dueño
"El slider de tienda va como slider hero, quedando atrás de la barra de arriba, siendo responsive y que abarque el
ancho total de la pantalla."

## Cambios (solo `/tienda`)
1. **Hero de banners a todo lo ancho:** de borde a borde de la ventana (`100vw`, sin márgenes laterales ni el
   ancho máximo del contenedor) y hasta arriba de la página.
2. **Detrás del header:**
   - el banner empieza en `top: 0`, debajo del header semitransparente, que queda encima flotando como vidrio;
   - quita el espacio que hoy separa el contenido del header solo en esta página;
   - el texto y los botones del banner no deben quedar tapados por el header, así que deja el relleno superior
     necesario dentro del slide;
   - el header conserva su legibilidad: un degradado oscuro sutil en la parte superior del banner.
3. **Alto:** escritorio unos `min(78svh, 720px)`, laptop 1366 × 768 unos `70svh`, celular unos `62svh` (mínimo
   420 px).
4. **Imágenes:** `object-fit: cover` centrado, con una imagen distinta para celular si el banner la tiene (revisa
   el modelo `Banner` y su administración). Si no hay, recorta con un `object-position` razonable.
5. **Autoplay** (como en R19d):
   - pausa con mouse, foco, toque y pestaña oculta, y nada con `prefers-reduced-motion`;
   - puntos o rayitas de progreso abajo, flechas grandes y gesto de deslizar en celular.
6. **Estilo gamer HUD:**
   - texto del banner con etiqueta silk, título en Chakra Petch y botón rojo con chaflán;
   - una línea de escaneo sutil y degradado inferior que funda el banner con la sección siguiente.
7. **Sin banners:** se queda el encabezado normal de la tienda, sin hueco.

## IMPORTANTE
- Mantén todo lo demás de R19b–R19d: identidad gamer, portada y hero de inicio sin cambios.
- No ejecutes migraciones, seeders ni `verify.sh migrate`/`all`. Tampoco comandos `catalogo:*`, `equipos:*` ni
  `productos:importar`. Si hace falta un campo nuevo en `banners`, crea la migración pero **no la corras**.
- No toques el portal interno, salvo lo mínimo del formulario de banners si agregas imagen para celular. Tampoco
  la escena 3D ni `index.html`/`assets`/`sitio` de la raíz.

## Revisión
Capturas de `/tienda` a 1440, 1366 × 768 y 390 px, arriba (header sobre el banner) y al hacer scroll. Guárdalas en
`sistema/.taste-skill/r19e/`.

## Terminado cuando
- `bash .delegate/verify.sh types`, `check`, `test` y `build` pasan.
- Hay una prueba de que el slider es lo primero de `/tienda`.
- No hagas commit.
