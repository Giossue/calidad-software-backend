# Paralelos seleccionables por ciclo

## Objetivo

Permitir que una carrera tenga más de un ciclo en el mismo nivel (ej. "Primer
Ciclo A" y "Primer Ciclo B") sin depender de texto libre en el nombre.

## Qué cambió

- `paralelo` (tabla del baseline no versionado, sin migración hasta ahora)
  gana CRUD propio vía `/api/v1/admin/sections` — mismo patrón que Modalidad
  (catálogo global, select + crear uno nuevo al vuelo en el formulario).
- `ciclo.fk_paralelo` (nullable) reemplaza a `nombre` como diferenciador de
  ciclos repetidos. La unicidad de `ciclo` pasa a
  `(fk_carrera, numero, fk_paralelo)`.
- Dos ciclos del mismo nivel **sin** paralelo asignado no están protegidos
  entre sí (NULL no es igual a NULL en PostgreSQL). Es aceptable: paralelo
  solo hace falta cuando de verdad hay más de un grupo en ese nivel.

## Iteración previa (ya reemplazada)

Hubo un primer intento más simple: diferenciar por `nombre` en vez de crear el
catálogo `paralelo`. Se revirtió el mismo día porque comparar por texto libre
es frágil (typos, "1er Ciclo A" vs "Primer Ciclo A") y porque ya existía un
catálogo `paralelo` pensado para esto, solo que desconectado de `ciclo`.

## Estado

Implementado y probado contra Postgres real en ambos escenarios: `paralelo`
como tabla nueva (entornos limpios) y `paralelo` ya existente con datos
(simula producción). `composer test` y `npm run build`/`lint`/`test` en
verde. Falta ejecutar `php artisan migrate --force` en producción.

## Despliegue

1. Respaldo de PostgreSQL antes de migrar.
2. `php artisan migrate --force` (dos migraciones: relaja la unicidad de
   `ciclo`, luego agrega `paralelo`/`fk_paralelo`).
3. Confirmar en el panel: crear un paralelo desde el formulario de Ciclo,
   registrar dos ciclos del mismo nivel con paralelos distintos.
