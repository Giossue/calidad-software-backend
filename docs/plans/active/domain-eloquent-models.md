# Modelos Eloquent del dominio

## Objetivo

Representar las 28 tablas académicas mediante modelos Eloquent compatibles con
el esquema PostgreSQL desplegado y con la autenticación existente.

## Criterios de aceptación

- Cada tabla de negocio tiene un modelo con tabla, clave primaria, fillable y
  casts explícitos.
- Las relaciones declaran tipos de retorno y las claves foráneas físicas.
- Todas las relaciones de identidad utilizan `App\Models\Usuario`; no existe un
  segundo modelo autenticable para `usuario`.
- La tabla de unión `ciclo_periodo` protege actualizaciones y eliminaciones con
  ambas columnas de su clave compuesta.
- No se eliminan migraciones requeridas ni se actualizan dependencias fuera del
  alcance.
- Migraciones desde cero, Pint, PHPStan y PHPUnit finalizan correctamente.
