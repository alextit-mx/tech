# Encargo R19c: pulido de portada y tienda (después de R19b)

R19b quedó bien: hero intacto y el lenguaje HUD gamer en todo. **No cambies la dirección ni el estilo.** Solo
corrige estos detalles.

## Lee primero
- `sistema/.taste-skill/r19b/REVIEW.md` (lo que hiciste) y `CLAUDE.md` de la raíz (identidad gamer obligatoria).
- Skills nuevas (léelas y aplícalas sin cambiar la identidad):
  - `/mnt/c/Users/tit/.claude/skills/storefront-best-practices/`: tarjetas, listados, ficha, versión de celular
    y SEO de tienda;
  - `/mnt/c/Users/tit/.claude/skills/web-design-guidelines/`: descarga sus reglas de la URL indicada y revisa las
    vistas del sitio que cambiaste;
  - `/mnt/c/Users/tit/.claude/skills/ui-ux-pro-max/`: su buscador local
    `python3 /mnt/c/Users/tit/.claude/skills/ui-ux-pro-max/scripts/search.py`, solo para afinar contraste,
    tipografía y estados. No para cambiar el estilo.

## Cambios
1. **Rieles de la portada variados.** Hoy "Entrega inmediata" muestra 4 discos Seagate seguidos.
   - Máximo 1 producto por categoría en cada riel, rotando entre categorías: video, procesadores, tarjetas madre,
     RAM, almacenamiento, gabinetes, monitores, periféricos, etc.
   - Siguen siendo solo productos con foto y existencias.
   - "Destacados" no repite productos de "Entrega inmediata".
2. **Departamentos.** Hoy solo salen 2 mosaicos (Componentes y Periféricos gamer). Muestra de 6 a 8 mosaicos con las
   categorías más útiles y con foto: tarjetas de video, procesadores, tarjetas madre, memoria RAM,
   almacenamiento, gabinetes, monitores, periféricos o PCs armadas, según existan. Cada uno con su conteo y una foto
   real de un producto de esa categoría.
3. **Orden "Recomendados" de la tienda** (`/tienda`, categoría, búsqueda): primero los productos **con foto**,
   luego por existencias. "Foto pendiente" queda al final. Los demás órdenes no cambian.
4. **Ficha sin foto:** la vitrina vacía es enorme. Que mida como máximo unos 360 px de alto en escritorio, con el
   emblema y "Foto pendiente", y que el panel de compra suba.
5. **Revisión con las skills:** aplica las reglas de `web-design-guidelines` y `storefront-best-practices` a las
   vistas del sitio:
   - foco visible;
   - `alt` y nombres accesibles;
   - tamaños de toque en celular;
   - jerarquía de precio y existencias;
   - datos estructurados de producto (JSON-LD `Product` con precio MXN y disponibilidad) en la ficha, si aún no
     están;
   - metadatos de categoría.
   Anota en `sistema/.taste-skill/r19c/REVIEW.md` qué encontraste y qué corregiste.

## IMPORTANTE
- No ejecutes migraciones, seeders ni `verify.sh migrate`/`all`. Tampoco comandos `catalogo:*`, `equipos:*` ni
  `productos:importar`.
- No toques el hero, el portal interno, la escena 3D ni `index.html`/`assets`/`sitio` de la raíz.

## Terminado cuando
- `bash .delegate/verify.sh types`, `check`, `test` y `build` pasan.
- Hay capturas después de los cambios en `sistema/.taste-skill/r19c/` (inicio, una categoría y una ficha sin foto,
  a 1440 y 390 px).
- Hay pruebas de la variedad de rieles y del orden con foto primero.
- No hagas commit.
