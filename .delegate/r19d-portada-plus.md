# Encargo R19d: comentarios del dueño sobre portada y tienda (después de R19c)

Al dueño le gustó la tienda y la portada "se ve bien, pero aún podemos mejorarla". **Se conserva la identidad
gamer HUD** (ver `CLAUDE.md` de la raíz y `sistema/.taste-skill/r19b/REVIEW.md`). No elijas una dirección nueva.

## Lee primero
- `resources/css/header.css`: el header gamer de R13 era semitransparente.
- `resources/css/catalog-hud.css`, `resources/views/site/pages/home.blade.php`,
  `resources/views/site/shop/index.blade.php` y `resources/views/site/partials/{banners,rail}.blade.php`.
- `resources/js/site/pages/home.js` y `emblem.js`: el emblema de partículas y su dispersión al hacer scroll.
- Skills: `/mnt/c/Users/tit/.claude/skills/ui-ux-pro-max/` (busca estilos "gaming", "cyberpunk" o "esports" con
  `python3 /mnt/c/Users/tit/.claude/skills/ui-ux-pro-max/scripts/search.py`; solo para refinar, no para cambiar
  la identidad), `storefront-best-practices` y `taste-skill`, de esta última solo el inspector y las capturas.

## Cambios
1. **Tienda: el slider va arriba.** En `/tienda`, el carrusel de banners regresa a la parte de arriba de la página,
   antes de cualquier otra sección, como estaba antes de R19.
2. **Header semitransparente** en todas las páginas del sitio, como en `header.css`:
   - vidrio oscuro semitransparente con `backdrop-filter: blur`, que deje ver lo de atrás al hacer scroll;
   - busca qué reglas lo volvieron opaco (`catalog-hud.css`, `site-design.css`, etc.) y quítalas;
   - en celular también.
3. **Carruseles con reproducción automática.** Aplica a los rieles de productos, los banners y cualquier carrusel
   del sitio:
   - avanzan solos cada 4 a 5 s y vuelven al inicio al llegar al final;
   - se pausan al pasar el mouse, al recibir foco, al tocar en celular o si la pestaña está oculta;
   - indicador de progreso fino en rojo y botones anterior/siguiente que siguen funcionando;
   - sin reproducción automática si el usuario tiene `prefers-reduced-motion`.
4. **Partículas visibles detrás de las secciones (portada).**
   - Las partículas del emblema deben seguir viéndose detrás del contenido al hacer scroll: lienzo fijo de fondo o
     la dispersión existente.
   - Los paneles y fondos de las secciones quedan **medio transparentes**: vidrio oscuro con
     `rgba(9,8,11,.5)` a `.62` y `backdrop-filter: blur(6–10px)`, para que las partículas se noten sin afectar
     la lectura.
   - Mantén contraste AA en textos.
   - **Rendimiento:** el lienzo se pausa con la pestaña oculta y en `lowPower`/celular usa menos partículas. Sin
     saltos al hacer scroll.
5. **Mejorar más la portada** (con criterio de diseño, dentro del lenguaje HUD). Ideas, elige las que mejor
   funcionen:
   - **Encabezados de sección estilo HUD**, con índice ("01 / DEPARTAMENTOS"), etiqueta silk y una línea con
     brillo.
   - **Aparición al hacer scroll:** barrido de escaneo o encendido tipo LED, sutil y una sola vez. Respeta
     `prefers-reduced-motion`.
   - **Tarjetas loot** con inclinación ligera y brillo que sigue al mouse.
   - **Rareza por rango de precio**, como en Arma tu PC (común, poco común, raro, épico, legendario), con su color
     de borde y brillo. Debe ser coherente con `LootCard.vue` del armador.
   - **Banda de marcas reales** del catálogo en desplazamiento continuo, estilo HUD, con pausa al pasar el mouse.
   - **Llamado a Arma tu PC** más vistoso: el esquema animado de piezas o el emblema, y el texto "Tu PC, pieza por
     pieza".
   - **Departamentos** con brillo de borde al pasar el mouse y número de productos con conteo animado al aparecer.
   - **Sin datos inventados:** nada de reseñas, cifras, premios ni años de experiencia.

## IMPORTANTE
- **El hero no cambia:** emblema, título, botones y modos.
- No ejecutes migraciones, seeders ni `verify.sh migrate`/`all`. Tampoco comandos `catalogo:*`, `equipos:*` ni
  `productos:importar`.
- No toques el portal interno, la escena 3D de Arma tu PC ni `index.html`/`assets`/`sitio` de la raíz.

## Revisión
- Capturas a 1440, 1366 × 768 y 390 px de inicio completo, `/tienda` (con el slider arriba) y una categoría. Incluye
  también una captura **a media página de la portada**, donde se vean partículas detrás de una sección. Guárdalas en
  `sistema/.taste-skill/r19d/`.
- El header debe verse semitransparente sobre el contenido al hacer scroll.

## Terminado cuando
- `bash .delegate/verify.sh types`, `check`, `test` y `build` pasan.
- Hay pruebas del orden de la tienda (slider primero) y del autoplay (atributos o configuración presentes).
- Textos en español de México.
- No hagas commit.
