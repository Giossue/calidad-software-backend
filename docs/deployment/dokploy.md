# Backend Laravel en Dokploy

## Build

- Repositorio: `Giossue/calidad-software-backend`
- Build Type: `Dockerfile`
- Docker File: `Dockerfile`
- Docker Context Path: `.`
- Docker Build Stage: `production`
- Puerto del dominio: `8080`
- Healthcheck: `/up`

El backend ya no compila Node, Vite ni React. No necesita build arguments.

## Environment

```dotenv
APP_NAME="Calidad Software"
APP_ENV=production
APP_KEY=base64:replace-with-generated-key
APP_DEBUG=false
APP_URL=https://api.example.com
FRONTEND_URL=https://app.example.com
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
TRUSTED_PROXIES=*

CORS_ALLOWED_ORIGINS=https://app.example.com
SANCTUM_EXPIRATION=60
SANCTUM_TOKEN_PREFIX=calidad_software_

LOG_CHANNEL=stderr
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=internal-postgres-host
DB_PORT=5432
DB_DATABASE=calidad_software
DB_USERNAME=calidad_software
DB_PASSWORD=replace-with-a-secret

CACHE_STORE=file
QUEUE_CONNECTION=sync
SESSION_DRIVER=file
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_HOST=replace-with-smtp-host
MAIL_PORT=587
MAIL_USERNAME=replace-with-user
MAIL_PASSWORD=replace-with-secret
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="Calidad Software"
```

Genera `APP_KEY` una sola vez con `php artisan key:generate --show`. No cambies
esa clave después de habilitar 2FA: protege secretos y códigos cifrados.

`CORS_ALLOWED_ORIGINS` admite varios orígenes separados por comas, pero nunca
debe contener `*` en producción. `FRONTEND_URL` se usa para enlaces enviados por
correo. Las credenciales solo pertenecen a Environment Settings.

## Migraciones

El entrypoint no modifica la base. Después de verificar un backup ejecuta una
sola vez desde **Advanced → Run Command**:

```bash
php artisan migrate:status
php artisan migrate --force
```

La API usa tokens Bearer, por lo que las sesiones y la caché no requieren tablas
PostgreSQL. Mientras no exista un worker de colas administrado, usa `sync`.

No uses `migrate:fresh`, `db:wipe`, seeders de demostración ni rollback automático
en producción.

Ejecuta las migraciones con la conexión y el rol PostgreSQL configurados para
la aplicación. Si una operación excepcional autorizada usa un rol
administrativo distinto, comprueba después los permisos del rol de la API
sobre las tablas y secuencias nuevas. Un `migrate:status` correcto no acredita
que ese rol pueda leer o escribir los objetos creados por otro propietario.

## Comprobación

```bash
curl --fail https://api.example.com/up
```

Prueba además login, registro, recuperación, verificación de correo y 2FA desde
el dominio real del frontend para validar CORS y los enlaces generados.

Ante un HTTP 500, identifica la ruta fallida en **Network** del navegador y
consulta **Logs** de la aplicación backend en Dokploy. Con `LOG_CHANNEL=stderr`,
Laravel envía allí la excepción. Por ejemplo, SQLSTATE `42501` indica falta de
privilegios en PostgreSQL; revisa el objeto indicado y el rol de la conexión.
Conserva `APP_DEBUG=false`: la respuesta pública genérica no contiene el
diagnóstico interno. Tras corregir la causa, repite las peticiones con una
cuenta del rol afectado contra el dominio desplegado.
