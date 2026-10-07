# Carga masiva por CSV

## Problema

Registrar facultades, carreras, ciclos, períodos, asignaturas, paralelos y
usuarios uno a uno es lento al iniciar un período o al migrar datos.

## Usuarios

- Administrador: facultades, carreras, ciclos, períodos académicos y usuarios.
- Coordinador de carrera: asignaturas, docentes y estudiantes de sus carreras.
- Coordinador de titulación: paralelos del período vigente.

## Requisitos funcionales

- Cada pantalla de registro ofrece **Cargar datos masivos** con plantilla CSV
  descargable (`GET /api/v1/imports/{type}/template`).
- `POST /api/v1/imports/{type}` recibe el archivo, valida encabezados y responde
  `202` con el id de la importación; el procesamiento ocurre en la cola.
- `GET /api/v1/imports/{id}` informa estado (`pending`, `processing`, `done`,
  `failed`), filas totales, creadas, con error y los mensajes por número de línea.
- Se importan las filas válidas; las inválidas se reportan y pueden corregirse y
  subirse en otro archivo.

## Tipos y columnas

Columnas en minúsculas; se aceptan tildes y mayúsculas en los encabezados.
Las columnas entre corchetes son opcionales.

| type | Columnas |
|---|---|
| `faculties` | nombre |
| `careers` | facultad, nombre, [ciclos], [modalidad] |
| `cycles` | carrera, numero, nombre |
| `academic-periods` | nombre, fecha_inicio, fecha_fin (AAAA-MM-DD) |
| `subjects` | carrera, nombre, [codigo], [ciclo], [modalidad] |
| `sections` | nombre |
| `users` (admin) | correo, rol, [carrera]¹, [cedula], [nombre], [telefono] |
| `teachers` (coordinador) | correo, carrera, [cedula], [nombre], [telefono] |
| `students` (coordinador) | correo, carrera, [cedula], [nombre], [telefono] |

¹ Obligatoria salvo para el rol `administrador`. Docentes y estudiantes requieren
correo `@ueb.edu.ec` y una carrera coordinada por quien importa.

## Reglas de negocio

- Cada fila se valida con el mismo Form Request y la misma autorización del
  registro individual (`App\Imports\FormRequestValidator`).
- Facultad, carrera, modalidad y paralelo se indican por nombre, sin distinguir
  mayúsculas, tildes ni espacios repetidos; el ciclo, por número dentro de la carrera.
- Cada fila se registra en su propia transacción. Una fila repetida dentro del
  archivo falla por unicidad porque la anterior ya quedó registrada.
- Separador `,` o `;`, codificación UTF-8 (con o sin BOM) o Windows-1252
  (CSV de Excel). Máximo 2 MB y 1000 filas.
- Solo el autor puede consultar su importación.

## Cuentas importadas y primer ingreso

- Se crean activas, con correo verificado y `must_complete_profile = true`.
  Sin cédula queda `NULL`; sin nombre se usa uno provisional derivado del correo.
- Si Excel quitó el cero inicial, se restituye en celulares (`991234567`) y en
  cédulas de 9 dígitos que solo son válidas con el cero (`201234567`).
- La contraseña provisional se envía con `QueuedProvisionalPasswordNotification`
  (cola cifrada, porque el payload contiene la clave).
- `EnsureProfileCompleted` responde `403` con `code: profile_incomplete` en toda
  la API salvo `auth/user`, `auth/logout` y `PUT auth/profile/complete`.
- `PUT /api/v1/auth/profile/complete` exige cédula o pasaporte, nombre, teléfono
  y una contraseña nueva distinta de la provisional; apaga el indicador y revoca
  los demás tokens del usuario.
- Gmail personal limita a unos 500 correos diarios; las importaciones grandes de
  usuarios deben repartirse en varios días o usar un proveedor transaccional.

## Datos

- Tabla `bulk_imports`. Las filas pendientes se guardan cifradas (`rows`) y se
  eliminan al terminar; quedan solo contadores y errores.
- Requiere un worker de cola (`queue:work`); ver `docs/deployment/dokploy.md`.
