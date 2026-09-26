# CT-04: Revisión de Temas de Titulación Pendientes por Paralelo y Período

## Objetivo

Permitir que el **Coordinador de Titulación** consulte y revise los temas de titulación propuestos por estudiantes que pertenecen a un paralelo en el período académico vigente, mostrando el listado de temas pendientes con su detalle completo para decidir si cumplen los requisitos.

## Requerimientos

1. **Filtro de Período y Paralelo:**
   - La consulta identifica automáticamente el período académico vigente (`estado = true`).
   - Se filtra por los estudiantes que pertenezcan al paralelo de dicho período (relación 1:1 en el ámbito de titulación).
   - Opcionalmente admite parámetro `section_id` para especificar el paralelo deseado.

2. **Criterio de "Pendiente de Revisión":**
   - El tema se considera pendiente cuando aún no registra fecha de revisión (`fecha_revision IS NULL`) o su estado es `'pendiente'`.

3. **Detalle del Tema:**
   - Exposición mediante `DegreeTopicResource`:
     - Identificador, título, descripción, fecha de propuesta, estado.
     - Datos del estudiante postulante (cédula, nombre, correo, teléfono).
     - Paralelo al que pertenece el estudiante.
     - Período académico correspondiente.

4. **Autorización:**
   - Exclusivo para usuarios autenticados con rol `coordinador_titulacion` o `administrador`.

## Contratos de API

- `GET /api/v1/coordination/degree-topics/pending` (acepta opcionalmente `?section_id=...`)
- `GET /api/v1/coordination/degree-topics/{topic}` (detalle individual)
