# Testing Strategy

## Test Pyramid

- Unit tests for pure domain logic.
- Use-case tests for application behavior.
- Integration tests for database, HTTP, auth, and external boundaries.
- End-to-end tests for critical user workflows.

## What Must Be Tested

- Validation boundaries, including the rejection paths and not only the accepted input.
- Auth and permissions, including access to a record the caller does not own.
- Error responses.
- Data persistence.
- Background jobs and cancellation.
- External service failure modes.
- Fortify authentication and protected-route behavior.
- Policies/Gates for each role and access to another actor's records.
- Eloquent relationships, casts, scopes and critical transactional writes.
- PostgreSQL migration assumptions.
- API form success, validation errors and stable JSON contracts.
- Table search, filters, sorting, pagination, column visibility, and row actions.
- Modal focus management, dismissal, scroll containment, and submission states.
- Toast semantics and retry behavior for successful and failed actions.
- Responsive layout, long text, empty collections, large collections, and dark mode.
- Permission-driven navigation and action visibility.

## Test Data

- Use model factories and deterministic fixtures.
- Avoid real secrets.
- Avoid relying on unstable external services in CI.
- Fake external calls with Laravel HTTP Client fakes behind client classes.

## Laravel testing rules

- Prefer feature tests for behavior that crosses route, middleware, request,
  policy, action and database; do not mock framework internals.
- Use `RefreshDatabase` and assert both JSON responses and persisted state.
- Every bug fix includes a regression test that fails before the fix.
- Use SQLite only for portable behavior. Run PostgreSQL-backed integration tests
  for checks, indexes, JSON/query behavior, locking or SQL specific to PostgreSQL.
- Test allowed and denied transitions, duplicate submissions and transaction rollback.
