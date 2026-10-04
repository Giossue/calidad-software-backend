# Arquitectura de despliegue

Dokploy despliega dos aplicaciones Docker independientes:

- backend Laravel/Apache, puerto `8080`, healthcheck `/up`;
- frontend React/Nginx, puerto `8080`, healthcheck `/health`.

PostgreSQL es un servicio persistente separado. Las migraciones se ejecutan
habitualmente con Artisan local y las credenciales de
`/home/giossue/.pgpass`, usadas solo en memoria. Dokploy describe el hosting y no
impone SSH ni ejecución dentro de su contenedor; una solicitud de aplicar
migraciones autoriza esta vía sin otra confirmación sobre el método.

El runner local selecciona explícitamente `187.127.6.234:8004`, la base
`calidad_software` y el rol de ejecución, comprueba la conexión real y verifica
un respaldo antes de aplicar. La cuenta administrativa corrige la propiedad
necesaria; Artisan ejecuta el lote bajo `calidad_software_app`. Después se
comprueban pendientes, datos, propietarios y permisos de tablas y secuencias
para el rol de la API. No se ejecutan comandos Artisan que puedan tomar una
conexión local predeterminada ni se guardan contraseñas en argumentos o archivos
nuevos. El procedimiento completo está en
[la guía de despliegue](../deployment/dokploy.md).

En producción se prohíben `migrate:fresh`, `db:wipe`, seeders de demostración y
rollbacks automáticos.

El backend requiere `APP_URL` con el dominio de la API, `FRONTEND_URL` con el
dominio de la SPA y una allowlist exacta en `CORS_ALLOWED_ORIGINS`. El frontend
recibe `VITE_API_URL` como argumento público de compilación.
