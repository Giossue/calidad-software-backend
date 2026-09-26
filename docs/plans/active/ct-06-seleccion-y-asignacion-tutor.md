# CT-06: Selección y Asignación de Tutor al Aprobar Tema de Titulación

## Objetivo

Permitir que el **Coordinador de Titulación** consulte el catálogo completo de docentes registrados en el sistema para seleccionar y asignar un docente tutor al momento de aprobar un tema de titulación.

## Reglas de Negocio

1. **Elegibilidad de Docentes:**
   - Todo docente activo en el sistema (`roles.slug = 'docente'`, `estado = true`) puede ser seleccionado como tutor.
   - No existe exclusión por tener tutorías o revisiones previas (un docente puede tener múltiples tutorías y actuar simultáneamente como par académico en otros temas).
   - El endpoint provee el listado completo con métricas de asignaciones activas (conteo de tutorías y conteo como par académico) para brindar visibilidad al coordinador.

2. **Asignación Obligatoria:**
   - La asignación del tutor se formaliza al aprobar la propuesta de titulación (`POST /api/v1/coordination/degree-topics/{topic}/approve`).
   - El campo `tutor_id` es obligatorio y debe corresponder a un docente activo.

## Contratos de API

- `GET /api/v1/coordination/teachers` (lista todos los docentes disponibles con conteo de asignaciones actuales).
- `POST /api/v1/coordination/degree-topics/{topic}/approve` (efectúa la asignación formal del tutor junto a los pares).
