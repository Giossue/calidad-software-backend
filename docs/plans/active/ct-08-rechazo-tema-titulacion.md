# CT-08: Rechazo de Tema de Titulación y Consulta de Observaciones por el Estudiante

## Objetivo

Permitir que el **Coordinador de Titulación** rechace formalmente una propuesta de tema de titulación (`tema_titulacion`) que no cumpla con los requisitos metodológicos o académicos, registrando el motivo/observaciones correspondientes para que el **Estudiante** pueda consultar el resultado y replantear su propuesta.

## Reglas de Negocio

1. **Precondición de Revisión:**
   - La propuesta de tema debe encontrarse en estado pendiente (`estado = 'pendiente'` y `fecha_revision IS NULL`).
   - Si la propuesta ya fue aprobada o rechazada previamente, la solicitud debe ser rechazada con código `422`.

2. **Registro Obligatorio de Observación:**
   - El coordinador debe ingresar obligatoriamente un motivo u observación descriptiva (`reason`, mínimo 5 caracteres).
   - Se registra en la tabla `observacion_titulacion` con el usuario coordinador (`fk_coord_tit`), el tema (`fk_tema_tit`), la fecha de registro y el detalle.

3. **Efectos de la Transacción:**
   - `tema_titulacion`:
     - `estado = 'rechazado'`
     - `fecha_revision = now()`
     - `fk_coord_revisor = coordinador->id`
   - `observacion_titulacion`:
     - Registro creado con la observación del coordinador.

4. **Visibilidad para el Estudiante:**
   - El estudiante autenticado puede consultar sus propuestas de titulación mediante `GET /api/v1/student/degree-topics`.
   - La respuesta expone el estado (`rechazado`), la fecha de revisión, el coordinador revisor y la lista de observaciones con su descripción y fecha.

## Contratos de API

- `POST /api/v1/coordination/degree-topics/{topic}/reject`
  - Body: `{ "reason": "La justificación metodológica carece de delimitación del alcance..." }`
  - Response: `200 OK` (`DegreeTopicResource` con estado `rechazado` y `observations`).
- `GET /api/v1/student/degree-topics`
  - Response: `200 OK` (Colección de `DegreeTopicResource` con historial de observaciones).
