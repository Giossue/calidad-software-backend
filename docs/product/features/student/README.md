# Estudiante: Mis tutorías

Pantalla `/panel/student-tutorings`. Al abrir una tutoría (`?tutoring={id}`) el
estudiante ve su espacio con las pestañas Contenido, Asistencia, Calificaciones
y Horarios.

## Encabezado

- Asignatura, ciclo, paralelo, docente y fecha de inscripción.
- Estado "En curso" / "Finalizada".
- Resultado final cuando el docente lo registra: insignia "Aprobado" o
  "Reprobado". Si el docente lo quita, la insignia desaparece.

## Contenido

- Avance de temas y lista de temas con actividades, metodologías y recursos.
- Filtro por clase: "Todas las clases" o una sesión ("Sesión N · fecha"; la
  primera clase es la Sesión 1).
- En "Todas las clases", cada tema indica la sesión o sesiones y la fecha en que
  se vio ("Sesión N · fecha"). Los temas aún no vistos no muestran sesión.

## Asistencia

- Récord por sesión con su fecha y el porcentaje de asistencia.

## Horarios

- Días, horas y aula de la tutoría, modalidad y duración por sesión.
- Tarjeta del docente con su nombre y correo. El celular del docente no se
  muestra ni se devuelve en esta respuesta.

## API

`GET /api/v1/student/tutoring` devuelve por inscripción `final_result`
(`approved`, `failed` o `null`) y en `subject.teacher` solo `id`, `name` y
`email`.

`GET /api/v1/student/tutoring/{tutoring}/topics` devuelve `sessions[]` con
`date` y los `topics` de cada sesión; la pantalla relaciona cada tema con sus
sesiones a partir de ellos.
