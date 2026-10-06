# Administración de períodos y modalidades

## Goal

Exponer operaciones administrativas autenticadas para registrar, actualizar y desactivar períodos académicos y modalidades, sin modificar el esquema PostgreSQL existente.

## Tasks

- [x] Añadir validación, autorización y respuestas JSON para los dos recursos.
- [x] Añadir rutas versionadas y acciones de desactivación lógica.
- [x] Añadir pruebas HTTP aisladas del esquema de dominio no migrado.
- [ ] Ejecutar la suite localmente con PHP y dependencias Composer instaladas.

## Decisions

- La desactivación actualiza exclusivamente `estado` a `false`; no se elimina ninguna fila.
- `2026-10-05` — Períodos académicos (PAO):
  - Solo puede existir un PAO activo. Lo garantiza el índice único parcial
    `periodo_academico_single_active_unique` y la acción de activación, que
    responde 422 (`period`) si ya hay otro activo.
  - Un PAO no se desactiva manualmente: se eliminó `PATCH .../deactivate`. Se
    desactiva solo cuando su `fecha_fin` ya pasó en hora de Ecuador
    (`America/Guayaquil`). El middleware `ExpireAcademicPeriods` lo comprueba
    una vez por día en las solicitudes a la API, porque el contenedor no
    ejecuta un scheduler.
  - Un PAO nuevo queda activo solo si no hay otro activo y su `fecha_fin` no ha
    pasado; en otro caso se registra inactivo. Un PAO vencido no puede activarse.
  - La `fecha_fin` de un PAO activo no puede moverse a una fecha anterior a hoy.
  - La migración `2026_10_05_000000_allow_single_active_academic_period` falla
    si, tras desactivar los PAO vencidos, sigue habiendo más de uno activo.
- Solo `administrador` puede operar estos recursos.
- El contrato público mantiene nombres en inglés, como el contrato de autenticación existente.

## Verification

- Pendiente: el clon de revisión no contiene dependencias Composer y sus migraciones no construyen las tablas de dominio.
