# Stack base

## Backend

- PHP 8.3+ y Laravel 13.
- Laravel Fortify para acciones y reglas de identidad.
- Laravel Sanctum para tokens Bearer opacos y revocables.
- Eloquent, migraciones y PostgreSQL.
- PHPUnit, Pint y Larastan/PHPStan.

## Frontend externo

React 19, TypeScript, Vite, Tailwind CSS 4 y shadcn/ui viven en el repositorio
`calidad-software-frontend`. Su URL pública se configura mediante
`FRONTEND_URL`; CORS usa `CORS_ALLOWED_ORIGINS`.

## Comandos

```bash
composer install
composer test
php artisan serve
```
