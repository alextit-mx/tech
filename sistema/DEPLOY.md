# Subir Tech Computer al hosting compartido (cPanel)

El hosting no tiene Node ni, a veces, SSH: todo se compila en tu PC y se sube ya listo.

## 1. Requisitos del hosting

- PHP **8.3** (en cPanel: _Select PHP Version_) con `pdo_mysql`, `mbstring`, `gd`, `intl`, `zip`, `bcmath`, `fileinfo`, `openssl`.
- MySQL 8 o MariaDB 10.6+. Crea una base y un usuario con todos los permisos (cPanel → _MySQL Databases_).
- SSL activo para el dominio (cPanel → _SSL/TLS Status_ → AutoSSL).

## 2. Preparar en tu PC (WSL)

```bash
cd ~/Projects/techcomputer && git pull
cd sistema && export PATH="$PWD/bin:$PATH"
composer install --no-dev --optimize-autoloader
npm ci && npm run build                 # genera public/build
php artisan key:generate --show         # copia la clave para el .env de producción (solo la primera vez)
```

## 3. Qué subir

Sube la carpeta `sistema/` completa **sin**: `node_modules/`, `.env`, `database/database.sqlite`, `storage/logs/*.log`,
`tests/`, `.git/`. Sí deben ir `vendor/` y `public/build/`. Lo más cómodo: comprímela en un `.zip`, súbela con el
_Administrador de archivos_ y descomprímela en `/home/USUARIO/techcomputer` (fuera de `public_html`).

## 4. Que el dominio apunte a `public/`

**Opción A (recomendada):** cPanel → _Dominios_ → edita `techcomputer.com.mx` → raíz del documento:
`/home/USUARIO/techcomputer/public`.

**Opción B (si no deja cambiarla):** guarda una copia de lo que hay en `public_html` (la página temporal), copia el
contenido de `techcomputer/public/` dentro de `public_html/` y en `public_html/index.php` cambia las rutas:

```php
require __DIR__.'/../techcomputer/vendor/autoload.php';
$app = require_once __DIR__.'/../techcomputer/bootstrap/app.php';
$app->usePublicPath(__DIR__);   // fotos en public_html/uploads
```

## 5. `.env` de producción

Crea `techcomputer/.env` (a partir de `.env.example`) con al menos:

```
APP_NAME="Tech Computer"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...                      # la del paso 2
APP_URL=https://www.techcomputer.com.mx
APP_LOCALE=es
APP_TIMEZONE=America/Mexico_City
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=usuario_techcomputer
DB_USERNAME=usuario_tc
DB_PASSWORD=...
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync
OWNER_EMAIL=correo-del-dueno@...
OWNER_PASSWORD=una-contraseña-larga
```

**Nunca** subas este archivo a GitHub (el repo es público).

## 6. Crear las tablas

- **Con Terminal de cPanel o SSH:** `cd ~/techcomputer && php artisan migrate --force --seed`
- **Sin terminal:** en cPanel → _Remote MySQL_ agrega tu IP, y desde tu PC corre la migración contra la base remota:
  `DB_CONNECTION=mysql DB_HOST=tu-servidor DB_DATABASE=... DB_USERNAME=... DB_PASSWORD=... php artisan migrate --force --seed`
  (con `bin/php` pasa las variables con `-e` o pon temporalmente esos datos en tu `.env` local). Quita tu IP al terminar.

El seeder crea categorías, configuración y el usuario dueño con `OWNER_EMAIL` / `OWNER_PASSWORD`. **No** corras
`DemoSeeder` en producción. Entra a `/admin`, activa la verificación en dos pasos del dueño y crea a los vendedores.

## 7. Permisos y caché

- `storage/` y `bootstrap/cache/` con permiso de escritura (755 o 775).
- Crea la carpeta `public/uploads` (o `public_html/uploads` en la opción B) con permiso de escritura: ahí van las fotos.
- Si tienes terminal: `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
  Si cambias el `.env` después: `php artisan config:clear`.

## 8. Actualizar después

Repite el paso 2, sube solo lo que cambió (o el zip completo sin `.env`, `public/uploads` ni `storage/`) y corre
`php artisan migrate --force` si hubo migraciones nuevas. Luego limpia caché (`php artisan optimize:clear`).

## 9. Respaldos

- Base de datos: cPanel → _Backup_ → descarga la base cada semana (o al menos antes de cada actualización).
- Fotos: descarga `public/uploads` de vez en cuando.

## 10. Revisión después de subir

- `/`, `/tienda`, una ficha, `/arma-tu-pc`, `/carrito` cargan; `/sitemap.xml` y `/robots.txt` responden.
- Generar una cotización de prueba desde el carrito y desde el configurador; abrir su PDF.
- `/admin`: entrar como dueño, crear un producto con foto, ver que aparece en la tienda.
- `/estado` y `/garantia` con una orden y una garantía de prueba.
- Da de alta el sitio en Google Search Console y envía `https://www.techcomputer.com.mx/sitemap.xml`.
