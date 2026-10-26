# Informe final PAO (formato oficial)

## Goal

El docente genera, imprime y envía a coordinación el «4. Informe final PAO»
(informe de ejecución de tutorías académicas) de sus asignaturas del período.

## Tasks

- [x] `FinalPaoReport`: vista previa y envío (`reports/final-pao`).
- [x] `teaching.passing_grade` (7) para la aprobación.
- [x] Documento con membrete de la Facultad, matriz de operacionalización, anexos y firmas; impresión/PDF.
- [x] Pruebas de backend y frontend.
- [x] Corrección: la búsqueda de informes del coordinador usaba la columna inexistente `usuario.apellido`.

## Decisions

- Reglas confirmadas por el usuario (2026-10-26): aprueba con promedio de parciales ≥ 7;
  «mejoró» = segundo parcial mayor que el primero; % efectividad = con riesgo que
  asistieron y aprobaron ÷ con riesgo que asisten.
- Riesgo académico = diagnóstico Bajo o Medio, igual que en el reporte de seguimiento.
- El informe se guarda en la tutoría desde la que se genera e incluye las tutorías del
  docente del mismo período y carrera seleccionadas.
- Extensión telefónica: el sistema no la registra; se muestra «S/n». El correo del
  destinatario es el del coordinador (no hay correo de carrera).
- El número de página («PÁGINA x de y») no se reproduce; lo añade el navegador al imprimir si se activa.
- «Anexo 2: Registro de tutorías» se genera con fecha, temas y asistentes de cada tutoría.
- La numeración de secciones respeta el formato oficial (pasa de 2 a 4).

## Verification

- `php artisan test`: 363 aprobadas, 2 omitidas.
- `npm run lint`, `npx vitest run` (167 aprobadas) y `npm run build`.
