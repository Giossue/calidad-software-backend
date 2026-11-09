# Tutorial del sistema

## Problema

Los usuarios nuevos no saben dónde está cada módulo ni para qué sirve cada
botón, filtro o tabla de las pantallas.

## Usuarios

Todos los roles: administrador, coordinador de carrera, coordinador de
titulación, docente y estudiante.

## Requisitos funcionales

- El frontend presenta guías con driver.js:
  - **Recorrido general**: menú lateral, módulos de la cuenta y herramientas de
    la cabecera.
  - **Guía por pantalla**: explica los elementos de cada pantalla y sub-pantalla
    (por ejemplo, cada pestaña de una tutoría del docente).
  - **Tutorial práctico del docente** (solo cuentas de docente, desde el botón
    de ayuda): el trabajo de cada clase en cuatro pasos (ver tutorías
    asignadas, tomar asistencia, registrar contenido, y registrar calificaciones
    y configurar horarios). No se presenta automáticamente; se registra como
    `teacher-guide`.
- El recorrido general se presenta la primera vez que el usuario entra, después
  de completar su perfil. Al terminarlo sigue la guía de la pantalla actual.
- La guía de cada pantalla se presenta la primera vez que el usuario la visita.
- El botón de ayuda (?) de la cabecera abre la guía de la pantalla actual o el
  recorrido general en cualquier momento.

## Reglas

- Terminar o cerrar una guía cuenta como vista; no vuelve a presentarse sola.
- Se guarda por usuario en el servidor, así que no se repite al cambiar de
  equipo o navegador.
- Solo se explican los módulos y elementos visibles para la cuenta.

## Datos

- `usuario.completed_tours` (JSON, nulo): identificadores de las guías vistas,
  por ejemplo `general`, `users` o `teacher-tutorings/grades`. Máximo 100.
- `UserResource.completed_tours` (lista de textos).

## API

- `POST /api/v1/auth/tours/complete` con `{ "tour": "<identificador>" }`:
  registra la guía como vista y devuelve el usuario. Es idempotente. El
  identificador admite minúsculas, números y guiones, con una sola `/` opcional
  (máximo 80 caracteres). Requiere sesión y perfil completo.
