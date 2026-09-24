# Reemplazar `usuario.rol` por `roles` + `role_user`

## Objetivo

Reemplazar la columna `usuario.rol` (string con `CHECK` fijo a 5 valores) por
un catálogo `roles` y un pivote `role_user`, para permitir varios roles por
cuenta como ya describe `docs/product/domain-model.md` y dejar de duplicar la
lista de roles válidos en migraciones y Form Requests.

## Estado

Implementado en código y cubierto por tests (`composer test` en verde).

Primer intento en producción (2026-09-24): `2026_09_23_100000_create_roles_and_role_user_tables`
corrió bien, pero `..._drop_rol_column_from_usuario_table` falló porque
PostgreSQL tenía un trigger `validar_cambio_rol_usuario` (con su función
`fn_validar_cambio_rol_usuario`) que no estaba versionado en ninguna
migración de este repo — bloqueaba `UPDATE OF rol` cuando el usuario aún
tenía registros ligados a su rol (estudiante/docente/coordinador_titulacion).
Como Postgres ejecuta el DDL en una transacción, la migración fallida se
revirtió sola; nada quedó roto.

Ese trigger ya no protegía nada desde que el código empezó a escribir el rol
vía `roles()->sync()` en vez de `UPDATE usuario SET rol = ...` (el trigger
solo dispara con `UPDATE OF rol`). La migración se corrigió para trasladar la
misma regla a un trigger `BEFORE DELETE ON role_user` (quitar una fila de
`role_user` es el equivalente de "cambiar de rol" en el modelo anterior).
Verificado contra una base Postgres real (no solo SQLite de los tests): la
cadena completa de migraciones corre limpia desde cero, el trigger nuevo
bloquea la baja de un rol con registros dependientes y la permite una vez que
se limpian, y `migrate:rollback` de esa migración no revienta.

Falta: reintentar `php artisan migrate --force` en producción con la
migración corregida.

## Criterios de aceptación

- `roles` guarda el catálogo (`estudiante`, `docente`, `coordinador_carrera`,
  `coordinador_titulacion`, `administrador`); `role_user` asigna N:N a `usuario`.
- `Usuario::roles()`, `hasRole()` y `hasAnyRole()` reemplazan las comparaciones
  `$user->rol === 'administrador'` en las 6 Policies existentes.
- `StoreUserRequest`/`UpdateUserRequest` validan `role` contra `Role::pluck('slug')`
  en vez de arrays repetidos.
- El contrato de la API no cambia: `UserResource.role` sigue siendo un string
  (el primer rol asignado). Exponer varios roles en la API queda fuera de este
  cambio y requiere coordinar con el frontend.
- `usuario.rol` se elimina después de hacer backfill a `role_user`.

## Despliegue

1. Crear un respaldo de PostgreSQL antes de migrar cualquier entorno con datos.
2. Ejecutar `php artisan migrate --force`. La migración de creación de
   `roles`/`role_user` hace el backfill desde `usuario.rol`; la segunda
   migración elimina la columna y su `CHECK`.
3. Confirmar login, gestión de usuarios (`/api/v1/users`) y los catálogos
   administrativos protegidos por Policies que dependían de `rol`.
