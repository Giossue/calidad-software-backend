# Bootstrap del proyecto

El proyecto ya fue creado con Laravel, el starter React/Inertia y PostgreSQL.

## Instalación local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer run dev
```

Configura antes `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y
`DB_PASSWORD`. No uses credenciales de producción localmente.

## Al iniciar un módulo

1. Confirma historia, permisos, estados y criterios en ScrumBan.
2. Actualiza el modelo de dominio si aparece una regla nueva.
3. Diseña constraints e índices y crea la migración.
4. Crea modelo/relaciones/factory, Form Requests, Policy y Action cuando aplique.
5. Crea controlador y rutas con nombre; genera contratos Wayfinder.
6. Compón la página Inertia con componentes existentes y props tipados.
7. Añade pruebas feature del flujo y unitarias de reglas complejas.
8. Ejecuta la Definition of Done y archiva el plan.

Usa generadores Artisan para respetar estructura y namespaces, y revisa siempre
el código generado antes de completarlo.

## Datos iniciales

- Factories producen datos de prueba deterministas y coherentes.
- Seeders locales pueden crear demostración, pero nunca secretos reales.
- Un usuario administrador inicial se crea mediante un comando/seeder explícito,
  idempotente y seguro cuando el backlog lo pida; no por credenciales hardcodeadas.
