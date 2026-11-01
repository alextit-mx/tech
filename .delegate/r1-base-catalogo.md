# Encargo R1: roles, configuración y catálogo del portal interno

## Objetivo
Que el dueño y los vendedores entren a `/admin` y administren productos, precios, existencias, fotos, categorías y marcas,
con permisos por rol (el vendedor ve costos pero **no márgenes**) y registro de cada cambio de precio.

## Lee primero
- `AGENTS.md` — comandos (PHP corre en Docker vía `bin/`) y reglas.
- `docs/plan.md` secciones 1 y 2 — **es la especificación; impleméntala completa.**
- `routes/web.php`, `routes/settings.php`, `resources/js/layouts/`, `resources/js/components/AppSidebar.vue`,
  `resources/js/pages/Dashboard.vue`, `app/Providers/FortifyServiceProvider.php`, `tests/Feature/` — patrones del kit.
- `../sitio/assets/js/catalogo.js` — modelos reales de CPU/GPU para el `DemoSeeder`.
- `../index.html` — datos del negocio y de transferencia para los valores iniciales de `settings`.

## Cambios
1. Migraciones, modelos (con casts y relaciones), factories y seeders de las secciones 1 y 2. `.env.example`: `OWNER_EMAIL`,
   `OWNER_PASSWORD`, `APP_LOCALE=es`, `APP_TIMEZONE=America/Mexico_City`; traducciones de validación al español
   (`lang/es`).
2. Login: rechaza usuarios inactivos con un mensaje claro (hazlo en Fortify, no en el front).
3. Rutas `admin.*` bajo `/admin` con `auth`. Mueve el panel a `/admin` (nombre `admin.dashboard`) y ajusta redirecciones de
   Fortify y pruebas del kit. En `/` deja temporalmente una página simple "Sitio en construcción" (R3 la reemplaza).
4. Controladores + Form Requests + Policies/Gates. Las props de Inertia para vendedor **no incluyen** `margin` ni nada para
   calcularlo aparte de costo y precio (el costo sí lo ven).
5. Páginas Vue/TS en `resources/js/pages/admin/` (products, categories, brands, users, settings) con los componentes `ui`
   del kit, en español, marca roja `#E30613`, fuentes Chakra Petch/Barlow. Edición rápida de precio y existencias en la fila
   (guardar con Enter o al salir del campo, con aviso de guardado o error). Formulario con campos de `specs` según
   `builder_slot`, fotos (subir varias, reordenar, borrar), historial de precios.
6. Imágenes: disco `uploads` en `config/filesystems.php` → `public_path('uploads')`; conversión con GD a WebP ≤1600 px.
   Agrega `/public/uploads` a `.gitignore`.
7. Ajuste masivo (dueño): ±% a seleccionados o a una categoría completa, redondeo hacia arriba a múltiplo de $10, registra
   `price_changes`.
8. Pruebas de feature: usuario inactivo no entra; vendedor no recibe `margin` en props y recibe 403 en usuarios,
   configuración, categorías y ajuste masivo; dueño sí; cambio de precio (normal, rápido y masivo) crea `price_changes`;
   redondeo a $10; costo en USD usa `exchange_rate`; validación de `specs` por slot; subida de imagen crea WebP;
   el dueño no puede desactivarse a sí mismo.

## Terminado cuando
- `php artisan migrate:fresh --seed` y `php artisan db:seed --class=DemoSeeder` corren limpio.
- `npm run build`, `npm run types:check`, `npm run check`, `vendor/bin/pint --test` y `php artisan test` pasan.
- Reporte final con archivos creados/cambiados/borrados, credenciales de prueba y pendientes.

## No hagas
- Nada de R2 en adelante (clientes, cotizaciones, sitio público, configurador).
- No cambies `platform.php` de composer ni agregues paquetes PHP en esta ronda.
