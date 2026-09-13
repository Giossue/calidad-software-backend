# Visión del producto

## Propósito

El Sistema de Control de Tutorías Académicas y Titulación centraliza el ciclo de
titulación: propuesta y revisión de temas, asignación de docentes, seguimiento
del avance, observaciones, horarios e informe final. Sustituye registros
dispersos y permite que cada actor vea y ejecute únicamente las tareas que le
corresponden dentro del período académico vigente.

Esta visión se deriva de los diagramas de contexto y entidad-relación entregados.
No es un SRS. Los detalles todavía no confirmados deben entrar al tablero
ScrumBan como historias con criterios de aceptación antes de implementarse.

## Actores

| Actor                     | Responsabilidad principal                                                                                          |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| Estudiante                | Proponer su tema y consultar tutorías, horarios, estado y observaciones.                                           |
| Docente                   | Consultar estudiantes asignados y registrar tutorías, actividades, asistencia y calificaciones cuando corresponda. |
| Coordinador de titulación | Revisar temas, registrar observaciones, asignar tutor/par, programar horarios y redactar informes.                 |
| Coordinador de carrera    | Proveer malla/materias y organizar asignaturas, docentes, ciclos y horarios para tutorías.                         |
| Administrador             | Gestionar usuarios, roles, períodos académicos y catálogos autorizados.                                            |

Una persona puede tener más de un rol. Los roles no crean cuentas duplicadas.

## Flujos principales

1. El administrador habilita usuarios, roles y el período académico.
2. El estudiante propone un tema de titulación dentro de un período.
3. El coordinador de titulación revisa la propuesta, registra observaciones y
   decide el estado según el flujo que defina una historia aceptada.
4. Se asignan docentes con rol de tutor o par académico.
5. Se abre una ficha de seguimiento para el tema y se registran actividades de
   avance con fecha, docente y evidencia descriptiva.
6. Se programa el horario/modalidad de titulación.
7. El coordinador genera el informe de titulación y registra observaciones finales.
8. Los actores consultan información y reportes de acuerdo con sus permisos.

## Alcance inicial

- Identidad, autenticación y autorización por roles.
- Períodos académicos.
- Propuestas y revisión de temas.
- Asignación de tutor y par académico.
- Fichas y actividades de seguimiento.
- Observaciones, horarios e informes de titulación.
- Consultas y reportes necesarios para los actores del diagrama de contexto.

## Fuera de alcance hasta tener una historia

- Integración automática con sistemas académicos externos.
- Firma electrónica, pagos, matrícula o expediente académico completo.
- Notificaciones por proveedores externos.
- Cálculos de calificación no definidos por criterios de aceptación.
- Flujos de aprobación o estados inferidos únicamente por nombres del diagrama.

## Fuente de verdad

1. Historia activa y criterios de aceptación acordados.
2. Reglas confirmadas en `domain-model.md` y decisiones en ADR.
3. Migraciones, tests y código desplegado que no contradigan lo anterior.

Las preguntas abiertas se registran en el backlog; nunca se resuelven inventando
comportamiento durante la implementación.
