# Glosario

| Término de producto       | Nombre en código                   | Significado                                                            |
| ------------------------- | ---------------------------------- | ---------------------------------------------------------------------- |
| Período académico         | `AcademicPeriod`                   | Intervalo institucional que agrupa el proceso de titulación.           |
| Tema de titulación        | `DegreeTopic`                      | Propuesta del estudiante que atraviesa revisión y seguimiento.         |
| Tutor                     | `TeacherAssignment::tutor`         | Docente responsable de acompañar el tema.                              |
| Par académico             | `TeacherAssignment::academic_peer` | Docente asignado para revisión académica complementaria.               |
| Ficha de seguimiento      | `TrackingSheet`                    | Registro principal del avance de un tema.                              |
| Actividad de avance       | `ProgressActivity`                 | Actividad fechada dentro de una ficha de seguimiento.                  |
| Observación               | `DegreeObservation`                | Comentario histórico del coordinador sobre un tema.                    |
| Horario de titulación     | `DegreeSchedule`                   | Programación temporal y modalidad asociada al tema.                    |
| Informe de titulación     | `DegreeReport`                     | Informe generado desde el seguimiento con observaciones finales.       |
| Coordinador de titulación | `degree_coordinator`               | Rol que revisa, observa, programa y genera informes.                   |
| Coordinador de carrera    | `career_coordinator`               | Rol que administra información académica de carrera según el contexto. |

## Reglas de lenguaje

- La UI usa los términos en español de esta tabla.
- El código usa los nombres ingleses definidos y no mezclas como `DegreeTema`.
- Los identificadores internos nunca se muestran como etiquetas al usuario.
- Cambiar el significado de un término requiere actualizar este glosario, los
  criterios de aceptación y las pruebas afectadas.
