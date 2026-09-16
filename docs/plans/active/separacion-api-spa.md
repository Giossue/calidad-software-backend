# Separación Laravel API y React SPA

## Objetivo

Separar el monolito actual en un backend Laravel y una SPA React desplegables en
dominios distintos, preservando autenticación, autorización y componentes UI.

## Fase 1 — Base de la API

- [x] Instalar y configurar Sanctum para tokens Bearer.
- [x] Publicar CORS mediante una allowlist de entorno.
- [x] Crear endpoints `/api/v1/auth/login`, `/api/v1/auth/logout` y
  `/api/v1/auth/user`.
- [x] Añadir validación, recursos JSON, rate limiting y pruebas de feature.
- [x] Documentar variables de entorno y contrato de autenticación.

## Fase 2 — Base de la SPA

- [x] Inicializar React 19, Vite, TypeScript y Tailwind CSS 4.
- [x] Inicializar shadcn respetando el sistema de componentes existente.
- [x] Crear cliente HTTP centralizado y configuración por entorno.
- [x] Implementar el flujo de login y sesión inicial.
- [x] Añadir pruebas del frontend, lint, typecheck y build.

## Fase 3 — Migración funcional

- [x] Migrar registro, recuperación, verificación y desafío 2FA con contratos
  probados.
- [ ] Diseñar passkeys/WebAuthn para dominios raíz distintos; permanecen
  deshabilitadas hasta contar con pruebas de navegador para el origen real.
- [ ] Migrar perfil y administración de 2FA; el dashboard base ya está separado.
- [x] Retirar Inertia, Wayfinder y recursos frontend del backend.
- [x] Ajustar Docker/Dokploy para dos despliegues independientes.
- [ ] Actualizar documentación y ejecutar pruebas de extremo a extremo.

## Criterio de corte

No se elimina un flujo del monolito hasta que su equivalente API/SPA funcione,
tenga pruebas y pueda desplegarse con rollback independiente.
