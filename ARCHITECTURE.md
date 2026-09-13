# Mapa de arquitectura

## Estilo del sistema

Monolito modular Laravel con interfaz React servida mediante Inertia. Laravel es
la autoridad para rutas, autenticación, autorización, validación, reglas de
negocio y persistencia; React renderiza interacción y presentación. PostgreSQL
es la fuente de verdad de los datos.

```text
Browser
  -> Laravel routes + middleware
    -> Form Request + Policy
      -> Controller
        -> Action / domain service
          -> Eloquent + PostgreSQL
        -> Inertia response
  <- React page + typed props
```

No hay una API y SPA independientes por defecto. Una API externa, cola, caché o
integración se añade solo cuando un requisito concreto lo justifica.

## Módulos del producto

- Identidad y acceso: usuarios, roles y perfiles.
- Configuración académica: períodos y catálogos necesarios.
- Titulación: propuestas, revisión, estado y asignación de tutor/par.
- Seguimiento: ficha, porcentaje y actividades de avance.
- Coordinación: observaciones, horarios e informes.
- Administración: usuarios, roles y datos maestros.

Cada módulo puede contener Controllers, Requests, Policies, Actions y recursos
propios. Se empieza con convenciones Laravel; se extraen capas adicionales solo
cuando reducen complejidad real.

## Directorios y responsabilidades

- `app/Models`: modelos Eloquent, relaciones, casts y scopes.
- `app/Http/Controllers`: coordinación HTTP/Inertia, sin reglas extensas.
- `app/Http/Requests`: validación y autorización de entradas.
- `app/Policies`: autorización por recurso.
- `app/Actions`: casos de uso y mutaciones reutilizables.
- `resources/js/pages`: destinos Inertia; orquestan la pantalla.
- `resources/js/components/ui`: primitivas visuales reutilizables.
- `resources/js/components`: composiciones compartidas del producto.
- `resources/js/layouts`: estructura persistente de navegación.
- `database/migrations`: fuente de verdad versionada del esquema.
- `tests/Feature`: HTTP, Inertia, auth, policies y persistencia.
- `tests/Unit`: reglas puras que lo ameriten.
- `docs`: producto, arquitectura, planes, calidad y seguridad.

## Dirección de dependencias

```text
routes -> controllers -> requests/policies -> actions -> models/database
                                  controllers -> Inertia props -> React pages
pages -> shared components -> UI primitives
```

- Las páginas no acceden a PostgreSQL ni conocen secretos.
- Los componentes UI no conocen reglas ni entidades concretas.
- Las Actions no dependen de Request, Response ni componentes React.
- Los modelos no deben convertirse en contenedores de flujos HTTP.
- Las reglas críticas se protegen tanto en aplicación como con constraints.

## Límites obligatorios

- Autenticación: Fortify/starter kit.
- Autorización: middleware, Gates y Policies en servidor.
- Entrada: Form Requests y allowlists para filtros/orden.
- Salida: props Inertia mínimos, tipados y sin atributos sensibles.
- Datos: Eloquent, migraciones, transacciones y constraints PostgreSQL.
- Cliente: componentes React puros, reutilizables y accesibles.
- Procesos lentos: Jobs idempotentes y persistidos cuando sean necesarios.

## Documentos detallados

Consulta `docs/architecture/index.md` para el índice y
`docs/product/domain-model.md` para las reglas del negocio.
