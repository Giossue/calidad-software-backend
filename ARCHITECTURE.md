# Mapa de arquitectura

## Estilo del sistema

El producto tiene dos unidades desplegables: una API Laravel modular y una SPA
React. Este repositorio contiene únicamente Laravel; PostgreSQL es la fuente de
verdad y el frontend se encuentra en `calidad-software-frontend`.

```text
React SPA -> JSON /api/v1 -> middleware Sanctum
                         -> Form Request + Policy
                         -> Controller -> Action -> Eloquent -> PostgreSQL
```

## Límites

- Fortify conserva reglas y acciones de identidad; Sanctum emite tokens Bearer.
- Form Requests validan entradas y Policies autorizan registros.
- Resources definen respuestas JSON versionadas y mínimas.
- Migraciones y constraints protegen la integridad en PostgreSQL.
- El backend no compila, sirve ni importa código React.
- Los dos repositorios se despliegan y revierten de forma independiente.

La decisión y sus compromisos están en
`docs/architecture/adr/0001-separar-api-y-spa.md`.
