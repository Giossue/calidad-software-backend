# Integrations

## External Services

No external integration is currently confirmed. Add one to this table only when
a ScrumBan item defines its purpose, owner, data exchanged, failure behavior and
acceptance criteria.

| Service | Purpose                                            | Auth | Owner |
| ------- | -------------------------------------------------- | ---- | ----- |
| None    | No external provider is part of the current scope. | —    | —     |

## Integration Rules

- Tokens stay server-side.
- Use typed payloads at boundaries.
- Normalize provider errors before exposing them internally.
- Avoid provider names in user-facing UI unless necessary.
- Document rate limits and usage gates.
- Use Laravel HTTP Client for server-side external API calls.
- Do not call external provider APIs directly from the browser when tokens are required.
- Set explicit `timeout`, `connectTimeout` and bounded retry behavior.
- Use `Http::fake()` in tests and prevent stray real requests.
- Add backoff, queueing, or persisted jobs for bulk provider calls.

## Provider Template

### Provider name

- Base URL:
- Docs:
- Auth:
- Timeout:
- Rate limit:
- Usage endpoint:
- Internal route prefix:
- Error behavior:

## Usage / Quota Gates

When a provider exposes usage or quota endpoints:

- Query usage before starting bulk jobs.
- Block jobs when quota is exhausted.
- Respect provider requests-per-minute limits.
- Surface user-friendly availability state in the UI.
- Keep raw provider tokens and provider internals out of user-facing text unless needed.
