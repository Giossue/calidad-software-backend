# Módulo Docente (DOC-01 a DOC-18)

## Objetivo

Implementar el Sprint 4 de `temp/Planificación_CalidadSoftware_Def.docx` en la
API y la SPA, siguiendo los módulos de coordinación existentes.

## Tareas

- [x] Migraciones aditivas para notas, contenido, métricas y sesiones.
- [x] API `/api/v1/teacher`: tutorías propias, estudiantes, notas y clasificación,
      asistencia con temas vistos, contenido, informes y asignaciones de titulación.
- [x] Policies, Form Requests, Actions y Resources con alcance por docente.
- [x] Navegación y pantallas React con componentes locales, estados de carga,
      errores, confirmaciones, historial y prevención de envíos duplicados.
- [x] Pruebas de permisos, aislamiento, reglas académicas e integración UI/API.
- [x] Verificación completa y documentación del contrato y uso.

## Decisiones

- Se reutilizan las tablas y modelos del baseline; las nuevas columnas, tablas
  auxiliares y rutas usan nombres ingleses. La UI permanece en español.
- La baja de un estudiante afecta a su inscripción, nunca a su cuenta global.
  El docente puede editar nombre y teléfono; cédula y correo identifican la cuenta.
- Las operaciones exigen cuenta activa, correo verificado y token `access-api`.
  Las tutorías o períodos inactivos son de consulta.
- La clasificación diagnóstica se guarda por inscripción y usa reglas del
  servidor configurables; la planificación no fija escala ni umbrales.
  Escala provisional 0–10; Bajo 0–3,99, Medio 4–6,99, Alto 7–10, en
  `config/teaching.php`. La UI consulta esa configuración.
- Las sesiones se identifican por tutoría y fecha, como la unicidad existente
  de asistencia; repetir el guardado actualiza la sesión y preserva el historial.
- El informe almacena el consolidado al enviarse y queda disponible para la
  supervisión del Coordinador de Carrera, sin depender de correo externo.
- Este trabajo prepara código y migraciones; la verificación usa SQLite en
  memoria, SQLite temporal y PostgreSQL local aislado con esquema legado.
  El despliegue y la aplicación de migraciones en producción son pasos
  posteriores sujetos al procedimiento Dokploy.
- Se detectó una limitación del rollback SQLite de la migración ya compartida;
  se registra en `docs/plans/technical-debt.md`. `up()` y la conservación de datos
  están verificados. No se reescribe la migración publicada.

## Verificación

- Fecha: 2026-09-29. Implementación DOC-01 a DOC-18 completada en ambos repositorios.
- `composer install`: correcto desde el lock, sin cambios de dependencias.
- `composer test`: Pint y PHPStan correctos; 192 pruebas y 1.408 aserciones.
  Nuevas: 24 pruebas funcionales y una de ampliación de esquema legado.
- `npm run lint`: cero errores, 11 advertencias previas fuera del módulo.
- `npm run test`: 64 pruebas correctas, incluidas 15 del módulo docente.
- `npm run build`: correcto; advertencia existente por chunk principal de
  716,95 kB (201,73 kB gzip).
- PostgreSQL 18.6 local: 23 comprobaciones de migración, conservación de historia,
  calificaciones, clasificación, sesiones, informes y constraints correctas.
- QA React/API real con SQLite temporal: siete secciones, inscripción, notas,
  metodología, asistencia y envío de informe visible al coordinador correctos.
- Capturas revisadas en escritorio de 1.440 px y móvil de 390 × 844, claro y
  oscuro; cero errores JavaScript y sin desbordamiento horizontal de página.
- Manual, aceptación, permisos, contratos y decisiones:
  `docs/product/features/teacher/README.md`.
- Despliegue y migración de producción fuera del alcance de esta implementación.

## Aplicación posterior de esquema

Por petición posterior del usuario, la migración docente se aplicó en producción
el 2026-09-29, lote 11, con 23 migraciones aplicadas y ninguna pendiente.
Las 37 comprobaciones posteriores fueron correctas. Véase
[Aplicación remota de la migración docente](migracion-docente-remota.md).
La publicación y la comprobación autenticada del código desplegado se verifican
separadamente de esta aplicación de esquema.
