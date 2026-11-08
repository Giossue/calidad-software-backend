# Versiones de la malla curricular

## Problema

Una carrera puede cambiar de malla. La nueva rige desde un PAO futuro y solo
para los estudiantes de los primeros ciclos; los que van más adelantados
terminan la carrera con la malla anterior.

## Usuarios

- Coordinador de carrera: sube la malla nueva desde «Malla curricular».

## Reglas

- `curricula`: versiones de la malla de cada carrera. Las asignaturas que ya
  existían quedan en la «Malla inicial» (sin PAO). Cada asignatura pertenece a
  una malla (`subjects.curriculum_id`); el código y el nombre son únicos dentro
  de su malla, no de la carrera. Las secuencias solo unen asignaturas de la
  misma malla.
- Una malla nueva indica **desde qué PAO** rige (el vigente o uno futuro) y
  **hasta qué ciclo** se aplica (`up_to_cycle`).
- Al crearla, los estudiantes de la carrera que hoy están **por encima** de ese
  ciclo quedan fijados a la malla anterior (`usuario.curriculum_id`) y la siguen
  hasta terminar. Los de ese ciclo o anteriores, y quienes ingresen después, no
  quedan fijados: siguen la malla que rige en cada PAO, es decir, la anterior
  hasta que llega el PAO de la nueva y la nueva desde entonces.
- Solo se ofrecen estudiantes de la malla de la tutoría (disponibles del
  coordinador y del docente) y tutorías de la malla del estudiante. La
  inscripción manual no se bloquea.
- Avance automático: inscribe solo en tutorías de la malla del estudiante. Una
  previa aprobada con otra malla vale si la asignatura se llama igual.
- Una malla subida por error se puede eliminar mientras ninguna tutoría use sus
  asignaturas. La malla inicial no se elimina.
- Una asignatura creada desde Asignaturas entra, si no se indica otra, en la
  malla que rige en el PAO activo.

## Archivo

- Excel (.xlsx) o CSV con las columnas `codigo`, `nombre`, `ciclo` y `previas`
  (códigos o nombres separados por `|`). El ciclo admite «3», «III» o «Tercero»;
  sin ciclo se toma del código (SW-B3-014 → 3.º).
- PDF: se leen los códigos de asignatura y el nombre que los acompaña en la
  misma línea; el nombre de un código que ya existe en la carrera se completa.
  Las flechas de un diagrama no se pueden leer: las previas se completan en la
  vista previa.
- La vista previa muestra cada asignatura con sus errores (sin nombre, ciclo
  fuera de 1 a 12, código o nombre repetido, previa inexistente o de un ciclo no
  anterior) y permite corregirlas antes de crear la malla.

## API

- `GET /api/v1/tutoring-coordination/curricula?career_id=` → mallas de la más
  reciente a la inicial (`in_force`, `from_period_name`, `up_to_cycle`,
  `previous_name`, `subjects_count`) y `periods` elegibles.
- `POST /api/v1/tutoring-coordination/curricula/preview` (`file`, `career_id`)
  → `subjects[]` con `code`, `name`, `cycle`, `previous` y `errors`.
- `POST /api/v1/tutoring-coordination/curricula` (`career_id`, `name`,
  `period_id`, `up_to_cycle`, `subjects[]`).
- `DELETE /api/v1/tutoring-coordination/curricula/{curriculum}`.
- Las asignaturas devuelven `curriculum_id` y se filtran con `curriculum_id`.
