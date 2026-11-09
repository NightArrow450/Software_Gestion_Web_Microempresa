# Envasados y configuración de materiales

Registra producción física por presentación. No controla mezclas, litros en bidones ni recetas de líquidos. Tampoco reserva unidades para un cliente.

## Permisos existentes

| Operación | Administrador | Operaciones Comerciales | Producción/Reparto |
| --- | --- | --- | --- |
| Consultar | Sí | Sí | Sí |
| Configurar, editar, activar/desactivar materiales | Sí | Sí | Sí |
| Registrar producción | Sí | No por defecto | Sí |
| Reportar incidencia | Sí | Sí | Sí |
| Corregir/anular mediante compensaciones | Sí | No por defecto | No por defecto |

Refleja EnvasadoPermisoSeeder; la autorización efectiva depende de los permisos actuales y no se modifica al documentarlos. No ejecutar el seeder para grabar videos.

## Configuración y registro

### Operación habitual: unidades terminadas

El control numérico de materiales está **desactivado por defecto**. No se requiere configurar alternativas ni registrar existencias de envases, etiquetas o contratapas. No se controlan litros ni químicos. No se inventan consumos ni movimientos de insumos.

1. Abrir Envasados → Registrar, o Registrar envasado desde Preparación.
2. Seleccionar la presentación física (incluido su aroma cuando corresponda).
3. Indicar las unidades realmente terminadas; el pedido y las observaciones son opcionales.
4. Revisar y confirmar. Se registra una única entrada de producto terminado.

Puede ser anticipado, sin pedido, o vinculado a un pedido pendiente admitido por el flujo existente. Se permiten parciales sucesivos. Vincular no cambia estados ni reserva stock. La Lista General permite registrar sin abandonar la pantalla según permiso.

Ejemplo: si se solicitan 54 unidades y se confirman 32 envasadas, ingresan **32 unidades**; las otras 22 continúan pendientes. Una incidencia o un ajuste comercial requiere su propio registro auditado. No se supone que exista líquido suficiente ni se completa producción automáticamente.

RegistrarEnvasado aumenta terminado en una transacción, con idempotencia, bloqueos y origen único. No registrar otra entrada manual por la misma producción. El servidor decide el modo; un campo manipulado no habilita ni evita el control exacto.

### Opción administrativa de materiales exactos

Solo el administrador **del entorno** puede habilitar expresamente `ENVASADO_CONTROL_MATERIALES_EXACTO=true`, después de contar y validar existencias físicas y configuraciones reales. La opción del servidor no cambia los roles ni se habilita desde el formulario de producción. La instalación habitual no necesita esta variable. No se modifica el `.env` operativo al instalar esta adaptación.

En ese modo se mantienen la alternativa activa compatible, los consumos configurados por unidad, la vista previa y el rechazo por falta de material. La configuración tiene versión; las ediciones obsoletas se rechazan. Contratapas y compatibilidades son expresas, sin deducciones por nombre o color. Las mangas no se descuentan automáticamente. No hay recetas ni cantidades precargadas.

Con el modo desactivado, los saldos anteriores de insumos se conservan como **registros históricos sin verificación física**, no como existencias exactas actuales. No se generan alertas numéricas de materiales; sí siguen las de productos terminados. Inventario y su CSV identifican esta diferencia mediante `control_existencias`. No se agrega un sistema nuevo de avisos manuales en esta adaptación.

## Correcciones y reportes

Reportar comunica una incidencia sin modificar por sí solo stock. Corregir requiere otra autorización:

- Error administrativo: compensar lo registrado erróneamente y devolver solo materiales realmente no utilizados o recuperados.
- Pérdida física: registrar la pérdida real, sin devolución ficticia de materiales.
- Conservar originales, responsable, motivo y movimientos compensatorios. No borrar historia.

Un envasado sin consumos no solicita devoluciones de materiales. Los envasados históricos con consumos conservan sus reglas y exigen declarar cada devolución, incluso cero, aunque el modo actual esté desactivado.

Se respetan cantidades registradas, stock disponible y concurrencia. No recuperar un material consumido únicamente por cambiar una cifra administrativa.

## Consulta y relación con inventario

Listado por pedido/fechas; detalle con presentación histórica, consumos, correcciones y neto acumulado vinculado cuando corresponde. La producción general no equivale a demanda pendiente.

El despacho consume terminado, no otra vez envases/etiquetas. Los retornos tampoco restituyen automáticamente insumos. Las acciones sensibles van a Auditoría; las variaciones físicas, a Movimientos. Consultar e imprimir no registra producción.

## Actualización y validación técnica

La migración `2026_10_31_222327_permitir_envasados_sin_configuracion_materiales.php` permite NULL únicamente en la referencia a configuración de `envasados`, conservando su clave foránea. No agrega tablas ni modifica filas históricas. No ofrece reversión automática a NOT NULL porque invalidaría los nuevos registros sin materiales.

Se mantienen los permisos, transacciones, auditoría, token de operación, validación de unidades y bloqueo de stock. La adaptación se comprueba en GitHub Actions con SQLite y MySQL 8.4.3 temporales, incluidos reenvíos simultáneos, correcciones y el flujo 54→32→despacho→retorno autorizado. Las suites completas no se ejecutan en la laptop ni en sus bases operativas.
