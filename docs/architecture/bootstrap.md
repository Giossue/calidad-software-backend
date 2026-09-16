# Bootstrap del backend

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Configura PostgreSQL, `FRONTEND_URL` y `CORS_ALLOWED_ORIGINS` antes de probar la
SPA. Los cambios se implementan verticalmente mediante migración, modelo, Form
Request, Policy, Action, Controller/Resource y pruebas según corresponda.
