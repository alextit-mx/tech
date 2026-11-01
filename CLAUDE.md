# Tech Computer · instrucciones para Claude Code

Antes de hacer nada, lee **`CONTINUAR.md`**: estado del proyecto, decisiones del dueño, cómo levantar el entorno y qué sigue.

## Cómo se trabaja (reglas del usuario)
- Gasta siempre los menos créditos de Claude posibles.
- Por defecto la implementación la hace **Codex**: Claude diseña, escribe el encargo, revisa y verifica.
  Encargos en `.delegate/rN-*.md`; se corren con `bash .delegate/run.sh <encargo.md> [low|medium|high]` (desde WSL, `bash -lic`).
- Claude lo hace directo solo si son 1–3 líneas, es una decisión que el usuario no ha tomado, es seguridad o permisos
  delicados, es producción/hosting, Codex ya falló 2 rondas o Codex no está disponible y el usuario lo pide.
- Revisión barata: solo los archivos que cambiaron (`git status`, `git diff`) y build, tipos, lint y pruebas
  (`bash .delegate/verify.sh`). No releer todo el proyecto.
- Commits y push solo cuando el usuario lo pida.
- Textos al usuario en español de México.

## Lo esencial del proyecto
- El sistema vive en `sistema/` (Laravel 13 + Inertia 3 + Vue 3 + TypeScript + Tailwind 4). Sus reglas de código están en
  `sistema/AGENTS.md` y la especificación completa en `sistema/docs/plan.md`.
- En WSL **no hay PHP**: se usa Docker con los envoltorios `sistema/bin/php` y `sistema/bin/composer`
  (imagen `techcomputer-php:8.3`, Dockerfile en `docker/php/`). Siempre `export PATH="$PWD/bin:$PATH"` antes de `npm`.
- `index.html` y `assets/` de la raíz son la página temporal que está en producción: no tocar.
  `sitio/` es el diseño original estático (referencia, ya portado a Blade).
