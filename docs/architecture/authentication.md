# Autenticación y autorización

## Autenticación

Laravel Fortify y el starter kit son dueños de registro (si está habilitado),
inicio/cierre de sesión, hashing, recuperación, verificación de correo, 2FA y
passkeys. No se crean controladores, tablas o algoritmos paralelos para estas tareas.

- El registro público permanece deshabilitado si el backlog no lo requiere.
- Cookies de sesión son HTTP-only, Secure en producción y SameSite adecuado.
- Se regenera la sesión al autenticar y se invalida al cerrar sesión.
- Login, recuperación y endpoints enumerables tienen rate limiting.
- Contraseñas, códigos, cookies y credenciales nunca se registran ni se envían a React.

## Usuarios y roles

Existe una identidad `users` por persona. Estudiante, docente, coordinadores y
administrador son roles/perfiles. Si una persona ejerce dos funciones conserva
una cuenta con dos roles.

- El administrador asigna roles; un usuario no puede elevar sus privilegios.
- El frontend recibe solo capacidades necesarias para presentación, no el detalle
  interno completo de permisos.
- Los perfiles separados aparecen solo cuando un rol posee atributos propios.

## Autorización

- Middleware protege grupos completos; Policies resuelven acciones por modelo.
- Form Requests pueden delegar en Policies desde `authorize()`.
- Denegar por defecto: pertenecer al rol no basta si no puede actuar sobre ese
  tema, período, asignación o ficha concretos.
- Un docente registra actividades únicamente en temas donde esté asignado.
- Un estudiante accede solo a sus temas y seguimiento, salvo regla confirmada.
- Coordinadores actúan dentro del alcance académico que defina el backlog.
- Ocultar/deshabilitar botones en React no sustituye ninguna comprobación servidor.

Las matrices exactas de permisos se completan por historia y se cubren con pruebas
de permitido, no autenticado, rol incorrecto y recurso ajeno.
