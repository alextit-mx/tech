# Tech Computer · sistema de diseño

Fuente de verdad para el sitio público y el portal. Identidad de un mostrador gamer de Plaza de la Tecnología: piezas, fichas, folios y trabajo de taller. Se conserva la marca ATS, su arranque BIOS, Chakra Petch, Barlow y las esquinas cortadas.

## Dirección

**La hoja de armado del local se convierte en la página.** Títulos claros como señalética, listas alineadas como una ficha y un solo corte en las superficies que importan. La ubicación, los folios COT / OS / GAR / POL, el IVA incluido, la consulta del equipo y el configurador son contenido propio; no se inventan métricas, testimonios ni fotografías de inventario.

Referencias leídas: NVIDIA aporta jerarquía técnica y precisión de catálogo; PlayStation, capítulos con distinta densidad; Lamborghini, fondos negros y geometría angular; BMW M, contraste entre titulares y texto; Linear y `taste-skill/app-screens`, superficies discretas y densidad de trabajo. No se importan sus marcas, imágenes, fuentes ni paletas.

Sorteo taste: `page-is-the-object`, semilla `1107863479`. Se adapta como pase sobre la estructura existente, conservando el hero con modos y emblema en lugar del montón de carpetas sorteado: la conservación del hero es requisito del encargo. Portal: `true-black-flat`, con elevación por tono y navegación neutral; también respeta la preferencia clara del kit.

## Color

| Rol                | Token público      | Valor ATS / uso                                       |
| ------------------ | ------------------ | ----------------------------------------------------- |
| Fondo              | `--ink`            | `#080808`, negro neutral                              |
| Superficie         | `--ink-2`          | `#141414`, ficha, formulario y capítulo               |
| Superficie elevada | `--surface-raised` | `#202020`, controles y selección neutral              |
| Texto              | `--paper`          | `#F5F5F3`, títulos y lectura                          |
| Secundario         | `--steel`          | `#B9B9B6`, información de apoyo                       |
| Metadatos          | `--steel-dim`      | `#999996`, nunca ocultar datos por falta de contraste |
| Acento             | `--red`            | `#E30613` en ATS; lo redefine el modo                 |
| Acento activo      | `--red-hot`        | `#F2101E` en ATS                                      |
| Acento profundo    | `--red-deep`       | `#7A0710` en ATS                                      |
| Positivo           | `--ok`             | `#69D99A`, existencia o confirmación real             |
| Aviso              | `--warn`           | `#EBC474`, atención real                              |

Todo acento sale de `var(--red)`, `var(--red-hot)`, `var(--red-deep)` o de `color-mix` con ellas. No se escriben rojos dentro de componentes. El rojo se reserva para la acción principal y una selección significativa. Los errores son semánticos y se acompañan de palabras.

Los modos ATS, RGB, Frost y Amber mantienen sus valores y comportamiento en `modes.css` y `theme.js`. RGB conserva su arcoíris como excepción de marca; no justifica degradados decorativos en tarjetas o fondos. Los botones sobre acentos claros usan tinta oscura; en RGB el relleno se oscurece para mantener texto blanco legible.

El portal usa los tokens semánticos del kit (`background`, `card`, `muted`, `foreground`, `border`, `input`, `primary`). Claro: fondo `#F7F7F5`, superficie blanca, tinta `#181818`, secundario `#60605C`. Oscuro: fondo `#0C0C0C`, superficie `#161616`, tinta `#F5F5F3`, secundario `#B9B9B6`. La acción del portal es tinta invertida; ATS identifica la marca y la información que necesita atención. Una sola familia de acento.

## Tipografía

- **Chakra Petch 600/700**: títulos del sitio, folios y precios. El diseño conserva su carácter técnico sin convertir todos los rótulos en mayúsculas.
- **Barlow 400/500/600/700**: párrafos, navegación, botones, etiquetas y portal. Las tablas y los números usan `font-variant-numeric: tabular-nums`.
- Sitio: display fluido 38–88 px, sección 28–44 px, subtítulo 20–22 px, cuerpo 17–18 px, metadatos 13–14 px. Máximo dos líneas en titulares; ajustar microcopia antes que reducirlos.
- Portal: título 24 px, sección 16 px, cuerpo 14 px, metadatos 12–13 px, cifra 24–30 px. Nunca un hero de marketing dentro del área de trabajo.
- Interlineado: display 1.06–1.12; cuerpo 1.5–1.6. Párrafos de hasta 62 caracteres por línea, alineados a la izquierda. Solo dos familias; sin monospace decorativo.

## Espacio y geometría

- Unidad de 4 px: 4 / 8 / 12 / 16 / 24 / 32 / 48 / 64 / 96 / 128.
- Sitio: margen lateral fluido de 20–40 px, igual en ambos lados; límite amplio de lectura, sin encerrar cada sección en otra tarjeta. Capítulos 96–128 px en escritorio, 64–80 px en celular. Los catálogos y formularios usan distancias menores.
- Portal: sidebar de 232 px, margen de contenido 28 px, separación principal 24 px y separación entre controles 8 px. A 1440: 1440 − 232 − 2×28 = 1152 px de trabajo; dos columnas de 564 con 24 de separación. En celular: margen 16 px y navegación en sheet del kit.
- Público: radio 0; corte de 10–14 px solo en CTA principal, ficha de producto y hoja de resumen. No cortar el foco ni el texto. Formularios: radio 4 px.
- Portal: panel 8 px, control 6 px, estado 4 px. Círculos solo para avatar y controles que lo necesitan.
- Sin sombras en reposo, paneles de vidrio ni mallas de fondo. Se separa por espacio y tono. Bordes de 1 px solo en formularios, tablas y app. Sombras reservadas para menús y diálogos.

## Componentes públicos

- Encabezado opaco, marca real, navegación simple y herramientas. Una sola fila en celular; menú desplegable sin cubrir el contenido. Acceso al personal y carrito mantienen sus nombres accesibles.
- Botón primario plano, mínimo 48 px, corte ATS y etiqueta de acción. Secundario neutral; sin círculos anidados para sus íconos. Foco visible de 2 px, con espacio exterior.
- Hero: conserva el emblema, las cuatro pestañas y la pausa. La información BIOS es una excepción de identidad, situada junto al contenido que explica; no se repite como adorno por todo el sitio. Sin numeración decorativa de pestañas.
- Servicio y asesoría: encabezado editorial con título a la izquierda y explicación/acción a la derecha; una sola columna en celular.
- Servicios: filas editoriales con título y explicación, sin íconos encerrados en cajas. El proceso conserva un `ol` porque sí es secuencial.
- Departamentos: índice tipográfico plano, distinto de las fichas de producto. Rejillas repetidas se reservan al catálogo.
- Producto: imagen cuadrada `contain`, especificaciones y precio con IVA. Si falta fotografía se conserva el recurso de marca existente; no se fabrica una foto que prometa un SKU distinto. La ficha muestra la marca fuera del titular.
- Categoría: filtros visibles en escritorio; resumen desplegable en celular para que la primera pieza aparezca pronto. Los filtros conservan método, nombres y valores.
- Carrito: lista y cantidades a la izquierda; total y datos de contacto a la derecha. Vacío útil con acceso a tienda y configurador.
- Cotización: documento con folio, destinatario, vigencia, partidas y total. En celular se conservan precio unitario y fecha de vencimiento; la tabla se adapta, no oculta información.
- Estado y garantía: formulario legible antes de cualquier resultado; etiquetas persistentes, error junto al formulario y resultados en una hoja plana.
- Contacto: ubicación y comunicación frente a transferencia; cuentas en cifras tabulares, botones Copiar y mapa existente.
- Pie: cierre con ubicación y datos reales, navegación útil y línea legal legible, sin un segundo hero.

## Componentes del portal

Usar `Button`, `Input`, `Textarea`, `Label`, `Badge` y el shell/sidebar del kit; respetar formularios, eventos, validaciones y permisos existentes. Una acción primaria por pantalla; acciones por fila en variante neutral. Tablas sin franjas, encabezados en oración y gris legible; importes a la derecha. Datos principales y sus acciones en la primera pantalla. Las tablas extensas se desplazan dentro de su marco en celular, con la identidad fija cuando resulte necesario.

Inicio: una franja de cifras y dos pares de bloques operativos, sin iconos en las cifras ni gráficas inventadas. Cotizaciones: folio y cliente primero. Editor: cliente, partidas, totales y guardar. Catálogo: título y alta separados de filtros, edición rápida legible. Servicio: equipo, estado e historial. Garantías y pólizas: vigencia y cobertura primero. Configuración y perfil: grupos de campos con un solo modelo de guardado por formulario.

Cero significa cero; un dato ausente se muestra como “—”. Estados vacío, error, deshabilitado y guardando conservan el espacio y la legibilidad. El diseño nunca cambia las props, los permisos ni la información enviada al navegador.

## Movimiento

Feedback 140–180 ms, menú 240 ms; entradas públicas discretas de 500–700 ms, sin esperar a la carga ni esconder contenido permanentemente. Cada familia tiene un gesto: listas por filas, imágenes por encuadre, hero por sus modos existentes. Solo `transform` y `opacity` en entradas. `prefers-reduced-motion` deja el contenido completo y elimina desplazamientos. En portal no hay entradas: solo respuesta a acciones. El configurador y el login mantienen sus implementaciones; solo se modifican si la inspección demuestra un problema. En el configurador móvil, HUD, escena y resumen recorren el flujo normal para no tapar el selector; las etiquetas técnicas tienen un mínimo de 11 px.

## Tono y contenido

Español de México, directo y de mostrador: “Arma tu PC”, “Consultar”, “Recibir equipo”, “Cotizar”. Hablar de la pieza, la falla y la solución concreta. Precios MXN con IVA incluido; no cambiar cálculos ni promesas de negocio. Sin cifras de rendimiento inventadas ni jerga decorativa. La microcopia acorta titulares y nombra acciones, sin alterar términos comerciales.

## Verificación

Capturas reales antes/después de las rutas públicas, sus variantes útiles y todas las pantallas del portal a 1440×900 y 390×844. Inspector taste a 1440, 768 y 390; distinguir fallos de geometría, contraste o contenido de excepciones de marca documentadas. Las referencias generadas son borradores de composición, no capturas reales ni nuevas fotos para producción. Verificar modos, teclado, menú, carrito, formularios y permisos con las pruebas existentes. El reporte debe decir qué se vio, qué se corrigió y qué falta.

## Excepciones comprobadas

- No existen fotos del local o inventario en los recursos recibidos. No se agregan imágenes externas ni se fabrican fotos de SKU. El mínimo de cuatro fotos del inspector se exceptúa solo en las páginas editoriales; se registra como limitación visual y afecta la crítica.
- El configurador conserva las tarjetas de rareza, sus degradados y el relieve de sus botones. Las etiquetas BIOS en mayúsculas son parte de la identidad; sí se corrige su tamaño y contraste. La escena y el loader quedan intactos.
- El login conserva `AuthBrandLayout.vue`: su fondo de marca y el párrafo de apoyo de tres líneas en escritorio. Ese párrafo no es el título principal; “Inicia sesión” queda en una línea.
- Catálogo, búsqueda, carrito, documentos y consultas se inspeccionan como pantallas de trabajo (`--app`), además de las capturas con el diseño público. Su título puede ser mayor al del portal para conservar la señalética de la tienda.
- Los avisos sobre filas de altura distinta y espacio tras una lista corta se evalúan con sus datos reales. No se inventan registros ni se estiran paneles para llenar la pantalla.

## R9 · producto y movimiento

El configurador conserva el HUD de R6 y muestra únicamente las piezas elegidas, sin cajas de reserva. Materiales físicos, ambiente de estudio CC0 local de 1K, aluminio cepillado, carbono, PCB serigrafiado y cables con peines dan escala al objeto. El ARGB comparte reloj: rojo ATS en reposo con desplazamiento de intensidad, espectro durante el final. La luz de color queda cerca del gabinete. El vidrio conserva reflejos de softbox discretos; el piso difumina el reflejo y apoya el equipo con una sombra suave. Polvo y grano quedan por debajo de la lectura de materiales.

La cámara entra durante 2.6 s al salir el loader cuando hay piezas. En un armado vacío no se simula un ventilador inexistente. Enfocar y elegir interrumpen la entrada. Las llegadas giran, caen, rebotan y encienden sus LEDs en secuencia, con chispas breves. `prefers-reduced-motion` elimina desplazamientos, giro de final y barridos RGB. En celular se usa el preset ligero. Una ventana real de fps, después del calentamiento, desactiva GTAO, reflejo, DOF, sombras y transmisión si baja de 45 fps. Los números de diagnóstico viven en `data-performance` del canvas y no forman parte del HUD.

`--mode-accent` es la fuente inmediata de color de cada modo; `--red` la interpola para los acentos del sitio. Los botones usan la fuente inmediata junto con su texto claro u oscuro para evitar combinaciones ilegibles durante el cambio de modo. El emblema tiene una paleta ATS fija `#E30613`, independiente del modo de arranque. La navegación entre documentos usa fundidos nativos de 160–240 ms, con alternativa sin animación. Hover, presión y teclado comparten tiempos de 140–180 ms; el foco queda dentro de las figuras recortadas. El acceso conserva el mismo diseño con luz de fondo más contenida.
