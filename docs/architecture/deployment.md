# Arquitectura de despliegue

Dokploy despliega dos aplicaciones Docker independientes:

- backend Laravel/Apache, puerto `8080`, healthcheck `/up`;
- frontend React/Nginx, puerto `8080`, healthcheck `/health`.

PostgreSQL es un servicio persistente separado. Las migraciones son un paso
manual y revisado después del backup:

```bash
php artisan migrate:status
php artisan migrate --force
```

El backend requiere `APP_URL` con el dominio de la API, `FRONTEND_URL` con el
dominio de la SPA y una allowlist exacta en `CORS_ALLOWED_ORIGINS`. El frontend
recibe `VITE_API_URL` como argumento público de compilación.
