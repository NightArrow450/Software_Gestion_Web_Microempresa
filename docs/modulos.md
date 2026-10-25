# Módulos y navegación

El panel organiza accesos por procesos y permisos efectivos. Ocultar enlaces no sustituye la autorización de rutas. No calcula ventas ni certifica la salud del servidor.

## Acceso y administración

La cuenta debe estar activa. Las cuentas iniciales que lo requieren cambian la contraseña antes de operar. Usuarios y Roles y Permisos están restringidos a Administrador. Los otros roles conservan sus autorizaciones configuradas. No publicar credenciales iniciales en documentación o videos.

## Clientes, catálogo y pedidos

El catálogo conserva categorías, productos, variantes/aromas y presentaciones con identidad propia de inventario. La venta por empaque guarda el tamaño histórico por línea. Los tamaños distintos pueden ser líneas comerciales distintas. El servidor calcula los precios; no se confía en importes manipulados del navegador.

Surtidos habilita formatos y SKU compatibles, no una combinación obligatoria. Cada paquete tiene composición individual y versionada, se puede copiar y editar independientemente. La suma debe completar exactamente su tamaño. Una composición pendiente no inventa demanda por aroma y bloquea el cierre de preparación y despacho.

El precio del paquete se cuenta una vez; no se suman precios de componentes. Las notas incluyen anexo de composición. El pedido y sus cantidades originales se conservan ante incidencias.

## Preparación e inventario

En Preparación, despacho y entrega se consultan pedidos y operaciones autorizadas. La Lista General mantiene juntas las presentaciones de cada producto, de menor a mayor capacidad física, sin bloquear otro orden de trabajo.

Distingue demanda pendiente, stock por SKU y producción registrada en el período indicado. La producción no se suma otra vez al stock: ya generó su entrada. Los despachados, entregados o cancelados no se convierten automáticamente en nueva demanda.

El formulario integrado de envasado reutiliza el servicio existente. Filtrar e imprimir no genera producción. Disponibilidad, Movimientos y Stock y alertas muestran existencias, historia y mínimos; los ajustes requieren autorización y motivo. No se inventan movimientos para igualar saldos.

## Envasados

Ver [documentación propia](envasados.md). Es producción física, distinta de la preparación individual y la auditoría.

## Distribución y retornos

El despacho descuenta una vez los SKU físicos, agregando normales y componentes surtidos. Una insuficiencia revierte toda la operación transaccional.

La entrega diferencia aceptadas, rechazadas y pendientes y admite resultados sucesivos dentro de lo despachado. Rechazar no devuelve stock. La recepción distingue aptas, dañadas, en revisión y no recuperadas. Solo una aprobación autorizada reincorpora aptas efectivamente recibidas. Se permiten retornos/aprobaciones parciales sin duplicar entradas. No se devuelven automáticamente materiales de envasado.

Producción/Reparto registra resultados y retornos con permiso; no aprueba reincorporaciones. Operaciones Comerciales y Administrador aprueban con autorización. La rectificación de resultados exige Administrador, motivo y auditoría. Las diferencias económicas son información de revisión, no reembolsos automáticos.

## Seguimiento y auditoría

Seguimiento consulta estados, historial e incidencias con seguimiento.ver. Auditoría tiene además restricción de rol; ver [Auditoría](auditoria.md).

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
