# Rediseño de ventas por unidades físicas

## Estado de implementación

Trabajo incremental desde `origin/develop` (`6f17cb3`), en la rama
`feature/redisenio-ventas-unidad-pedidos-envasado`. No aplicar esta rama a la
base operativa hasta completar las pruebas y autorizar sus migraciones.

## Diagnóstico inicial

- `PedidoController` calcula líneas normales por unidad o por empaque. La
  edición solo admite registrados u observados y puede borrar detalles normales.
- `GestionarSurtidos` conserva composiciones versionadas y genera proyecciones
  físicas. Es necesario conservarlo para pedidos anteriores, no convertirlos.
- `SurtidosController::index` carga todos los formatos y todos los SKU. Blade
  repite todos los SKU dentro de cada formulario: el HTML crece como formatos
  multiplicados por SKU. Ocultar opciones con JavaScript no reduce ese trabajo.
- `RegistrarEnvasado` ya registra consumo e ingreso dentro de una transacción;
  la lista general ya permite invocarlo en un formulario integrado. Se reutiliza.
- `DescontarInventarioPorDespacho` descuenta el pedido completo. Sus referencias
  y las restricciones de origen presuponen un despacho por pedido y SKU.
- `ControlEntregaPedido` fotografía el despacho y alimenta entregas, rechazos,
  recepciones y aprobaciones. Esos hechos no deben reconstruirse con detalles
  comerciales que luego fueron modificados.
- Los indicadores separan precios comerciales y cantidades físicas; todos los
  cambios deben mantener esa separación y actualizar demanda residual.

## Diseño de coexistencia

Los pedidos existentes mantienen su interpretación original. Los nuevos tendrán
una identificación explícita del modelo de unidades. Cada detalle comercial
representará un SKU, una cantidad física positiva y su precio unitario histórico.
Agrupar no crea otra venta ni otro producto de inventario.

Las versiones del pedido conservarán cabecera, líneas y agrupaciones completas,
autor, fecha y motivo. Retirar no significa borrar. Las agrupaciones referencian
líneas de esa versión; el remanente no agrupado se identifica como suelto. La
suma asignada nunca puede superar la cantidad vigente de una línea. No se
mezclan producto, capacidad, unidad de medida o envase incompatibles.

El aumento de una línea conserva su precio pactado; un SKU nuevo utiliza el
precio unitario vigente del catálogo y guarda su fotografía. Los SKU sin precio
unitario válido necesitan corrección comercial del catálogo, no precios
deducidos de empaques antiguos.

## Atención parcial e inventario

Preparación registra hechos, cantidades e incidencias. Envasar no reserva ni
duplica existencias y nunca supone que hay líquido disponible. El trabajador
confirma la producción física y elige una configuración de materiales existente.

Cada despacho parcial necesita identidad propia, detalle físico inmutable,
precio histórico y movimiento único por operación y SKU. Los resultados de
entrega y retorno deben referirse a ese hecho físico, no a una versión comercial
mutable. Una edición posterior no altera salidas, entregas ni consumos.

Reducir retira obligación comercial; dejar en espera conserva obligación
pendiente sin inventar una fecha de reposición. No se marca completa una atención
que conserva cantidades sin resolver. Las sustituciones conservan su origen y
generan una instrucción visible para reparto; no exigen aprobación digital del
cliente ni generan comunicaciones.

## Orden de trabajo

1. Medir y limitar el renderizado del archivo histórico de surtidos.
2. Incorporar esquema aditivo y pruebas de conservación sobre datos ficticios.
3. Registro, edición versionada y agrupaciones en un mismo formulario.
4. Preparación, incidencias, espera y reutilización del envasado transaccional.
5. Despachos parciales y su integración con entregas y retornos existentes.
6. Impresión, indicadores, CSV, permisos y regresiones de todos los módulos.
7. Suites Laravel, MySQL independiente, JavaScript, Larastan, Pint, Vite y
   auditorías; evidencias y Pull Request sin merge automático.

## Límites de seguridad

No se ejecutan migraciones, seeders ni pruebas de escritura en
`software_gestion_web_microempresa` ni `ecolimp_respaldo_prueba`. El checkout
independiente no recibe el `.env` operativo. PHPUnit y Artisan de desarrollo
utilizan explícitamente `tests/bootstrap.php`, SQLite en memoria o una instancia
MySQL identificada y permitida. No se eliminan índices ni restricciones para
sortear la protección de despachos antiguos.

No se implementan líquidos en bidones, mezclas químicas, mensajería, nuevos
materiales de agrupación, descuentos o cobros automáticos. Los guiones de video
anteriores describen el flujo previo y necesitarán actualización después del
cierre del rediseño, no antes de verificarlo.
