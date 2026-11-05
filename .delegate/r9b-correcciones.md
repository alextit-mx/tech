# Encargo R9b: correcciones de la revisión de R9

Cambios chicos; no toques nada más. Capturas de referencia en `.design/r9/` (`builder-complete-desktop.png`,
`frost-to-ats.png`).

## Cambios
1. **Menú duplicado**: en el encabezado del sitio aparece el enlace "Buscar productos" junto al campo "Buscar" (se ve en
   `builder-complete-desktop.png`). Quita ese enlace del menú; si lo agregaste para celular (donde el campo se oculta),
   muéstralo solo cuando el campo de búsqueda no está visible.
2. **Barra del modo ATS en 0 %**: al volver al modo ATS (pestaña o avance automático) la fila "Tu próxima PC" queda en
   `0%` con la barra vacía (`frost-to-ats.png`). Al entrar a ATS debe animarse de 0 a 100 % igual que en el arranque
   (o mostrarse en 100 % con movimiento reducido).
3. **Guion suelto**: en `frost-to-ats.png` hay un `_` pequeño bajo el logo, arriba a la izquierda del hero (≈ x 200,
   y 75). Probablemente el cursor parpadeante de la fila BIOS o un elemento fuera de lugar. Que no aparezca.

## Terminado cuando
- Capturas nuevas en `.design/r9b/` del inicio (ATS tras volver desde Frost) y del configurador a 1440×900.
- `bash ../.delegate/verify.sh all` en verde.
