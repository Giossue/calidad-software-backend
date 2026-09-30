# Aplicación remota de la migración docente

## Objetivo

Aplicar `2026_09_29_000000_create_teacher_module_schema` a la base remota
`calidad_software`, a petición del usuario, con las credenciales locales autorizadas.

## Tareas

- [x] Verificar conexión efectiva y migraciones pendientes en la base objetivo.
- [x] Comprobar acceso de ejecución: SSH rechazado; el usuario indica expresamente
      conectar directamente mediante `.pgpass`.
- [x] Crear y verificar respaldo externo al repositorio antes de la escritura.
- [x] Aplicar únicamente la migración docente pendiente.
- [x] Verificar esquema, historial y permisos del rol de la API.
- [x] Registrar resultado y evidencia sanitizada.

## Decisiones

- Las credenciales se consumen desde su configuración local autorizada sin
  mostrarlas, copiarlas a archivos nuevos ni versionarlas.
- No se ejecutan operaciones de borrado, seeders ni rollbacks automáticos.
- La autorización del usuario cubre esta aplicación de esquema; la publicación
  de versiones de backend y frontend se verifica separadamente.
- El usuario corrige expresamente el método: «no es ssh, es pgpass». Se aplica
  mediante Artisan local contra la conexión remota verificada, como excepción
  autorizada para esta operación al procedimiento habitual del contenedor Dokploy.
  La autenticación usa `admin_root`; antes de migrar se selecciona mediante
  `SET ROLE` el propietario verificado `calidad_software_app`. Las tablas y
  secuencias nuevas pertenecen a ese rol. Se verifican sus privilegios después.
- La ejecución aplica exclusivamente el archivo cuyo SHA-256 coincide con la
  migración probada. Usa bloqueo asesor de PostgreSQL y límites de espera.

## Verificación

- Aplicada el **2026-09-29 a las 20:59:56 de Ecuador**
  (`2026-09-30 01:59:56 UTC`) en PostgreSQL de producción `calidad_software`.
- Conexión efectiva confirmada: PostgreSQL 18.6, endpoint autorizado
  `187.127.6.234:8004`, base y roles comprobados antes de cualquier escritura.
- Respaldo custom completo, externo al repositorio, de 162.766 bytes y permisos
  `0600`. Lectura integral con `pg_restore --file=/dev/null` correcta.
  SHA-256: `f7e0ae69202672b710aca6cc807a87c2d4dc93901b64da1cbda1365568142005`.
- Artisan aplicó únicamente `2026_09_29_000000_create_teacher_module_schema`,
  con código de salida 0, en el **lote 11**.
- Historial: **22 → 23 migraciones aplicadas; ninguna pendiente** respecto del
  repositorio verificado.
- **37 comprobaciones posteriores correctas**: columnas nullable, tablas,
  cinco índices válidos, seis claves foráneas, permisos de lectura/escritura del
  rol de la aplicación y permisos de la secuencia nueva.
- Huellas y conteos de las columnas anteriores coinciden en las diez tablas
  verificadas; no se alteraron sus registros. Las columnas nuevas se excluyen de
  esa comparación para comprobar exclusivamente la conservación de historia.
- Consulta de tablas nuevas y antiguas como `calidad_software_app` correcta.
- `https://api.calidad.devs-ueb.tech/up`: **HTTP 200** antes y después.
- No se modificaron credenciales, cuentas, roles de usuarios ni datos académicos.
  La publicación del código y la comprobación funcional autenticada de las
  pantallas no forman parte de esta aplicación de esquema.

## Evidencia

Directorio privado, externo al repositorio:
`/home/giossue/.local/state/calidad-software/deployments/teacher-migration-20260930T015204Z/`.

- `before-teacher-migration.dump` y `backup-manifest.json`: respaldo e integridad.
- `before.json` y `history-before.json`: conexión, migraciones e historia previas.
- `migration-execution.log`, `migration-result.json` y los registros
  `migrate-status-before.log` / `migrate-status-after.log`: ejecución de Artisan.
- `verification-result.json`: 37 comprobaciones posteriores y metadatos de esquema.
- Los scripts auxiliares consumen `.pgpass` en memoria, sin escribir su contenido
  ni incluir las credenciales en argumentos, registros o documentos.
