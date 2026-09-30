# Diagnóstico de la interfaz de titulación — 2026-09-27

## Síntoma y evidencia

La cuenta de coordinador de titulación inicia sesión, pero el frontend muestra
un menú vacío y un mensaje de módulos no habilitados.

- El inicio de sesión en la API de producción confirma el rol
  `coordinador_titulacion`; la credencial está asociada al rol esperado.
- `src/features/tutoring/tutoring-navigation.ts` del frontend solo resuelve
  destinos para administración y coordinación de carrera. Titulación termina
  en `home`.
- `src/pages/dashboard-page.tsx` solo incorpora menús y contenido para esos
  dos grupos. No existen páginas ni cliente API de coordinación de titulación.
- Se actualizaron todas las referencias remotas del frontend, sin hacer merge.
  Tampoco existen esas pantallas en las ramas publicadas de ese repositorio.
  La única novedad de `origin/main` es `6f9766a`, relacionada con el login.
- La comprobación posterior de GitHub incluye PR abiertos, cerrados e
  integrados: el backend tiene cinco PR, todos integrados; el frontend solo
  tiene uno, también integrado. Ambos repositorios reportan cero forks.
- El [PR backend #5](https://github.com/Giossue/calidad-software-backend/pull/5)
  implementa CT-03 a CT-09 y fue integrado el 2026-09-26. Sus 42 archivos son
  API, modelos, acciones, migraciones, pruebas y documentación del backend;
  el commit `512d7b6` ya pertenece al historial actual.
- El [PR frontend #1](https://github.com/Giossue/calidad-software-frontend/pull/1)
  fue integrado el 2026-09-22. Sus 18 archivos cubren administración,
  autenticación, accesibilidad y componentes compartidos; no incorporan las
  pantallas de titulación. El merge `5da7566` ya pertenece al historial actual.
  No existe un PR pendiente de integrar que aporte esas pantallas.
- Antes de la incorporación de tutorías, el rol también recibía contenido
  denegado para administrar catálogos; mostrarlo correctamente requiere una
  interfaz de titulación conectada a su propia API.

## API existente y prueba de producción

Con un token temporal del coordinador de titulación:

| Petición | Resultado |
| --- | --- |
| `POST /api/v1/auth/login` | 200; rol correcto |
| `GET /api/v1/academic-periods/current` | 200 |
| `GET /api/v1/academic-periods/current/sections` | 200; un paralelo |
| `GET /api/v1/coordination/teachers` | 200; un docente |
| `GET /api/v1/coordination/degree-topics/pending` | 500 |
| `DELETE /api/v1/auth/logout` | 204; token de diagnóstico revocado |

La revisión de código identifica una incompatibilidad ya documentada con el
baseline: el filtro de pendientes compara `estado` con `'pendiente'`, mientras
la columna existente en producción es booleana. Las acciones de aprobación y
rechazo también escriben estados textuales. Debe corregirse y comprobarse esa
compatibilidad antes de considerar funcional la integración.

## Implementación autorizada

El usuario solicita completar el módulo después de revisar el diagnóstico y
los PR. Se implementó la interfaz de coordinación y la consulta del
estudiante, usando los contratos existentes y conservando el diseño del
frontend. El alcance incluye:

- Menú y destino propios por rol; período vigente y paralelos; docentes;
  listado y detalle de propuestas, aprobación, rechazo, observaciones y pares.
- Listado filtrable de propuestas pendientes, aprobadas y rechazadas, con
  búsqueda y filtro de paralelo, conservando la ruta anterior de pendientes.
- Compatibilidad del baseline PostgreSQL, observaciones de hasta 1000
  caracteres y autorización consistente en triggers de coordinación.
- Token completo, correo verificado y cuenta activa en rutas del módulo;
  aislamiento de propuestas del estudiante y transacciones de revisión.
- Pruebas backend, frontend, migración PostgreSQL y flujo integrado con datos
  aislados. Se registrará por separado el estado real de producción.

## Resultado de implementación y pruebas

- Backend: nuevo listado filtrable, Policies, protección de cuenta activa,
  correo verificado y token completo; revisión con bloqueos y transacciones;
  pares sustituidos conservados inactivos y observaciones ordenadas.
- Frontend: menú y destino de titulación para coordinador y administrador;
  propuestas con detalle y acciones, docentes, período/paralelos y **Mis
  propuestas** para el estudiante. Reutiliza los componentes existentes.
- `composer install` correcto desde el lock. `composer test`: Pint y PHPStan
  correctos; 163 pruebas y 1144 aserciones.
- Frontend: lint sin errores y 11 advertencias anteriores; 37 pruebas
  correctas; build correcto con advertencia de tamaño de chunk (634 kB).
- PostgreSQL 18.6 aislado: 49 comprobaciones de migración sobre un esquema real
  restaurado sin datos, más 11 comprobaciones de Actions y rutas HTTP reales.
  Incluyen roles SQL sin propiedad ni superusuario y conservación de permisos.
- Chromium con React y Laravel reales sobre SQLite aislado: registro de
  paralelo, catálogo docente, aprobación, observación mayor de 255 caracteres,
  sustitución de pares, recuperación tras recarga, rechazo, consulta privada
  del estudiante y navegación del administrador. Sin errores de JavaScript ni
  respuestas HTTP fallidas. Vistas revisadas a 1440 px y móvil 390 × 844.
- Evidencia local fuera del repositorio: pruebas PostgreSQL en
  `/home/giossue/.local/state/calidad-software/migration-tests/degree-coordination-20260927/`
  y prueba de navegador en `/tmp/degree-coordination-e2e-1q6dqjqs/`.

## Actualización de producción

La migración se aplicó a la base objetivo verificada `calidad_software` a las
`2026-09-27T22:23:12Z`, después de comprobar un respaldo completo con SHA-256 y
lectura integral del archivo. Se utilizó la excepción previamente autorizada
de Artisan local con las credenciales de `.pgpass` exclusivamente en memoria.
La ejecución está limitada a la nueva migración de compatibilidad.

Se verificaron 21 migraciones aplicadas, estado textual de propuestas,
observaciones de tipo texto y conservación exacta de propietarios y permisos
de tablas y secuencias. Antes de migrar había cinco usuarios y ninguna
propuesta, asignación de titulación u observación. La operación no carga datos
de demostración ni modifica credenciales.

El respaldo y los resultados privados están en
`/home/giossue/.local/state/calidad-software/deployments/degree-coordination-20260927T222205Z/`.
Código publicado en `main`: backend `ab7f038` y frontend `63dea64`. La
verificación de producción concluyó correctamente a las
`2026-09-27T22:27:46Z`:

- Once consultas HTTP 200 con las cuentas reales de coordinador de titulación,
  estudiante, administrador y coordinador de carrera. La ruta anterior de
  pendientes y el nuevo listado filtrado funcionan sin el error 500.
- Chromium contra ambos dominios reales: inicio del coordinador con menú y
  propuestas, período/paralelos, docentes, diseño móvil, consulta del estudiante
  y acceso del administrador a titulación. Sin errores de JavaScript ni de API.
- Se compararon los identificadores y paralelos de los diez ciclos del
  catálogo administrativo con los expuestos al coordinador de carrera:
  coinciden. Se conservan los datos introducidos por el equipo.
- Producción no tenía propuestas de titulación; la interfaz muestra el estado
  vacío correspondiente. Las mutaciones de negocio completas se verificaron
  con datos aislados, no creando revisiones ficticias en producción.
- Los tokens exclusivos del diagnóstico fueron revocados al concluir. No se
  reemplazaron las sesiones del navegador del equipo.

La carpeta privada del despliegue contiene `production-api-verification.json`,
`production-ui-verification.json`, capturas de producción y los resultados de
las pruebas locales. Los servidores y el clúster de prueba aislados se
detuvieron tras terminar la verificación.

## Procedencia de los ciclos y paralelos de tutorías

La consulta `GET /api/v1/tutoring-coordination/cycles` utiliza el mismo modelo
`Ciclo` y la relación `paralelo` que el catálogo administrativo. Solo expone
ciclos y carreras activos dentro del alcance del coordinador. El formulario
filtra además los ciclos vinculados a la asignatura elegida. `ManageTutoring`
obtiene `fk_paralelo` directamente del ciclo y comprueba que esté activo;
registrar una tutoría no crea ciclos ni paralelos.

## Auditoría inicial

Se solicitó al usuario la ubicación del trabajo de sus compañeros por si las
pantallas existen en otro repositorio o rama todavía no publicada. Recuperar
esa implementación, o construir la interfaz que falta, requiere integrar menú,
destino inicial, período/paralelos, docentes y revisión de propuestas.

La integración debe mantener separados los permisos de coordinación de carrera
y titulación, exigir token completo de 2FA y validar cuentas activas. También
debe resolver el límite de observaciones del baseline, la autorización de los
triggers y el acceso posterior a propuestas revisadas. No se han modificado
roles, datos académicos, esquema ni permisos como parte de este diagnóstico.
