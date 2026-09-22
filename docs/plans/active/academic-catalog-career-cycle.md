# Administración de carreras y ciclos

## Alcance

Implementar las historias ADM-07 a ADM-12 en la API v1:

- registrar, actualizar y desactivar carreras;
- registrar, actualizar y desactivar ciclos.

Las mutaciones requieren una cuenta autenticada, con correo verificado y rol
`administrador`. Desactivar conserva el registro y cambia `estado` a `false`.

## Contrato

- `GET /api/v1/admin/faculties`
- `GET /api/v1/admin/careers`
- `POST /api/v1/admin/careers`
- `PATCH /api/v1/admin/careers/{career}`
- `PATCH /api/v1/admin/careers/{career}/deactivate`
- `GET /api/v1/admin/cycles`
- `POST /api/v1/admin/cycles`
- `PATCH /api/v1/admin/cycles/{cycle}`
- `PATCH /api/v1/admin/cycles/{cycle}/deactivate`

Los recursos exponen identificadores y nombres públicos en inglés:
`faculty_id`, `career_id`, `name`, `number` y `status`; los modelos mantienen el
mapa físico existente de las tablas académicas.

La migración crea las tablas solo cuando faltan y no tiene rollback automático,
para no eliminar catálogos de un baseline PostgreSQL ya desplegado.

## Reglas

- No se aceptan facultades o carreras inexistentes o inactivas.
- Una carrera no puede repetir nombre dentro de una facultad.
- Un ciclo no puede repetir número dentro de una carrera.
- Las actualizaciones son parciales y no permiten un cuerpo vacío.
