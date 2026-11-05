# Encargo R20b: armados sugeridos que se atoran + vista previa de logotipos

## IMPORTANTE
- **R19 sigue corriendo** en el sitio público. No toques `resources/views/site/**`, `layouts/site.blade.php`,
  `resources/css/{site,shop,site-design,header,modes}.css` ni `resources/js/site/**`.
- No ejecutes migraciones, seeders ni `verify.sh migrate`/`all`. Puedes leer la base de desarrollo, pero
  **no corras `equipos:sugeridos` sin `--simular` ni `equipos:revisar` sobre ella**: los ejecuto yo.
- Si `npm run build` falla porque otra compilación corre al mismo tiempo, espera un minuto y vuelve a intentar.

## 1. `equipos:sugeridos` no termina
- Sobre la base de desarrollo corrió **más de 80 minutos al 100 % de CPU** sin imprimir nada, y lo detuve. Hay
  3,882 productos, con muchos gabinetes y disipadores en "Ficha pendiente".
- En cambio, `GET /arma-tu-pc/sugerido` responde en 0.2 s para `gaming1440`/`gaming1080` con cualquier
  presupuesto. Revisa los demás usos (`gaming4k`, `stream`, `create`, `office`) y los PRESETS de
  `app/Console/Commands/SuggestBuilds.php`.
- Encuentra la causa en `app/Support/BuildSuggester.php` (búsqueda con vuelta atrás) y en el comando.
  Sospechas: explosión combinatoria, el límite de 8 candidatos que no se aplica en algún paso, bajar de nivel
  que reinicia la búsqueda sin tope, o consultas dentro de los ciclos.
- **Arréglalo:**
  - un tope duro de combinaciones evaluadas por armado (por ejemplo 20,000) y de tiempo (por ejemplo 5 s);
  - si se alcanza, devuelve "no disponible" con el motivo;
  - candidatos precargados una sola vez, con sus fichas;
  - el comando imprime el avance por armado (nombre, resultado y tiempo).
- Que el endpoint del sitio use los mismos topes.
- Agrega `--simular` a `equipos:sugeridos`: calcula e imprime, pero no guarda.
- **Pruebas:** con cientos de candidatos por tipo, todos sin ficha, el comando termina en segundos y responde
  "no disponible". Con un catálogo pequeño y válido, arma bien como hoy.
- Mide el tiempo con `php artisan equipos:sugeridos --simular` sobre la base de desarrollo (solo lectura) y
  repórtalo. Debe terminar en menos de 1 minuto en total.

## 2. Vista previa de logotipos (`resources/js/components/LogoSetting.vue`)
- En R20 cambiaste la vista previa "sobre fondo claro" por `bg-muted`. Era intencional: la dueña necesita ver
  cómo se ve el logo sobre **blanco** (PDF, sitio claro) y sobre **negro** (sitio oscuro).
- Regresa la caja clara a blanco fijo (`bg-white`, también en tema oscuro) y la oscura a negro fijo, con el
  texto "Sobre fondo claro" / "Sobre fondo oscuro" visible debajo de cada una.

## Terminado cuando
- `bash .delegate/verify.sh types`, `check`, `test` y `build` pasan.
- Reportas la causa encontrada, el tiempo medido con `--simular` y los archivos que cambiaste.

## No hagas
- Lo de la sección IMPORTANTE.
- No cambies las reglas de compatibilidad.
- No hagas commit.
