# Formularios y flujos

React administra la interacción y Laravel valida cada entrada mediante Form
Requests. La API devuelve `422` con `errors` para validación, `401` sin
autenticación, `403` sin autorización y códigos `2xx` para operaciones correctas.

Toda mutación debe ser idempotente cuando el caso de uso lo permita, usar una
transacción para varias escrituras y exponer un contrato probado.
