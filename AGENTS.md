# Guía de trabajo para agentes

## Proyecto

Este repositorio contiene la API Laravel 13 del sistema Calidad Software. React,
Tailwind y shadcn/ui viven en `calidad-software-frontend`. PostgreSQL es la base
principal; Sanctum emite tokens Bearer y Fortify conserva las reglas y acciones
de identidad.

## Convenciones

- Mensajes para usuarios en español; clases, métodos, rutas internas y nombres
  físicos de datos en inglés.
- La API pública se versiona bajo `/api/v1` y responde JSON mediante Resources.
- Controladores delgados, Form Requests, Policies y Actions para casos de uso.
- Validación y autorización siempre en servidor; React no es una frontera segura.
- Usa Eloquent y migraciones. Constraints e índices protegen invariantes.
- Nunca expongas secretos y nunca aceptes comodines CORS en producción.
- Los tokens de acceso deben expirar y ser revocables. 2FA no puede omitirse.
- No habilites passkeys hasta implementar y probar el desafío WebAuthn específico
  para el origen del frontend desplegado en otro dominio raíz.

## Verificación

```bash
composer install
composer test
```

Para trabajo no trivial actualiza `docs/plans/active/`. No hagas commit ni push
salvo petición explícita.
