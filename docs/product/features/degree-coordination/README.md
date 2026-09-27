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
| `GET /coordination/teachers?search=...` | Docentes activos y carga de asignaciones |
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
