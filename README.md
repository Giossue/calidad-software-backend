# Calidad Software

Sistema de control de tutorías académicas y titulación construido con Laravel,
React, Inertia, TypeScript y PostgreSQL.

## Requisitos

- PHP 8.3 o superior
- Composer
- Node.js 22 o superior y npm
- PostgreSQL

## Instalación

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer run dev
```

Crea una base PostgreSQL llamada `calidad_software` y ajusta las variables `DB_*`
en `.env`. La aplicación se sirve por defecto en `http://localhost:8000`.

## Verificación

```bash
composer test
npm run check
npm run types:check
npm run build
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

## Despliegue

La imagen Docker de producción y la configuración exacta para Dokploy se
documentan en [docs/deployment/dokploy.md](docs/deployment/dokploy.md).
