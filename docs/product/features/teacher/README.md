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
| DOC-19 | Informes | Generar el Reporte de la Evaluación Diagnóstica con el formato oficial y enviarlo a coordinación. |
| DOC-20 | Informes | Generar el Reporte de Seguimiento a las Tutorías Académicas de un parcial con el formato oficial y enviarlo a coordinación. |
| DOC-21 | Informes | Generar el Informe final PAO (ejecución de tutorías) de sus asignaturas del período con el formato oficial y enviarlo a coordinación. |
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
   **Reporte de evaluación diagnóstica** abre el formato oficial calculado con las
   notas diagnósticas registradas: encabezado (facultad, carrera, fecha, docente,
   asignatura, ciclo, período y paralelo), estudiantes *en la lista* (inscripciones
   activas) y *en el aula* (con nota), número y porcentaje por métrica sobre los
   evaluados, plan de acción, observaciones, firmas del docente y del coordinador
   de carrera y, como anexo, las calificaciones («No asiste» si no hay nota). El
   docente elige la fecha y escribe las observaciones, revisa la vista previa, puede
   **Imprimir o guardar PDF** y lo envía con **Enviar a coordinación**. Se exige al
   menos una nota diagnóstica.
   **Reporte de seguimiento** genera el «Reporte de Seguimiento a las Tutorías
   Académicas» de un parcial. El docente elige el parcial (primero o segundo), el
   rango de fechas de las tutorías y la fecha de presentación, y escribe la
   observación. El servidor calcula: datos informativos, antecedentes, actividades
   programadas (por cada tutoría del rango, sus temas abordados con las actividades
   y metodologías activas), estudiantes que asisten y estudiantes que mejoraron su
   rendimiento. Se exige al menos una tutoría registrada en el rango.

   - *Riesgo académico*: diagnóstico en grupo Bajo o Medio. *Sin riesgo*: grupo Alto.
     Los inscritos sin diagnóstico no entran en ninguna columna; si asisten, se
     informa aparte.
   - *Asiste*: tiene al menos una asistencia marcada como presente en el rango.
   - *Mejoró su rendimiento*: nota del parcial elegido mayor que su nota
     diagnóstica (primer parcial: `partial`; segundo: `partial_two`).

   **Informe final PAO** genera el informe de ejecución de tutorías académicas del
   período. Incluye las tutorías del docente del mismo período y carrera (la actual
   siempre; las demás, a elección). El docente indica la fecha y, si quiere, el
   número de informe (por defecto `NN-TA-AAAA`: se conserva el del informe ya
   enviado para ese PAO o se toma el siguiente correlativo del año), escribe la
   observación de cada asignatura, las conclusiones y las recomendaciones. El
   servidor calcula los datos generales, el horario, la matriz de
   operacionalización y los anexos (estudiantes que mejoraron al segundo parcial y
   registro de tutorías). Reglas de la matriz, acordadas con coordinación:

   - *Horas por semana*: suma de los horarios activos. *Horas realizadas*: por cada
     tutoría registrada, las horas de los horarios de ese día de la semana (si no
     hay, el promedio de los horarios).
   - *Riesgo académico*: diagnóstico Bajo o Medio. *Asiste*: al menos una
     asistencia como presente.
   - *Aprobó*: promedio de los dos parciales ≥ `teaching.passing_grade` (7).
   - *Mejoró su rendimiento*: segundo parcial mayor que el primero (igual que el
     Anexo 1).
   - *% Efectividad*: con riesgo que asistieron y aprobaron ÷ con riesgo que asisten.
9. En **Titulación**, buscar por tema o estudiante, filtrar por tutor/par académico
   y abrir el detalle de las asignaciones propias.

## Calificación y clasificación

La escala oficial proviene del formato «1. Reporte Evaluación Diagnóstica» del
Plan de Tutorías Académicas y está en `config/teaching.php`, junto con el plan de
acción de cada nivel:

| Grupo | Formato oficial | Intervalo inclusivo (nota /10) | Plan de acción |
| --- | --- | --- | --- |
| Bajo | 0% – 50% | 0–5,00 | Semana de adaptación de Conocimientos; Tutorías adicionales obligatorias; Estudio Autónomo |
| Medio | 51% – 69% | 5,01–6,99 | Semana de adaptación de Conocimientos; Tutorías adicionales obligatorias |
| Alto | 70% – 100% | 7,00–10 | Semana de adaptación de Conocimientos |

Con dos decimales, cualquier nota superior al 50 % (5,01) se clasifica como Medio.
Las notas admiten hasta dos decimales, con mínimo 0 y máximo 10. La interfaz
obtiene esa misma configuración de `/api/v1/teacher/grade-settings`; no decide
la clasificación. Antes de cambiar la escala, actualizar mínimo, máximo y
grupos conjuntamente, sin huecos ni superposiciones, y ejecutar las pruebas.
Cambiar la configuración no reclasifica automáticamente diagnósticos anteriores;
la migración `2026_10_26_100000_reclassify_knowledge_metrics_with_official_ranges`
reclasificó los registrados con la escala provisional anterior (Bajo < 4).

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
  módulo de supervisión, sin correo externo. Los reportes de evaluación diagnóstica,
  de seguimiento y el informe final PAO se imprimen o guardan en PDF desde el navegador; el anexo de
  calificaciones del reporte diagnóstico va en una página aparte.
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
| GET | `/tutorings/{tutoring}/reports/diagnostic/preview` | `date` opcional (hoy por defecto); devuelve el reporte diagnóstico calculado sin guardarlo. |
| POST | `/tutorings/{tutoring}/reports/diagnostic` | `date` (hasta hoy), `observations` opcional; guarda el reporte con `summary.format = diagnostic_evaluation`. |
| GET | `/tutorings/{tutoring}/reports/follow-up/preview` | `partial` (1 o 2), `from`, `to` (hasta hoy, no anterior a `from`), `date`; devuelve el reporte de seguimiento sin guardarlo. |
| POST | `/tutorings/{tutoring}/reports/follow-up` | Igual que la vista previa más `observations` opcional; guarda el reporte con `summary.format = tutoring_follow_up`. |
| GET | `/tutorings/{tutoring}/reports/final-pao/preview` | `date`; `tutoring_ids[]` opcional (por defecto, todas las elegibles); devuelve el informe y `available_tutorings` sin guardarlo. |
| POST | `/tutorings/{tutoring}/reports/final-pao` | `date`, `tutoring_ids[]` (propias, mismo período y carrera, incluida la actual), `report_number`, `observations` por id, `conclusions` y `recommendations` opcionales; `summary.format = final_pao`. |

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

La migración se aplicó a producción el **2026-09-29**, en el **lote 11**, por
petición posterior del usuario. La conexión a `calidad_software` y el respaldo se
verificaron antes de la escritura. El usuario indicó usar `.pgpass` directamente
como excepción autorizada al contenedor Dokploy: Artisan se autenticó con la
cuenta administrativa y ejecutó el cambio como `calidad_software_app`, propietario
de las tablas existentes y nuevas. Esta excepción corresponde a esa operación.

El estado final tiene **23 migraciones aplicadas y ninguna pendiente**.
Las **37 comprobaciones posteriores** verificaron esquema, constraints,
conservación de registros y privilegios del rol de la aplicación. `/up` respondió
HTTP 200 después. El registro y la ubicación privada del respaldo están en
[`migracion-docente-remota.md`](../../../plans/completed/migracion-docente-remota.md).

La activación del módulo requiere versiones compatibles de backend y frontend;
esta operación de esquema no acredita su publicación ni la comprobación
autenticada de las pantallas desplegadas. Para publicaciones y migraciones
futuras, seguir [`docs/deployment/dokploy.md`](../../../deployment/dokploy.md).
La configuración de correo debe permitir la notificación de contraseña provisional
al registrar cuentas.

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
