# Despliegue en Dokploy con Docker

## Arquitectura

Dokploy despliega el monolito como una sola Application. El `Dockerfile`
multi-stage compila Composer, Wayfinder y React en etapas de construcción; la
etapa final contiene Apache, PHP, dependencias de producción y assets estáticos.
El contenedor escucha en `0.0.0.0:8080` y expone `/up` como healthcheck.

PostgreSQL se crea como Database de Dokploy o servicio administrado separado. No
se instala dentro de la imagen de la aplicación y sus datos nunca dependen del
ciclo de vida del contenedor web.

## 1. Preparar PostgreSQL

1. Crea una base PostgreSQL en Dokploy.
2. Usa un nombre de base y usuario dedicados al proyecto.
3. Copia el hostname **interno** mostrado por Dokploy; `localhost` apuntaría al
   contenedor web y no a PostgreSQL.
4. Configura backups antes del primer despliegue con datos reales.

## 2. Crear la Application

- Conecta el repositorio Git.
- Producción debe desplegar una rama estable (`main`); usa `dev` para staging.
- En **Build Type** selecciona `Dockerfile`.
- **Docker File**: `Dockerfile`
- **Docker Context Path**: `.`
- **Docker Build Stage**: `production`

En **Build-time Arguments** puede definirse únicamente:

```text
VITE_APP_NAME=Calidad Software
```

No coloques `APP_KEY`, contraseñas ni tokens en build arguments: se conservarían
en metadatos/capas de construcción. Este proyecto no necesita secretos durante
el build.

## 3. Variables de entorno

Genera `APP_KEY` una sola vez fuera de Dokploy:

```bash
php artisan key:generate --show
```

Configura en la pestaña **Environment**:

```text
APP_NAME="Calidad Software"
APP_ENV=production
APP_KEY=base64:replace-with-generated-key
APP_DEBUG=false
APP_URL=https://your-domain.example
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_EC
TRUSTED_PROXIES=*

LOG_CHANNEL=stderr
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=internal-postgres-host
DB_PORT=5432
DB_DATABASE=calidad_software
DB_USERNAME=calidad_software
DB_PASSWORD=replace-with-a-secret

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
BROADCAST_CONNECTION=log

MAIL_MAILER=log
MAIL_FROM_ADDRESS="no-reply@your-domain.example"
MAIL_FROM_NAME="Calidad Software"
```

`TRUSTED_PROXIES=*` es apropiado cuando el único acceso al puerto 8080 llega por
la red privada/Traefik de Dokploy. No publiques el puerto 8080 directamente al
Internet. Si existe otra topología, reemplaza `*` por las IP/CIDR reales del proxy.

### Referencia de variables

Las variables de **Environment Settings** se inyectan cuando arranca el
contenedor. Los secretos pertenecen únicamente a esta sección, nunca a los
argumentos de construcción ni al repositorio.

| Variable              | Obligatoria | Propósito y valor esperado                                                                                                                                            |
| --------------------- | ----------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `APP_NAME`            | Sí          | Nombre visible de la aplicación. Conserva `Calidad Software`.                                                                                                         |
| `APP_ENV`             | Sí          | Entorno de Laravel. En Dokploy debe ser `production`.                                                                                                                 |
| `APP_KEY`             | Sí          | Clave de cifrado de Laravel. Genera una vez con `php artisan key:generate --show`, guárdala como secreto y no la cambies entre despliegues.                           |
| `APP_DEBUG`           | Sí          | Muestra detalles internos de errores. Debe permanecer en `false` en producción; el entrypoint impide arrancar con otro valor.                                         |
| `APP_URL`             | Sí          | URL pública canónica, con dominio real y `https://`.                                                                                                                  |
| `APP_LOCALE`          | No          | Idioma principal de mensajes del servidor. Se usa `es`.                                                                                                               |
| `APP_FALLBACK_LOCALE` | No          | Idioma de respaldo si falta una traducción. Se usa `es`.                                                                                                              |
| `APP_FAKER_LOCALE`    | No          | Localización de datos generados en desarrollo/pruebas. Se usa `es_EC`; no modifica datos reales.                                                                      |
| `TRUSTED_PROXIES`     | Sí          | Proxies confiables para reconocer HTTPS y la IP reenviada por Traefik. Usa `*` solo si el puerto 8080 queda accesible exclusivamente desde la red privada de Dokploy. |

| Variable      | Obligatoria | Propósito y valor esperado                                                                                               |
| ------------- | ----------- | ------------------------------------------------------------------------------------------------------------------------ |
| `LOG_CHANNEL` | Sí          | Destino de logs. `stderr` permite consultarlos desde la pestaña Logs de Dokploy.                                         |
| `LOG_LEVEL`   | No          | Nivel mínimo registrado. Se recomienda `warning` en producción. Puede cambiarse temporalmente a `info` para diagnóstico. |

| Variable        | Obligatoria | Propósito y valor esperado                                                           |
| --------------- | ----------- | ------------------------------------------------------------------------------------ |
| `DB_CONNECTION` | Sí          | Driver de base de datos. Debe ser `pgsql`.                                           |
| `DB_HOST`       | Sí          | Hostname interno entregado por el servicio PostgreSQL de Dokploy; nunca `localhost`. |
| `DB_PORT`       | Sí          | Puerto interno de PostgreSQL, normalmente `5432`.                                    |
| `DB_DATABASE`   | Sí          | Nombre de la base creada para la aplicación.                                         |
| `DB_USERNAME`   | Sí          | Usuario dedicado de PostgreSQL.                                                      |
| `DB_PASSWORD`   | Sí          | Contraseña del usuario de PostgreSQL. Debe tratarse como secreto.                    |

| Variable                | Obligatoria | Propósito y valor esperado                                                                                                                   |
| ----------------------- | ----------- | -------------------------------------------------------------------------------------------------------------------------------------------- |
| `SESSION_DRIVER`        | Sí          | Persistencia de sesiones. `database` conserva las sesiones fuera del contenedor.                                                             |
| `SESSION_LIFETIME`      | No          | Minutos de inactividad antes de expirar una sesión; el valor definido es `120`.                                                              |
| `SESSION_ENCRYPT`       | No          | Cifra el contenido almacenado de la sesión. Se mantiene en `false`; las cookies siguen firmadas por Laravel.                                 |
| `SESSION_PATH`          | No          | Ruta en la que aplica la cookie. `/` cubre toda la aplicación.                                                                               |
| `SESSION_DOMAIN`        | No          | Dominio de la cookie. `null` usa el host actual y evita compartirla con otros subdominios.                                                   |
| `SESSION_SECURE_COOKIE` | Sí          | Con `true`, el navegador solo envía la cookie mediante HTTPS.                                                                                |
| `SESSION_SAME_SITE`     | No          | Protección de solicitudes entre sitios. `lax` es el valor compatible con el flujo web actual.                                                |
| `CACHE_STORE`           | Sí          | Almacén de caché. `database` evita depender del disco efímero del contenedor.                                                                |
| `QUEUE_CONNECTION`      | Sí          | Backend de trabajos en cola. `database` deja preparados los trabajos para un worker futuro.                                                  |
| `FILESYSTEM_DISK`       | Sí          | Disco predeterminado de Laravel. `local` escribe en `storage`; los archivos que deban persistir requerirán el volumen descrito más adelante. |
| `BROADCAST_CONNECTION`  | No          | Canal de broadcasting. `log` evita requerir un servicio WebSocket mientras esta función no se use.                                           |

| Variable            | Obligatoria | Propósito y valor esperado                                                                                        |
| ------------------- | ----------- | ----------------------------------------------------------------------------------------------------------------- |
| `MAIL_MAILER`       | Sí          | Transporte de correo. `log` registra los mensajes sin enviarlos; debe sustituirse al habilitar un proveedor real. |
| `MAIL_FROM_ADDRESS` | Sí          | Dirección remitente. Adáptala al dominio de producción antes de activar correo real.                              |
| `MAIL_FROM_NAME`    | No          | Nombre que verá el destinatario. Se usa `Calidad Software`.                                                       |

`APP_KEY`, `DB_PASSWORD` y futuras credenciales de correo son secretos. No deben
aparecer en `.env.example`, capturas, incidencias ni logs. Si cambia `APP_KEY`,
las sesiones, cookies y valores cifrados existentes dejan de poder descifrarse.

### Variables de compilación

**Build-time Arguments** solo recibe valores públicos necesarios para compilar
el frontend:

| Variable        | Propósito                                                                       |
| --------------- | ------------------------------------------------------------------------------- |
| `VITE_APP_NAME` | Nombre público incorporado en los assets React. Su valor es `Calidad Software`. |

No definas `NODE_ENV` ni `PORT` en **Environment Settings**. Node solo participa
en la etapa de compilación y no existe en el contenedor final. Apache escucha en
el puerto fijo `8080`, declarado por el Dockerfile y seleccionado en **Domains**.

## 4. Dominio y healthcheck

1. Despliega la Application.
2. En **Domains**, agrega el dominio y selecciona el puerto de contenedor `8080`.
3. Habilita HTTPS/Let's Encrypt.
4. No necesitas un Published Port para acceder mediante dominio.
5. Verifica `https://your-domain.example/up`; debe responder HTTP 200.

El Dockerfile ya declara un healthcheck contra `http://127.0.0.1:8080/up`.
Si Traefik no enruta, revisa primero el estado del healthcheck, el puerto 8080 y
que Apache escuche en todas las interfaces.

## 5. Migraciones controladas

El entrypoint **no ejecuta migraciones**. Después de que el primer contenedor esté
activo, abre **Advanced → Run Command** y ejecuta:

```bash
php artisan migrate:status
php artisan migrate --force
```

Antes de cada despliegue que incluya cambios de esquema:

1. Revisa las migraciones y prueba desde cero y sobre datos representativos.
2. Toma/verifica un backup de producción.
3. Usa cambios compatibles con la versión anterior (expand/migrate/contract).
4. Despliega el código.
5. Ejecuta una sola vez `php artisan migrate --force` desde Run Command.
6. Comprueba `/up`, logs y el flujo afectado.

No ejecutes `migrate:fresh`, `db:wipe` ni seeders de demostración en producción.
No uses `migrate:rollback` automáticamente: confirma antes que la migración sea
reversible y que no destruya datos; normalmente es más seguro corregir hacia delante.

## 6. Caché y arranque

En cada inicio, `docker/entrypoint.sh` valida variables mínimas y ejecuta:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Por eso toda configuración debe leerse mediante `config()` y las variables deben
existir antes de arrancar. Los logs se envían a `stderr` para aparecer en Dokploy.

## 7. Archivos, workers y scheduler

Hoy no hay uploads de producto que requieran persistencia. Cuando se implementen,
crea un volumen Dokploy montado en:

```text
/var/www/html/storage/app/public
```

Incluye ese volumen en backups. Para jobs o tareas programadas crea procesos
separados basados en la misma etapa `production`; no ejecutes varios schedulers
sin un mecanismo que impida duplicados.

## 8. Verificación y rollback

Verificación mínima del release:

```bash
curl --fail https://your-domain.example/up
php artisan migrate:status
php artisan about --only=environment
```

Si falla el contenedor antes de migrar, corrige variables o vuelve a desplegar la
imagen anterior. Si ya se migró, confirma compatibilidad del esquema antes de
volver al código anterior. Mantén migraciones aditivas para que el rollback del
código sea posible sin revertir datos.
