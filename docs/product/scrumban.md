# Gestión del producto con ScrumBan

## Fuente de requisitos

El proyecto evoluciona mediante historias y tareas en un tablero ScrumBan; no
mantiene un SRS monolítico. Cada ítem listo para desarrollo debe incluir:

- problema, actor y resultado esperado;
- alcance y exclusiones;
- criterios de aceptación observables;
- reglas de negocio y estados implicados;
- permisos por actor;
- casos límite y datos necesarios;
- evidencia esperada (pruebas, captura o medición según corresponda).

## Flujo recomendado

```text
Backlog -> Ready -> In progress -> Review -> Verify -> Done
```

- Define límites WIP visibles para `In progress`, `Review` y `Verify` en el
  tablero del equipo; no se inventan números en este repositorio.
- Se termina trabajo abierto antes de iniciar trabajo nuevo.
- Un bloqueo registra causa, responsable de resolverlo y siguiente revisión.
- Bugs críticos pueden usar una clase de servicio expedita, sin omitir revisión,
  pruebas ni Definition of Done.

## Definition of Ready mínima

Un ítem entra a `Ready` cuando el actor, el resultado, los criterios de aceptación,
las dependencias y las preguntas de negocio críticas están resueltas. Un mockup o
diagrama orienta, pero no sustituye reglas y estados verificables.

## Trazabilidad ligera

- El plan técnico en `docs/plans/active/` enlaza o nombra el ítem del tablero.
- Las pruebas usan nombres que describen el criterio, no el número del ticket.
- Una decisión transversal o costosa se registra en un ADR.
- Al completar, el plan pasa a `docs/plans/completed/` con verificaciones y deuda.
- La deuda no aceptable para Done no se pospone; la deuda legítima se registra en
  `docs/plans/technical-debt.md` con impacto y objetivo.

## Cambio de alcance

Cuando aparece información nueva, actualiza primero el ítem/criterio y después
el código y estos documentos en el mismo cambio. Si el diagrama, la historia y
el comportamiento existente discrepan, pide decisión al responsable de producto.
