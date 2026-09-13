# Stack base

Este documento describe el stack instalado. Una desviación requiere necesidad
concreta, revisión de dependencias y un ADR.

## Backend

- PHP `^8.3` y Laravel `^13.17`.
- Laravel Fortify para autenticación headless.
- Eloquent para modelos, relaciones y acceso a datos.
- PostgreSQL como base de datos principal.
- PHPUnit 12 para pruebas.
- Laravel Pint para estilo y Larastan/PHPStan nivel 7 para análisis estático.
- Jobs, eventos, notificaciones, caché y cliente HTTP nativos de Laravel cuando
  el caso de uso los necesite.

## Frontend

- React 19 y TypeScript 5.7.
- Inertia 3: navegación y contrato entre Laravel y React.
- Tailwind CSS 4 y primitivas Radix ya instaladas.
- Componentes existentes del starter kit en `resources/js/components/ui`.
- Lucide React para iconos y Sonner para feedback.
- Wayfinder para generar rutas/acciones tipadas.
- Vite Plus/Vite 8 para desarrollo, validación y build.

No se asumen React Hook Form, Zod, Axios, TanStack Table, Redux ni shadcn CLI:
no están instalados. Inertia `useForm`/`Form`, validación Laravel y el cliente HTTP
de Laravel cubren el baseline. Una dependencia nueva debe resolver una necesidad
demostrada mejor que las capacidades existentes.

## Herramientas y comandos

```bash
composer install
npm install
composer run dev
composer test
npm run check
npm run types:check
npm run build
```

- Composer es el único gestor PHP y npm el único gestor frontend.
- Se versionan `composer.lock` y `package-lock.json`.
- `.env`, `vendor`, `node_modules` y `public/build` no se versionan.

## Persistencia y configuración

- Las migraciones Laravel son la fuente de verdad del esquema.
- `.env.example` documenta variables sin secretos.
- Accede a configuración mediante `config()`, nunca con `env()` fuera de archivos
  de `config`, para que `config:cache` sea seguro.
- PostgreSQL se usa en desarrollo y producción. La suite puede usar SQLite en
  memoria para velocidad, pero toda característica dependiente de PostgreSQL
  necesita una prueba PostgreSQL en CI o integración.

## Versionado

Los números de versión de este archivo siguen a `composer.lock` y
`package-lock.json`. Actualiza el documento cuando cambie una pieza mayor del stack.
