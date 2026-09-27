# Módulo de coordinación de tutorías (COOR-01 a COOR-17)

## Fuente y alcance

El documento `temp/Planificación_CalidadSoftware_Def.docx` describe al actor como
**Coordinador de Carrera** y agrupa 17 historias en el Sprint 3. Esta
implementación cubre asignaturas, su vínculo con ciclos, docentes, tutorías,
horarios y consultas de asistencia e informes.

La especificación, los contratos de la API, el manual por rol y los pasos de
despliegue están en
[`docs/product/features/tutoring-coordination/README.md`](../../product/features/tutoring-coordination/README.md).

## Modelo y compatibilidad

- El baseline de producción ya contiene `asignatura_tutoria`, `horario`,
  `inscripcion_tutoria`, `asistencia`, `reporte` y `ciclo_periodo`. Las migraciones
  nuevas conservan esos registros y completan los esquemas locales de prueba.
- Los nombres nuevos están en inglés: modelo `Subject`, tabla `subjects`,
  vínculo `subject_cycle` y referencia nullable
  `asignatura_tutoria.subject_id`. Las tablas anteriores conservan sus nombres.
- `career_coordinator` define el alcance por carrera del coordinador y
  `career_teacher` vincula las cuentas de docentes gestionables a sus carreras.
  Las relaciones de usuario son `coordinatedCareers()` y `teachingCareers()`.
- Las tutorías pueden crearse sin docente; `fk_docente` admite `null`.
  `horario.room` y `reporte.content` son opcionales para preservar registros
  anteriores. No se infieren asignaturas ni contenido de informes históricos.
- En PostgreSQL se actualiza `fn_validar_rol_usuario()` para que los triggers
  existentes consulten `role_user` y `roles`, tras la eliminación previa de
  `usuario.rol`. Un índice único parcial de horarios activos permite conservar
  un horario deshabilitado y registrar su reemplazo.
- Se usan transacciones y bloqueo de la tutoría para validar cambios de horarios
  y asignaciones. Constraints, índices únicos y claves foráneas complementan
  las validaciones de la aplicación.

## API y reglas

- Todas las rutas del módulo usan `/api/v1/tutoring-coordination`, Sanctum,
  verificación de correo, capacidad `access-api` y autorización de rol y estado.
  Los tokens parciales de 2FA no permiten operar el módulo. Los administradores
  pueden gestionar todas las carreras; cada coordinador accede a las asignadas.
- El administrador asigna y revoca carreras mediante
  `PUT /api/v1/admin/users/{user}/careers`; `career_ids: []` revoca todas.
  Puede conservar carreras inactivas previamente asignadas, pero toda carrera
  nueva de la selección debe estar activa.
- Asignaturas y docentes aceptan creación, edición y baja lógica. Las tutorías
  aceptan creación, edición, baja lógica, cambio de ciclo y asignación de docente.
- El ciclo, asignatura, carrera, paralelo, período y modalidad deben estar
  activos y ser compatibles. Una nueva tutoría exige asignatura vinculada al
  ciclo y es única por asignatura, ciclo y período, incluso tras una baja.
- Las inscripciones, aunque estén deshabilitadas, impiden cambiar período,
  ciclo o paralelo de la tutoría. Las tutorías históricas sin `subject_id`
  permiten cambio de ciclo dentro de su carrera si no tienen inscripciones.
  Al editar la configuración se puede conservar el período o modalidad actual
  aunque se haya deshabilitado; elegir otro exige que esté activo.
- El docente asignado debe estar activo y tener rol `docente`. Asignarlo a una
  tutoría no concede permiso para editar su cuenta. El coordinador solo modifica
  docentes con ese único rol, con carreras vinculadas enteramente dentro de su
  alcance y sin tutorías fuera de sus carreras; la API informa `can_manage`.
  Deshabilitar al docente revoca sus tokens y conserva sus asignaciones.
- Los horarios requieren día, hora inicial, hora final y aula; la hora final
  debe ser posterior y no puede existir superposición activa dentro de la misma
  tutoría y día. Los extremos consecutivos son válidos. Los códigos de día son
  `lunes`, `martes`, `miercoles`, `jueves`, `viernes`, `sabado` y `domingo`.
- Asistencia e informes son consultas de solo lectura por tutoría. Los informes
  incluyen contenido cuando existe y conteos de inscripciones, asistencias y
  ausencias, independientes de la página de resultados.
- React incorpora navegación para Asignaturas, Docentes y Tutorías; el detalle
  de tutoría reúne horarios, asistencia e informes. Usuarios incorpora el diálogo
  de asignación de carreras para administradores.

## Estado y verificación — 2026-09-27

- Backend implementado; `composer test` completo correcto: Pint, PHPStan sin
  errores y suite global con 143 pruebas y 961 aserciones.
  `composer install` desde el lock verificado sin cambios versionables.
  El módulo aporta 42 pruebas de aceptación con 450 aserciones en
  `tests/Feature/Api/V1/Tutoring/`; Pint de esos archivos pasó.
- Compatibilidad de la migración contrastada con un baseline sintético de
  PostgreSQL 18.6 local aislado: 18 comprobaciones correctas. Esta prueba no
  constituye un despliegue ni una migración de producción.
- Frontend: 26 pruebas correctas, lint con cero errores y 11 advertencias
  previas; build correcto con advertencia de chunk de 609 kB. Verificación
  repetida después del ajuste final de lectura móvil; `git diff --check` limpio.
- Prueba integral con React, Laravel y SQLite aislados correcta: administrador
  asigna carreras; coordinador registra asignatura, vínculo con ciclo, docente,
  tutoría, responsable y horarios consecutivos; se rechaza superposición y se
  consultan asistencia e informes con contenido e históricos. Lectura de
  informes en diálogo revisada en móvil de 390 × 844 y escritorio de 1440 px,
  sin errores JavaScript ni desbordamiento horizontal.
- Migraciones **aplicadas en producción el 2026-09-27** sobre la base objetivo
  verificada `calidad_software`: cinco migraciones en el lote 8 —las cuatro del
  25 de septiembre y la del módulo del 27—. `migrate:status` registra 20
  migraciones aplicadas y ninguna pendiente. Las 22 comprobaciones posteriores
  de esquema y conservación de datos fueron correctas. Una sesión independiente
  de `psql` de solo lectura confirmó la base objetivo, el lote y la ausencia de
  migraciones pendientes.
- Tras rechazarse la conexión SSH, el usuario autorizó expresamente la excepción
  al procedimiento de [`docs/deployment/dokploy.md`](../../deployment/dokploy.md):
  ejecutar Artisan local con las credenciales locales de `.pgpass`, sin
  mostrarlas ni copiarlas. Se verificó previamente un backup externo al
  repositorio, con manifest SHA y lectura completa mediante `pg_restore`.
- Evidencia de la operación conservada fuera del repositorio en
  `/home/giossue/.local/state/calidad-software/backups/`: backup
  `calidad_software-before-tutoring-20260927T212946Z.dump` y registro sanitizado
  `migration-execution-20260927T213639Z.log`.
- La activación del módulo requiere **desplegar versiones compatibles del
  backend y frontend**. La verificación HTTP de producción posterior a la
  corrección de permisos se registra en la siguiente sección; no identifica
  por sí sola el commit desplegado ni sustituye una prueba visual del frontend.

## Incidencia de permisos en producción — 2026-09-27

El inicio de sesión del coordinador responde correctamente, pero las consultas
HTTP de carreras, ciclos, asignaturas y tutorías devuelven 500. Los catálogos de
períodos y modalidades responden 200. Las cuatro consultas fallidas acceden a
`career_coordinator` para limitar las carreras visibles.

La inspección de la base objetivo confirma que las cinco tablas creadas por
las migraciones pertenecen al rol administrativo, mientras las tablas previas
pertenecen al rol de la aplicación. Este último carece de permisos de lectura
y escritura sobre `periodo_paralelo`, `subjects`, `subject_cycle`,
`career_coordinator` y `career_teacher`, y de acceso a sus cinco secuencias.
Una consulta de solo lectura bajo ese rol reproduce SQLSTATE `42501`.
Las comprobaciones anteriores se ejecutaron como administrador y no detectaron
la falta de privilegios del rol de ejecución.

Corrección **aplicada en producción a las 22:05 UTC**: se concedieron
exclusivamente `SELECT`, `INSERT`, `UPDATE` y `DELETE` sobre esas cinco tablas,
y `USAGE`, `SELECT` sobre sus secuencias al rol existente de la aplicación,
dentro de una transacción. Se verificaron los 30 privilegios y las lecturas
bajo ese rol antes de confirmar la transacción. Los datos académicos, las
cuentas y los propietarios de los objetos permanecen sin cambios.

La API desplegada en `https://api.calidad.devs-ueb.tech` se probó con la cuenta
de coordinador: inicio de sesión 200 y 11 consultas 200, con los datos esperados
en carreras, ciclos, períodos, modalidades, asignaturas, tutorías, docentes,
docentes disponibles, horarios, asistencia e informes. El cierre de sesión
respondió 204 y revocó el token exclusivo del diagnóstico.

La evidencia operativa se conserva fuera del repositorio, en
`/home/giossue/.local/state/calidad-software/incidents/20260927-tutoring-permissions/`:
`privileges-before.json`, `repair.sql`, `repair-result.json` y `http-after.json`.
No se incluyen credenciales en este documento ni en esos resultados.
