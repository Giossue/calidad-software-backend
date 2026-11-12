# Malla curricular

## Problema

La malla es la carrera: tener una malla distinta es registrar otra carrera con
su propio código (ver la carrera en el catálogo académico). Subir la malla debe
crear las asignaturas de la carrera, sus ciclos y sus previas en un solo paso,
sin tener que crearlas después una por una.

## Usuarios

- Administrador: crea las carreras (con su código).
- Coordinador de carrera: sube la malla de su carrera y administra sus
  asignaturas desde «Malla curricular».

## Reglas

- Solo el coordinador de la carrera importa su malla (los mismos permisos que
  para crear asignaturas).
- Importar la malla crea en la carrera cada asignatura del archivo (activa, con
  la modalidad de la carrera), la asocia a los ciclos de ese número (uno por
  paralelo; si la carrera aún no tiene el ciclo, se crea) y registra sus previas.
  Todo ocurre en una sola transacción: si una fila tiene errores no se crea
  ninguna.
- Toda asignatura tiene modalidad. Si la carrera no tiene modalidad, la
  importación se rechaza (422): «La carrera no tiene modalidad; pide al
  administrador que la registre.»
- El código y el nombre de una asignatura son únicos dentro de la carrera. Una
  fila cuyo código o nombre ya existe en la carrera es un error; la malla se
  puede importar por partes (lo ya importado no se repite).
- Una previa es otra fila del archivo o una asignatura que la carrera ya tiene,
  indicada por código o por nombre, y debe ser de un ciclo anterior.
- No hay versiones de la malla: no hay PAO desde el que rige, «hasta qué ciclo»
  ni estudiantes fijados a una malla. Los estudiantes disponibles, las tutorías
  disponibles y el avance automático usan las asignaturas de la carrera.
- Las asignaturas se agregan, editan, desactivan o reactivan una por una desde
  la misma pantalla «Malla curricular».

## Archivo

- Excel (.xlsx) o CSV con las columnas `codigo`, `nombre`, `ciclo` y `previas`
  (códigos o nombres separados por `|`). El ciclo admite «3», «III» o «Tercero»;
  sin ciclo se toma del código (SW-B3-014 → 3.º).
- PDF: se leen los códigos de asignatura y el nombre que los acompaña en la
  misma línea. Las flechas de un diagrama no se pueden leer: las previas se
  completan en la vista previa.
- La vista previa muestra cada asignatura con sus errores (sin nombre, ciclo
  fuera de 1 a 12, código o nombre repetido en el archivo, código o nombre que
  ya existe en la carrera, previa inexistente o de un ciclo no anterior) y
  permite corregirlas antes de importar.

## API

- `POST /api/v1/tutoring-coordination/curricula/preview` (`file`, `career_id`)
  → `data.subjects[]` con `code`, `name`, `cycle`, `previous` y `errors`.
- `POST /api/v1/tutoring-coordination/curricula` (`career_id`, `subjects[]` con
  `code`, `name`, `cycle`, `previous`) → `201 { "created": n }`; `422` en
  `subjects` si alguna fila tiene errores (por ejemplo, un código o nombre que ya
  existe en la carrera).

## Migración

`2026_11_11_110000_remove_curriculum_versions` quita `curricula`,
`subjects.curriculum_id` y `usuario.curriculum_id`, y vuelve a exigir un código
único por carrera. Si alguna carrera tiene una malla además de la inicial, se
detiene con un error que nombra esas carreras: hay que registrar cada malla
nueva como otra carrera antes de migrar.
