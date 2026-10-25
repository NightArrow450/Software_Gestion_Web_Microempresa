# Auditoría de acciones

Auditoría y Envasados son módulos independientes. Auditoría permite consultar acciones sensibles; no producir ni corregir inventario.

## Acceso y contenido

Requiere autenticación, rol Administrador y auditoria.ver. Tener solo ese permiso con otro rol no basta.

Muestra fecha en Lima, ID del responsable, acción, entidad/identificador, detalle permitido e IP. La pantalla identifica al usuario por ID, no por una relación de nombre completo. Filtros: usuario, acción, entidad y fechas; paginación de 20.

## Acciones registradas

Los puntos existentes abarcan operaciones sensibles de usuarios/roles/permisos, ajustes, cancelaciones y operaciones de producción, configuración, composición e incidencias que invocan el servicio. No significa que todo clic o lectura se audite.

Ejemplos: configuración de materiales creada/modificada/activada/desactivada, envasado registrado y envasado corregido. Pedidos, composición y entregas conservan además historiales/eventos propios.

RegistroAuditoria usa la identidad autenticada y una lista de campos permitidos. No debe almacenar contraseñas ni hashes. Los registros son inmutables; no hay edición ni borrado desde la pantalla.

Los eventos de autenticación y seguridad también usan el canal de logs de seguridad. No todos ellos son filas del módulo Auditoría.

## Diferencia respecto de movimientos

Auditoría explica quién realizó una acción sensible, sobre qué entidad y con qué detalle permitido. Movimientos explica cantidades físicas, origen y saldos anterior/nuevo. Ninguno sustituye al otro. Para investigar, relacionar operación, entidad, responsable y fecha, sin modificar datos para hacerlos concordar artificialmente.

En videos usar solo la instancia ficticia. No exponer logs privados, cookies, tokens, credenciales ni IP/correos innecesarios.
