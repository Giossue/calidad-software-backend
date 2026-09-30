# Módulo Docente (DOC-01 a DOC-18)

## Objetivo

Implementar el Sprint 4 de `temp/Planificación_CalidadSoftware_Def.docx` en la
API y la SPA, siguiendo los módulos de coordinación existentes.

## Tareas

- [ ] Migraciones aditivas para notas, contenido, métricas y sesiones.
- [ ] API `/api/v1/teacher`: tutorías propias, estudiantes, notas y clasificación,
      asistencia con temas vistos, contenido, informes y asignaciones de titulación.
- [ ] Policies, Form Requests, Actions y Resources con alcance por docente.
- [ ] Navegación y pantallas React con componentes locales, estados de carga,
      errores, confirmaciones, historial y prevención de envíos duplicados.
- [ ] Pruebas de permisos, aislamiento, reglas académicas e integración UI/API.
- [ ] Verificación completa y documentación del contrato y uso.

## Decisiones

- Se reutilizan las tablas y modelos del baseline; las nuevas columnas, tablas
  auxiliares y rutas usan nombres ingleses. La UI permanece en español.
- La baja de un estudiante afecta a su inscripción, nunca a su cuenta global.
  El docente puede editar nombre y teléfono; cédula y correo identifican la cuenta.
- Las operaciones exigen cuenta activa, correo verificado y token `access-api`.
  Las tutorías o períodos inactivos son de consulta.
- La clasificación diagnóstica se guarda por inscripción y usa reglas del
  servidor configurables; la planificación no fija escala ni umbrales.
- Las sesiones se identifican por tutoría y fecha, como la unicidad existente
  de asistencia; repetir el guardado actualiza la sesión y preserva el historial.
- El informe almacena el consolidado al enviarse y queda disponible para la
  supervisión del Coordinador de Carrera, sin depender de correo externo.
- Este trabajo prepara código y migraciones; la verificación usa SQLite en
  memoria. El despliegue y la aplicación de migraciones en producción son pasos
  posteriores sujetos al procedimiento Dokploy.

## Verificación

Pendiente: `composer install`, `composer test`, `npm run lint`, `npm run test`,
`npm run build` y revisión visual de las pantallas.
