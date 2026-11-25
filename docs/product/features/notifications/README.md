# Avisos en la campana

Los avisos de acciones del sistema se guardan en la base de datos
(`ActionNotification`, canal `database`) y se muestran en la campana. Se envían
con `App\Support\ActionNotifier`: nunca avisa a quien realizó la acción ni a
cuentas deshabilitadas, y no repite el mismo aviso a la misma persona dentro de
una misma solicitud. Al abrir un aviso con enlace, la campana lleva a esa
pantalla.

## Resultado final de la tutoría

- Cuándo: el docente registra el resultado final de un estudiante
  (`PUT /api/v1/teacher/tutorings/{tutoring}/students/{enrollment}/final-result`)
  y el valor cambia a aprobado o reprobado.
- Quién lo recibe: el estudiante.
- Tipo: `tutoring_final_result`.
- Título: "Tutoría aprobada" o "Tutoría reprobada".
- Mensaje: "Aprobaste la tutoría de {asignatura}" o
  "Reprobaste la tutoría de {asignatura}".
- Enlace: `/panel/student-tutorings?tutoring={id}`.
- No se avisa al quitar el resultado ni al volver a guardar el mismo valor.
  Cambiar de aprobado a reprobado (o al revés) avisa del nuevo resultado.
