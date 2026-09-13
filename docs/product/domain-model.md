# Modelo de dominio

## Criterio de modelado

El diagrama recibido representa conceptos, no el esquema físico definitivo. La
implementación normaliza datos repetidos, usa convenciones Laravel y preserva
integridad con claves foráneas, unicidad, checks e índices PostgreSQL.

## Identidad y roles

`User` representa una persona autenticable (`identification`, `name`, `email`,
credenciales y estado de acceso). Sus roles posibles son:

- `student`
- `teacher`
- `degree_coordinator`
- `career_coordinator`
- `administrator`

Una cuenta puede tener varios roles. Los datos exclusivos de un actor viven en
un perfil asociado solo si aparecen atributos adicionales reales; cédula,
nombre y correo no se duplican en tablas por rol.

## Agregados y entidades

### AcademicPeriod

- Nombre, fecha inicial, fecha final y estado.
- La fecha final no puede ser anterior a la inicial.
- La regla sobre cuántos períodos pueden estar activos queda pendiente de negocio.

### DegreeTopic

- Pertenece a un estudiante y a un período académico.
- Registra título, descripción, estado, fecha de propuesta y fecha de revisión.
- El revisor es un usuario con rol `degree_coordinator`; puede estar vacío antes
  de la primera revisión.
- El título no sustituye un identificador y su unicidad debe confirmarse por período.

### TeacherAssignment

- Vincula un tema con un usuario que tenga rol `teacher`.
- El rol de asignación es `tutor` o `academic_peer`.
- Registra fecha y estado de la asignación.
- Un mismo docente no puede repetir el mismo rol en el mismo tema.
- La cantidad permitida de tutores/pares debe confirmarse antes de imponer unicidad
  adicional.

### TrackingSheet

- Pertenece a un tema de titulación.
- Registra fecha de apertura, porcentaje de avance y estado.
- El porcentaje está entre 0 y 100.
- La cardinalidad tema-ficha se considera uno a uno según el diagrama, pendiente
  de confirmar si debe existir historial de fichas.

### ProgressActivity

- Pertenece a una ficha y registra al docente responsable.
- Contiene descripción, fecha y marca de finalización.
- El docente debe estar asignado al tema de la ficha en el momento de registrar.

### DegreeObservation

- Pertenece a un tema y al coordinador de titulación que la redacta.
- Contiene descripción y fecha de registro.
- Es historial: no se sobrescribe una observación anterior para simular una nueva.

### DegreeSchedule

- Pertenece a un tema y registra al coordinador que lo creó.
- Contiene día o fecha, hora inicial, hora final, modalidad y estado.
- La hora final debe ser posterior a la inicial.
- El diagrama solo indica `día_semana`; la historia debe decidir entre fecha
  concreta, recurrencia semanal o ambas antes de crear la migración.

### DegreeReport

- Pertenece a una ficha y al coordinador que lo genera.
- Contiene fecha de generación, observaciones finales y estado.
- Su cardinalidad y estados exactos quedan sujetos a criterios de aceptación.

## Relaciones conceptuales

```text
User(student) 1 ── * DegreeTopic * ── 1 AcademicPeriod
DegreeTopic   1 ── * TeacherAssignment * ── 1 User(teacher)
DegreeTopic   1 ── 0..1 TrackingSheet
TrackingSheet 1 ── * ProgressActivity * ── 1 User(teacher)
DegreeTopic   1 ── * DegreeObservation * ── 1 User(degree_coordinator)
DegreeTopic   1 ── * DegreeSchedule * ── 1 User(degree_coordinator)
TrackingSheet 1 ── * DegreeReport * ── 1 User(degree_coordinator)
```

## Estados

Los estados de ciclo de vida se implementan con enums PHP respaldados por string
y casts Eloquent. No se inventarán valores hasta confirmar el flujo. `is_active`
y `is_completed` pueden ser booleanos solo cuando no representan más estados.

Cada transición debe:

- declarar origen, destino, actor autorizado y precondiciones;
- ejecutarse en servidor y dentro de una transacción si escribe varios registros;
- ser rechazada por defecto si no está definida;
- conservar fecha/actor cuando la trazabilidad sea relevante;
- tener pruebas para transición válida e inválida.

## Eliminación y auditoría

- Usuarios y registros académicos referenciados se desactivan o archivan en vez
  de borrarse, salvo regla expresa.
- Las FKs usan `restrict` por defecto para historia académica; `cascade` solo en
  hijos sin sentido independiente y con decisión documentada.
- Todas las tablas de negocio incluyen timestamps. Soft deletes se añaden por
  necesidad de recuperación/auditoría, no automáticamente.

## Preguntas abiertas para backlog

- Estados y transiciones exactas del tema, ficha, horario e informe.
- Regla de aprobación/rechazo y quién puede revertir una decisión.
- Número permitido de estudiantes por tema y de tutores/pares por tema.
- Definición y cálculo del porcentaje de avance.
- Alcance de notas diagnóstica/parcial, asistencia y contenidos de tutoría que
  aparecen en el diagrama de contexto pero no en el modelo entidad-relación.
- Relación del coordinador de carrera con carreras, mallas, asignaturas y ciclos.
- Horario como fecha puntual o recurrencia por día de semana.
- Contenido, versión, emisión y cierre del informe final.
