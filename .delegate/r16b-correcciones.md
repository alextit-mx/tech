# Encargo R16b: correcciones a "Fichas pendientes"

## IMPORTANTE (igual que R16)
Sigue corriendo una carga de Icecat sobre la base de desarrollo: no ejecutes migraciones, seeders, `verify.sh migrate`
ni `all`, ni comandos `catalogo:*`, `equipos:*` o `productos:importar`. Las pruebas (SQLite en memoria) sí.

## 1. "Revisar armados" no sirve cuando todo está despublicado
`app/Support/BuildReviewer.php` solo mira armados publicados. Hoy los 6 están despublicados, así que tras capturar
fichas el botón responde "0 publicados, 0 despublicados" y el personal no sabe si ya puede publicar.
- Revisa también los no publicados con `publicationProblems()`. **No los publiques solos**: devuélvelos como
  `ready` (sin problemas) o con sus problemas.
- Respuesta: `published`, `unpublished` (los que se despublicaron en esta revisión), `ready` (no publicados y ya sin
  problemas) y `results` con `id`, `name`, `status` (`publicado` | `despublicado` | `listo` | `con_problemas`),
  `problems`.
- `PendingSpecs.vue` muestra el resumen y, para los `listo`, un enlace "Publicar" al editor de ese armado
  (Admin → Equipos armados). Los `con_problemas` muestran sus motivos.
- `equipos:revisar` imprime lo mismo (incluye "Listos para publicar: N" con sus nombres).

## 2. El contador del menú recorre todo el catálogo en cada página de Admin
`HandleInertiaRequests` comparte `pendingSpecsCount` y `PendingSpecs::products()` carga todos los productos de
armado en cada visita a Admin.
- Guárdalo en caché (`Cache::remember`, 10 min, clave única) y bórralo cuando cambien fichas: al guardar en
  `PendingSpecsController::update`, al guardar o confirmar ficha en el editor de producto, y al final de
  `productos:importar`, `catalogo:fichas` y `catalogo:icecat` (no simulados).
- Si es fácil, que `index` reutilice el mismo cálculo de conteos sin cargar dos veces la lista.

## Pruebas
Amplía `tests/Feature/PendingSpecsTest.php`: un armado despublicado sin problemas sale como `listo` y sigue sin
publicarse; uno publicado con problemas se despublica; el contador se invalida al guardar una ficha.

## Terminado cuando
`bash .delegate/verify.sh types`, `check`, `test` y `build` pasan. Reporta los archivos que cambiaste.

## No hagas
Nada de lo de IMPORTANTE. No toques otras pantallas ni el motor de compatibilidad. No hagas commit.
