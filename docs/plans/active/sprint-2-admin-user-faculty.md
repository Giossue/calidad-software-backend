# Plan: Sprint 2 - Gestión de usuarios y facultades

## Goal

Implementar únicamente el backend para ADM-01 (registrar usuario), ADM-02
(actualizar usuario), ADM-03 (desactivar usuario), ADM-04 (registrar facultad),
ADM-05 (actualizar facultad) y ADM-06 (desactivar facultad), siguiendo la
arquitectura existente (controladores delgados + Form Requests + Resources).

## Tasks

- [x] Crear migración `facultad` (no existía ninguna tabla para facultades).
- [x] Crear `FacultyResource` y ampliar `UserResource` con `phone` e `is_active`.
- [x] Crear Form Requests: `StoreUserRequest`, `UpdateUserRequest`,
      `StoreFacultyRequest`, `UpdateFacultyRequest`.
- [x] Crear `UserController` y `FacultyController`.
- [x] Registrar rutas bajo `/api/v1/users` y `/api/v1/faculties` con `auth:sanctum`.
- [x] Reparar referencia rota de `RegisterRequest` (phpstan), sin habilitar rutas.
- [x] Tests de Feature para las seis historias.
- [x] Verificación: pint, phpstan, `php artisan test`.

## Decisions

- `Facultad.estado` y `Usuario.estado` (booleanos existentes) representan
  activo/inactivo; desactivar no borra el registro (no SoftDeletes).
- La API pública mantiene wrapper `{"data": ...}` y claves en inglés
  (`identification`, `name`, `email`, `phone`, `role`, `is_active`) como el
  `UserResource` existente.
- Los roles aceptados son los del check constraint real de la tabla `usuario`.
- Longitudes de validación alineadas con las columnas reales de la BD
  (cedula 20, nombre 150, correo 150, telefono 20).
- Rutas protegidas con `auth:sanctum` (infraestructura existente); no se agrega
  autorización por rol porque no existe infraestructura previa y el alcance son
  solo las seis historias.
- `RegisterRequest` se creó únicamente para restaurar `types:check`; la ruta de
  registro público permanece deshabilitada.

## Verification

- `php artisan migrate:fresh --env=testing` (sqlite en memoria).
- `composer lint:check` (pint).
- `php artisan test` (nuevos tests + suite existente salvo registro público,
  cuyo fallo 404 es preexistente por diseño).
- `vendor/bin/phpstan analyse --memory-limit=512M` (types:check).