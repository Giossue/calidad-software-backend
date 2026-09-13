# Arquitectura frontend

## Rol de React e Inertia

React presenta e interactúa; Laravel conserva la autoridad sobre datos, permisos
y reglas. Las páginas en `resources/js/pages` reciben props tipados mediante
Inertia y componen layouts y componentes. No se crea una capa REST/fetch paralela
para operaciones que encajan en visitas o formularios Inertia.

## Organización

- `pages/`: destinos asociados a rutas; ensamblan y conectan formularios.
- `layouts/`: shell, navegación y layouts persistentes.
- `components/ui/`: primitivas visuales sin conocimiento del dominio.
- `components/`: patrones compartidos de aplicación.
- `features/<module>/`: composiciones, hooks y tipos propios del módulo cuando el
  volumen justifique agruparlos.
- `types/`: contratos globales y props compartidos.

No organices todo en carpetas `atoms/molecules/organisms`. Conserva la idea
atómica —pieza pequeña, enfocada y componible— cerca de quien la usa.

## Escala de reutilización

1. Elemento HTML semántico o primitiva existente.
2. Componente UI compartido: Button, Input, Dialog, Badge.
3. Composición compartida: FormField, DataTable, EmptyState, ConfirmDialog.
4. Bloque de módulo: TopicReviewForm, TrackingTimeline.
5. Página Inertia: obtiene props y orquesta los bloques.

Extrae al nivel compartido cuando el contrato sea estable o haya un segundo uso
real. Dos componentes parecidos con reglas diferentes no se fuerzan dentro de
una API llena de flags.

## Reglas de componentes

- Props pequeñas, explícitas, readonly y tipadas; usa uniones discriminadas para
  estados mutuamente excluyentes.
- Render puro: no mutar props/estado ni producir efectos durante render.
- Prefiere composición (`children`, slots concretos) sobre docenas de opciones.
- Estado local solo para interacción efímera. Estado compartible va en URL; datos
  persistentes vienen de props Inertia/Laravel.
- Evita `useEffect` para derivar valores que pueden calcularse en render.
- Memoiza únicamente después de medir un render costoso.
- No propagues un prop por capas que no lo usan; reestructura la composición antes
  de crear contexto global.

## Formularios y navegación

- Usa `useForm` o `<Form>` de Inertia, con validación definitiva en Form Requests.
- Conserva valores y muestra errores de servidor junto al campo.
- Deshabilita doble envío con `processing` y muestra resultado con flash/toast.
- Usa `<Link>` para navegación Inertia y Wayfinder para rutas tipadas.
- Búsqueda, filtros, orden y paginación compartibles viven en query string.
- Comparte pocos props globales y con namespace; todos viajan en cada respuesta.

## Rendimiento y accesibilidad

- Props mínimos y consultas paginadas; partial/deferred props para datos pesados
  cuando una medición lo justifique.
- Divide código pesado por página/feature; reserva dimensiones en skeletons.
- Semántica HTML, labels, foco visible, teclado, anuncios de error, contraste y
  reducción de movimiento son obligatorios.
- Prueba carga, vacío, error, sin permiso, textos largos, móvil y tema oscuro.

Aplica además `component-system.md`, los documentos de patrones de interfaz y
`../quality/frontend-checklist.md`.
