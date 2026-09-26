# CT-07: Asignación y Gestión de Pares Académicos en Titulación

## Objetivo

Permitir que el **Coordinador de Titulación** consulte y gestione (asigne y reasigne) los docentes que actúan como pares académicos de un tema de titulación (`tema_titulacion`), asegurando que validen y revisen el desarrollo del trabajo del estudiante.

## Reglas de Negocio

1. **Elegibilidad:**
   - Todo par académico debe ser un usuario activo en el sistema con rol `docente`.
   - Un docente **no puede** ser asignado simultáneamente como tutor y como par académico dentro del mismo tema de titulación.
   - Los identificadores de pares deben ser distintos entre sí.

2. **Consulta Dedicada:**
   - `GET /api/v1/coordination/degree-topics/{topic}/peers` retorna exclusivamente los pares académicos asignados al tema con la información del docente, fecha de asignación y estado.

3. **Reasignación Posterior:**
   - `PUT /api/v1/coordination/degree-topics/{topic}/peers` permite al coordinador actualizar o sustituir los pares académicos asignados al tema tras su aprobación si surge algún imprevisto.

## Contratos de API

- `GET /api/v1/coordination/degree-topics/{topic}/peers`
- `PUT /api/v1/coordination/degree-topics/{topic}/peers`
