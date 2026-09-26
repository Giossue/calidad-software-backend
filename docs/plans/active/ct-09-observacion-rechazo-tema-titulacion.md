# CT-09: Observación Libre al Rechazar Tema de Titulación (Extend)

## Objetivo

Permitir que el **Coordinador de Titulación**, al rechazar una propuesta de tema de titulación (`tema_titulacion`), registre de manera opcional una observación en texto libre (`observacion_titulacion`) que explique al estudiante los motivos específicos del rechazo y las pautas para replantear su propuesta.

## Reglas de Negocio

1. **Naturaleza Opcional (Extend):**
   - El rechazo de la propuesta (`POST /api/v1/coordination/degree-topics/{topic}/reject`) no bloquea si el coordinador no envía una observación; los campos `observation` / `reason` son opcionales (`nullable`).
   - Si no se proporciona observación, el tema pasa a estado `rechazado` registrando revisor y fecha sin generar registros en `observacion_titulacion`.
   - Si se proporciona un texto libre, se registra automáticamente en `observacion_titulacion`.

2. **Registro Adicional de Observaciones:**
   - Se provee además el endpoint `POST /api/v1/coordination/degree-topics/{topic}/observations` para permitir al coordinador agregar observaciones o retroalimentación adicional a un tema en cualquier momento posterior a su revisión.

3. **Validación del Campo de Texto:**
   - Longitud máxima de 1000 caracteres, permitiendo texto libre enriquecido para retroalimentación académica.

4. **Visibilidad Estudiantil:**
   - Todas las observaciones asociadas al tema son visibles en `GET /api/v1/student/degree-topics` mediante `DegreeTopicResource`.

## Contratos de API

- `POST /api/v1/coordination/degree-topics/{topic}/reject`
  - Body (opcional): `{ "observation": "Texto explicativo..." }` o `{ "reason": "..." }` o `{}`
- `POST /api/v1/coordination/degree-topics/{topic}/observations`
  - Body: `{ "observation": "Nueva observación explicativa..." }`
  - Response: `201 Created` con el recurso de la observación creada.
