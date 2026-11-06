# Tutorial del sistema

## Goal

Recorrido guiado para todos los roles, presentado una vez al ingresar y
disponible desde el botón de ayuda.

## Tasks

- [x] Columna `usuario.tour_completed_at`, campo `tour_completed` y
      `POST /api/v1/auth/tour/complete`.
- [x] Frontend: driver.js, pasos por rol, botón de ayuda y arranque automático.
- [x] Guías por pantalla: `usuario.completed_tours` reemplaza a
      `tour_completed_at` y `POST /api/v1/auth/tours/complete` a
      `POST /api/v1/auth/tour/complete`.
- [ ] Aplicar las migraciones en producción.

## Decisions

- Se guarda en el servidor y no en `localStorage`, para que cada usuario lo vea
  una sola vez aunque cambie de equipo.
- Cerrar el recorrido también cuenta como visto.
- Cada pantalla tiene su guía; se identifica por la sección y, si existe, la
  pestaña o sub-vista (`teacher-tutorings/grades`, `tutorings/detail`).

## Verification

- `php artisan test tests/Feature/Api/V1/Auth`: pasa.
