# Carga del listado PDF de una asignatura

## Objetivo

El coordinador de carrera sube el «Registro de actividades académicas» (LISTADO)
que descarga del sistema académico y el sistema deja lista la tutoría: la crea
si no existe, le asigna el docente y registra e inscribe a los estudiantes.

## Flujo

1. En Tutorías, «Cargar listado PDF», con la carrera elegida.
2. Vista previa (`confirm=false`): asignatura, ciclo, paralelo, período, docente,
   si la tutoría ya existe y qué pasará con cada estudiante.
3. Confirmación (`confirm=true`): aplica la carga y devuelve el mismo informe con
   el resultado de cada fila.

## Lectura del PDF (`App\Imports\RosterPdf`)

- Usa `smalot/pdfparser` (PHP puro, sin dependencias del sistema).
- Cabecera: `Carrera`, `Docente`, `Asignatura` y `Paralelo`; los valores largos
  ocupan dos líneas y se unen.
- Filas: número, apellidos y nombres, correo y teléfono opcional. Se reconstruyen
  los correos cortados en la celda y los pegados al teléfono. Solo se guarda el
  teléfono si es un celular `09XXXXXXXX`. Correos repetidos se ignoran.
- Sin asignatura, paralelo o estudiantes con correo, el archivo se rechaza (422).

## Reglas (`App\Actions\Tutoring\ImportRoster`)

- La carga va a la carrera elegida por el coordinador (debe coordinarla). Si el
  PDF dice otra carrera (salvo «GRUPO COMUN»), se avisa y se carga igual.
- Asignatura: por nombre, sin mayúsculas ni tildes, entre las activas de la
  carrera; debe tener un ciclo. Paralelo: por nombre. Período: el activo.
- Tutoría: la de esa asignatura, paralelo y período. Si no existe se crea con
  `ManageTutoring::create` (modalidad de la asignatura o de la carrera); si
  está deshabilitada, la carga se detiene.
- Docente: por nombre, sin importar el orden de las palabras ni las tildes,
  primero entre los de la carrera. Se asigna solo si la tutoría no tiene
  docente; si no se encuentra, se avisa.
- Estudiantes (`status`):
  - `new`: se crea la cuenta (sin cédula, contraseña provisional por correo),
    en la carrera y en el ciclo de la tutoría, y se inscribe.
  - `enroll`: ya registrado; se inscribe (y se ubica en el ciclo si no tenía).
  - `enrolled`: ya inscrito; no cambia nada.
  - `blocked`: correo no institucional, cuenta no estudiantil o deshabilitada,
    de otra carrera, en titulación, o arrastra una previa de la asignatura.
  - `error`: la fila falló al aplicar; las demás siguen.
- Volver a subir el mismo listado no duplica tutorías ni inscripciones.

## API

`POST /api/v1/tutoring-coordination/roster-imports` (multipart): `file` (PDF,
máx. 5 MB), `career_id`, `confirm`. Responde `data` con `applied`, los datos del
PDF (`career_name`, `subject_name`, `section_name`, `teacher_name`), lo
encontrado (`subject`, `cycle_number`, `period_name`, `teacher`, `tutoring`),
`errors` (impiden aplicar), `notes` y `students`.
