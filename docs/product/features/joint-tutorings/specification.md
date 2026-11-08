# Clases conjuntas

## Objetivo

Un docente puede dictar la misma asignatura a grupos de distintas carreras o
paralelos a la misma hora (por ejemplo, Cálculo III para TI y para Software). El
horario se permite y el docente toma asistencia y califica a ambos grupos juntos.

## Reglas (`App\Support\JointTutorings`)

- Dos tutorías son conjuntas si están activas, tienen el mismo docente y el mismo
  período, el mismo nombre de asignatura (sin mayúsculas ni tildes) y al menos un
  horario que se cruza el mismo día.
- `ManageSchedule::findTeacherScheduleConflict` ignora los cruces con tutorías de
  la misma asignatura. Con otra asignatura el cruce se sigue rechazando.
- Cada tutoría conserva sus estudiantes, sesiones y notas; la vista conjunta solo
  reúne los grupos y guarda en cada tutoría lo suyo.

## Docente

- Asistencia: al registrar la fecha de un día en que coinciden, se muestran los
  estudiantes de las tutorías conjuntas de ese día y al guardar se registra la
  sesión en cada una.
- Calificaciones: la tabla incluye a los estudiantes de las tutorías conjuntas y
  al guardar una etapa se guarda en cada tutoría.
- El docente puede ocultar el otro grupo y trabajar solo con el suyo.

## API

`GET /api/v1/teacher/tutorings/{tutoring}/joint-tutorings` devuelve `id`, `name`,
`career_name`, `cycle_number`, `section_name`, `days` (días en que coinciden) y
`can_manage`.
