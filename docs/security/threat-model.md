# Threat Model

## Assets

- User data.
- Auth sessions.
- Database credentials.
- External provider tokens.
- Generated files and exports.

## Trust Boundaries

- Browser to Laravel/Inertia application.
- Laravel application to PostgreSQL.
- Laravel application/job to external provider.
- Background job runner to database.
- CI/deployment to production environment.

## Threats

| Threat                          | Impact                                | Mitigation                                                              |
| ------------------------------- | ------------------------------------- | ----------------------------------------------------------------------- |
| Token leakage                   | Provider abuse                        | Keep tokens server-side and out of logs.                                |
| Unauthorized data access        | Privacy breach                        | Enforce auth middleware and permission checks.                          |
| Unvalidated bulk input          | Data corruption                       | Validate, chunk, and report progress.                                   |
| Hand-rolled authentication      | Account compromise                    | Use Fortify, session middleware and documented policies.                |
| Horizontal privilege escalation | Exposure of another student's process | Authorize each record with Policies, not only roles or hidden controls. |
| Invalid lifecycle transition    | Corrupt academic history              | Enforce explicit transitions, transactions and database constraints.    |
| Overprivileged database user    | Production data loss                  | Reduce privileges after migrations.                                     |
| External outage                 | Workflow failure                      | Normalize errors and expose retry-safe states.                          |
