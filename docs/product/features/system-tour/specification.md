# Tutorial del sistema

## Problema

Los usuarios nuevos no saben dónde está cada módulo ni qué herramientas tienen
en la cabecera.

## Usuarios

Todos los roles: administrador, coordinador de carrera, coordinador de
titulación, docente y estudiante.

## Requisitos funcionales

- El frontend presenta un recorrido guiado (driver.js) por el menú lateral, los
  módulos que la cuenta tiene en su menú y las herramientas de la cabecera.
- El recorrido se presenta automáticamente la primera vez que el usuario entra,
  después de completar su perfil y cambiar la contraseña provisional.
- El botón de ayuda (?) de la cabecera vuelve a abrir el recorrido en cualquier
  momento.

## Reglas

- Terminar o cerrar el recorrido cuenta como visto; no vuelve a presentarse solo.
- Se guarda por usuario en el servidor, así que no se repite al cambiar de
  equipo o navegador.
- Solo se explican los módulos visibles para la cuenta (por ejemplo, un
  estudiante de titulación no ve la explicación de tutorías).

## Datos

- `usuario.tour_completed_at` (timestamp, nulo): primera vez que terminó o cerró
  el recorrido.
- `UserResource.tour_completed` (bool): `tour_completed_at` no es nulo.

## API

- `POST /api/v1/auth/tour/complete`: registra el recorrido como visto y devuelve
  el usuario. Es idempotente y conserva la primera fecha. Requiere sesión y perfil
  completo.
