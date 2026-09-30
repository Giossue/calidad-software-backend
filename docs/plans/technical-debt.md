# Technical Debt

## Active Debt

| Item | Impact | Owner | Target |
| ---- | ------ | ----- | ------ |
| Rollback SQLite de la migración docente del 2026-09-29 | `down()` intenta eliminar `asistencia.session_id` sin retirar su índice; SQLite rechaza la operación. También falta retirar explícitamente el índice único de `metrica_conocimiento.enrollment_id`. Afecta a la reversión local del esquema; `up()` y los flujos del módulo están verificados en SQLite y PostgreSQL. | Equipo backend | Preparar corrección compatible con migración ya compartida antes de necesitar una reversión local. Producción no admite rollback automático. |

Descubierto al verificar [Módulo Docente](completed/modulo-docente.md).

## Rules

- Debt must describe impact, not just discomfort.
- Debt discovered during implementation should be linked to the relevant plan.
- Critical debt should become an active plan.
