# Coordinación de titulación

El módulo conecta las historias CT-03 a CT-09 con una interfaz para el
coordinador de titulación y una consulta de resultados para el estudiante.
Comparte identidad, componentes visuales y catálogos con el sistema existente.

## Acceso y navegación

- Coordinador de titulación: propuestas, período/paralelos y docentes.
- Administrador: las mismas herramientas de coordinación, además de sus
  catálogos administrativos.
- Estudiante: **Mis propuestas**, con sus estados, docentes y observaciones.
- Coordinador de carrera: conserva las herramientas de tutorías; no adquiere
  permisos de titulación por compartir parte del catálogo académico.

Las rutas del módulo requieren una cuenta activa, correo verificado y un token
Sanctum completo. El token temporal del desafío 2FA no concede acceso. El
servidor verifica los roles y limita la consulta del estudiante a sus propias
propuestas, independientemente de la navegación del frontend.

## Flujo de coordinación

1. Consultar el período vigente y sus paralelos; registrar un paralelo si hace
   falta. El período vigente lo determina el catálogo académico existente.
2. Consultar propuestas y filtrar por estado, paralelo o texto. El listado
   permite recuperar propuestas revisadas después de recargar la página.
3. Abrir una propuesta pendiente para revisar estudiante, descripción y
   período. Aprobar exige un tutor y al menos un par académico, todos docentes
   activos, con personas distintas para tutor y pares.
4. Aprobar o rechazar una sola vez. El rechazo admite una observación opcional;
   una revisión posterior puede añadir observaciones, hasta 1000 caracteres.
5. En propuestas aprobadas, actualizar los pares académicos. Las asignaciones
   sustituidas permanecen inactivas en el historial; las consultas muestran
   las asignaciones vigentes.
6. El estudiante consulta el resultado y la retroalimentación en **Mis
   propuestas**. Esta entrega conecta la consulta existente; no incorpora
   un formulario de presentación de propuestas ni procesos de defensa.

La falta de período o de propuestas se presenta como un estado vacío. Si solo
hay un docente disponible, la aprobación necesita que administración registre
otro docente para que tutor y par puedan ser distintos.

## Contratos

Todas las rutas usan `/api/v1` y Resources con `data`. Los catálogos y las
listas de propuestas no están paginados.

| Método y ruta | Uso |
| --- | --- |
| `GET /academic-periods/current` | Período vigente |
| `GET /academic-periods/current/sections` | Paralelos del período |
| `POST /academic-periods/current/sections` | Registrar o vincular paralelo: `name` |
| `GET /coordination/teachers?search=...` | Docentes activos de **todas las carreras y facultades**, con sus carreras (`careers`) y carga de asignaciones. Cada palabra de `search` debe coincidir con nombre, correo, cédula, carrera o facultad. |
| `GET /coordination/degree-topics` | Propuestas del período vigente |
| `GET /coordination/degree-topics/pending` | Ruta anterior de pendientes, conservada |
| `GET /coordination/degree-topics/{topic}` | Detalle |
| `POST /coordination/degree-topics/{topic}/approve` | `tutor_id`, `peer_ids` |
| `POST /coordination/degree-topics/{topic}/reject` | `observation` opcional |
| `POST /coordination/degree-topics/{topic}/observations` | `observation` |
| `GET /coordination/degree-topics/{topic}/peers` | Pares vigentes |
| `PUT /coordination/degree-topics/{topic}/peers` | Sustituir selección: `peer_ids` |
| `GET /student/degree-topics` | Propuestas del usuario autenticado |

El nuevo listado acepta `status=pendiente|aprobado|rechazado`, `section_id` y
`search`. Omitir `status` incluye todos los estados; omitir `section_id` incluye
todos los paralelos. La ruta anterior `/pending` conserva su selección
predeterminada del primer paralelo. Las listas del período devuelven 404 cuando
no existe un período vigente.

## Compatibilidad y despliegue

La migración `2026_09_27_010000_align_degree_coordination_baseline` alinea los
estados booleanos del baseline PostgreSQL con los estados textuales de la API.
Comprueba primero que el historial permita una conversión sin ambigüedades.
Las inconsistencias detienen la operación dentro de una transacción.

También amplía el texto de observaciones y permite administrador o coordinador
de titulación en los triggers de revisión y observación. No amplía los roles
permitidos para estudiante o docente en otras relaciones.

Aplicar con un respaldo verificado, mediante el procedimiento de
[Dokploy](../../../deployment/dokploy.md). La migración conserva los
propietarios y privilegios de las tablas existentes. No admite rollback
automático que reduzca estados o trunque observaciones.

Desplegar versiones compatibles del backend y frontend y verificar login,
navegación por rol, listas, revisión, reasignación de pares y consulta del
estudiante. El estado de ejecución se registra en
[el plan activo](../../../plans/active/diagnostico-interfaz-titulacion.md).

## Historial de propuestas rechazadas — 2026-10-06

Mientras un estudiante no tenga un tema aprobado, sus propuestas rechazadas se
muestran como historial en **Mis propuestas** y en el listado del coordinador.
Cuando se aprueba uno de sus temas, los listados ocultan sus rechazos y solo
muestran el tema aprobado (scope `TemaTitulacion::withoutSupersededRejections`).
Los rechazos y sus observaciones no se eliminan: permanecen en la base de datos
como respaldo.

## Módulos del estudiante según su ciclo — 2026-10-06

El administrador registra el ciclo que cursa cada estudiante (`cycle_number`,
columna `usuario.ciclo_actual`) junto con su carrera; es obligatorio para el rol
estudiante y no puede superar el número de ciclos de la carrera.

- Último ciclo de la carrera (octavo en una carrera de ocho): solo **Titulación**.
- Ciclos anteriores: solo **Tutorías**.

`UserResource` expone `cycle_number` y `academic_stage` (`tutorias`,
`titulacion` o `null`). El middleware `student.stage` aplica la regla en las
rutas `/student/tutoring*` y `/student/degree-topics*` (403 fuera de etapa).
Los estudiantes sin ciclo registrado conservan el acceso anterior: tutorías y,
si están matriculados, titulación. La matrícula en titulación sigue siendo
necesaria para presentar propuestas.

La regla se aplica en todos los registros de estudiantes:

- **Administrador** (Usuarios): elige carrera y ciclo.
- **Coordinador de carrera** (Registrar estudiante): elige el ciclo y paralelo
  (`cycle_id`, obligatorio); se guardan carrera, ciclo y paralelo. En el ciclo de
  titulación no se puede asignar una tutoría inicial.
- **Estudiante nuevo inscrito desde una tutoría** (coordinador o docente): toma la
  carrera y el ciclo de la tutoría.
- Un estudiante del ciclo de titulación no se inscribe ni se reinscribe en tutorías.
- La matrícula en titulación rechaza a estudiantes con un ciclo anterior al último.
- El autorregistro público (`/auth/register`) no pide carrera ni ciclo: esos
  estudiantes conservan el acceso anterior hasta que se les asigne.
