# CT-03: Registrar Paralelo a un Período Académico Vigente

## Objetivo

Permitir que el **Coordinador de Titulación** consulte el período académico vigente desde la base de datos y registre un paralelo (ej. "A", "B") asociado a dicho período.

## Requerimientos

1. **Período Vigente:**
   - La base de datos determina el período vigente como aquel con `estado = true` en `periodo_academico`.
   - La API expone `GET /api/v1/academic-periods/current` para que la vista del coordinador lo recupere automáticamente.
   - Si no existe ningún período activo, responde `404 Not Found` con mensaje descriptivo.

2. **Registro de Paralelo:**
   - Endpoint: `POST /api/v1/academic-periods/current/sections`.
   - Solo usuarios con rol `coordinador_titulacion` o `administrador` están autorizados.
   - Body: `{ "name": "A" }`.
   - Persiste el paralelo en `paralelo` y lo vincula al período vigente en `periodo_paralelo`.
   - Si ya está registrado en ese período, evita duplicación.

## Integridad

- Respeta al 100% el código existente de `SectionController`, `Paralelo`, `Ciclo` y sus pruebas.
- No modifica las rutas de administración preexistentes.
