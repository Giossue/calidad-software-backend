# Reemplazar `usuario.rol` por `roles` + `role_user`

## Objetivo

Reemplazar la columna `usuario.rol` (string con `CHECK` fijo a 5 valores) por
un catálogo `roles` y un pivote `role_user`, para permitir varios roles por
cuenta como ya describe `docs/product/domain-model.md` y dejar de duplicar la
lista de roles válidos en migraciones y Form Requests.

## Estado

Implementado en código y cubierto por tests (`composer test` en verde). No se
ha ejecutado `php artisan migrate` contra ninguna base persistente (ni existe
todavía la base local `calidad_software`); falta el paso de despliegue.

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
