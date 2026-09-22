# Administración de períodos y modalidades

## Goal

Exponer operaciones administrativas autenticadas para registrar, actualizar y desactivar períodos académicos y modalidades, sin modificar el esquema PostgreSQL existente.

## Tasks

- [x] Añadir validación, autorización y respuestas JSON para los dos recursos.
- [x] Añadir rutas versionadas y acciones de desactivación lógica.
- [x] Añadir pruebas HTTP aisladas del esquema de dominio no migrado.
- [ ] Ejecutar la suite localmente con PHP y dependencias Composer instaladas.

## Decisions

- La desactivación actualiza exclusivamente `estado` a `false`; no se elimina ninguna fila.
- Solo `administrador` puede operar estos recursos.
- El contrato público mantiene nombres en inglés, como el contrato de autenticación existente.

## Verification

- Pendiente: el clon de revisión no contiene dependencias Composer y sus migraciones no construyen las tablas de dominio.
