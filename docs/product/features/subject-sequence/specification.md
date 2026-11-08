# Secuencia de asignaturas y avance de estudiantes

## Problema

La malla curricular define qué asignatura continúa a otra. El sistema no lo
sabía: al empezar un período había que inscribir a mano, en cada tutoría, a los
estudiantes que aprobaron la asignatura previa.

## Usuarios

- Coordinador de carrera: configura la secuencia en Asignaturas.
- Docente: registra al cerrar el ciclo si cada estudiante aprobó o reprobó.
- Estudiante: queda inscrito en la tutoría siguiente y recibe el aviso.

## Reglas

- `subject_sequences`: `next_subject_id` continúa a `subject_id`. Una asignatura
  puede tener varias previas y continuar en varias. Ambas son de la misma
  carrera; no se permiten ciclos (A → B → A).
- Para avanzar a una asignatura hay que **aprobar todas sus previas**. Una
  asignatura sin secuencia termina la cadena.
- El resultado final es `approved` o `failed` en `inscripcion_tutoria.final_result`
  (con `final_result_at`). Lo decide el docente; la API le sugiere
  (`suggested_result`) según el promedio de los dos parciales frente a
  `teaching.passing_grade`.
- El aprobado se inscribe en la tutoría de la asignatura siguiente de un período
  **posterior** y del **mismo paralelo** en que aprobó:
  - al crear o habilitar esa tutoría, con quienes ya aprobaron las previas;
  - al registrar el aprobado, si esa tutoría ya existe.
- No se inscribe a estudiantes inactivos, sin rol estudiantil o en titulación,
  ni se duplica una inscripción existente. La inscripción usa el aviso habitual
  al estudiante y al docente.
- Cambiar un aprobado a reprobado no retira la inscripción ya creada.

## Asignaturas pendientes (arrastres)

- `student_pending_subjects` guarda las asignaturas de ciclos anteriores que el
  estudiante arrastra. Se cargan con la columna `asignaturas_pendientes` de la
  carga masiva de estudiantes.
- Quien arrastra una asignatura **no se ofrece ni se puede inscribir** en las que
  la continúan, directa o indirectamente, en la malla. El resto de asignaturas de
  su ciclo sí. Aplica a los estudiantes disponibles del docente y del coordinador,
  a las tutorías disponibles de un estudiante y al avance automático.
- Cuando el docente registra que aprobó la asignatura, deja de arrastrarla
  (`App\Support\PendingSubjects`).
- La lista de estudiantes del coordinador devuelve `pending_subjects`.

## Datos

- Malla de Tecnologías de la Información (RCU-009-2022-044) cargada por la
  migración `2026_11_08_100200`, por código de asignatura y, sin código, por
  nombre.
- Malla de Software (8 ciclos, modalidad presencial) cargada por la migración
  `2026_11_09_100100`, por código de asignatura y, si no lo tiene, por nombre.

## API

- `PATCH /api/v1/tutoring-coordination/subjects/{subject}` acepta
  `next_subject_ids` (también en `POST`). Las asignaturas devuelven
  `next_subjects` y `previous_subjects`.
- `PUT /api/v1/teacher/tutorings/{tutoring}/students/{enrollment}/final-result`
  con `{ "result": "approved" | "failed" | null }`. Las inscripciones del
  docente devuelven `final_result` y `suggested_result`.
