# Feature Specification: Avisos de inasistencia

## Problem

Un estudiante que sabe que no podrá asistir a una clase necesita avisar a su
docente antes de que se tome lista, sin equivocarse de fecha.

## Users

- Estudiante inscrito (activo) en la tutoría: envía y consulta sus avisos.
- Docente de la tutoría: ve los avisos al tomar lista y decide presente o ausente.

## Business Rules

- Solo se avisa para una clase real: un día con horario activo de la tutoría.
- La fecha va desde hoy hasta el fin del período académico.
- Un aviso por fecha; el aviso no se edita ni se elimina.
- El motivo tiene entre 5 y 500 caracteres.
- El aviso es informativo: no justifica la falta; el docente decide al tomar lista.

## API / UI Surface

| Método | Ruta (`/api/v1/student`) | Uso |
| --- | --- | --- |
| GET | `/tutoring/{tutoring}/absence-notices` | Avisos enviados, del más reciente al más antiguo, con `status` `pendiente`, `presente` o `ausente`. |
| GET | `/tutoring/{tutoring}/absence-notices/available-dates` | Próximas clases en las que aún se puede avisar: `date`, `day_of_week`, `start_time`, `end_time`. Aplica las mismas reglas que el envío. |
| POST | `/tutoring/{tutoring}/absence-notices` | `date` y `reason`. |

## Interface Decisions

- La pestaña Asistencia muestra primero el récord y al final un recuadro
  «Avisos de inasistencia» con «Ver avisos (n)» y «Avisar inasistencia».
- En el formulario, la fecha se elige de un desplegable con las próximas clases
  (por ejemplo «Martes 6 oct 2026 · 10:00 – 12:00»), nunca de un calendario
  libre, para que el estudiante no pueda elegir un día sin clase.
- Si no hay clases disponibles (sin horario, fin del período o todas avisadas),
  el formulario lo explica y no permite enviar.
