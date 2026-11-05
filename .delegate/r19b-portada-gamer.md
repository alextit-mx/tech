# Encargo R19b: portada y tienda mejoradas SIN perder la magia gamer

## Contexto
- R19 eligió una dirección nueva de taste-skill ("specimen catalogue"): logo plano, cuadros blancos de producto y
  estilo de catálogo genérico. Al dueño **no le gustó**: "desentona más, perdió la magia gamer".
- **Ya regresé todos los archivos de R19 a como estaban antes.**
- El respaldo de lo que hizo R19 está en
  `/mnt/c/Users/tit/AppData/Local/Temp/claude/16aba57062ee7798/aa9a046e-edf1-4851-8f67-6a1057555fe1/scratchpad/r19-backup/`.
  Puedes **reusar su lógica de datos**: consultas del `ShopController` para departamentos, rieles y marcas, y la
  de la barra de confianza. **No reuses su estilo.**
- Su diagnóstico de problemas sigue en `sistema/docs/r19-diagnostico.md`: úsalo como lista de qué arreglar, no como
  guía de estilo.

## Dirección (obligatoria, no la cambies)
- **Prohibido elegir una dirección nueva en taste-skill.** La dirección ya existe: el lenguaje gamer HUD del sitio
  y de Arma tu PC. De taste-skill usa solo el inspector y el ciclo de captura y corrección.
- **Lenguaje visual a extender**, que ya está en `resources/js/builder/builder.css` y `sistema/DESIGN.md`:
  - paneles oscuros de vidrio (`rgba(9,8,11,.74)` con `backdrop-filter: blur`) y borde interior de 1 px
    `rgba(234,242,255,.08)`;
  - **esquinas cortadas** (`clip-path` con chaflán de 12 px);
  - etiquetas "silk" en Chakra Petch mayúsculas, con `letter-spacing` .16em;
  - cifras tabulares y rojo #E30613 con brillo (`box-shadow` o `drop-shadow`) para acciones y estados;
  - líneas de escaneo sutiles y ámbar solo para avisos;
  - hover con brillo rojo y una línea que barre;
  - Barlow para el texto.
- **Fondo siempre oscuro. Nada de cuadros blancos** ni secciones claras.
- Textos breves. Nada de párrafos largos ni bloques de texto plano que desentonen. Cada sección debe sentirse
  parte del mismo "sistema" que el hero y el armador.

## Portada (`resources/views/site/pages/home.blade.php` y sus parciales)
1. **El hero se queda igual**: emblema de partículas, "Tu próxima PC se arma aquí.", botones, barra "Tu próxima PC"
   y modos ATS/RGB/Frost/Amber. No toques `emblem.js` ni la lógica de modos en `home.js`.
   - Solo puedes agregar, si cabe sin apretar, un buscador del catálogo estilo HUD debajo de los botones.
   - La altura del hero no cambia.
2. **Debajo del hero**, en este orden aproximado y con el lenguaje HUD:
   - **Barra de confianza**, como el HUD superior de Arma tu PC: 4 celdas con etiqueta silk y valor. Entrega
     inmediata o en 24 h (según existencias reales), garantía, formas de pago y tienda física en Plaza de la
     Tecnología.
   - **Departamentos**: mosaicos de vidrio con chaflán, foto de un producto real del departamento en una
     "vitrina" (ver Imágenes), nombre en Chakra Petch y conteo de productos; con brillo y barrido al pasar el
     mouse.
   - **Rieles de productos** "Entrega inmediata" y "Destacados":
     - **solo productos con foto y existencias**;
     - tarjeta estilo loot card del armador (`resources/js/builder/LootCard.vue` como referencia): vitrina,
       marca silk, nombre en 2 líneas, precio tabular y estado de entrega con LED verde o ámbar;
     - botón para agregar a la cotización.
   - **Equipos armados** y llamado a Arma tu PC.
   - **Servicio técnico y asesoría**: dos paneles HUD con ícono, una línea y botón a WhatsApp.
   - **Ubicación**: panel con la dirección desde Configuración, enlace a Google Maps (sin API key) y WhatsApp.
   - No inventes reseñas, cifras ni testimonios.

## Tienda (`/tienda`, categoría, búsqueda y ficha)
- El mismo lenguaje: encabezados con etiqueta silk, filtros y orden en paneles HUD, cuadrícula de tarjetas loot
  (las mismas de los rieles), paginación con chaflán y estado vacío con el emblema.
- **Ficha de producto:**
  - galería en vitrina;
  - panel de compra HUD (precio, existencias local y 24 h, botones);
  - especificaciones en tabla HUD (etiqueta silk y valor);
  - productos relacionados con loot cards.
- Corrige los bloques de texto que desentonan según el diagnóstico.

## Imágenes de producto (en todos lados)
Tarjetas, rieles, departamentos, ficha y galería, carrito, sugerencias del buscador y equipos armados.
- Las fotos traen fondo blanco. Para que no se vean como cuadros blancos sobre el fondo oscuro, van en una
  **vitrina**:
  - panel interior con chaflán y degradado gris medio-claro (por ejemplo `#d9dadf` a `#bfc1c8`), no blanco puro;
  - sombra interior;
  - imagen con `mix-blend-mode: multiply`, para que su fondo blanco se funda con el panel;
  - un brillo rojo tenue detrás al pasar el mouse.
- Proporción fija y relleno interno. Imagen centrada con `object-fit: contain`.
- **Las chicas no se estiran** más de su tamaño natural (`scale-down`). Nada recortado ni pixeleado.
- **Sin foto:** vitrina oscura con el emblema ATS tenue y "Foto pendiente". En la portada no se muestran
  productos sin foto.

## IMPORTANTE
- No ejecutes migraciones, seeders ni `verify.sh migrate`/`all`. Tampoco comandos `catalogo:*`, `equipos:*` o
  `productos:importar`.
- No toques el portal interno (`resources/js/pages/admin`, `components`, `layouts`, controladores `Admin`).
- No toques `index.html`, `assets/` ni `sitio/` de la raíz, ni la escena 3D de Arma tu PC.

## Revisión (obligatoria)
- Haz capturas de antes y después, a 1440, a 1366 × 768 y a 390 px: inicio completo, tienda, categoría, búsqueda y
  ficha. Guárdalas en `sistema/.taste-skill/r19b/`.
- Compara el hero antes y después: debe verse igual.
- Revisa tú mismo que ninguna sección se vea clara, blanca, plana o de "catálogo genérico". Si alguna se ve así,
  corrígela antes de terminar.

## Terminado cuando
- `bash .delegate/verify.sh types`, `check`, `test` y `build` pasan.
- Hay pruebas de que la portada muestra solo productos con foto y existencias.
- Textos en español de México.
- Reportas lo que cambiaste por sección y las rutas de las capturas.
- No hagas commit.
