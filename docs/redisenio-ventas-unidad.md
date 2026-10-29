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

## Corte de trabajo del 29 de octubre de 2026

**Borrador incompleto: no fusionar ni aplicar sus migraciones.** El esquema y
los servicios nuevos tienen pruebas focalizadas SQLite, pero todavía no cuentan
con la validación MySQL, de concurrencia ni visual requerida para su aprobación.
No se ha modificado ninguna de las dos bases protegidas.

### Avance implementado

- Archivo histórico de surtidos paginado, sin formularios repetidos por SKU.
- Formulario nuevo con búsqueda paginada, venta por unidades, agrupaciones
  logísticas opcionales y precio unitario validado por el servidor.
- Dos migraciones aditivas propuestas: seis tablas para versiones comerciales,
  agrupaciones, preparación real y despachos parciales. Conservan los índices
  anteriores y agregan una referencia independiente por despacho y SKU.
- Versiones comerciales inmutables, retirada lógica, motivo y bloqueo de
  versiones para los pedidos nuevos. No se adoptan ni convierten históricos.
- Preparación parcial, reutilización del formulario de envasado existente y
  despachos por operaciones distintas, sin retorno automático de inventario.
- Adaptación inicial de entregas, recepciones, aprobaciones e indicadores para
  consultar los hechos físicos de cada despacho y la demanda residual.

Estas capacidades no equivalen al cierre de todos los requisitos del rediseño.

### Decisión comercial pendiente

Para editar pedidos históricos todavía no despachados, es necesario decidir
entre mantener su modelo y precio pactados por empaque o permitir una adopción
explícita del modelo por unidades con revisión de cantidades y precios. No se
puede deducir silenciosamente un precio unitario de un paquete histórico.
La interfaz histórica de edición conserva por ahora sus restricciones previas;
no satisface todavía la edición durante todo el ciclo de vida.

### Resultados ejecutados

Comandos ejecutados en el checkout independiente, sin `.env` operativo:

```powershell
php -d auto_prepend_file=tests/bootstrap.php artisan test --compact
php -d auto_prepend_file=tests/bootstrap.php artisan test --compact --filter='PedidoUnidadesTest|ArchivoSurtidosTest|MejoraBuscadorPedidosTest|EstadoPedidoTest'
php -d auto_prepend_file=tests/bootstrap.php vendor/bin/phpstan analyse --memory-limit=1G --no-progress
php -d auto_prepend_file=tests/bootstrap.php vendor/bin/pint --dirty --format agent
node --test tests/js/*.test.cjs
npm run build
composer audit --locked --no-interaction
npm audit --json
```

- Laravel completo: 645 escenarios, 597 aprobados, 48 fallidos en el último
  corte completo registrado. No se eliminan, omiten ni silencian esos fallos.
- Fallos agrupados: EmpaqueVariablePorLineaTest (22), SurtidosTest (19),
  FlujosEnvasadoCompletoTest (3), ProcesarPedidosTest (1), RegistroPedidosTest
  (1) y ValidacionPedidosTest (2). Incluyen contratos antiguos de registro por
  empaque/surtido, despacho total y validaciones de edición. Requieren revisar
  cada escenario y preservar su cobertura histórica con fixtures apropiados;
  no se consideran resueltos por cambiar la regla comercial de altas nuevas.
- Focalizadas: 35 aprobadas, 312 aserciones. El archivo nuevo PedidoUnidadesTest
  aporta 21 escenarios; la cobertura focalizada no reemplaza la suite completa.
- JavaScript: 37 aprobadas, incluidas nueve del formulario nuevo.
- Larastan nivel 5: cero errores; Pint: aprobado sobre los PHP modificados.
- Vite: compilación aprobada. npm audit: cero vulnerabilidades reportadas.
- Composer audit: falla por symfony/mime 7.4.19, aviso de gravedad media
  CVE-2026-45070 / PKSA-wtxr-p26d-nn42. No se modificaron dependencias. Se
  requiere analizar y autorizar una actualización mínima por separado conforme
  a las reglas del proyecto; no se excluye el aviso para conseguir verde.
- Primer intento MySQL 8.4.3 en GitHub: 68 errores al preparar el esquema.
  El nombre automático del índice de agrupación/detalle superaba el límite de
  MySQL. Se reemplazó por `agrupacion_detalle_unico` en la migración nueva,
  sin modificar migraciones históricas. El segundo intento descubrió una mezcla
  de collations al comparar el identificador textual de origen con su conversión
  numérica. Se usa comparación binaria exacta en el CHECK MySQL sin alterar el
  charset de las columnas existentes. Se agregan dos escenarios MySQL para
  duplicación concurrente de identidad y parciales con bloqueo de versión.
  La repetición del trabajo queda **PENDIENTE DE EJECUCIÓN**.
- Selenium, revisión visual móvil y paginación de impresión: **PENDIENTE DE
  EJECUCIÓN**. Los cambios locales Selenium del checkout original se conservan.
- GitHub Actions: consultar los trabajos del último commit del borrador; no
  trasladar resultados de PR anteriores ni considerar aprobada esta rama.

Medición focalizada del archivo de surtidos con datos ficticios SQLite: seis
consultas, 12 301 bytes de HTML, aproximadamente 108 ms y 56 MiB de pico PHP.
No existe una medición previa comparable ni un ensayo MySQL de carga de esta
pantalla; estos datos no demuestran todavía una mejora porcentual.

### Integraciones y verificaciones todavía pendientes

1. Resolver y probar el tratamiento de edición histórica sin conversiones
   silenciosas ni borrado de detalles normales existentes.
2. Completar las 46 situaciones exigidas, especialmente rectificaciones,
   espera motivada, faltantes e incidencias, agrupaciones afectadas por cambios
   y protección de todas las operaciones físicas previas.
3. Conservar la distribución logística exacta de paquetes y unidades sueltas
   por despacho parcial; la referencia de versión comercial no basta para
   identificar qué agrupaciones salieron físicamente en cada operación.
4. Mostrar las sustituciones a reparto, completar seguimiento, catálogo,
   desglose logístico de preparación y referencias de despachos en movimientos.
5. Completar la presentación y filtros de indicadores/CSV, distinguir claramente
   históricos, agrupaciones y hechos físicos, sin duplicaciones económicas.
6. Validar las dos migraciones con datos históricos ficticios sobre MySQL
   independiente, incluidos triggers, restricciones, valores NULL, concurrencia
   y fallos atómicos. No proponer su ejecución sobre Laragon antes de ello.
7. Corregir las regresiones, ejecutar todas las suites, revisar impresión y
   móviles, actualizar la evidencia y los guiones tras el cierre funcional.

No se agregaron permisos ni seeders, no se cambiaron credenciales ni se aplicó
ninguna migración sobre la base real. El borrador no está listo para uso
empresarial ni para reemplazar los videos del flujo anterior.
