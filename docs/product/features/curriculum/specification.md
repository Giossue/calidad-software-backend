# Malla curricular

## Problema

Una carrera puede trabajar con varias mallas a la vez (p. ej. «Malla 2016» y
«Malla 2025») sin registrarse de nuevo como otra carrera: docentes,
coordinadores y estudiantes siguen vinculados a la carrera. Subir una malla debe
crear en ella sus asignaturas, sus ciclos y sus previas en un solo paso, sin
tener que crearlas después una por una.

## Usuarios

- Administrador: crea las carreras (con su código). Cada carrera nueva nace con
  una malla vigente «Malla inicial».
- Coordinador de carrera: administra las mallas de su carrera, sube cada malla y
  administra sus asignaturas desde «Malla curricular», eligiendo con qué malla
  trabaja (la vigente por defecto).

## Mallas

- Una carrera tiene una o más mallas, con nombre único en la carrera (máx. 100
  caracteres), y **exactamente una vigente**. Lo garantiza el código y un índice
  único parcial en la base (`malla_one_current_per_career`).
- El coordinador de la carrera crea una malla (opcionalmente vigente), la
  renombra y la marca vigente; marcar una vigente desmarca la anterior. Una
  malla vigente no se desmarca: se marca otra en su lugar.
- Una malla se elimina solo si no es la vigente y no tiene asignaturas,
  estudiantes ni tutorías.
- Las mallas son independientes (sin equivalencias): lo aprobado en una no
  cuenta en otra. Previas, asignaturas pendientes y avance automático se
  resuelven dentro de una malla.

## Cada estudiante sigue una malla

- `usuario.malla_id` es la malla que sigue el estudiante, siempre de su carrera
  (`App\Support\Mallas`).
- Un estudiante registrado por una tutoría (estudiante nuevo desde el panel del
  docente o del coordinador, carga del listado) sigue la malla de esa tutoría;
  registrado en cualquier otro lugar (registro, importaciones, coordinador,
  administrador) sigue la malla vigente de su carrera. El coordinador puede
  indicar otra malla de la carrera al registrar un estudiante.
- Al cambiar de carrera (administrador, completar el perfil) el estudiante pasa
  a la malla vigente de la nueva carrera.
- El coordinador puede mover a un estudiante a otra malla de su carrera, salvo
  que tenga inscripciones activas en tutorías de otra malla en el período
  vigente.
- Un estudiante solo se inscribe en tutorías de su malla (la de la asignatura de
  la tutoría): las inscripciones explícitas se rechazan con «El estudiante sigue
  otra malla.» y los listados de disponibles lo excluyen.

## Reglas de la importación

- Solo el coordinador de la carrera importa una malla (los mismos permisos que
  para crear asignaturas).
- Importar crea en la malla elegida cada asignatura del archivo (activa, con la
  modalidad de la carrera), crea el ciclo de ese número si la carrera aún no lo
  tiene y registra sus previas. Todo ocurre en una sola transacción: si una fila
  tiene errores no se crea ninguna.
- Toda asignatura tiene modalidad. Si la carrera no tiene modalidad, la
  importación se rechaza (422): «La carrera no tiene modalidad; pide al
  administrador que la registre.»
- El código y el nombre de una asignatura son únicos dentro de la malla; dos
  mallas de la misma carrera pueden repetirlos. Una fila cuyo código o nombre ya
  existe en la malla es un error; la malla se puede importar por partes (lo ya
  importado no se repite).
- Una previa es otra fila del archivo o una asignatura que la malla ya tiene,
  indicada por código o por nombre, y debe ser de un ciclo anterior.
- Las asignaturas se agregan, editan, desactivan o reactivan una por una desde
  la misma pantalla «Malla curricular», dentro de la malla elegida. Las previas
  de una asignatura deben ser de su misma malla.
- Los ciclos (`ciclo`) siguen siendo de la carrera, compartidos por sus mallas.

## Archivo

- Excel (.xlsx) o CSV con las columnas `codigo`, `nombre`, `ciclo` y `previas`
  (códigos o nombres separados por `|`). El ciclo admite «3», «III» o «Tercero»;
  sin ciclo se toma del código (SW-B3-014 → 3.º).
- PDF: se leen los códigos de asignatura y el nombre que los acompaña en la
  misma línea. Las flechas de un diagrama no se pueden leer: las previas se
  completan en la vista previa.
- La vista previa muestra cada asignatura con sus errores (sin nombre, ciclo
  fuera de 1 a 12, código o nombre repetido en el archivo, «El código ya existe
  en la malla.», «La asignatura ya existe en la malla.», previa inexistente o de
  un ciclo no anterior) y permite corregirlas antes de importar.

## API

Todo bajo `/api/v1/tutoring-coordination`, con la autorización del resto de la
coordinación (coordinador de esa carrera).

- `GET careers` → cada carrera trae `mallas: [{ id, name, is_current }]` (la
  vigente primero y luego por nombre).
- `GET mallas?career_id=` → `data: [{ id, career_id, name, is_current,
  subjects_count, students_count, tutorings_count }]`.
- `POST mallas` (`career_id`, `name`, `is_current?`) → `201` malla; 422 «Ya
  existe una malla con ese nombre en la carrera.».
- `PATCH mallas/{malla}` (`name?`, `is_current?: true`) → malla; `is_current:
  false` se rechaza (422).
- `DELETE mallas/{malla}` → `204`; 422 en `malla`: «La malla tiene asignaturas,
  estudiantes o tutorías.» o «No se puede eliminar la malla vigente.».
- `POST curricula/preview` (`file`, `career_id`, `malla_id`) → `data.subjects[]`
  con `code`, `name`, `cycle`, `previous` y `errors`.
- `POST curricula` (`career_id`, `malla_id`, `subjects[]` con `code`, `name`,
  `cycle`, `previous`) → `201 { "created": n }`; `422` en `subjects` si alguna
  fila tiene errores. `malla_id` debe ser de `career_id` («La malla debe ser de la
  carrera elegida.»).
- `GET subjects?malla_id=`; `POST subjects` exige `malla_id` (la carrera es la de
  la malla); las asignaturas traen `malla_id` y `malla_name`.
- `GET tutorings?malla_id=` (malla de la asignatura); las tutorías traen
  `malla_id` y `malla_name`.
- `GET students?malla_id=` y `GET degree-students?malla_id=`; los estudiantes
  traen `malla_id` y `malla_name`. `POST students` acepta `malla_id` opcional
  (422 «La malla no pertenece a la carrera.» si es de otra carrera).
- `PATCH students/{student}/malla` (`malla_id`) → estudiante; 422 «La malla debe
  ser de la carrera del estudiante.» o «Retira primero sus tutorías activas de la
  otra malla.».
- `GET reports?malla_id=`.
- `POST roster-imports` acepta `malla_id` opcional (de la carrera, si no 422 «La
  malla no pertenece a la carrera.»); sin ella la asignatura del listado se busca
  primero en la malla vigente.

## Migración

`2026_11_15_110000_create_malla_table` crea `malla`, `subjects.malla_id`
(obligatorio, código único por malla) y `usuario.malla_id`. Cada carrera
existente recibe una malla vigente «Malla inicial» con todas sus asignaturas y
sus estudiantes; no se pierde ningún dato. `down()` restaura el esquema anterior
(código único por carrera) solo mientras cada carrera tenga una sola malla; si
no, se detiene y nombra esas carreras.

Antes, `2026_11_11_110000_remove_curriculum_versions` quitó las versiones de la
malla (`curricula`, PAO desde el que regía, «hasta qué ciclo»).
