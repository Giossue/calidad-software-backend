# Reporte de la Evaluación Diagnóstica (formato oficial)

## Goal

El docente genera, imprime y envía a coordinación el «1. Reporte Evaluación
Diagnóstica» del Plan de Tutorías Académicas, con su anexo «1.a Calificaciones
Diagnóstica», usando la escala oficial de métricas de conocimiento.

## Tasks

- [x] Escala oficial en `config/teaching.php`: Bajo 0–50 %, Medio 51–69 %, Alto 70–100 %, con plan de acción.
- [x] Migración que reclasifica las métricas calculadas con la escala provisional.
- [x] `DiagnosticEvaluationReport`: vista previa y envío (`reports/diagnostic`).
- [x] Documento con el formato oficial, anexo de calificaciones e impresión/PDF (docente y coordinador).
- [x] Pruebas de backend (ejemplo oficial, permisos, validación, migración) y frontend.
- [ ] Aplicar la migración en producción cuando se solicite.

## Decisions

- Porcentajes sobre los estudiantes evaluados («en el aula»), como en el ejemplo oficial (4/12 = 33,33 %).
- Con dos decimales, Bajo ≤ 5,00 y Medio ≥ 5,01: el formato no cubre el intervalo 50 %–51 %.
- El ejemplo numérico del formato (4/5/3) se calculó con la escala provisional; con la oficial,
  las mismas notas dan 6/3/3.
- «En la lista» cuenta inscripciones activas; los inscritos sin nota diagnóstica figuran como «No asiste».
- El reporte se guarda en `reporte` con `summary.format = diagnostic_evaluation`; no requiere cambio de esquema.
- La firma del coordinador muestra al primer coordinador activo de la carrera.
- No se incluyen apellidos separados: `usuario.nombre` se muestra completo en el anexo.

## Verification

- `php artisan test`: 354 aprobadas, 2 omitidas.
- `npm run lint`, `npx vitest run` (165 aprobadas) y `npm run build`.
