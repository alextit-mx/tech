# Tech Computer — página temporal

Página de "sitio en construcción" de Tech Computer (ATS Computer): venta,
ensamble, mantenimiento y actualización de PCs gamer en Plaza de la
Tecnología, CDMX.

- `index.html` — toda la página (estilos y código incluidos).
- `assets/` — logo, emblema (transparentes, rojo de marca `#E30613`) y favicon.

## Qué hace

- El emblema ATS se arma con miles de partículas al cargar (Three.js) y,
  al bajar, se separa como una vista explotada.
- Arranque tipo BIOS: procesador, tarjeta de video y memoria "listos" y el
  sitio web en 72 %.
- Botón para cotizar por WhatsApp, cómo llegar (Google Maps), servicios,
  correos, datos para transferencia (con botón de copiar), razón social y RFC.
- Se adapta a celular; respeta "reducir movimiento" del sistema; si el
  navegador no tiene WebGL, muestra el emblema como imagen.

## Verla en tu PC

Usa módulos desde CDN, así que no abre con doble clic; necesita un servidor:

```bash
cd ~/Projects/techcomputer
python3 -m http.server 8765
# abre http://localhost:8765/
```

## Publicarla

Sube `index.html` y la carpeta `assets/` a la raíz del hosting de
www.techcomputer.com.mx (por FTP o el administrador de archivos). No necesita
nada más.
