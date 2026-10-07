# Feature Decisions

## Decision Log

### `2026-10-07` - Importar filas válidas y reportar las inválidas

Decision: cada fila se registra en su propia transacción; las inválidas se
reportan con su número de línea.

Reason: lo pidió negocio para no rehacer archivos grandes por un error aislado.

Consequences: una importación puede quedar parcial; el reporte indica qué filas
corregir y volver a subir.

### `2026-10-07` - Procesamiento en cola

Decision: la importación se procesa en `ProcessBulkImport` y el frontend consulta
el estado.

Reason: bcrypt y el envío SMTP por usuario superan el `max_execution_time` de 60 s.

Consequences: producción necesita un worker (`queue:work`), iniciado por el
entrypoint, y las tablas `jobs` y `failed_jobs`, que la consolidación de identidad
había eliminado y `2026_10_07_100200_restore_queue_tables` vuelve a crear. El
registro de la importación y su job se crean en la misma transacción.

### `2026-10-07` - Cédula opcional y nombre provisional

Decision: `usuario.cedula` admite `NULL`; `nombre` sigue obligatorio y toma un
valor provisional derivado del correo hasta que el usuario complete su perfil.

Reason: el CSV puede traer solo el correo; mantener `nombre` obligatorio evita
cambiar vistas y reportes que lo asumen.

Consequences: las respuestas pueden devolver `identification: null`.
