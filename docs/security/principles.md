# Security Principles

- Secrets are never committed.
- Provider tokens stay server-side.
- Validate input at every boundary.
- Use least-privilege credentials in production.
- Do not expose internal provider details unless users need them.
- Authentication and authorization must be explicit on protected routes.
- Avoid storing sensitive data unless there is a clear product need.
- Use Laravel Fortify and the starter-kit flows instead of custom auth primitives.
- Store browser sessions through Laravel's configured session driver when the
  client and server share a registrable domain.
- Use PostgreSQL credentials with the minimum privileges needed after migrations.
- The cross-domain SPA uses short-lived Sanctum bearer tokens because it cannot
  share first-party session cookies with the API.
- The SPA keeps its token in `sessionStorage`, never `localStorage`, and removes
  it on logout. Treat prevention of XSS as a critical control.
- Revoke the current token on logout and require a fresh token after expiration.
- Token login must not bypass Fortify 2FA; reject confirmed 2FA accounts until
  the API challenge flow is implemented.

## Environment Variables

Document required variables in the app repo, not in this template.

Use placeholders only:

```text
SERVICE_API_TOKEN=replace-with-token
DB_HOST=127.0.0.1
DB_DATABASE=calidad_software
```
