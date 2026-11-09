# Módulos y navegación

El panel organiza accesos por procesos y permisos efectivos. Ocultar enlaces no sustituye la autorización de rutas. No calcula ventas ni certifica la salud del servidor.

## Acceso y administración

La cuenta debe estar activa. Las cuentas iniciales que lo requieren cambian la contraseña antes de operar. Usuarios y Roles y Permisos están restringidos a Administrador. Los otros roles conservan sus autorizaciones configuradas. No publicar credenciales iniciales en documentación o videos.

## Clientes, catálogo y pedidos

El catálogo conserva categorías, productos, variantes/aromas y presentaciones con identidad propia de inventario. Los pedidos nuevos se venden exclusivamente por unidades físicas enteras positivas, sin exigir múltiplos ni comprobar líquido disponible al confirmar. El servidor utiliza el precio unitario del SKU y guarda su fotografía; no confía en importes enviados por el navegador. Si falta un precio unitario, debe definirlo Operaciones en el catálogo: no se deduce de un empaque antiguo.

Las agrupaciones opcionales se definen en el formulario del pedido. No crean otro producto ni otro precio. Se pueden copiar y modificar individualmente, mezclar variantes compatibles del mismo producto y presentación física, indicar paquetes expresamente incompletos y dejar unidades sueltas. El editor muestra un paquete a la vez. No se crean combinaciones de aromas en el catálogo.

Los pedidos normales anteriores conservan modalidad, cantidades, tamaños y precios pactados. Las líneas antiguas sin un SKU verificable quedan para revisión histórica: no crean demanda ficticia ni se convierten automáticamente. La modalidad comercial surtida, sus rutas y su editor fueron retirados. Los hechos físicos ya registrados se conservan en referencias históricas independientes para entregas y retornos.

Operaciones edita con motivo y control de versión durante la atención. Reducir retira obligación comercial; dejar unidades en espera mantiene una obligación pendiente. Cambiar aroma implica retirar o reducir el SKU anterior y añadir otro SKU autorizado, conservando ambas fotografías. Las unidades ya despachadas se rectifican mediante incidencias y retornos, sin reescribir su salida. Tras el cierre físico no se agregan arbitrariamente nuevas salidas al mismo pedido.

En históricos pendientes, las cantidades comerciales se valoran con el precio pactado. Si una cantidad física no corresponde a ese modelo, la pantalla ofrece un ajuste explícito de unidades físicas e importe revisado a Administrador u Operaciones autorizados. Conserva el importe original, el nuevo, el autor y el motivo; no modifica pagos ni convierte automáticamente precios.

Las notas mantienen dos pedidos por hoja A4 horizontal y anexos legibles de agrupaciones logísticas. Los resultados actuales y evidencias de impresión se detallan en [el informe del PR45](pr45-correccion-flujo-ventas-y-eliminacion-surtidos.md).

## Preparación e inventario

En Preparación, despacho y entrega se consultan pedidos y operaciones autorizadas. La Lista General mantiene juntas las presentaciones de cada producto, de menor a mayor capacidad física, sin bloquear otro orden de trabajo.

Distingue demanda pendiente, cantidades en espera, demanda activa, preparación física confirmada, stock por SKU y producción registrada en el período indicado. La producción no se suma otra vez al stock: ya generó su entrada. Los despachados, entregados o cancelados no se convierten automáticamente en nueva demanda.

El formulario integrado de envasado reutiliza el servicio existente. Filtrar e imprimir no genera producción. Disponibilidad, Movimientos y Stock y alertas muestran existencias, historia y mínimos; los ajustes requieren autorización y motivo. No se inventan movimientos para igualar saldos.

## Envasados

Ver [documentación propia](envasados.md). Es producción física, distinta de la preparación individual y la auditoría.

## Distribución y retornos

Cada despacho nuevo registra una salida parcial o completa con identidad propia, paquetes seleccionados y unidades sueltas. Sus líneas físicas, precios y distribución son inmutables; una agrupación ya enviada no se envía otra vez. Cada operación descuenta una vez los SKU físicos. Una insuficiencia revierte toda la operación transaccional. El despacho normal histórico conserva sus reglas; entregas y retornos consultan las referencias físicas verificables sin depender de las seis tablas retiradas.

La entrega diferencia aceptadas, rechazadas y pendientes y admite resultados sucesivos dentro de lo despachado. Rechazar no devuelve stock. La recepción distingue aptas, dañadas, en revisión y no recuperadas. Solo una aprobación autorizada reincorpora aptas efectivamente recibidas. Se permiten retornos/aprobaciones parciales sin duplicar entradas. No se devuelven automáticamente materiales de envasado.

Producción/Reparto registra resultados y retornos con permiso; no aprueba reincorporaciones. Operaciones Comerciales y Administrador aprueban con autorización. La rectificación de resultados exige Administrador, motivo y auditoría. Las diferencias económicas son información de revisión, no reembolsos automáticos.

## Seguimiento y auditoría

Seguimiento consulta estados, versiones comerciales, cambios frente a la solicitud original, historial e incidencias con seguimiento.ver. Auditoría tiene además restricción de rol; ver [Auditoría](auditoria.md).

## Indicadores y quince exportaciones

| Reporte | Alcance |
| --- | --- |
| Comercial | Líneas comerciales e importes históricos. |
| Composiciones | Paquetes, versiones y componentes. |
| Situación de entregas | Acumulados verificables por identidad física. |
| Preparación | Demanda pendiente, stock y faltante conocido. |
| Físicas | Unidades por SKU, separadas de paquetes comerciales. |
| Pendientes | Composición sin distribuir, sin aromas inventados. |
| Entregas | Resultados registrados en el período. |
| Retornos | Recepciones físicas y clasificación. |
| Aprobaciones | Reincorporaciones autorizadas. |
| Envasados | Producción y efectos de correcciones. |
| Materiales | Consumos y compensaciones detallados. |
| Materiales resumen | Agregados por material y destino. |
| Movimientos | Variación física de cada movimiento. |
| Saldos período | Inicial conocido, variación, final y continuidad. |
| Inventario | Stock actual; no reconstrucción histórica. |

Pantalla y CSV comparten filtros y fuentes. Cada reporte indica su alcance; no todos admiten todos los filtros. Fechas en Lima. Los CSV incluyen BOM UTF-8, punto y coma, período y alcance; neutralizan fórmulas en textos.

Permisos independientes: indicadores.ver, indicadores.economicos.ver e indicadores.exportar. Consultar no autoriza exportar ni ver importes. La asignación prevista es Administrador y Operaciones Comerciales; Producción/Reparto no los recibe por defecto.

Envasados/materiales filtran por SKU histórico de la operación; Inventario usa la identidad actual. «Concordante» exige balance agregado y continuidad; no certifica hechos físicos ausentes. Los importes no acreditan cobros y las referencias no verificables no se sustituyen por cero.
