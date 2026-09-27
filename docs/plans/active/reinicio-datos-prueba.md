# Reinicio de datos de prueba — 2026-09-27

## Solicitud y estado

El usuario solicita limpiar la base actual y comenzar con una credencial por
rol, usando datos ficticios como en el alta de la cuenta de coordinador.
La base objetivo verificada es `calidad_software`, en producción.

Estado: **completado en producción el 2026-09-27**. El usuario reiteró
expresamente que la operación debía realizarse en producción y confirmó la
sustitución completa de las cuentas y los catálogos por el conjunto de prueba.
Tras el ensayo local correcto se ejecutó la transacción y se confirmó su commit.
Una segunda conexión de solo lectura verificó el resultado a las
`2026-09-27T21:54:36Z`.

## Inventario previo

Antes de la sustitución, la base contenía 38 tablas, cinco usuarios, seis facultades, cuatro carreras,
20 ciclos, tres modalidades, dos paralelos y un período académico. También
contenía las relaciones de roles y carrera del coordinador y un token de
acceso. Las tablas de tutorías, asistencia, informes y titulación estaban vacías.

El procedimiento conservó:

- Las 38 tablas, sus columnas, índices, restricciones, funciones y triggers.
- Los cinco roles existentes y sus identificadores.
- Las 20 migraciones aplicadas y su historial.

La sustitución afectó los datos de las otras 36 tablas, incluidos todos los
usuarios anteriores y sus credenciales, asignaciones y tokens. La operación usó
una lista explícita de tablas dentro de una transacción, sin `CASCADE`, sin
reconstruir el esquema ni ejecutar los seeders del repositorio.

## Carga inicial aplicada

| Rol | Cuenta de prueba |
| --- | --- |
| Administrador | `admin@email.com` |
| Coordinador de carrera | `coordinador@email.com` |
| Coordinador de titulación | `titulacion@email.com` |
| Docente | `docente.prueba@ueb.edu.ec` |
| Estudiante | `estudiante@email.com` |

Cada cuenta tiene un solo rol, un nombre de prueba, cédula sintética válida,
teléfono ficticio y contraseña almacenada mediante bcrypt, igual que el alta
administrativa. Las cuentas se crean activas y verificadas. No se envían
notificaciones ni se simula la configuración de 2FA. El correo del docente
respeta el dominio institucional exigido por su formulario de edición.

Las contraseñas y sus hashes se guardan exclusivamente en archivos privados
fuera del repositorio. No se incluyen en este documento, código ni commits.
La cuenta del coordinador conserva las credenciales indicadas por el usuario;
las otras cuatro reciben contraseñas individuales generadas para esta carga.

El escenario académico incluye:

- Una facultad de Ciencias de la Ingeniería y la carrera Software.
- Modalidades Presencial, Virtual e Híbrida; paralelo A; ciclos primero y segundo.
- Período PAO II 2026, del 2026-09-01 al 2027-02-28.
- Coordinador y docente vinculados a Software; estudiante vinculado al paralelo.
- Asignatura CS-101, Calidad de Software, vinculada a ambos ciclos.
- Una tutoría del primer ciclo, con docente, modalidad presencial y horario de
  miércoles de 10:00 a 11:00 en Laboratorio 1.
- Una inscripción del estudiante desde el 2026-09-14; asistencia presente el
  2026-09-16 y ausente el 2026-09-23; informe identificado como ejemplo.
- Un tema, actividad, metodología y nota diagnóstica de ejemplo con valor 8,
  validados contra las restricciones del esquema existente e incluidos en la carga.

Se crean todos los vínculos necesarios: carrera-coordinador, carrera-docente,
usuario-paralelo, ciclo-período, período-paralelo y asignatura-ciclo. Se respeta
la FK compuesta de asistencia y el trigger que exige pertenencia al paralelo
antes de inscribir al estudiante en una tutoría.

Titulación queda al inicio del flujo, sin propuestas ni aprobaciones inventadas.
La aprobación requiere tutor y par académico distintos; una cuenta docente no
puede ocupar ambos puestos. Las tablas sin funcionalidades implementadas no
adquieren pantallas ni endpoints por cargar datos.

## Respaldo y ensayo

Los archivos operativos están fuera del repositorio, en:

`/home/giossue/.local/state/calidad-software/resets/20260927T214604Z/`

- `before-reset.dump`: respaldo previo de producción.
- `backup-manifest.json`: fecha, SHA-256 y verificación de lectura completa.
- `inventory.json`: conteos y metadatos del esquema, sin contraseñas ni hashes.
- `reset.php`: procedimiento revisado, ensayado y ejecutado.
- `production-result.json`: resultado de la transacción y sus 15 comprobaciones.
- `production-verification.json`: verificación independiente posterior.
- `credenciales.md`: entrega privada de las cuentas de prueba, fuera del repositorio.

Se restauró el respaldo en una instancia PostgreSQL local aislada, accesible
solo mediante un socket privado. El ensayo completo pasó sus 15 comprobaciones,
con el conjunto previsto y sin omisiones. Después se ejecutó el mismo
procedimiento contra la base de producción verificada. No se cambió la
configuración de conexión de la aplicación ni se expusieron las credenciales
locales de `.pgpass`. El clúster local de ensayo quedó detenido al terminar.

## Hallazgos ajenos a la limpieza

El inventario confirma diferencias previas entre la API de titulación y el
baseline: `tema_titulacion.estado` es booleano mientras la API utiliza estados
textuales; la descripción de observaciones admite 255 caracteres y la API
acepta 1000. La limpieza de datos no corrige estas diferencias ni las oculta.
Resolverlas requiere un cambio de esquema separado y probado.

## Resultado y comprobaciones de ejecución

Las 15 comprobaciones de la operación fueron correctas: identidad de la base,
lista exacta de tablas, integridad de relaciones, cinco cuentas con un rol cada
una, almacenamiento válido de contraseñas, alcance de Software, conteos de
asistencia e informes, ausencia de tokens previos y conservación del esquema,
funciones, triggers, roles e historial de migraciones.

Los conteos finales de producción coinciden con los del ensayo: cinco cuentas,
cinco roles, 20 migraciones aplicadas y cero tokens de acceso. El catálogo quedó
con una facultad, tres modalidades, una carrera, un paralelo, dos ciclos y un
período. Tutorías contiene una asignatura vinculada a ambos ciclos, una tutoría,
un horario, una inscripción, dos asistencias y un informe, además de los cuatro
registros complementarios de ejemplo. Titulación y seguimiento permanecen
vacíos para iniciar su flujo.

La segunda conexión de solo lectura verificó todos los conteos, el rol único
de cada cuenta y la correspondencia de las cinco contraseñas con sus valores
almacenados, sin imprimirlas. El respaldo previo y los resultados permanecen
en la carpeta operativa privada; las contraseñas y los hashes no se incorporan
al repositorio ni a este documento.
