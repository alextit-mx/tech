# R6 · revisión de diseño

Pase de diseño del sitio público y del portal, con la identidad ATS y sus cuatro modos. La fuente de verdad es [DESIGN.md](../DESIGN.md).

## Decisiones aplicadas

La hoja de armado del mostrador organiza el sitio: títulos cortos, fichas alineadas, folios y listas de taller. Negro neutral, un acento activo, Chakra Petch para señalética y Barlow para lectura. Los cortes se reservan a acciones y fichas; el portal usa superficies discretas, controles del kit y cifras tabulares. Los encabezados de servicio y asesoría tienen dos columnas en escritorio y una en celular.

Se conservaron los modos ATS/RGB/Frost/Amber, el emblema y su cambio de paleta. Los acentos nuevos consumen las variables del modo. La consola detectó un fallo previo de Lenis al girar la rueda: la versión instalada invoca `prevent` como función aunque su valor por defecto es booleano. Se proporcionó una función que devuelve falso, conservando el comportamiento esperado del scroll y sin actualizar dependencias. No se modificaron `scene.ts`, el loader del configurador ni `AuthBrandLayout.vue`. En el configurador, el inspector justificó únicamente estos cambios de CSS: etiquetas de al menos 11 px, contraste del pie, márgenes móviles, eliminación del brillo del total y flujo normal del HUD/escena/resumen para que no tapen “Procesador”. Los brillos restantes conservan la identidad existente y ahora consumen `var(--red)`.

El portal tiene marca y navegación reales, acción principal reconocible, campos y botones del kit, tablas contenidas en celular, editor con columnas legibles y configuración del kit en español. `Textarea` sigue el patrón de `Input`; su tipado acepta los valores nulos que ya usaban los formularios. Los permisos y las props no cambiaron. Se tradujeron los nombres visibles de componentes de categoría, conservando sus claves internas y valores de envío.

Las referencias NVIDIA, PlayStation, Lamborghini y BMW M orientaron jerarquía y composición; Linear orientó la densidad del portal. Se generaron **24 bocetos** antes de implementar las familias de pantallas. Están en `.design/r6/references/`; son referencias de composición, no fotos de inventario ni capturas reales. Se descartaron los textos y marcas incorrectos que aparecieron en algunos bocetos. No se incorporó ningún bitmap generado a producción.

## Evidencia visual

[Galería antes/después](../.design/r6/index.html). Hay **35 pantallas × 2 tamaños × 2 encuadres × 2 fases = 280 capturas base**, más estados de interacción. Los tamaños pedidos son 1440×900 y 390×844; cada pantalla tiene primera vista y página completa. Las bases están en `.design/r6/shots/before-*` y `.design/r6/shots/after-*`.

El entorno permite escribir solamente dentro de `sistema/` y `/tmp`: por eso las capturas están en `sistema/.design/r6/shots/`, y no en `../.delegate/shots/`. Queda pendiente copiar la evidencia a esa ubicación si se requiere conservar exactamente la carpeta del encargo.

Se usó `playwright-cli` contra `http://localhost:8000`, con el build de producción de Vite servido por Laravel. El navegador inicialmente carecía de NSS/NSPR/ALSA; se extrajeron temporalmente las bibliotecas en `/tmp` y se indicó su ruta al navegador, sin modificar el sistema. No hace falta instalar paquetes de producción. Para una instalación normal del navegador, el comando solicitado por el encargo sigue siendo `sudo $(which npx) playwright install-deps chromium`.

El portal se abrió con una sesión del dueño que crea `DatabaseSeeder` y reutiliza la carga de demo. No se cambió su contraseña ni se guardaron credenciales en archivos versionados. Para capturar las pantallas con contenido se agregaron registros locales identificados como “muestra R6”: cliente, cotización, orden, garantía y póliza. Antes de `migrate:fresh` se respaldó SQLite y después se restauró para conservar los datos locales previos y los mismos folios en las comparaciones.

Cobertura pública: inicio; tienda; categoría; ficha; búsqueda; carrito vacío y con producto; cotización pública; estado; garantía; servicio técnico; asesoría; contacto; configurador y login.

Cobertura del portal: inicio; cotizaciones; editor nuevo y edición; clientes; productos; formulario nuevo y edición; servicio y orden; garantías y emisión; pólizas; categorías; marcas; usuarios; configuración; perfil; apariencia y seguridad. También se capturaron los formularios abiertos de cliente, recepción y póliza, el ajuste masivo, el editor con partida decimal, resultados de consulta, menú móvil y portal oscuro.

La captura “antes” de seguridad muestra la confirmación de contraseña que solicitaba esa sesión; la posterior muestra Seguridad después de confirmar la sesión. Los comparables principales de negocio no tienen esa diferencia.

## Inspector taste

Se conservaron los informes sin excepciones en `.design/r6/inspect/final-raw-*`. La versión final usa `final-*` para las rutas públicas y las pantallas ajustadas al final; el resto del portal conserva su informe `final-raw-*`, sin fallos. El índice completo se encuentra en `.design/r6/inspection-summary.json`.

El adaptador `.design/r6/inspect.mjs` carga el inspector original, sin modificar sus reglas ni sus mediciones. Solo configura Chrome con `--no-sandbox`, importa la sesión local del dueño, prepara el carrito y realiza las acciones reales de pausa del hero y “Desde cero”/omitir extra del configurador. Las capturas del inspector abarcan 1440, 768 y 390 px. Los tableros `.design/r6/boards/` muestran sus primeras vistas y hojas completas para revisarlas juntas.

Excepciones explícitas, de alcance acotado:

| Pantalla                             | `--allow`                        | Motivo                                                                                                                                 |
| ------------------------------------ | -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| Inicio, servicio, asesoría, contacto | `few-pictures`                   | Los recursos recibidos contienen únicamente los logos. El encargo prohíbe imágenes externas y no se inventan fotos del local o de SKU. |
| Configurador                         | `micro-label,gradient,button-fx` | Serigrafía BIOS y tarjetas de rareza de la escena que el usuario pidió conservar. Tamaño, contraste y colisiones sí se corrigieron.    |
| Login                                | `headline-lines,gradient`        | El layout de marca existente se conserva. El texto de tres líneas es un párrafo lateral, no el h1 “Inicia sesión”.                     |

Catálogo, búsqueda, carrito, cotización, consultas, builder y portal se midieron con `--app`: son listas con filtros, documentos o interfaces de trabajo. Inicio, servicio, asesoría y contacto se midieron como páginas editoriales.

Los avisos restantes se explican por títulos públicos más grandes que los de una app, variación de tamaños dentro del sistema, ausencia de video, el borde del último glifo frente al margen geométrico y listas cortas con datos reales. Los avisos de alturas distintas corresponden a listas operativas/formularios con contenido diferente; no se agregaron rellenos ni registros ficticios para igualarlos. Se corrigió el aviso real de contraste de Claro/Oscuro usando tokens semánticos del portal. No quedan fallos de desbordamiento, texto tapado, texto diminuto ni contraste en los informes finales.

En catálogo, `panel-void` corresponde a fichas sin foto y textos de distinta extensión; `mixed-font-headline` separa el importe en Chakra de la leyenda de IVA en Barlow. `below-fold` señala productos relacionados, situados después de los datos de compra. El título verbal “Reparamos tu PC” y su explicación sí identifican el servicio, aunque la heurística lo clasifica como `name-only-title`. En contacto, los metadatos de 15 px y once entradas discretas explican `body-size` y `still-page`. Los rótulos de folio y acceso explican `micro-label`; el ancho de formulario protegido del login explica `unused-canvas`.

El lint de taste del CSS público terminó con **0 FAIL / 0 WARN**. El del portal, ejecutado con `--app`, terminó con **0 FAIL / 3 WARN**: dos sombras de menús de búsqueda y el uso de “—” para datos ausentes. No se aplicó el mínimo de fotografías a tablas y formularios.

<!-- inspection-table:start -->

Resultado final: **0 FAIL / 44 WARN**, 0 errores de consola, 280 capturas base.

| Pantalla             | Antes / después | FAIL / WARN |
| -------------------- | --------------- | ----------- |
| Inicio público       | 4 / 4           | 0 / 3       |
| Tienda               | 4 / 4           | 0 / 1       |
| Categoría            | 4 / 4           | 0 / 2       |
| Ficha de producto    | 4 / 4           | 0 / 3       |
| Búsqueda             | 4 / 4           | 0 / 2       |
| Carrito vacío        | 4 / 4           | 0 / 1       |
| Carrito con producto | 4 / 4           | 0 / 3       |
| Cotización pública   | 4 / 4           | 0 / 2       |
| Estado del equipo    | 4 / 4           | 0 / 1       |
| Consulta de garantía | 4 / 4           | 0 / 1       |
| Servicio técnico     | 4 / 4           | 0 / 3       |
| Asesoría             | 4 / 4           | 0 / 2       |
| Contacto             | 4 / 4           | 0 / 5       |
| Configurador         | 4 / 4           | 0 / 1       |
| Login del personal   | 4 / 4           | 0 / 3       |
| Inicio del portal    | 4 / 4           | 0 / 1       |
| Cotizaciones         | 4 / 4           | 0 / 1       |
| Nueva cotización     | 4 / 4           | 0 / 1       |
| Editar cotización    | 4 / 4           | 0 / 1       |
| Clientes             | 4 / 4           | 0 / 1       |
| Productos            | 4 / 4           | 0 / 0       |
| Editar producto      | 4 / 4           | 0 / 0       |
| Nuevo producto       | 4 / 4           | 0 / 0       |
| Servicio             | 4 / 4           | 0 / 1       |
| Orden de servicio    | 4 / 4           | 0 / 1       |
| Garantías            | 4 / 4           | 0 / 1       |
| Nueva garantía       | 4 / 4           | 0 / 0       |
| Pólizas              | 4 / 4           | 0 / 2       |
| Categorías           | 4 / 4           | 0 / 0       |
| Marcas               | 4 / 4           | 0 / 0       |
| Usuarios             | 4 / 4           | 0 / 0       |
| Configuración        | 4 / 4           | 0 / 0       |
| Perfil               | 4 / 4           | 0 / 0       |
| Apariencia           | 4 / 4           | 0 / 1       |
| Seguridad            | 4 / 4           | 0 / 0       |

<!-- inspection-table:end -->

## Pruebas

`bash ../.delegate/verify.sh all` terminó con migración/seed, build, tipos y lint en código 0. PHPUnit: **61 aprobadas, 328 aserciones; 1 omitida**. Se confirmó por separado que la omitida es `product image upload creates webp`, porque la imagen PHP de este entorno no dispone de GD WebP. Queda pendiente comprobar esa carga en un entorno con dicho soporte.

`vendor/bin/pint --dirty` no puede detectar Git con el montaje normal de `bin/php`, que solo expone `sistema/`. Se ejecutó el mismo comando dentro de Docker montando el repositorio de lectura y `sistema/` de escritura: **PASS, código 0**. `pint --test` también terminó en 0. No se cambió el envoltorio ni la imagen Docker.

Las comprobaciones reales de navegador pasaron para los cuatro modos y un solo h1 activo, menú móvil, filtros desplegables, agregar/cambiar/quitar cantidades del carrito, resultados de estado y garantía, movimiento reducido, selección de cliente, precio decimal y total del editor, campos de póliza, apariencia clara/oscura, navegación móvil del portal con Barlow y desplazamiento contenido de usuarios. Las pantallas base devolvieron HTTP 200 y no desbordaron la página a ninguno de los dos tamaños pedidos.

Los logs locales están en `.design/r6/verify-all.log`, `pint-dirty-repo.log`, `pint-test.log`, `interactions-public.log`, `interactions-admin.log`, `states-admin.log`, `after-public.log` y `after-admin.log`. El aviso de tamaño del chunk de Three.js permanece; no se dividió la escena protegida para resolver un aviso de empaquetado.

## Crítica visual

Se revisaron primeras vistas y hojas completas de los tres anchos. Las puntuaciones siguientes son una autoevaluación con los límites de la skill; pasar el inspector no implica una nota de premio.

```text
ROUND 1
Seen in one second: emblema ATS con demasiada interfaz decorativa alrededor.
Idea (one sentence): la hoja de armado del local organiza páginas y documentos.
Live thing: los modos, el configurador y la consulta de folios.
Layout words by section: hero=emblema / servicios=lista / armado=ficha / tienda=índice / cierre=mostrador.
Scores: design 5.5 · usability 6 · creativity 7 · content 5 → overall 5.9
Caps applied: fallos del inspector; ausencia de fotografías; recursos de sustitución de producto.
Top three problems (most visible first), each with the screenshot it shows in and the fix:
1. before-home-desktop-fold.png: fondos y adornos compiten con el emblema. Aplanar el marco y reservar el acento.
2. before-category-mobile-fold.png: filtros ocupan la entrada al catálogo. Convertirlos en disclosure nativo.
3. before-dashboard-desktop-fold.png: marca y controles del portal no forman un sistema. Aplicar shell, tokens y componentes del kit.

ROUND 2
Seen in one second: señalética técnica y un mostrador de trabajo más claro.
Idea (one sentence): listas de piezas, fichas y folios con una sola tinta de acento.
Live thing: cotización editable y catálogo con datos reales.
Layout words by section: hero=emblema / servicios=filas / armado=hoja / categorías=índice / proceso=secuencia.
Scores: design 6.5 · usability 7.5 · creativity 7 · content 5 → overall 6.8
Caps applied: ausencia de fotos; sustitución de producto; colisiones que aún se corrigieron después.
Top three problems (most visible first), each with the screenshot it shows in and the fix:
1. round2-service-desktop.png: el encabezado deja media pantalla vacía. Componer título y explicación en dos columnas.
2. round2-quote-edit-mobile-fold.png: campos de partidas demasiado angostos. Dar ancho mínimo a la tabla y desplazamiento local.
3. inspect/round2-builder/mobile-page-01.jpg: HUD y dock tapan “Procesador”. Retirar la fijación de esos bloques en móvil.

ROUND 3
Seen in one second: el emblema y la acción de armar; en el portal, el trabajo pendiente.
Idea (one sentence): un mostrador de Plaza de la Tecnología convertido en fichas, listas y documentos.
Live thing: cambiar de modo, configurar, consultar folios y editar cotizaciones.
Layout words by section: hero=emblema / servicio=editorial / mantenimiento=filas / reparación=lista / asesoría=guía / preguntas=acordeón / cotización=documento.
Scores: design 6.5 · usability 8 · creativity 7 · content 5 → overall 7.0
Caps applied: menos de cuatro fotografías y recursos de marca donde faltan fotos de producto. No se redondea a 7.5.
Top three problems (most visible first), each with the screenshot it shows in and the fix:
1. after-product-mobile-fold.png: falta la fotografía real del SKU. Requiere material real del inventario; no se fabrica.
2. inspect/final-raw-home/tablet-fold.jpg: el emblema cruza la lectura. Corregido reservando su zona a 641–860 px y reinspeccionado.
3. inspect/final-raw-appearance/mobile-fold.jpg: Claro/Oscuro tenían contraste insuficiente. Corregido y medido de nuevo en final-appearance.
```

Portal final: diseño 7.5 · usabilidad 8 · creatividad 7 · contenido 8 → **7.6**. La identidad es más sobria que el sitio y las tablas extensas requieren desplazamiento en celular. Es una decisión de trabajo; no se ocultan costo, margen ni acciones al dueño para simular que caben todas las columnas.

## Pendientes y límites

- Fotos reales del local y de productos para sustituir los recursos de marca y elevar la crítica visual pública. No forman parte de los recursos entregados.
- Carga WebP: una prueba omitida por la extensión GD del contenedor.
- Copiar las capturas a `../.delegate/shots/` si esa ubicación exacta es necesaria; la evidencia completa sí está generada dentro del directorio permitido.
- Se conservan las excepciones de marca y avisos documentados del inspector. Los informes originales permanecen disponibles.
- Sin commits, dependencias nuevas de producción ni cambios de lógica de negocio. No se modificaron `../index.html`, `../sitio/` o `../assets/`.

## Archivos del pase

Creados:

- `DESIGN.md`
- `docs/r6-design-review.md`
- `.taste-skill/direction.json`
- `.taste-skill/app/direction.json`
- `resources/css/site-design.css`
- `resources/css/portal.css`
- `resources/js/site/design.js`
- `resources/js/components/ui/textarea/index.ts`
- `resources/js/components/ui/textarea/Textarea.vue`

Modificados (respecto a los cambios preparados que ya existían al comenzar):

- `resources/css/app.css`
- `resources/css/modes.css`
- `resources/js/app.ts`
- `resources/js/builder/builder.css`
- `resources/js/components/AppLogo.vue`
- `resources/js/components/AppShell.vue`
- `resources/js/components/AppSidebar.vue`
- `resources/js/components/AppSidebarHeader.vue`
- `resources/js/components/AppearanceTabs.vue`
- `resources/js/components/CustomerPicker.vue`
- `resources/js/components/DeleteUser.vue`
- `resources/js/components/ManageTwoFactor.vue`
- `resources/js/components/PasswordInput.vue`
- `resources/js/components/TwoFactorRecoveryCodes.vue`
- `resources/js/components/TwoFactorSetupModal.vue`
- `resources/js/components/UserMenuContent.vue`
- `resources/js/components/ui/dialog/DialogContent.vue`
- `resources/js/components/ui/dialog/DialogScrollContent.vue`
- `resources/js/components/ui/input/Input.vue`
- `resources/js/components/ui/sheet/SheetContent.vue`
- `resources/js/components/ui/sidebar/SidebarTrigger.vue`
- `resources/js/components/ui/sidebar/Sidebar.vue`
- `resources/js/components/ui/sidebar/SidebarRail.vue`
- `resources/js/layouts/app/AppSidebarLayout.vue`
- `resources/js/layouts/settings/Layout.vue`
- `resources/js/pages/Dashboard.vue`
- `resources/js/pages/admin/Brands.vue`
- `resources/js/pages/admin/Categories.vue`
- `resources/js/pages/admin/Customers.vue`
- `resources/js/pages/admin/ProductForm.vue`
- `resources/js/pages/admin/Products.vue`
- `resources/js/pages/admin/QuoteEditor.vue`
- `resources/js/pages/admin/Quotes.vue`
- `resources/js/pages/admin/ServiceOrder.vue`
- `resources/js/pages/admin/ServiceOrders.vue`
- `resources/js/pages/admin/ServicePlans.vue`
- `resources/js/pages/admin/Settings.vue`
- `resources/js/pages/admin/Users.vue`
- `resources/js/pages/admin/Warranties.vue`
- `resources/js/pages/admin/WarrantyForm.vue`
- `resources/js/pages/auth/ConfirmPassword.vue`
- `resources/js/pages/auth/ForgotPassword.vue`
- `resources/js/pages/auth/ResetPassword.vue`
- `resources/js/pages/auth/TwoFactorChallenge.vue`
- `resources/js/pages/settings/Appearance.vue`
- `resources/js/pages/settings/Profile.vue`
- `resources/js/pages/settings/Security.vue`
- `resources/js/site/main.js`
- `resources/js/site/pages/home.js`
- `resources/views/layouts/site.blade.php`
- `resources/views/site/pages/asesoria.blade.php`
- `resources/views/site/pages/contacto.blade.php`
- `resources/views/site/pages/home.blade.php`
- `resources/views/site/pages/servicio-tecnico.blade.php`
- `resources/views/site/quote.blade.php`
- `resources/views/site/shop/cart.blade.php`
- `resources/views/site/shop/category.blade.php`
- `resources/views/site/shop/index.blade.php`
- `resources/views/site/shop/product.blade.php`
- `resources/views/site/shop/search.blade.php`
- `resources/views/site/tracking/status.blade.php`
- `resources/views/site/tracking/warranty.blade.php`

Borrados: ninguno. `modes.css` solo recibió normalización de comillas del formateador. El cambio ajeno en `../CLAUDE.md` no pertenece a R6. Los artefactos locales `.design/r6/` quedan ignorados por Git y no se publican.
