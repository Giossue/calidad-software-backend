# Calidad Software — Backend

API del sistema de control de tutorías académicas y titulación, construida con
Laravel y PostgreSQL. La interfaz React vive en el repositorio
`calidad-software-frontend`.

## Requisitos

- PHP 8.3 o superior
- Composer
- PostgreSQL

## Instalación

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Crea una base PostgreSQL llamada `calidad_software` y ajusta las variables `DB_*`
en `.env`. Configura también `CORS_ALLOWED_ORIGINS` con los orígenes exactos del
frontend, separados por comas. La API se sirve por defecto en
`http://localhost:8000`.

## API de autenticación

La API expone:

- `POST /api/v1/auth/register`;
- `POST /api/v1/auth/login`: recibe `email`, `password` y `device_name`;
- `POST /api/v1/auth/two-factor-challenge`;
- `POST /api/v1/auth/forgot-password` y `/reset-password`;
- envío y consumo de enlaces de verificación de correo;
- `GET /api/v1/auth/user`: requiere `Authorization: Bearer <token>`;
- `DELETE /api/v1/auth/logout`: revoca el token actual.

Los tokens expiran según `SANCTUM_EXPIRATION` (60 minutos por defecto). Las
cuentas con 2FA confirmado reciben un token de desafío limitado a cinco minutos;
solo un código TOTP o de recuperación válido produce el token de sesión.

## Verificación

```bash
composer test
```

## Conocimiento del proyecto

El proyecto usa ScrumBan y no mantiene un SRS monolítico. Antes de implementar:

- lee [AGENTS.md](AGENTS.md) para las reglas de trabajo;
- consulta [ARCHITECTURE.md](ARCHITECTURE.md) para los límites técnicos;
- revisa [la visión del producto](docs/product/overview.md),
  [el modelo de dominio](docs/product/domain-model.md) y
  [el flujo ScrumBan](docs/product/scrumban.md);
- usa [la Definition of Done](docs/quality/definition-of-done.md).

Las reglas aún abiertas de los diagramas están identificadas en el modelo de
dominio y deben resolverse como criterios de aceptación antes de programarse.

## Arquitectura y despliegue

La decisión de separar la SPA de la API está registrada en
[ADR-0001](docs/architecture/adr/0001-separar-api-y-spa.md). El plan incremental
se encuentra en [docs/plans/active/separacion-api-spa.md](docs/plans/active/separacion-api-spa.md).

La imagen Docker de producción y la configuración exacta para Dokploy se
documentan en [docs/deployment/dokploy.md](docs/deployment/dokploy.md).
