# Arquitectura backend

## Principio

Laravel es un backend modular y la única autoridad sobre autenticación, reglas de
negocio y persistencia. Expone una API JSON versionada para la SPA React. Empieza
con sus convenciones y extrae una Action o servicio cuando una operación tiene
reglas, varias escrituras, reutilización o complejidad que no pertenece al
controlador. Evita una arquitectura ceremonial.

## Flujo de una solicitud

1. La ruta bajo `/api/v1` aplica `auth:sanctum`, verificación y/o autorización.
2. Un Form Request normaliza, autoriza y valida la entrada.
3. El controlador recibe datos validados y delega la mutación a una Action.
4. Una Policy/Gate comprueba capacidad y acceso al registro concreto.
5. Eloquent persiste; `DB::transaction()` delimita cambios atómicos.
6. El controlador devuelve un Resource JSON con datos mínimos y explícitos y el
   código HTTP correspondiente.

## Responsabilidades

### Controllers

- Un método por acción HTTP/resource.
- Sin consultas repetidas, reglas extensas ni manejo manual de contraseñas.
- Usa route model binding y respuestas/redirects convencionales.
- No entrega modelos completos al navegador por comodidad.

### Form Requests

- Reglas de forma, tipo, longitud, existencia y combinaciones de entrada.
- `authorize()` o Policy explícita para la acción.
- `validated()`/`safe()` como única entrada a la mutación.
- Mensajes y nombres de atributos en español cuando llegan a la UI.

### Actions y reglas

- Nombre verbal y una responsabilidad, por ejemplo `ProposeDegreeTopic`.
- Reciben valores/modelos, no objetos Request/Response.
- Protegen invariantes y transiciones; una transición desconocida se rechaza.
- Usan transacción si el caso de uso debe confirmarse o revertirse completo.
- Despachan eventos después del commit cuando un consumidor requiere datos firmes.

### Models Eloquent

- Declaran relaciones, casts, scopes y atributos derivados cohesivos.
- Protegen mass assignment con `$fillable` explícito o construcción controlada.
- Ocultan credenciales y datos sensibles con `$hidden`.
- Los estados se castean a enums; fechas/booleanos/decimales tienen casts explícitos.
- No contienen autorización, respuestas HTTP ni grandes orquestaciones.

## Lecturas y listados

- Pagina colecciones y aplica orden determinista.
- Usa eager loading para evitar N+1 y selecciona columnas necesarias.
- Filtros y columnas ordenables provienen de allowlists.
- Usa scopes pequeños para consultas reutilizables.
- Los Resources constituyen el contrato público: no serialices modelos completos.

## Errores, jobs e integraciones

- Las excepciones de dominio se traducen a mensajes seguros; el detalle va al log.
- No atrapes `Throwable` para continuar silenciosamente.
- Trabajo lento/reintentable usa Jobs idempotentes con timeout, reintentos y manejo
  de fallo; nunca se envían modelos o secretos innecesarios en el payload.
- Servicios externos se encapsulan detrás de una clase cliente usando Laravel HTTP
  Client, con timeout y errores normalizados.
