# ADR 0001: Separar Laravel API y React SPA

## Status

Accepted

## Context

El producto comenzó como un monolito Laravel/Inertia. El equipo solicitó que el
backend y el frontend vivan en repositorios y despliegues independientes, con
React consumiendo una API HTTP. Ambos servicios usarán dominios raíz diferentes,
por lo que no pueden depender de las cookies first-party exigidas por Sanctum
para su modo SPA.

## Decision

- `calidad-software-backend` será una API Laravel versionada bajo `/api/v1`.
- `calidad-software-frontend` será una SPA React/Vite independiente.
- La API usará tokens Bearer opacos de Sanctum para autenticación entre dominios.
- Fortify seguirá siendo responsable de credenciales y flujos de cuenta que
  puedan exponerse de forma segura como JSON.
- CORS aceptará únicamente los orígenes declarados en configuración.
- Inertia y Wayfinder se retiran del backend cuando los flujos equivalentes de
  identidad están disponibles y probados en la SPA.

## Consequences

- Backend y frontend se despliegan y versionan de forma independiente.
- Los contratos JSON requieren versionado, recursos y pruebas explícitas.
- Los tokens nunca se incluirán en URLs ni logs y deberán poder revocarse.
- 2FA y passkeys necesitan un diseño específico para el flujo basado en tokens;
  no se declararán migrados hasta tener pruebas de extremo a extremo.
- Passkeys permanecen deshabilitadas hasta diseñar un intercambio WebAuthn que
  funcione con el origen del frontend en otro dominio raíz sin cookies compartidas.
