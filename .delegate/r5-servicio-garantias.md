# Encargo R5: servicio técnico, garantías, pólizas y guía de despliegue

## Objetivo
Registrar equipos que entran a servicio, emitir garantías y pólizas de mantenimiento con PDF, que el cliente consulte el
estado de su equipo y su garantía en el sitio, y dejar la guía para subir todo al hosting compartido.

## Lee primero
- `AGENTS.md`, `docs/plan.md` sección 6 (**la especificación; impleméntala completa**), y los patrones de R2 (folios,
  PDF, tokens, WhatsApp) y R3 (layout público).

## Cambios
1. Migraciones, modelos, Policies y páginas `admin/service-orders`, `admin/warranties`, `admin/service-plans`; reutiliza el
   generador de folios de R2 (prefijos `OS`, `GAR`).
2. Orden de servicio: alta rápida en mostrador, cambio de estado con nota (pública o interna), PDF de recepción, WhatsApp al
   cliente con folio y enlace a `/estado`.
3. Garantía desde cotización aceptada u orden entregada, o manual; PDF; anular con motivo.
4. Pólizas con registro de visitas y visitas restantes.
5. Público: `/estado` y `/garantia` (formularios con `throttle`; nunca muestran datos internos ni el teléfono completo) y
   enlaces en el menú de Servicio técnico.
6. Inicio del portal: equipos por estado, garantías y pólizas que vencen en 30 días.
7. `DEPLOY.md` según la sección 6.
8. Pruebas: folios; flujo de estados con eventos; consulta pública exige folio + 4 dígitos correctos y no filtra notas
   internas; vigencia por partida; garantía copia partidas de la cotización; visitas restantes.

## Terminado cuando
- build, types:check, check, pint y pruebas pasan; `DemoSeeder` incluye órdenes, garantías y una póliza.
