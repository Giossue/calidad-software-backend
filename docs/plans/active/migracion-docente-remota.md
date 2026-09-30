# Aplicación remota de la migración docente

## Objetivo

Aplicar `2026_09_29_000000_create_teacher_module_schema` a la base remota
`calidad_software`, a petición del usuario, con las credenciales locales autorizadas.

## Tareas

- [ ] Verificar conexión efectiva y migraciones pendientes en la base objetivo.
- [x] Comprobar acceso de ejecución: SSH rechazado; el usuario indica expresamente
      conectar directamente mediante `.pgpass`.
- [ ] Crear y verificar respaldo externo al repositorio antes de la escritura.
- [ ] Aplicar únicamente la migración docente pendiente.
- [ ] Verificar esquema, historial y permisos del rol de la API.
- [ ] Registrar resultado y evidencia sanitizada.

## Decisiones

- Las credenciales se consumen desde su configuración local autorizada sin
  mostrarlas, copiarlas a archivos nuevos ni versionarlas.
- No se ejecutan operaciones de borrado, seeders ni rollbacks automáticos.
- La autorización del usuario cubre esta aplicación de esquema; la publicación
  de versiones de backend y frontend se verifica separadamente.
- El usuario corrige expresamente el método: «no es ssh, es pgpass». Se aplica
  mediante Artisan local contra la conexión remota verificada, como excepción
  autorizada para esta operación al procedimiento habitual del contenedor Dokploy.
  Se verifican después los privilegios del rol propietario de la API.

## Verificación

En preparación.
