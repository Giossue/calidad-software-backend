# CT-05: Aprobación de Tema de Titulación con Asignación de Tutor y Pares Académicos

## Objetivo

Permitir que el **Coordinador de Titulación** apruebe formalmente una propuesta de tema de titulación (`tema_titulacion`), registrando al coordinador revisor, la fecha de revisión, el estado aprobado y realizando obligatoriamente la asignación de un docente tutor y uno o más docentes pares académicos (`asignacion_docente`).

## Requerimientos

1. **Precondición:**
   - La propuesta de tema debe existir y encontrarse en estado pendiente (`fecha_revision IS NULL` o `estado = 'pendiente'`).

2. **Parámetros Obligatorios:**
   - `tutor_id`: Identificador de un docente activo (`usuario` con rol `docente`).
   - `peer_ids`: Arreglo de al menos un identificador de docente activo (`peer_ids.*` con rol `docente`).
   - Regla de exclusión: Un docente no puede ser tutor y par académico en el mismo tema.

3. **Efectos:**
   - Actualización atómica en base de datos (`DB::transaction`):
     - `tema_titulacion`: `estado = 'aprobado'`, `fecha_revision = now()`, `fk_coord_revisor = auth()->id()`.
     - `asignacion_docente`:
       - Registro con rol `'tutor'`.
       - Registros con rol `'par_academico'` para cada par indicado.

4. **Contrato:**
   - `POST /api/v1/coordination/degree-topics/{topic}/approve`
   - Respuesta: `200 OK` con `DegreeTopicResource` enriquecido con las asignaciones docentes.
