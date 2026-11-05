# Encargo R18: logotipos configurables (sitio, PDF e ícono)

## Objetivo
En Admin → Configuración, la dueña puede subir y cambiar tres imágenes sin tocar código:
1. **Logo del sitio:** encabezado y pie del sitio público, pantalla de inicio de sesión y panel.
2. **Logo de cotizaciones y PDF:** cotización, orden de servicio y garantía.
3. **Ícono:** el emblema cuadrado. Se usa como ícono de la pestaña del navegador (favicon), ícono de acceso
   directo en celular (apple-touch-icon) y emblema chico del panel.

Si no hay imagen subida, todo se ve como hoy, con los archivos actuales de `public/img/`.

## IMPORTANTE
Sigue corriendo una carga de Icecat sobre la base de desarrollo.
- No ejecutes migraciones, seeders ni `verify.sh migrate`/`all`.
- No ejecutes `catalogo:*`, `equipos:*` ni `productos:importar`.
- No hace falta migración: los ajustes viven en la tabla `settings` (clave/valor).
- Las pruebas, con SQLite en memoria, sí.

## Lee primero
- `sistema/AGENTS.md`.
- `app/Http/Controllers/Admin/AdminController.php` (ajustes), `app/Http/Requests/Admin/SettingsRequest.php`,
  `resources/js/pages/admin/Settings.vue`, `resources/js/components/ImageUploader.vue`.
- `app/Support/WebpImage.php` y el disco `uploads` (`config/filesystems.php`, raíz `public/uploads`).
- `app/Support/Pdf.php`, que hoy pasa `$logo = public_path('img/ats-computer.png')`.
- `resources/views/layouts/site.blade.php`, `resources/views/app.blade.php`,
  `resources/js/components/{AppLogo,AppLogoIcon}.vue`, `resources/js/layouts/auth/AuthBrandLayout.vue`.
  Busca con grep todos los usos de `ats-computer.png`, `ats-emblema.png` y `favicon`.

## Cambios
1. **`App\Support\Branding`**, con `siteLogoUrl()`, `pdfLogoPath()` (ruta local para dompdf), `iconUrl()` e
   `iconPath(int $size)`.
   - Lee los ajustes `logo_site`, `logo_pdf` y `logo_icon`. Si están vacíos, usa los archivos actuales de
     `public/img/`.
   - Las URL llevan `?v=` con la fecha del archivo, para que el navegador no muestre el logo viejo.
2. **Subida** (solo quien ya puede cambiar Configuración):
   - Ruta `POST /admin/settings/logos/{tipo}`, con `tipo` = `site`, `pdf` o `icon`, más una para quitar la imagen
     y volver a la predeterminada.
   - **Formatos:** PNG, JPG o WebP, de máximo 2 MB. **No aceptes SVG** porque puede traer código.
   - Valida dimensiones mínimas: logos de 300 px de ancho; ícono cuadrado de 256 px o más, con tolerancia de 2 %.
   - **Guardado** en `public/uploads/branding/`, con un nombre único por versión. Borra el archivo anterior al
     reemplazarlo.
   - **Logo del sitio:** guarda el original redimensionado a un máximo de 800 px de ancho, conservando la
     transparencia.
   - **Logo del PDF:** guárdalo siempre como **PNG**, porque dompdf no lee WebP de forma confiable. Máximo
     1200 px de ancho.
   - **Ícono:** genera PNG de 32, 180 y 512 px, con transparencia.
3. **Usarlos en todos lados:**
   - Encabezado y pie del sitio, login y panel: `AppLogo`/`AppLogoIcon` reciben la URL desde una prop
     compartida de Inertia (`branding`).
   - `<link rel="icon">` y `apple-touch-icon` en los layouts del sitio y de la app.
   - `Pdf::stream` usa `Branding::pdfLogoPath()`.
4. **Configuración** (`Settings.vue`), sección "Logotipos", con tres tarjetas:
   - Cada una muestra una vista previa sobre fondo claro y otra sobre fondo oscuro, para ver si el logo se lee.
   - Cada una dice dónde se usa y el tamaño recomendado, y tiene los botones "Subir imagen" y "Usar el
     predeterminado".
   - El ícono muestra cómo se ve a 32 px.
   - Sigue el estilo actual del panel (`taste-skill`, pantallas de app).
5. **Pruebas** (`tests/Feature/BrandingTest.php`):
   - Subir cada tipo y comprobar que se guarda y se muestra en el layout y el favicon.
   - El PDF usa el logo subido.
   - Se rechazan SVG, archivos de más de 2 MB e imágenes chicas o no cuadradas en el ícono.
   - Quitar la imagen vuelve a la predeterminada.
   - Sin permiso, la respuesta es 403.

## Terminado cuando
- `bash .delegate/verify.sh types`, `check`, `test` y `build` pasan.
- Textos en español de México.
- Revisa la pantalla de Configuración en 1280 px y en celular.
- Reporta los archivos que cambiaste.

## No hagas
- Lo de la sección IMPORTANTE.
- No cambies el diseño de los PDF ni del encabezado del sitio, solo de dónde sale la imagen.
- No toques `index.html`, `assets/` ni `sitio/` de la raíz.
- No hagas commit.
