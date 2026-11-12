# Coordinación de tutorías

El módulo implementa las historias COOR-01 a COOR-17 del Sprint 3 de
`temp/Planificación_CalidadSoftware_Def.docx`. El actor del documento es el
**Coordinador de Carrera**, cuyo rol del sistema es `coordinador_carrera`.
La implementación comprende la API Laravel de este repositorio y las pantallas
React del repositorio `calidad-software-frontend`.

## Alcance y criterios de aceptación

| Historia | Operación | Resultado esperado |
| --- | --- | --- |
| COOR-01 | Registrar asignatura | Carrera autorizada, código y nombre obligatorios; código único por carrera. |
| COOR-02 | Actualizar asignatura | Permite corregir código y nombre de una asignatura seleccionada. |
| COOR-03 | Deshabilitar asignatura | Baja lógica; conserva vínculos con ciclos y tutorías anteriores. |
| COOR-04 | Asignar asignatura a ciclo | La asignatura guarda su número de ciclo de la malla (`cycle_number`, de 1 al mayor ciclo activo de la carrera); no lleva paralelos. |
| COOR-05 | Asignar tutoría a ciclo | Fila de ciclo con paralelo de la carrera y del número de ciclo de la asignatura; actualiza el paralelo y registra el vínculo ciclo/período. |
| COOR-06 | Registrar docente | Valida cédula ecuatoriana o pasaporte extranjero, nombre, correo institucional y teléfono; asigna exclusivamente rol docente y envía contraseña provisional, que debe cambiarse al primer ingreso. |
| COOR-07 | Actualizar docente | Edita una cuenta docente dentro del alcance autorizado. Cuando el docente ya completó su perfil (`profile_completed`), su cédula y su correo no se pueden cambiar; el administrador sí puede corregirlos. |
| COOR-08 | Quitar docente de la carrera | El coordinador solo desvincula al docente de su carrera; desactivar la cuenta es tarea del administrador. |
| COOR-09 | Registrar tutoría | Asignatura con número de ciclo; el paralelo del período se elige con la fila de ciclo (`cycle_id`) de ese número. Período vigente y modalidad activa; puede quedar inicialmente sin docente. |
| COOR-10 | Actualizar tutoría | Edita período y modalidad; protege el período si existen inscripciones. Las tutorías de un período no vigente son de solo consulta. |
| COOR-11 | Deshabilitar tutoría | Baja lógica; conserva horarios, inscripciones, asistencia e informes. |
| COOR-12 | Asignar docente a tutoría | Tutoría activa y cuenta activa con rol docente. |
| COOR-13 | Registrar horario | Día, inicio, fin y aula obligatorios; duración positiva y sin superposición activa en la misma tutoría. |
| COOR-14 | Actualizar horario | El horario pertenece a la tutoría seleccionada; valida también los cambios parciales. |
| COOR-15 | Deshabilitar horario | Baja lógica; un horario deshabilitado no bloquea su reemplazo. |
| COOR-16 | Consultar asistencia | Muestra la asistencia de la tutoría autorizada por sesión: cada vez que se imparte es una sesión numerada (Sesión 1, 2…) con su fecha, temas, presentes y su lista; solo lectura. |
| COOR-17 | Revisar informes | Muestra autor, tipo, fecha, contenido disponible y resumen de la tutoría; solo lectura. |

## Acceso por rol

Las peticiones requieren cuenta activa, correo verificado y token Sanctum con
capacidad `access-api` —los tokens completos con `*` también son válidos—.
Un token parcial `two-factor:challenge` no permite acceder. El servidor aplica
estas reglas aunque se invoque directamente la API.

| Rol | Acceso |
| --- | --- |
| Administrador | Gestiona todas las carreras y asigna las carreras de cada coordinador. |
| Coordinador de carrera | Gestiona asignaturas, tutorías y sus detalles únicamente en las carreras asignadas. |
| Coordinador de titulación | No tiene acceso por ese rol; conserva sus funciones de titulación. |
| Docente o estudiante | No tienen acceso a la coordinación de tutorías por esos roles. |

La asignación de carreras no se deduce del nombre del usuario ni del rol.
Un coordinador sin carreras asignadas obtiene listados vacíos y no puede crear
registros ni consultar el selector de docentes disponibles.

La gestión de cuentas docentes tiene un alcance adicional. El coordinador puede
editar o deshabilitar una cuenta cuando tiene únicamente el rol `docente`, posee
al menos una carrera vinculada, todas esas carreras están dentro de su alcance
y todas sus tutorías pertenecen también a carreras que coordina. Las cuentas
compartidas fuera de ese alcance y las que tienen otros roles quedan a cargo de
administración. `can_manage` expresa ese permiso en cada fila del listado.

El selector de responsables permite encontrar docentes activos de la institución.
Solo expone identificador, nombre, correo y estado. Asignar uno a una tutoría
**no** añade vínculos de gestión de cuenta ni concede al coordinador permiso para
editarlo. Los docentes creados desde este módulo sí quedan vinculados a la
carrera elegida.

## Manual de uso

### Administrador

1. Verificar en los catálogos académicos que existan carrera, ciclo con paralelo,
   período y modalidad activos.
2. En **Usuarios**, registrar o seleccionar al usuario con rol
   **Coordinador de carrera**.
3. Abrir **Asignar carreras**, marcar las carreras que debe coordinar y pulsar
   **Guardar carreras**. El guardado reemplaza la selección completa. Se pueden
   conservar carreras ya asignadas que luego se deshabilitaron; las nuevas
   asignaciones requieren carreras activas.
4. Para revocar todas, desmarcar las carreras y guardar. El coordinador conservará
   su cuenta y rol, pero dejará de acceder a los registros de esas carreras.
5. Gestionar desde administración las cuentas docentes compartidas o con roles
   adicionales que el coordinador solo pueda consultar.

### Coordinador de carrera

1. Iniciar sesión, completar 2FA cuando esté habilitado y abrir el módulo de
   tutorías. Sus secciones son **Asignaturas**, **Docentes** y **Tutorías**.
2. En **Asignaturas**, registrar código, nombre y carrera. Después, seleccionar
   **Asignar ciclo** para vincular la asignatura con un ciclo de esa carrera.
3. En **Docentes**, registrar las cuentas que correspondan a su carrera o
   actualizar las cuentas autorizadas. El correo debe terminar en `@ueb.edu.ec` o
   `@mailes.ueb.edu.ec`;
   la cédula y el correo no pueden duplicarse. El registro envía una contraseña
   provisional al correo institucional mediante la configuración de correo del
   backend.
4. En **Tutorías**, registrar una oferta seleccionando asignatura, paralelo
   (fila de ciclo del número de la asignatura), período y modalidad. El listado
   muestra el período vigente; los anteriores se consultan con el filtro de
   período y no se editan. Posteriormente puede
   **Asignar docente** o cambiar el responsable por otro docente habilitado.
5. Abrir el detalle de la tutoría para registrar horarios con día, inicio, fin y
   aula. Los horarios se pueden editar y deshabilitar; la duración se obtiene de
   las horas de inicio y fin.
6. Consultar en ese detalle la asistencia registrada y los informes disponibles.
   Estas vistas no registran asistencia ni generan informes nuevos.
7. Usar **Desactivar** para retirar asignaturas, tutorías u horarios; un docente se quita de la carrera con **Quitar de la carrera**.
   La operación conserva sus registros históricos. Una tutoría deshabilitada
   permite seguir consultando asistencia e informes.

Si no aparecen carreras o ciclos, administración debe revisar las asignaciones
y el estado de los catálogos. Un docente marcado como consulta requiere que
administración haga los cambios de su cuenta.

## Reglas de integridad e historial

- La carrera de una asignatura no cambia mediante su edición. Su código es
  único dentro de la carrera, incluidos los registros deshabilitados.
- Una asignatura pertenece a la malla: código, nombre, número de ciclo
  (`subjects.cycle_number`) y previas. No lleva paralelos; los paralelos que
  tiene en un período son los de sus tutorías de ese período. Nada se copia al
  cambiar de período: malla, carreras, docentes y paralelos simplemente persisten.
- Una tutoría nueva requiere asignatura activa con número de ciclo y una fila de
  ciclo activa de su carrera, con paralelo activo y del mismo número
  («El paralelo debe ser del ciclo N, el de la asignatura.»), período vigente y
  modalidad activa. La combinación asignatura/ciclo/período es única incluso
  después de deshabilitar una oferta.
- Cambiar el ciclo de una tutoría exige una fila del mismo número de ciclo de su
  asignatura y de su carrera. El paralelo se toma del ciclo de destino.
- Solo hay un período vigente, garantizado por un índice único parcial sobre
  `periodo_academico.estado`. Las tutorías de otro período son historial de solo
  consulta: editar, configurar, cambiar ciclo o docente, deshabilitar, habilitar,
  inscribir, reinscribir o retirar estudiantes responde `422` en `period_id`
  («La tutoría pertenece a un período académico que no está vigente; solo puede
  consultarse.»). El avance automático sí inscribe en tutorías de períodos posteriores.
- Un estudiante no puede estar inscrito activamente en dos tutorías de la misma
  asignatura en un período (p. ej. paralelos A y B): «El estudiante ya está
  inscrito en «Asignatura» en otro paralelo de este período.» El avance automático
  y la carga de listados omiten o marcan esa fila.
- Una tutoría con inscripciones conserva período, ciclo y paralelo. La regla
  incluye las inscripciones deshabilitadas para preservar la interpretación de
  sus datos históricos.
- Al editar una tutoría se permite conservar su período o modalidad actual
  aunque el catálogo los haya deshabilitado. Cambiar a otro período o modalidad
  requiere una opción activa; la protección por inscripciones sigue vigente.
- Una tutoría deshabilitada no admite nuevas asignaciones de ciclo o docente,
  ni creación o edición de horarios.
- Los horarios activos de la misma tutoría y día no pueden superponerse. Se
  permiten intervalos consecutivos, como 09:00–10:00 y 10:00–11:00. Esta versión
  no verifica cruces de aula o docente entre tutorías distintas.
- Los horarios se identifican dentro de su tutoría. Usar un horario de otra
  tutoría en la URL se rechaza, aunque el coordinador tenga acceso a ambas.
- Deshabilitar un docente revoca sus tokens y lo retira del selector de nuevas
  asignaciones, sin borrar sus responsabilidades anteriores.
- La consulta de informes incluye `meta.enrollment_count`,
  `meta.present_count` y `meta.absent_count` para la tutoría completa, con
  independencia de la paginación. Las inscripciones incluyen registros activos
  e históricos; las asistencias y ausencias cuentan registros de asistencia.

## Contratos de la API

Las siguientes rutas usan el prefijo `/api/v1/tutoring-coordination` y responden
JSON con `data`. La creación devuelve `201`, la edición y consulta `200`, la
validación de datos `422`, la ausencia de autenticación `401` y la falta de
permiso `403`.

| Método | Ruta | Campos de entrada o finalidad |
| --- | --- | --- |
| GET | `/careers` | Carreras activas del alcance autorizado. |
| GET | `/cycles` | Ciclos activos de carreras activas del alcance autorizado. |
| GET | `/periods` | Todos los períodos, del más reciente al más antiguo, con `is_active`. |
| GET | `/modalities` | Modalidades activas. |
| GET | `/subjects` | Listado; filtros `search`, `career_id`, `cycle_number`, `page`, `per_page`. Devuelve `cycle_number`; `cycle_ids` y `parallel_ids` son vínculos heredados, solo informativos. |
| POST | `/subjects` | `career_id`, `code`, `name`, `cycle_number`, `modality_id`, `next_subject_ids`. `cycle_id` se acepta por compatibilidad y aporta su número; `parallel_id`, `parallel_ids`, `new_parallel_name` y `period_id` se rechazan. |
| PATCH | `/subjects/{subject}` | `code`, `name`, `cycle_number`, `modality_id`, `next_subject_ids`. No cambia el número si la asignatura tiene tutorías activas del período vigente en otro número. |
| PATCH | `/subjects/{subject}/deactivate` | Baja lógica. |
| PUT | `/subjects/{subject}/cycles/{cycle}` | Vincular al ciclo indicado; sin cuerpo obligatorio. |
| GET | `/teachers` | Cuentas vinculadas al alcance autorizado; filtros `search`, `career_id`, `page`, `per_page`; incluye `can_manage`. |
| GET | `/available-teachers` | Docentes activos para seleccionar; filtro `search`, máximo 100 resultados. |
| POST | `/teachers` | `career_id`, `identification`, `name`, `email`, `phone`. |
| PATCH | `/teachers/{teacher}` | `identification`, `name`, `email`, `phone`; `identification` y `email` se rechazan si cambian y el docente ya completó su perfil. |
| GET | `/tutorings` | Listado de un período: `period_id` o, por defecto, el vigente (vacío si no hay). `meta.period` = `{ id, name, is_active }` o `null`. Filtros `search`, `career_id`, `cycle_number`, `status`, `page`, `per_page`. |
| POST | `/tutorings` | `subject_id`, `cycle_id` (fila con paralelo del número de ciclo de la asignatura), `period_id` (por defecto el vigente), `modality_id`. |
| PATCH | `/tutorings/{tutoring}` | `period_id`, `modality_id`. |
| PATCH | `/tutorings/{tutoring}/deactivate` | Baja lógica. |
| PATCH | `/tutorings/{tutoring}/activate` | Rehabilita una tutoría; exige período, ciclo y carrera activos y conserva docente e inscripciones. |
| DELETE | `/tutorings/{tutoring}` | Elimina una tutoría creada por error, con sus horarios, solo si nunca tuvo estudiantes ni registros y es del período vigente; si no, 422 y debe desactivarse. |
| PUT | `/tutorings/{tutoring}/cycle` | `cycle_id`. |
| PUT | `/tutorings/{tutoring}/teacher` | `teacher_id`. |
| GET | `/tutorings/{tutoring}/schedules` | Horarios activos e históricos. |
| POST | `/tutorings/{tutoring}/schedules` | `day`, `start_time`, `end_time`, `room`. |
| PATCH | `/tutorings/{tutoring}/schedules/{schedule}` | Cambios parciales de día, horas y aula. |
| PATCH | `/tutorings/{tutoring}/schedules/{schedule}/deactivate` | Baja lógica. |
| GET | `/tutorings/{tutoring}/attendance` | Asistencia; `page`, `per_page`. |
| GET | `/tutorings/{tutoring}/sessions` | Sesiones de la más reciente a la primera, con `number`, `date`, `topics`, `present_count`, `absent_count` y `attendance`; `page`, `per_page`. |
| GET | `/tutorings/{tutoring}/reports` | Informes con formato oficial (`summary.format`: `diagnostic_evaluation`, `diagnostic_grades`, `tutoring_follow_up` o `final_pao`) y resumen; `page`, `per_page`. Los informes libres no se listan. |

`day` usa `lunes`, `martes`, `miercoles`, `jueves`, `viernes`, `sabado` o
`domingo`; la interfaz muestra las tildes correspondientes. Las horas usan
`HH:mm`; `room` admite hasta 100 caracteres y no puede estar vacío.
Los listados paginados limitan `per_page` entre 1 y 100. Sus valores por defecto
son 15 para asignaturas, docentes y tutorías, y 20 para asistencia e informes.

La ruta administrativa tiene otro prefijo:

```http
PUT /api/v1/admin/users/{user}/careers
Content-Type: application/json
Authorization: Bearer <token completo de administrador>

{"career_ids": [1, 2]}
```

El usuario seleccionado debe tener rol `coordinador_carrera`. Los identificadores
no pueden repetirse: cada nueva carrera debe estar activa, pero se permite
conservar una carrera inactiva que ya estuviera asignada. `{"career_ids": []}`
revoca todas las asignaciones. La respuesta incluye
`data.coordinated_career_ids`; el listado administrativo de usuarios también
incluye las carreras asignadas.

## Esquema y compatibilidad con registros anteriores

Los nombres físicos nuevos están en inglés: `subjects`, `subject_cycle`,
`career_coordinator` y `career_teacher`. El modelo `Subject` expone `career()`,
`cycles()` y `tutorings()`; `Usuario` expone `coordinatedCareers()` y
`teachingCareers()`. Las tablas preexistentes mantienen sus nombres.

La migración `2026_09_27_000000_create_tutoring_coordination_schema.php` añade
`asignatura_tutoria.subject_id`, `horario.room` y `reporte.content` como nullable,
y permite `asignatura_tutoria.fk_docente = null` para crear ofertas antes de
designar al responsable. No crea asignaturas a partir de nombres anteriores ni
asigna automáticamente carreras a usuarios existentes.

Una tutoría histórica con `subject_id = null` conserva su nombre y puede
consultarse y recibir docente u horarios. Puede cambiar a otro ciclo activo de
su carrera si no tiene inscripciones. Un horario anterior puede tener
`room = null`; su próxima edición debe completar el aula. Un informe con
`content = null` sigue mostrando sus datos de registro: no existe un texto de
informe recuperable en ese campo y la aplicación no lo inventa.

En PostgreSQL, la migración adapta la función usada por los triggers de rol a
`role_user`/`roles`, elimina la restricción antigua `horario_unique` y crea
`schedule_active_slot_unique` sobre horarios activos. Añade verificaciones de
texto no vacío, días válidos y horas ordenadas. Las claves foráneas y los índices
únicos protegen los vínculos y duplicados. Las operaciones de agenda bloquean la
tutoría durante la transacción para validar superposiciones concurrentes.

El método `down()` rechaza el rollback automático, ya que las tablas afectadas
pueden contener información previa al módulo.

## Estado y verificación — 2026-09-27

- Backend implementado; **`composer test` completo correcto**: Pint, PHPStan sin
  errores y suite global con **143 pruebas y 961 aserciones**.
  `composer install` desde el lock verificado sin cambios versionables.
- Pruebas propias del módulo: **42 pruebas y 450 aserciones correctas**;
  `vendor/bin/pint tests/Feature/Api/V1/Tutoring` correcto.
- Migración verificada con **18 comprobaciones correctas sobre PostgreSQL 18.6
  local aislado**, usando un baseline sintético compatible con el esquema
  anterior.
- Frontend integrado: **26 pruebas correctas**, lint con **cero errores y 11
  advertencias previas**, build correcto con advertencia de chunk de 609 kB.
  Verificación repetida después del último ajuste de lectura móvil;
  `git diff --check` limpio.
- Prueba integral con **React, Laravel y SQLite aislados** correcta: asignación
  administrativa de carreras; creación de asignatura, vínculo de ciclo, docente,
  tutoría y responsable; horarios consecutivos y rechazo de superposición;
  consulta de asistencia e informes con contenido y registros históricos.
  Consulta final de asistencia e informes y lectura de informes en diálogo
  correctas; capturas inspeccionadas en móvil de 390 × 844 y escritorio de
  1440 px, sin errores JavaScript ni desbordamiento horizontal.
- Migraciones **aplicadas en producción el 2026-09-27**: cinco migraciones en el
  lote 8, con 20 aplicadas y ninguna pendiente al terminar. Las 22 comprobaciones
  posteriores de esquema y conservación de datos fueron correctas.
- La activación del módulo requiere **desplegar versiones compatibles del
  backend y frontend**. El estado de ese despliegue no está verificado; el envío
  de commits a los repositorios no acredita la activación en producción.

Pruebas focalizadas reproducibles:

```bash
php artisan test tests/Feature/Api/V1/Tutoring
```

El gate completo del backend es `composer test` —Pint, PHPStan y pruebas—. En el
frontend corresponde ejecutar `npm run lint`, `npm run test` y `npm run build`.

## Migración aplicada y activación del código

El 2026-09-27 se verificó la conexión a la base de producción
`calidad_software` y se aplicaron estas migraciones en el **lote 8**:

| Migración | Resultado |
| --- | --- |
| `2026_09_25_000000_create_academic_period_and_section_pivot_if_missing` | Aplicada. |
| `2026_09_25_010000_create_tema_titulacion_and_usuario_paralelo_if_missing` | Aplicada. |
| `2026_09_25_020000_create_asignacion_docente_if_missing` | Aplicada. |
| `2026_09_25_030000_create_observacion_titulacion_if_missing` | Aplicada. |
| `2026_09_27_000000_create_tutoring_coordination_schema` | Aplicada. |

El acceso SSH fue rechazado. Para esta operación, el usuario autorizó
expresamente una excepción al procedimiento habitual de Dokploy: ejecutar
Artisan desde el entorno local con las credenciales locales de `.pgpass`, sin
mostrarlas ni copiarlas. Esta excepción documenta lo autorizado para esta
ejecución y no sustituye el procedimiento habitual para otros despliegues.

Antes de migrar se verificó un backup externo al repositorio, su manifest SHA y
su lectura completa mediante `pg_restore` hacia `/dev/null`. Los archivos de
evidencia permanecen en `/home/giossue/.local/state/calidad-software/backups/`:

- Backup: `calidad_software-before-tutoring-20260927T212946Z.dump`.
- Registro sanitizado de ejecución: `migration-execution-20260927T213639Z.log`.

El resultado final de `migrate:status` fue **20 migraciones aplicadas y ninguna
pendiente**. Las **22 comprobaciones posteriores** verificaron tablas y columnas,
docente nullable, índices y constraints, adaptación de validación de roles al
pivot y conservación de datos en las tablas existentes. No se eliminaron filas.
Una confirmación independiente mediante `psql` en una sesión de solo lectura
verificó la base `calidad_software`, las 20 migraciones aplicadas, las cinco del
lote 8 y la ausencia de pendientes respecto del repositorio local.

La actualización del esquema está confirmada. **La activación de la API y del
frontend requiere un despliegue compatible**, cuyo estado no está verificado en
este registro, y la configuración operativa de carreras por coordinador.

### Publicación de la aplicación

Seguir el procedimiento de
[`docs/deployment/dokploy.md`](../../../deployment/dokploy.md).

1. Preparar las versiones correspondientes del backend y frontend con sus
   verificaciones completas. El backend usa el Dockerfile del repositorio,
   stage `production`, puerto `8080` y healthcheck `/up`.
2. Verificar la conexión efectiva del contenedor de Dokploy a la base objetivo
   `calidad_software`. La configuración local, `127.0.0.1` y `.env.example` no
   acreditan una conexión de producción.
   Mantener las credenciales en su configuración autorizada sin mostrarlas ni
   copiarlas a documentos o repositorios.
3. Desde **Advanced → Run Command** del contenedor de Dokploy, comprobar el estado:

   ```bash
   php artisan migrate:status
   ```

   Las cinco migraciones de esta entrega ya están aplicadas. Si una publicación
   posterior incluye otras pendientes, verificar su backup y usar
   `php artisan migrate --force` conforme al procedimiento de Dokploy.

4. Mantener la clave `APP_KEY` existente. Revisar `FRONTEND_URL`, el origen exacto
   en `CORS_ALLOWED_ORIGINS`, la expiración de Sanctum y la configuración SMTP
   necesaria para el alta de docentes. El entrypoint no ejecuta migraciones.
5. Publicar el frontend compatible después de que la API y el esquema estén
   listos. Comprobar `/up`, login, verificación y 2FA desde el dominio real del
   frontend.
6. Como administrador, asignar las carreras reales a cada coordinador. Con una
   cuenta autorizada, comprobar listados y acceso a tutorías históricas; validar
   que una cuenta de otra carrera no pueda consultarlas. Confirmar la lectura
   de asistencia e informes existentes sin alterarlos.

No usar `migrate:fresh`, `db:wipe`, seeders de demostración ni rollbacks
automáticos en producción. El registro anterior acredita la actualización del
esquema; la publicación de la aplicación debe comprobarse por separado del
estado de los commits y del push.

## Estudiantes por ciclo

- Cada estudiante registra el ciclo que cursa en `usuario.fk_ciclo`. El paralelo no
  lo identifica: un paralelo («A») es compartido por los ciclos de todas las carreras.
- La pantalla **Estudiantes** muestra una pestaña por ciclo de la carrera, más
  **Sin ciclo**. Las pestañas desde 6.º ciclo son de titulación: gestionan la
  matrícula en titulación en lugar de tutorías.
- **Nuevo estudiante** registra al estudiante en el ciclo de la pestaña (si el ciclo
  tiene varios paralelos, se elige uno) y lo ubica en el paralelo de ese ciclo. La
  tutoría inicial opcional debe ser del mismo ciclo; los ciclos de titulación no la admiten.
- **Asignar ciclo** (`PATCH /students/{student}/cycle`) solo da el primer ciclo a un
  estudiante que aún no tiene uno. Con ciclo registrado responde `422` en `cycle_id`:
  «El ciclo del estudiante se actualiza solo con sus tutorías del período.» El
  registro, las importaciones y completar el perfil siguen fijando el ciclo inicial.
- Un estudiante sin ciclo toma el de su primera tutoría al inscribirse (coordinación
  o docente). Los importados por carga masiva quedan en «Sin ciclo».
- **Ciclo por período** (`estudiante_periodo`): cada alta, rehabilitación o baja de
  una inscripción (coordinación, docente, avance automático o carga de listados)
  recalcula `StudentCycle::syncPeriod`. El ciclo del período es el número más alto
  entre las inscripciones activas del estudiante en tutorías de ese período, guardado
  como el ciclo base (sin paralelo) de ese número o, si la carrera no lo tiene, como
  el ciclo de la tutoría. Sin inscripciones activas en el período, el registro se
  elimina. Si el período es el vigente, `usuario.fk_ciclo` lo sigue (solo cambia si
  difiere el número o la carrera), y al habilitarse un período los estudiantes toman
  el ciclo registrado en él.
- `GET /students` y `GET /degree-students` aceptan `cycle_number`; `GET /students`
  acepta `without_cycle=1`. Ambos devuelven `cycle_id`, `cycle_number`, `cycle_name`,
  `section_name`, `academic_stage` (`tutorias` o `titulacion`) y `cycle_history`:
  `[{ period_id, period_name, cycle_number, cycle_name }]`, del período más reciente
  al más antiguo.
- La migración `2026_11_12_110000_create_estudiante_periodo_table` crea la tabla y
  la rellena con las inscripciones activas existentes; no modifica `usuario.fk_ciclo`.
  `2026_11_12_120000_add_cycle_number_to_subjects_table` rellena `cycle_number` con
  el menor número de los ciclos vinculados de cada asignatura y conserva
  `subject_cycle`. `2026_11_12_100000_enforce_single_active_academic_period` deja un
  solo período activo (el que está en curso o, si ninguno, el de inicio más reciente)
  antes de crear el índice único parcial.
- La migración `2026_10_27_100000_add_cycle_to_usuario_table` crea la columna y la
  rellena con el ciclo de la inscripción activa más reciente o, si no hay, con el único
  ciclo de la carrera que use alguno de los paralelos del estudiante.

## Etapas académicas: tutorías y titulación

`teaching.degree_from_cycle` (6) separa las etapas; el servidor las valida:

- **Tutorías (1.º a 5.º ciclo):** solo se crean tutorías en esos ciclos y solo se
  inscriben estudiantes sin ciclo o de esos ciclos (coordinación y docente). Los
  listados de estudiantes disponibles para una tutoría excluyen a los de titulación.
- **Titulación (desde 6.º ciclo):** solo se matriculan estudiantes desde 6.º ciclo.
  El estudiante accede al módulo de titulación (estado `is_enrolled`, presentar o
  cambiar su tema) si está desde 6.º ciclo **y** matriculado en el período vigente;
  `degree-enrollment-status` informa además `is_degree_stage`.
- **Coordinador de titulación:** `GET /coordination/degree-students` (y el de
  coordinación de carrera) lista solo estudiantes desde 6.º ciclo.
