# Módulo Docente

Implementa DOC-01 a DOC-18 del Sprint 4 de
`temp/Planificación_CalidadSoftware_Def.docx`, en la API Laravel y la SPA React.
Reutiliza la navegación, tablas, formularios, diálogos y estilos de los módulos
de coordinación.

## Alcance y criterios de aceptación

| Historia | Sección | Resultado |
| --- | --- | --- |
| DOC-01 | Calificaciones | Registrar diagnóstico y clasificar automáticamente por inscripción. |
| DOC-02 | Calificaciones | Registrar nota parcial sin alterar el grupo diagnóstico. |
| DOC-03 | Mis tutorías | Consultar únicamente tutorías asignadas al docente, incluidos sus horarios. |
| DOC-04 | Estudiantes | Inscribir una cuenta existente del paralelo o registrar una cuenta estudiantil nueva. |
| DOC-05 | Estudiantes | Actualizar nombre y teléfono de cuentas con rol exclusivamente estudiantil. |
| DOC-06 | Estudiantes | Deshabilitar la inscripción conservando cuenta, notas y asistencia. |
| DOC-07 | Asistencia | Guardar presentes y ausentes por fecha, junto con temas vistos. |
| DOC-08 | Contenido | Registrar temas dentro de la tutoría propia. |
| DOC-09 | Contenido | Actualizar nombre y descripción del tema. |
| DOC-10 | Contenido | Deshabilitar temas mediante baja lógica. |
| DOC-11 | Contenido | Registrar actividades de un tema activo. |
| DOC-12 | Contenido | Actualizar nombre y duración de una actividad. |
| DOC-13 | Contenido | Deshabilitar actividades mediante baja lógica. |
| DOC-14 | Contenido | Registrar metodologías de una actividad activa. |
| DOC-15 | Contenido | Actualizar la descripción de una metodología. |
| DOC-16 | Contenido | Deshabilitar metodologías mediante baja lógica. |
| DOC-17 | Informes | Generar un consolidado y dejarlo disponible al Coordinador de Carrera. |
| DOC-18 | Titulación | Consultar las asignaciones propias vigentes como tutor o par académico. |

## Acceso y permisos

- Cuenta activa con rol `docente`, correo verificado y token Sanctum con capacidad
  `access-api`; un token parcial de desafío 2FA no concede acceso.
- Los listados y las escrituras se limitan al docente asignado a la tutoría.
  Tener otro rol, incluida administración, no concede acceso por sí solo.
- Las tutorías y períodos deshabilitados admiten consulta de su historial.
  No admiten inscripciones, notas, asistencia, cambios de contenido ni informes nuevos.
- Las escrituras bloquean la tutoría en una transacción y vuelven a autorizar al
  docente. Una reasignación no concede al responsable anterior permiso para escribir.
- Los identificadores anidados deben pertenecer a la tutoría, tema y actividad
  indicados. El servidor valida estos vínculos aunque la petición omita la UI.
- El docente consulta sus asignaciones de titulación; su administración sigue a
  cargo del Coordinador de Titulación.

## Manual de uso

1. Iniciar sesión y completar el flujo de verificación y 2FA que corresponda.
   El panel docente abre **Mis tutorías** y presenta siete secciones en **Docencia**.
2. En **Mis tutorías**, consultar asignatura, período, ciclo, paralelo, estado,
   número de inscritos y horarios. El menú de cada fila abre sus secciones con
   `?tutoring=<id>` para conservar el contexto.
3. En **Estudiantes**, seleccionar una tutoría. **Registrar estudiante** permite
   buscar una cuenta activa del mismo paralelo o registrar una nueva con cédula
   ecuatoriana, nombre, correo `@ueb.edu.ec` y teléfono opcional.
   Una cuenta nueva recibe contraseña provisional mediante la notificación
   existente del sistema. El envío requiere la configuración de correo operativa.
4. Editar nombre o teléfono cuando la cuenta tenga únicamente rol estudiantil.
   La cédula y el correo identifican la cuenta y su cambio corresponde a
   administración. **Desactivar inscripción** conserva el historial y no bloquea
   la cuenta global; **Reinscribir** reutiliza la inscripción anterior.
5. En **Calificaciones**, registrar diagnóstico o parcial, consultar el grupo
   calculado por el servidor y abrir el historial de notas.
6. En **Contenido**, crear temas y abrir sus **Actividades** y **Metodologías**.
   Las acciones de edición y baja lógica se ofrecen dentro de cada nivel.
7. En **Asistencia**, elegir una fecha dentro del período y hasta hoy. Cada
   estudiante comienza como **Sin registrar**: marcar explícitamente su estado o
   usar **Todos presentes** y corregir las ausencias. Indicar si se abordaron
   temas y seleccionar los correspondientes. Guardar y consultar el historial.
   Cambiar de fecha con modificaciones pendientes exige confirmar su descarte.
8. En **Informes**, enviar título y observaciones. El servidor genera el
   consolidado, lo conserva y lo hace accesible desde la supervisión de tutorías
   del Coordinador de Carrera. **Leer informe** muestra el contenido enviado.
9. En **Titulación**, buscar por tema o estudiante, filtrar por tutor/par académico
   y abrir el detalle de las asignaciones propias.

## Calificación y clasificación

La planificación no establece una escala ni los umbrales de los grupos. La
configuración provisional está en `config/teaching.php`:

| Grupo | Intervalo inclusivo |
| --- | --- |
| Bajo | 0–3,99 |
| Medio | 4–6,99 |
| Alto | 7–10 |

Las notas admiten hasta dos decimales, con mínimo 0 y máximo 10. La interfaz
obtiene esa misma configuración de `/api/v1/teacher/grade-settings`; no decide
la clasificación. Antes de adoptar otra escala, actualizar mínimo, máximo y
grupos conjuntamente, sin huecos ni superposiciones, y ejecutar las pruebas.
Cambiar la configuración no reclasifica automáticamente diagnósticos anteriores.

La clasificación se guarda en `metrica_conocimiento.enrollment_id`. Un
estudiante puede pertenecer a grupos diferentes en distintas tutorías. Registrar
un diagnóstico con un valor diferente añade una nota histórica y actualiza el
grupo; repetir el mismo valor no duplica la nota. Los parciales conservan el
grupo diagnóstico.

## Historial e integridad

- Inscripciones, temas, actividades y metodologías usan bajas lógicas. Deshabilitar
  un padre bloquea nuevos cambios en sus elementos, conservando su consulta.
- El nombre de un tema es único dentro de su tutoría y el de una actividad dentro
  de su tema, incluidos los registros deshabilitados.
- Las sesiones se identifican por tutoría y fecha. Repetir el guardado actualiza
  la sesión y la asistencia, conservando las demás fechas.
- La UI exige marcar a todos los estudiantes activos. La API admite guardar un
  conjunto explícito de inscritos activos, sin inferir ausencias para los omitidos.
- `topics_covered` registra si se abordaron temas. El campo `tema.visto` se deriva
  de los temas vinculados a sesiones; corregir una fecha no elimina lo registrado
  en otras. Un tema deshabilitado ya vinculado puede conservarse en esa sesión,
  pero no añadirse a una nueva.
- El informe guarda un resumen y texto consolidados al enviarse: cambios
  posteriores en la tutoría no modifican un informe anterior. Un reintento
  idéntico devuelve el mismo informe. Su entrega consiste en disponibilidad en el
  módulo de supervisión, sin correo externo ni exportación PDF.
- La consulta mantiene notas anteriores de cualquier tipo y asistencias sin
  sesión. Estas últimas muestran `topics_covered: null`; al guardar su fecha se
  vinculan a la sesión sin duplicarse.

## Contrato de la API

Prefijo `/api/v1/teacher`. Resources JSON con `data`; los listados paginados
incluyen `links` y `meta`. Consulta, edición, notas y guardado de sesión devuelven
`200`; altas e informes, `201`; validación, `422`; autenticación ausente, `401`;
falta de permiso, `403`; elementos anidados ajenos, `404`.

| Método | Ruta | Entrada o resultado |
| --- | --- | --- |
| GET | `/tutorings` | Tutorías propias; `search`, `status`, `page`, `per_page`; incluye `can_manage`, fechas del período y horarios. |
| GET | `/grade-settings` | Mínimo, máximo y grupos diagnósticos configurados. |
| GET | `/degree-assignments` | Asignaciones propias vigentes; `search`, `role`, `page`, `per_page`. |
| GET | `/tutorings/{tutoring}/students` | Inscripciones y notas; `search`, `status`, `page`, `per_page`. |
| GET | `/tutorings/{tutoring}/available-students` | Estudiantes activos del paralelo no inscritos activamente; `search`, máximo 100 resultados. |
| POST | `/tutorings/{tutoring}/students` | `student_id` existente **o** `identification`, `name`, `email`, `phone` opcional. |
| PATCH | `/tutorings/{tutoring}/students/{enrollment}` | `name`, `phone` opcional; identidad y correo no se aceptan. |
| PATCH | `/tutorings/{tutoring}/students/{enrollment}/deactivate` | Baja de la inscripción. |
| PUT | `/tutorings/{tutoring}/students/{enrollment}/grades/{type}` | `value`; `type` es `diagnostic` o `partial`. |
| GET | `/tutorings/{tutoring}/topics` | Temas con actividades y metodologías; `search`, `status`, `page`, `per_page`. |
| POST | `/tutorings/{tutoring}/topics` | `name`, `description` opcional. |
| PATCH | `/tutorings/{tutoring}/topics/{topic}` | `name`, `description` opcional. |
| PATCH | `/tutorings/{tutoring}/topics/{topic}/deactivate` | Baja lógica del tema. |
| POST | `/tutorings/{tutoring}/topics/{topic}/activities` | `name`, `duration` (texto, por ejemplo «30 minutos»). |
| PATCH | `/tutorings/{tutoring}/topics/{topic}/activities/{activity}` | `name`, `duration`. |
| PATCH | `/tutorings/{tutoring}/topics/{topic}/activities/{activity}/deactivate` | Baja lógica de la actividad. |
| POST | `/tutorings/{tutoring}/topics/{topic}/activities/{activity}/methodologies` | `description`. |
| PATCH | `/tutorings/{tutoring}/topics/{topic}/activities/{activity}/methodologies/{methodology}` | `description`. |
| PATCH | `/tutorings/{tutoring}/topics/{topic}/activities/{activity}/methodologies/{methodology}/deactivate` | Baja lógica de la metodología. |
| GET | `/tutorings/{tutoring}/sessions` | Sesiones con asistencia y temas; `date`, `page`, `per_page`. |
| GET | `/tutorings/{tutoring}/attendance` | Asistencia actual e histórica; `date`, `page`, `per_page`. |
| PUT | `/tutorings/{tutoring}/sessions` | `date`, `topics_covered`, `topic_ids`, `attendance: [{enrollment_id, present}]`. |
| GET | `/tutorings/{tutoring}/reports` | Informes conservados; `page`, `per_page`. |
| POST | `/tutorings/{tutoring}/reports` | `title`, `observations` opcional; el servidor calcula `summary` y `content`. |

`status` admite `active` e `inactive`; `role`, `tutor` y `par_academico`;
`date`, `YYYY-MM-DD`. `per_page` admite 1–100. Las sesiones limitan una petición
a 500 asistencias y 100 temas, sin identificadores repetidos. Los métodos PATCH
de edición exigen los campos principales completos de su formulario.

## Esquema y despliegue

`2026_09_29_000000_create_teacher_module_schema.php` reutiliza el baseline y crea
las tablas de notas, contenido y métricas solo si faltan. Añade el índice de
historial de notas, `metrica_conocimiento.enrollment_id` único y nullable,
`tutoring_sessions`, `tutoring_session_topic`, `asistencia.session_id` nullable y
`reporte.summary` nullable. Conserva los registros existentes; no asigna una
inscripción a las métricas históricas ni inventa sesiones o resúmenes anteriores.

El código y la migración están preparados. **Este trabajo no despliega ni aplica
migraciones en producción.** Para activar el módulo, seguir
[`docs/deployment/dokploy.md`](../../../deployment/dokploy.md): verificar la
conexión efectiva del contenedor a `calidad_software`, comprobar respaldo y estado
de migraciones, aplicar las pendientes desde Dokploy y publicar versiones
compatibles de backend y frontend. La configuración de correo debe permitir la
notificación de contraseña provisional al registrar cuentas.

El rollback de esta migración tiene una limitación conocida en SQLite: no
retira explícitamente los índices de `asistencia.session_id` y
`metrica_conocimiento.enrollment_id` antes de eliminar las columnas. La aplicación
de `up()` está verificada. El caso se registra en
[`technical-debt.md`](../../../plans/technical-debt.md); el procedimiento de
producción prohíbe rollbacks automáticos.

## Verificación — 2026-09-29

- `composer install` desde el lock correcto, sin cambios de dependencias.
- `composer test`: Pint y PHPStan correctos; **192 pruebas, 1.408 aserciones**.
  El módulo añade 24 pruebas funcionales y una de ampliación de esquema legado.
- Frontend: **64 pruebas correctas**, incluidas 15 del módulo; lint sin errores,
  con las 11 advertencias previas; build correcto, con advertencia por el chunk
  principal de 716,95 kB.
- PostgreSQL 18.6 en instancia local aislada: **23 comprobaciones correctas**
  sobre el esquema SQL del repositorio, actualizado al estado anterior al módulo
  y poblado con historia representativa. Valida conservación al migrar, notas,
  clasificación, reintentos, sesión, informe, unicidad y claves foráneas.
- React + Laravel + SQLite temporal: inscripción nueva, diagnóstico, parcial,
  metodología, asistencia e informe consultable por el coordinador correctos;
  siete secciones revisadas en escritorio de 1.440 px y móvil de 390 × 844,
  con temas claro y oscuro, sin errores JavaScript ni desbordamiento de página.

Pruebas focalizadas:

```bash
APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= \
  php artisan test tests/Feature/Api/V1/Teacher
```

El plan completado está en
[`modulo-docente.md`](../../../plans/completed/modulo-docente.md).
