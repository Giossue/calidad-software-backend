# Revisión y aplicación de migraciones pendientes — 2026-10-03

## Objetivo

Identificar y aplicar las migraciones pendientes después de actualizar `main` a
`6bd8154`, verificando la conexión real de producción y corrigiendo su
compatibilidad con los datos y el esquema existentes.

## Tareas

- [x] Leer el procedimiento de despliegue y las instrucciones locales.
- [x] Verificar la conexión PostgreSQL y consultar el historial en modo solo lectura.
- [x] Contrastar el historial con los archivos de migración del repositorio.
- [x] Revisar constraints, duplicados, dependencias y diferencias del baseline.
- [x] Registrar los bloqueos y los pasos necesarios para resolverlos.

Completado en producción el **2026-10-03 a las 19:54:03 de Ecuador**
(`2026-10-04T00:54:03Z`). Las ocho migraciones quedaron aplicadas en el lote 12;
el historial registra 31 migraciones y ninguna pendiente.

## Aplicación autorizada

- [x] Corregir la unicidad sin borrar carreras ni perder referencias.
- [x] Corregir el trigger conservando las dependencias opcionales existentes.
- [x] Hacer compatible la migración de seguimiento con el baseline.
- [x] Crear y verificar un respaldo completo externo al repositorio.
- [x] Probar el lote completo sobre una copia PostgreSQL del respaldo.
- [x] Ejecutar `composer install` y `composer test`.
- [x] Aplicar con el acceso acordado, propietario correcto y límites de espera.
- [x] Verificar historial, integridad de datos, esquema y permisos posteriores.

## Decisiones

- La petición inicial solicitó una revisión sin escrituras; la petición posterior
  «resuelve todo y haz las migraciones» autoriza corregir y aplicar el lote.
- Las credenciales se consumen en memoria desde `.pgpass`, sin mostrarlas ni
  copiarlas a archivos o al repositorio.
- Al iniciar la operación, las reglas exigían el respaldo verificado y Artisan
  en el contenedor Dokploy. Tras solicitar definir el acceso porque SSH
  fue rechazado, el usuario respondió «no pero con pgpass tengo». Esta indicación
  autoriza Artisan local contra la conexión verificada como excepción para esta
  operación, con las mismas guardas, respaldo y comprobación de permisos.
- Tras completar la operación, el usuario aclaró que Dokploy se había incluido
  como contexto de despliegue y que `.pgpass` debía ser el método habitual.
  Se actualizaron las reglas globales y las del repositorio para usar Artisan
  local con esas credenciales en memoria, conservando las verificaciones de
  conexión, respaldo, integridad y permisos. Ya no se exige SSH ni el contenedor
  para aplicar futuras migraciones solicitadas por el usuario.
- No ejecutar borrados, seeders de demostración ni rollbacks automáticos.
- La carrera `Software` ID 1 concentra 42 asignaturas, tres docentes y diez
  ciclos. Conserva el nombre original. Las carreras IDs 4 y 5 conservan todos sus
  registros y ocho ciclos cada una; se distinguen agregando la facultad al nombre.
- La corrección administrativa transfirió únicamente `subjects` y su
  secuencia dependiente al propietario verificado de la aplicación, sin conceder
  privilegios de superusuario al rol de la API.
- La autenticación excepcional consume la entrada administrativa de `.pgpass`;
  la ejecución de las migraciones usa `SET LOCAL ROLE calidad_software_app`.
  Las credenciales no se escriben en argumentos, scripts ni registros.

## Ensayo de aplicación

- Respaldo custom completo y legible, creado a las `2026-10-03T23:30:56Z`,
  178.111 bytes, 416 entradas, permisos privados. SHA-256:
  `4140033fd4dd16ac6261ab90b46c44762b85d38eead8ef56f992649a829d448b`.
- Restaurado en PostgreSQL 18.6 aislado, con socket privado y sin escucha TCP.
  Se conservó la propiedad original de los objetos durante la restauración.
- El ensayo aplicó las ocho migraciones mediante Artisan como
  `calidad_software_app`, después de transferir `subjects` desde `admin_root`.
- Resultado del ensayo: **23 → 31 migraciones**, las ocho en el lote 12,
  ninguna pendiente, a las `2026-10-03T23:34:45Z`.
- **81 comprobaciones aprobadas** dentro de la transacción: conteos y huellas de
  las columnas históricas, exactamente dos cambios de nombres revisados,
  conservación de todos los constraints/triggers de seguimiento, ensanchamiento
  a TEXT y permisos de tablas/secuencias para la aplicación.
- Verificación adicional bajo el rol de la aplicación: columnas nullable y cinco
  FK nuevas correctas, matrícula única por estudiante/período.
- Las pruebas de regresión PostgreSQL del trigger pasaron: dos pruebas,
  37 aserciones. Las pruebas SQLite de las otras correcciones también pasaron.
- El primer `composer test` encontró fallos de formato existentes en 21 archivos
  del código traído. Pint corrigió exclusivamente esos archivos. PHPUnit después:
  299 pruebas, 297 aprobadas, dos omitidas de PostgreSQL, 1943 aserciones.
  Se corrigieron los contratos de tipos y se completó `composer test` con salida
  0: Pint aprobado, PHPStan sin errores y las mismas 299 pruebas/1943 aserciones.
- Las correcciones adicionales para la verificación son de formato, anotaciones
  PHPDoc de casts/relaciones, tipos Validator, callbacks y normalización de IDs ya
  validados. La revisión independiente confirmó la conservación de reglas,
  respuestas y contexto de errores 404.
- API de producción `/up`: HTTP 200 antes de cualquier aplicación.
- Evidencia del ensayo y scripts con guardas en directorio externo privado:
  `/home/giossue/.local/state/calidad-software/deployments/pending-migrations-20261003T233012Z/`.
  El script de aplicación verifica respaldo, ensayo, huellas de migraciones y la
  autorización del método. No incluye credenciales. El cluster local de ensayo
  quedó detenido al terminar su verificación.

## Estado de producción

Antes de la aplicación final se volvió a verificar el endpoint autorizado,
`187.127.6.234:8004`, la base `calidad_software`, el propietario
`calidad_software_app`, las 23 migraciones aplicadas y el último lote 11. Las
huellas de los archivos coincidieron con el ensayo.

- Respaldo renovado inmediatamente antes de aplicar:
  `before-production-apply.dump`, completo y legible, 178.111 bytes, 416 entradas,
  permisos privados, creado a las `2026-10-04T00:52:25Z`.
  SHA-256: `1375dfc50750f34305856fd96fd19e8d238444c9e6826c67248c3324c954f312`.
- Artisan aplicó las **ocho migraciones en el lote 12**, autenticándose mediante
  `.pgpass` y ejecutando el lote como `calidad_software_app`.
- La transferencia de propiedad de `subjects` y su secuencia, los cambios de
  nombres y el lote se confirmaron en una sola transacción PostgreSQL. Se usaron
  un bloqueo asesor, aislamiento REPEATABLE READ, espera de bloqueo de diez
  segundos y límite por sentencia de 120 segundos.
- Historial final: **23 → 31 migraciones; ninguna pendiente**.
- **81 comprobaciones aprobadas antes del commit**, incluidas las huellas y
  conteos de las columnas históricas de **39 tablas**. Los únicos cambios de
  nombres fueron los revisados:
  - ID 4: `Software (Facultad de Jurisprudencia Ciencias Sociales y Políticas)`.
  - ID 5: `Software (Facultad de Ciencias de la Educación Sociales Filosóficas y Humanísticas)`.
  Se conservaron todos sus IDs, estados, ciclos, timestamps y referencias.
- **21 comprobaciones independientes posteriores aprobadas** a las
  `2026-10-04T00:54:23Z`: historial y lote, ausencia de duplicados, columnas,
  constraints nuevos, protección de roles y propiedad de tabla/secuencia.
- El rol de la aplicación pudo leer las tablas afectadas y usar la secuencia
  nueva de matrícula. Los permisos DML de las tablas nuevas y existentes se
  comprobaron dentro de la transacción.
- API `/up`: **HTTP 200 antes y después** de la aplicación.
- La aplicación del esquema terminó antes de publicar el código. En una petición
  posterior, el usuario autorizó expresamente el commit y push de las
  correcciones, las pruebas, este registro y las reglas actualizadas.
  Las correcciones y sus pruebas quedaron en el commit `83f7df0`; las reglas y la
  documentación se publican en un commit posterior.

Evidencia final, en el directorio privado indicado en el ensayo:

- `authorization.json`: indicación del usuario sobre la vía de acceso.
- `backup-manifest.json` y `before-production-apply.dump`: respaldo renovado.
- `execution-manifest.json`: lote, huellas y nombres esperados.
- `rehearse-result.json`: ensayo PostgreSQL completo.
- `apply-result.json`, `apply-execution.log`, `apply-status-before.log` y
  `apply-status-after.log`: aplicación mediante Artisan e integridad histórica.
- `independent-verification.json`: verificación posterior de solo lectura.

## Evidencia preliminar

- Conexión verificada a `calidad_software`, PostgreSQL 18.6, mediante el endpoint
  autorizado `187.127.6.234:8004`. Propietario: `calidad_software_app`.
- Historial: 23 migraciones aplicadas; última migración docente en el lote 11.
- Ocho archivos del repositorio todavía no constan en el historial.
- Hay un grupo de nombres duplicados en `carrera`: bloquea `UNIQUE(nombre)`.
- `ficha_seguimiento`, `actividad_avance` e `informe_titulacion` ya existen;
  la migración `2026_10_03_144624` intenta crearlas sin comprobar su existencia.
- `subjects` pertenece a `admin_root`; se deben comprobar los permisos del rol
  de la aplicación para los cambios de esa tabla.
- El acceso SSH al host Dokploy con la configuración local fue rechazado:
  `Permission denied (publickey)`.

## Migraciones pendientes en la revisión inicial

| Migración | Resultado de la revisión |
| --- | --- |
| `2026_09_25_040000_create_tutoring_tables_if_missing` | Las tres tablas ya existen; su `up` debería registrar la migración sin recrearlas. |
| `2026_10_02_162220_make_career_name_unique_across_all_faculties` | Bloqueada por tres carreras activas llamadas `Software`. |
| `2026_10_02_170000_add_fk_carrera_to_usuario_table` | La columna todavía no existe. Cero usuarios con carreras distintas en las dos pivots; no se detecta actualmente la ambigüedad del backfill. |
| `2026_10_02_172000_fix_fn_validar_baja_rol_usuario_trigger` | La versión nueva elimina comprobaciones de `actividad_avance` e `informe_titulacion`, tablas existentes; requiere conservar esas protecciones. |
| `2026_10_02_180000_make_code_nullable_in_subjects_table` | `subjects.code` sigue siendo NOT NULL. El rol `calidad_software_app` no dispone del rol propietario de la tabla. |
| `2026_10_02_190000_add_modality_id_to_subjects_table` | La columna todavía no existe; presenta el mismo bloqueo de propiedad de `subjects`. |
| `2026_10_03_090000_create_matricula_titulacion_table` | Tabla inexistente; no se identifica una colisión del nombre o una dependencia ausente. |
| `2026_10_03_144624_create_ficha_seguimiento_and_actividad_avance_and_informe_titulacion_tables` | Bloqueada: intenta crear tres tablas existentes sin comprobar existencia. |

## Bloqueos identificados antes de las correcciones

1. **Unicidad de carreras.** `Software` figura en los IDs 1, 4 y 5, todos
   activos, con facultades 3, 4 y 2 respectivamente. Debe definirse el tratamiento
   correcto de esos registros antes de imponer la unicidad global. No renombrar,
   fusionar ni eliminar carreras arbitrariamente. Referencias actuales:
   `ciclo`, `subjects`, `career_coordinator` y `career_teacher`.
2. **Tablas de seguimiento preexistentes.** Adaptar la migración final para
   conservar el baseline y alinear explícitamente las columnas necesarias.
   Omitir simplemente la creación no basta: `actividad_avance.descripcion` e
   `informe_titulacion.observaciones_finales` son VARCHAR(255), mientras la
   migración nueva propone TEXT y el controlador admite descripciones de hasta
   1000 caracteres. El baseline conserva UNIQUE por tema, CHECKs de porcentaje y
   texto, timestamps con zona horaria y triggers que la nueva creación no incluye.
   Las tres tablas tienen cero filas en el momento de la revisión.
3. **Protecciones al quitar roles.** La función desplegada ya consulta
   `actividad_avance` e `informe_titulacion`. La versión pendiente elimina esas
   consultas bajo la premisa incorrecta de que las tablas no existen. Debe
   conservarse la protección de los registros históricos.
4. **Propiedad de `subjects`.** La tabla pertenece a `admin_root` y
   `pg_has_role('calidad_software_app', relowner, 'USAGE')` es falso. Los permisos
   DML existentes no autorizan ALTER TABLE. Verificar el rol efectivo del
   contenedor y preparar una corrección administrativa acotada de propiedad o
   ejecución, con verificación posterior de permisos.
5. **Acceso a Dokploy.** No se pudo entrar mediante el perfil SSH local. Para
   aplicar el lote se necesita ejecutar Artisan en el contenedor, o una excepción
   expresamente autorizada para esta operación. Ninguna migración se ha aplicado
   durante esta revisión.

## Verificación y evidencia de la revisión inicial

- Todas las consultas utilizaron `default_transaction_read_only=on`; se verificó
  que `transaction_read_only` devolvía `on`.
- Se comprobaron historial, propietarios, pertenencia al rol propietario,
  duplicados, columnas, constraints, funciones y conteos de las tablas existentes.
- El cambio de `subjects.code` en la migración histórica del 27 de septiembre no
  se reejecuta en producción; la migración del 2 de octubre adapta esa columna.
- Evidencia sanitizada, externa al repositorio y con permisos privados:
  `/home/giossue/.local/state/calidad-software/migration-reviews/20261003T232733Z/review.json`.
- No se requiere ejecutar la suite de aplicación para esta auditoría de solo
  lectura. Las correcciones posteriores deben probarse contra PostgreSQL con el
  baseline real antes de su aplicación.
