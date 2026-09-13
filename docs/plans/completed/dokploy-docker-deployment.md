# Dokploy Docker deployment

## Goal

Package the Laravel/Inertia monolith as a production Docker image and document a
safe Dokploy deployment with PostgreSQL and explicit migrations.

## Tasks

- [x] Confirm Dokploy Dockerfile fields, runtime variables and command support.
- [x] Add a multi-stage production Dockerfile and runtime configuration.
- [x] Configure Laravel for Dokploy's trusted reverse proxy.
- [x] Document Dokploy build, domain, database, migration and rollback steps.
- [x] Validate configuration, application tests and production build.

## Decisions

- One application container serves Laravel and prebuilt React assets on port 8080.
- Apache is the web runtime; Node and Composer remain in build stages only.
- Database migrations never run automatically in the container entrypoint.
- PostgreSQL and uploaded files have lifecycles independent from application images.

## Verification

- `composer validate`
- `composer test`: 39 tests, 136 assertions.
- `npm run check`
- `npm run types:check`
- `npm run build`
- Production image built with the `production` target using Podman.
- Container health check reached `healthy`; `/up` and `/` returned HTTP 200.
- Runtime image includes `intl`, `pdo_pgsql`, `zip` and OPcache.
