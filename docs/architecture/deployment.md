# Arquitectura de despliegue

## Unidad desplegable

Laravel y el build React/Inertia forman una sola aplicación. El servidor web
sirve `public/` y PHP ejecuta Laravel. En Dokploy, una imagen Apache/PHP expone el
puerto interno 8080. PostgreSQL es externo o usa un volumen persistente separado;
workers/scheduler reutilizan la misma imagen cuando sean necesarios.

Servicios según necesidad:

- `web`: reverse proxy y archivos públicos.
- `app`: PHP-FPM/Laravel.
- `worker`: `php artisan queue:work` si existen jobs asíncronos.
- `scheduler`: `php artisan schedule:work` o cron equivalente.
- PostgreSQL administrado o persistente fuera del ciclo del contenedor app.

No se despliega un servidor Node: Node/npm solo construye assets, salvo que un
ADR habilite SSR de Inertia.

La guía operativa exacta está en `docs/deployment/dokploy.md`.

## Build y release

```bash
composer install --no-dev --classmap-authoritative
npm ci
npm run build
php artisan migrate --force
php artisan optimize
```

- Construye assets antes de activar el release.
- Migraciones son un paso explícito y revisado; no arrancan en cada réplica.
- Reinicia workers de forma gradual tras desplegar código nuevo.
- `storage` y `bootstrap/cache` requieren permisos mínimos de escritura.
- `APP_DEBUG=false`, `APP_ENV=production` y `APP_URL` correcto son obligatorios.

## Configuración

Variables mínimas relevantes:

```text
APP_NAME="Calidad Software"
APP_ENV=production
APP_KEY=replace-with-generated-key
APP_DEBUG=false
APP_URL=https://app.example.com
DB_CONNECTION=pgsql
DB_HOST=database-host
DB_PORT=5432
DB_DATABASE=calidad_software
DB_USERNAME=least_privilege_user
DB_PASSWORD=replace-with-secret
SESSION_SECURE_COOKIE=true
```

No se versiona `.env`. Los secretos pertenecen al gestor de secretos de la
plataforma y solo variables `VITE_*` explícitamente públicas llegan al bundle.

## Operación segura

- TLS termina en proxy confiable y Laravel configura proxies/hosts confiables.
- Endpoint de salud comprueba proceso; readiness considera dependencias críticas
  sin exponer versiones, credenciales o excepciones.
- Logs van a stdout/servicio central, sin datos sensibles.
- Backups de PostgreSQL se automatizan y su restauración se prueba.
- Desplegar o revertir código nunca elimina volúmenes ni datos.
- Migraciones incompatibles usan expand/migrate/contract para permitir rollback.
