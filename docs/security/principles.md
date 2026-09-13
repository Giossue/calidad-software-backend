# Security Principles

- Secrets are never committed.
- Provider tokens stay server-side.
- Validate input at every boundary.
- Use least-privilege credentials in production.
- Do not expose internal provider details unless users need them.
- Authentication and authorization must be explicit on protected routes.
- Avoid storing sensitive data unless there is a clear product need.
- Use Laravel Fortify and the starter-kit flows instead of custom auth primitives.
- Store sessions through Laravel's configured session driver.
- Use PostgreSQL credentials with the minimum privileges needed after migrations.
- Use HTTP-only cookies for sessions.

## Environment Variables

Document required variables in the app repo, not in this template.

Use placeholders only:

```text
SERVICE_API_TOKEN=replace-with-token
DB_HOST=127.0.0.1
DB_DATABASE=calidad_software
```
