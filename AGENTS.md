# Guía de trabajo para agentes

## Proyecto

`Calidad Software` es un sistema de control de tutorías académicas y titulación.
Es un monolito modular construido con Laravel 13, PHP 8.3+, React 19,
TypeScript, Inertia 3, Tailwind CSS 4 y PostgreSQL. Laravel Fortify administra
la autenticación y Eloquent administra la persistencia.

La interfaz y los mensajes para usuarios se escriben en español. El código,
clases, métodos, rutas internas y nombres físicos de base de datos se escriben
en inglés de forma consistente.

## Leer antes de trabajar

- Producto: `docs/product/overview.md`
- Dominio y reglas: `docs/product/domain-model.md`
- Trabajo ScrumBan: `docs/product/scrumban.md`
- Mapa técnico: `ARCHITECTURE.md`
- Stack: `docs/architecture/stack.md`
- Backend: `docs/architecture/backend.md`
- Frontend y componentes: `docs/architecture/frontend.md` y
  `docs/architecture/component-system.md`
- Base de datos: `docs/architecture/database.md`
- Definition of Done: `docs/quality/definition-of-done.md`
- Seguridad: `docs/security/principles.md` y `docs/security/hardening.md`

## Flujo de trabajo

1. Lee este archivo y el `AGENTS.md` más cercano al código que modificarás.
2. Identifica la historia o tarea ScrumBan y sus criterios de aceptación.
3. Para trabajo no trivial crea o actualiza un plan en `docs/plans/active/`.
4. Implementa el cambio vertical mínimo: migración, backend, UI y pruebas que
   sean necesarias para completar un comportamiento.
5. Actualiza la documentación cuando cambien reglas, datos, arquitectura o UX.
6. Ejecuta las verificaciones aplicables antes de declarar el trabajo terminado.
7. No hagas commit ni push salvo petición explícita.

No existe un SRS fijo. El conocimiento vigente se compone del backlog y sus
criterios de aceptación, estas reglas, el modelo de dominio, los ADR y el código
verificado. Si se contradicen, detén la implementación y registra/aclara la
decisión; no inventes una regla de negocio.

## Comandos requeridos

```bash
composer install
npm install
composer test
npm run check
npm run types:check
npm run build
```

Para desarrollo local usa `composer run dev`. Usa Composer para PHP y npm para
frontend. Conserva `composer.lock` y `package-lock.json`; no introduzcas otro
gestor de paquetes.

## Reglas de arquitectura

- Mantén un monolito modular Laravel/Inertia. No añadas una API REST separada ni
  estado de servidor duplicado en React sin una necesidad documentada en un ADR.
- Controladores delgados: coordinan Form Requests, Policies, Actions/Services y
  respuestas Inertia; las reglas de negocio no viven en controladores ni JSX.
- Usa Form Requests para validar y autorizar entradas no triviales, Policies para
  permisos por registro y transacciones para operaciones con varias escrituras.
- Usa Eloquent y migraciones. Evita SQL crudo; si es indispensable, parametrízalo,
  aísla la consulta y documenta la razón.
- Evita asignación masiva accidental, N+1 y serialización de atributos sensibles.
  Selecciona solo los props que necesita cada página y carga relaciones de forma
  explícita.
- PostgreSQL es la base principal. Los constraints e índices protegen invariantes;
  la validación de aplicación no reemplaza la integridad de base de datos.
- Fortify y las capacidades del starter kit son dueños de contraseñas, sesiones,
  verificación, recuperación, 2FA y passkeys. No implementes autenticación propia.
- Toda ruta protegida aplica autenticación y autorización en servidor. Ocultar un
  botón en React es solo presentación.
- Usa rutas con nombre y Wayfinder; no disperses URLs escritas a mano en JSX.
- Mantén secretos en `.env`; solo variables `VITE_*` pueden llegar al navegador.

## Componentes React

- Construye de abajo hacia arriba: primitivas UI, componentes compuestos, bloques
  de módulo, layouts y páginas. “Atómico” describe responsabilidad, no obliga a
  crear carpetas artificiales `atoms/molecules`.
- Antes de crear un componente, busca uno equivalente en `resources/js/components/ui`
  o `resources/js/components`. Reutiliza y compón; no dupliques tablas, modales,
  campos, badges, feedback ni paginación.
- Las páginas Inertia ensamblan componentes y reciben props tipados. Mantén los
  componentes puros; efectos y estado local solo para sincronización/interacción.
- Extrae un componente compartido cuando existe un contrato estable o un segundo
  uso real. No generalices por anticipado ni conviertas reglas del dominio en props.
- Los componentes compartidos no realizan consultas ni deciden permisos de negocio.
  Reciben datos, callbacks y estados tipados.
- Cada mutación expone estado pendiente, evita doble envío y comunica éxito/error.
- Accesibilidad, teclado, foco, contraste, móvil, estados vacío/carga/error y tema
  oscuro son parte del comportamiento, no mejoras opcionales.

## Convenciones del dominio y datos

- El diagrama entregado es conceptual. En código usa modelos singulares en
  `StudlyCase`, tablas plurales `snake_case`, PK `id` y FK `<model>_id`.
- No uses prefijos `fk_` ni columnas `id_<tabla>` en migraciones nuevas.
- Una persona tiene una sola cuenta `users`; estudiante, docente, coordinador de
  carrera, coordinador de titulación y administrador son roles/perfiles, no copias
  de credenciales.
- Usa enums PHP respaldados por string para estados de negocio. Reserva booleanos
  para hechos binarios reales como `is_active` o `is_completed`.
- Fechas académicas usan `date`; horas sin zona usan `time`; eventos/auditoría usan
  timestamps. Porcentajes tienen constraint entre 0 y 100.
- Conserva historial académico: prefiere desactivar/archivar frente a borrar cuando
  existen temas, asignaciones, fichas, observaciones, horarios o informes asociados.

## Definition of Done resumida

- Criterios de aceptación satisfechos y trazables a pruebas.
- Validación, autorización e integridad de datos cubiertas en servidor.
- Pruebas de feature para el flujo y unitarias para reglas complejas.
- Pint, Larastan/PHPStan, TypeScript, Vite y suite PHPUnit en verde.
- Sin secretos, artefactos, dependencias o abstracciones innecesarias.
- Documentación y plan ScrumBan actualizados en el mismo cambio.
