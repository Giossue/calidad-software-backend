# Arquitectura de base de datos

## Fuente de verdad

PostgreSQL es la base principal y las migraciones Laravel versionadas son la
fuente de verdad del esquema. Eloquent es el acceso predeterminado.

## Convenciones físicas

- Tablas plurales `snake_case`, modelos singulares `StudlyCase`.
- PK `id`; FK `<relation>_id`; timestamps `created_at`/`updated_at`.
- Nombres en inglés consistentes con el código; la UI permanece en español.
- `foreignId()->constrained()` con política de borrado explícita.
- Enums de negocio se guardan como strings y se castean a enums PHP.
- `date` para fechas académicas, `time` para hora local y timestamps para eventos.
- `decimal` para porcentajes/notas; nunca `float` para valores exactos.

## Modelo inicial recomendado

| Tabla                 | Propósito                                                                     |
| --------------------- | ----------------------------------------------------------------------------- |
| `users`               | Identidad autenticable única.                                                 |
| `roles`, `role_user`  | Roles múltiples por usuario, salvo que un enum simple se confirme suficiente. |
| `academic_periods`    | Períodos académicos.                                                          |
| `degree_topics`       | Propuestas de titulación por estudiante/período.                              |
| `teacher_assignments` | Tutor o par académico asignado al tema.                                       |
| `tracking_sheets`     | Seguimiento general del tema.                                                 |
| `progress_activities` | Actividades registradas dentro de la ficha.                                   |
| `degree_observations` | Historial de observaciones de coordinación.                                   |
| `degree_schedules`    | Programación de titulación.                                                   |
| `degree_reports`      | Informes derivados del seguimiento.                                           |

Esta lista no autoriza crear tablas cuyas cardinalidades/reglas sigan abiertas en
`docs/product/domain-model.md`.

## Integridad

- Toda relación tiene FK; toda unicidad de negocio confirmada tiene unique constraint.
- Usa check constraints para rangos (`0 <= progress_percentage <= 100`) y orden
  temporal cuando PostgreSQL pueda protegerlo.
- Valida también en Form Requests para buen feedback; la base protege carreras y
  escrituras fuera de la UI.
- Restrict es el borrado por defecto de historia académica. Cascade requiere que
  el hijo no tenga valor ni auditoría independiente.
- Operaciones con varias escrituras relacionadas usan `DB::transaction()`.

## Índices y consultas

- Las FKs consultadas y filtros frecuentes se indexan explícitamente.
- Índices compuestos siguen el orden de filtros/orden real, no intuición.
- Usa `EXPLAIN (ANALYZE, BUFFERS)` con datos representativos antes de añadir un
  índice de rendimiento; cada índice también cuesta escritura y espacio.
- Listados usan paginación y orden total/determinista.

## Migraciones

- Una migración es pequeña, reversible cuando sea seguro y no se edita después de
  ser compartida: crea otra migración.
- Cambios destructivos usan estrategia expand/migrate/contract, respaldo y plan
  de rollback; no mezclan backfill pesado con un despliegue bloqueante.
- Prueba migración desde cero y sobre una copia representativa para cambios riesgosos.
- Producción ejecuta `php artisan migrate --force` como paso explícito de despliegue.
