# Consolidar la identidad en `usuario`

## Objetivo

Usar la tabla `usuario` del esquema académico como única fuente de identidad y
conservar únicamente las tablas auxiliares que necesita la API Laravel.

## Criterios de aceptación

- Registro, login, recuperación, verificación y 2FA usan `usuario`.
- El registro solicita cédula y crea cuentas con rol `estudiante`.
- Sanctum conserva `personal_access_tokens` y Fortify conserva
  `password_reset_tokens`.
- `migrations` permanece como historial técnico del esquema.
- Se eliminan `users`, `sessions`, `cache`, `cache_locks`, `jobs`,
  `job_batches`, `failed_jobs` y `passkeys` después de comprobar que no contienen
  información que deba conservarse.
- Sesión y caché usan archivos; las colas son síncronas hasta configurar servicios
  externos persistentes.
- Las pruebas del flujo de autenticación permanecen en verde.

## Despliegue

1. Publicar el backend y frontend compatibles.
2. Cambiar `SESSION_DRIVER=file`, `CACHE_STORE=file` y
   `QUEUE_CONNECTION=sync` en Dokploy.
3. Crear un respaldo de PostgreSQL.
4. Ejecutar `php artisan migrate --force`.
5. Validar registro, login y `/api/v1/auth/user`.

