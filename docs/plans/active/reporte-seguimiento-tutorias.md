# Reporte de Seguimiento a las Tutorías Académicas (formato oficial)

## Goal

El docente genera, imprime y envía a coordinación el «3. Seguimiento Tutorías» del
Plan de Tutorías Académicas para el primer o el segundo parcial.

## Tasks

- [x] `TutoringFollowUpReport`: vista previa y envío (`reports/follow-up`).
- [x] Encabezado común `TutoringReportHeader`, también usado por el reporte diagnóstico.
- [x] Documento con el formato oficial e impresión/PDF (docente y coordinador).
- [x] Pruebas de backend y frontend.

## Decisions

- El sistema no registra las fechas de cada parcial: el docente indica el rango de
  tutorías (por defecto, desde el inicio del período hasta hoy).
- Riesgo académico = diagnóstico Bajo o Medio (planes de acción con tutorías
  obligatorias). El ejemplo oficial (10 / 9 / 2) no puede verificarse con los datos
  del formato 1.a; con la escala oficial esas notas dan 9 estudiantes en riesgo.
- Los inscritos sin diagnóstico no entran en las columnas del formato; si asisten,
  se informa como nota al pie.
- «Mejoraron su rendimiento»: nota del parcial elegido mayor que la diagnóstica,
  para todos los inscritos activos, asistan o no a tutorías.
- Actividades programadas: por cada tutoría del rango, los temas abordados con sus
  actividades y metodologías activas (las actividades no tienen fecha propia).
- Se corrige la errata «poyo» del texto de antecedentes por «apoyo».

## Verification

- `php artisan test`: 359 aprobadas, 2 omitidas.
- `npm run lint`, `npx vitest run` (166 aprobadas) y `npm run build`.
