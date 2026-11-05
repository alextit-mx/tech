# Tech Computer · instrucciones para Claude Code

Antes de hacer nada, lee **`CONTINUAR.md`**: estado del proyecto, decisiones del dueño, cómo levantar el entorno y qué sigue.

## Cómo se trabaja (reglas del usuario)
- Gasta siempre los menos créditos de Claude posibles.
- **Codex implementa SIEMPRE** (plan Plus), aunque sea un cambio pequeño: Claude diseña, escribe el encargo, revisa y
  verifica. Encargos en `.delegate/rN-*.md`; se corren con `bash .delegate/run.sh <encargo.md> [low|medium|high]`
  (desde WSL, `bash -lic`).
- Los errores que encuentre la revisión se le **regresan a Codex** en un encargo de corrección; Claude no los corrige.
- Si Codex no está disponible, avisar al usuario y esperar. Producción/hosting: confirmar con el usuario antes.
- Antes de lanzar una ronda, `git add -A` (sin commit) para revisar después solo lo que cambió Codex con `git diff`.
- Revisión barata: solo los archivos que cambiaron (`git status`, `git diff`) y build, tipos, lint y pruebas
  (`bash .delegate/verify.sh`). No releer todo el proyecto.
- Commits y push solo cuando el usuario lo pida.
- **Diseño**: todo trabajo visual usa las skills `taste-skill` (design-taste-frontend), `awesome-design-md`,
  `image-to-code` y `playwright-cli` (para capturas). Codex las lee desde `/mnt/c/Users/tit/.claude/skills/`
  y `.claude/skills/playwright-cli/`; Claude verifica con capturas, sin rediseñar él mismo.
  - Desde 2026-11-04 también: `ui-ux-pro-max` (estilos, paletas y tipografías; su buscador local es
    `python3 /mnt/c/Users/tit/.claude/skills/ui-ux-pro-max/scripts/search.py`), `storefront-best-practices`
    (tienda en línea) y `web-design-guidelines` (revisión de accesibilidad y UX). `find-skills` sirve para buscar
    más en skills.sh.
  - **Identidad gamer obligatoria** en el sitio público: no elegir direcciones nuevas de taste-skill; extender el
    lenguaje HUD de Arma tu PC (vidrio oscuro, esquinas cortadas, rojo con brillo, Chakra Petch). La portada con
    el emblema de partículas se queda.
- Textos al usuario en español de México.

## Lo esencial del proyecto
- El sistema vive en `sistema/` (Laravel 13 + Inertia 3 + Vue 3 + TypeScript + Tailwind 4). Sus reglas de código están en
  `sistema/AGENTS.md` y la especificación completa en `sistema/docs/plan.md`.
- En WSL **no hay PHP**: se usa Docker con los envoltorios `sistema/bin/php` y `sistema/bin/composer`
  (imagen `techcomputer-php:8.3`, Dockerfile en `docker/php/`). Siempre `export PATH="$PWD/bin:$PATH"` antes de `npm`.
- `index.html` y `assets/` de la raíz son la página temporal que está en producción: no tocar.
  `sitio/` es el diseño original estático (referencia, ya portado a Blade).
