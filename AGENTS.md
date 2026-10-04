# Guía de trabajo para agentes

## Proyecto

Este repositorio contiene la API Laravel 13 del sistema Calidad Software. React,
Tailwind y shadcn/ui viven en `calidad-software-frontend`. PostgreSQL es la base
principal; Sanctum emite tokens Bearer y Fortify conserva las reglas y acciones
de identidad.

## Convenciones

- Mensajes para usuarios en español; clases, métodos, rutas internas y nombres
  físicos de datos en inglés.
- La API pública se versiona bajo `/api/v1` y responde JSON mediante Resources.
- Controladores delgados, Form Requests, Policies y Actions para casos de uso.
- Validación y autorización siempre en servidor; React no es una frontera segura.
- Usa Eloquent y migraciones. Constraints e índices protegen invariantes.
- Nunca expongas secretos y nunca aceptes comodines CORS en producción.
- Los tokens de acceso deben expirar y ser revocables. 2FA no puede omitirse.
- No habilites passkeys hasta implementar y probar el desafío WebAuthn específico
  para el origen del frontend desplegado en otro dominio raíz.

## PostgreSQL y migraciones de producción

- El método habitual es Artisan local con las credenciales administrativas de
  `/home/giossue/.pgpass`, leídas y usadas solo en memoria. Nunca mostrar,
  copiar ni versionar credenciales, ni incluirlas en argumentos o archivos nuevos.
- La base objetivo es `calidad_software`, en `187.127.6.234:8004`. La entrada
  administrativa de `.pgpass` para `central-db` y `admin_root` permite acceder a
  esa base; el runner debe seleccionar explícitamente `calidad_software`.
- Antes de ejecutar, verificar el endpoint efectivo, la base y el rol mediante
  la conexión real. No asumir que `127.0.0.1`, `.env.example` o una configuración
  Laravel cacheada corresponden a producción.
- Verificar un respaldo antes de aplicar. Usar un runner local que configure la
  conexión explícita en memoria, compruebe el objetivo y ejecute Artisan con el
  rol `calidad_software_app`; corregir la propiedad administrativa necesaria y
  comprobar después propietarios, permisos de tablas y uso de secuencias para
  el rol de la API. Seguir `docs/deployment/dokploy.md`.
- Dokploy describe el hosting. No exige SSH ni ejecutar migraciones dentro del
  contenedor. Si el usuario pide aplicar migraciones, usar `.pgpass` sin solicitar
  otra confirmación sobre la vía de ejecución.
- No ejecutar `migrate:fresh`, `db:wipe`, seeders de demostración ni rollbacks
  automáticos en producción.

## Verificación

```bash
composer install
composer test
```

Para trabajo no trivial actualiza `docs/plans/active/`. No hagas commit ni push
salvo petición explícita.
