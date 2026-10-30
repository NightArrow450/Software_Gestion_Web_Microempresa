> **Antecedente del PR44 (29/10/2026), cerrado sin merge.** Este documento conserva la evidencia de aquella revisión; sus menciones de archivo de surtidos, esquema de 47 migraciones y estado de aprobación no describen la versión actual. El contrato, las migraciones y los resultados vigentes están en [el informe PR45](pr45-correccion-flujo-ventas-y-eliminacion-surtidos.md).

# PR44: ventas por unidades, históricos y atención física parcial

Estado del corte del 29/10/2026: **validación funcional y visual completada;
pendiente de revisión del PR y autorización separada para Laragon**. Las
regresiones automatizadas están corregidas y las evidencias de Selenium,
escritorio, móvil e impresión fueron revisadas. No se ha hecho merge ni se ha
modificado la base real. Véase [el informe visual y de inventario](pruebas/evidencias/redisenio_pr44/README.md).

## Modelo comercial y precios

Los pedidos nuevos se identifican con `modelo_comercial = unidades`. Cada línea
vende un SKU físico y una cantidad entera positiva, sin múltiplos comerciales.
Se conservan hasta 18 líneas y el tope de S/999 999,99 por línea; existen límites
técnicos de almacenamiento y hasta 1000 agrupaciones por versión. El servidor
calcula el importe desde el precio unitario del catálogo y guarda la fotografía.
Una edición del mismo SKU conserva el precio pactado. Un SKU añadido toma su
precio unitario actual; si falta, no se inventa ni se deduce de un precio por caja.

Los paquetes son instrucciones logísticas opcionales, sin otro precio ni otro
inventario. Se define cada composición dentro del pedido, se copia con identidad
nueva y se edita individualmente. Se permiten paquetes homogéneos, mezclas de
variantes compatibles y paquetes expresamente incompletos. No se mezclan productos,
contenidos, unidades de medida o envases incompatibles. Las cantidades asignadas
no superan las vendidas; el resto es suelto. El editor pagina de a un paquete y el
catálogo se consulta por páginas, sin descargar todos los SKU en cada formulario.

## Históricos: decisión aprobada

`modelo_comercial = NULL` conserva las reglas anteriores. Las migraciones no
convierten las líneas, sus modalidades, tamaños, precios, importes ni movimientos.
Se mantienen los surtidos históricos y sus fotografías individuales del PR38.
No existe un endpoint de alta comercial por empaque para pedidos nuevos.

La edición histórica pendiente utiliza el precio pactado por unidad o empaque,
incluso si cambió o se desactivó el catálogo. Antes del primer ajuste se guarda
una versión original 0. Cada ajuste conserva autor, fecha, motivo, cantidades,
composiciones e importes. Retirar es lógico, no borrar detalles.

Si una cantidad física no puede valorarse con el modelo anterior, Administrador
u Operaciones con permiso de edición deben introducir explícitamente las unidades
físicas revisadas **y** el importe total revisado. Se conserva el precio original
por empaque, sin dividirlo. El ajuste no cambia pagos ni comprobantes. Si el total
histórico de cabecera tenía una diferencia respecto a sus líneas, se conserva esa
diferencia; no se corrige silenciosamente. Los detalles ya despachados conservan
sus cantidades físicas; las observaciones o revisiones comerciales se auditan.

Las pruebas antiguas de empaques y surtidos ahora construyen pedidos anteriores
al rediseño y ejercitan su edición real. Se conservan los escenarios de tamaños,
precios, stock, impresión, respaldo de tamaños NULL, permisos e idempotencia.
Las aserciones no se desactivan para fingir compatibilidad mediante altas nuevas
que ya no corresponden al modelo aprobado.

## Preparación y envasado

Registrar o confirmar no comprueba líquido ni descuenta stock. Preparación declara
unidades realmente listas o incidencias de líquido, envases, etiquetas/tapas,
insumos químicos, tiempo u otra causa. Declarar preparación no genera producción,
no consume materiales y no reserva existencias para un cliente.

El formulario integrado reutiliza `RegistrarEnvasado`: consulta materiales
configurados y solo consume e ingresa producto terminado cuando el trabajador
confirma producción real. Se conservan el módulo independiente, los envasados
anticipados y los excedentes reales. No se controlan litros preparados, mezclas
ni materiales adicionales por agrupar paquetes; las mangas no se descuentan aquí.

La lista general conserva filtros, orden por producto y capacidad, impresión y
separación entre SKU. Muestra demanda aún no despachada, espera motivada, demanda
activa, preparación física vigente, stock y producción neta del período. La espera
no desaparece ni se interpreta como producción urgente. El faltante activo es
`max(0, pendientes - espera - stock)`; producir no se suma de nuevo al stock.
Las agrupaciones pendientes son un desglose informativo incluido en la demanda,
no otra necesidad adicional. Un paquete mixto puede aparecer en varios SKU.

Reducir 54 a 32 cambia la obligación vigente y conserva la solicitud original.
Dejar 22 en espera mantiene esa obligación sin prometer reposición. Las ediciones
no alteran producción ni salidas anteriores. Un cambio de aroma se registra como
reducción/retirada del SKU anterior y adición del nuevo, con motivo. Cumplimiento
y Seguimiento muestran las diferencias acumuladas frente a la solicitud original,
aunque una versión posterior solo modifique observaciones.

## Despachos, entregas y retornos

Cada salida necesita operación única, versión actual, cantidades físicamente
preparadas, stock suficiente, paquetes seleccionados y unidades sueltas. Se
conserva la composición y la capacidad de cada paquete efectivamente enviado.
Un paquete ya despachado no se vuelve a enviar ni se modifica retrospectivamente.
Para abrir o redistribuir un paquete pendiente se edita su agrupación con motivo.

Pedido, inventarios y referencias se bloquean dentro de una transacción. El
movimiento es único por salida y SKU; reenviar la misma operación no vuelve a
descontar, y reutilizarla con otro contenido se rechaza. La identidad de paquete
es no nula y única por pedido en la tabla de pertenencia al despacho, con claves
foráneas compuestas que verifican pedido y versión. Se permiten múltiples salidas
legítimas. Stock insuficiente revierte la operación completa.

Las entregas y retornos reutilizan el PR34 mediante líneas físicas inmutables de
cada salida. Se aceptan/rechazan SKU concretos, sin reescribir la línea comercial.
Rechazar y recibir no aumentan stock; una aprobación autorizada reincorpora solo
lo apto físicamente recibido. No se devuelven materiales del envasado. Un pedido
con obligación pendiente conserva `despacho_parcial`; no vuelve automáticamente
a preparación ni se marca totalmente entregado por resolver la primera salida.

Después de hechos físicos se usan incidencias y rectificaciones autorizadas.
Una atención cerrada no permite agregar arbitrariamente nuevas salidas. Roles,
permisos de aprobación/rectificación e idempotencia anteriores permanecen.

## Esquema propuesto, sin aplicar a las bases protegidas

Solo dos migraciones nuevas:

1. `2026_10_29_033324_create_versiones_y_agrupaciones_pedidos.php`: modelo comercial
   nullable, espera, ajuste físico explícito y tres tablas de versiones y grupos.
2. `2026_10_29_033326_create_despachos_parciales_pedido.php`: preparación física,
   salidas, líneas físicas y pertenencia única de paquetes al despacho. Nueva FK
   nullable e índice por salida/SKU en movimientos. Conserva restricciones antiguas.

Siete tablas nuevas: `pedido_versiones`, `pedido_agrupaciones`,
`pedido_agrupacion_detalles`, `pedido_preparaciones`, `pedido_despachos`,
`pedido_despacho_lineas` y `pedido_despacho_agrupaciones`. El resultado esperado en
una instalación aislada es **47 migraciones y 51 tablas empresariales**, sin contar
marcadores de laboratorio. No se crean permisos ni seeders de aplicación nuevos.

Triggers conservan versiones, paquetes enviados, líneas físicas y movimientos
inmutables. Las nuevas columnas admiten el estado histórico sin reinterpretarlo.
El origen de salida nueva no altera la referencia única de despacho histórico.
La migración SQLite preserva los triggers originales al reconstruir la tabla.
El `down` se detiene antes de borrar historia; no hay recuperación destructiva
automática. Una actualización real requerirá autorización, respaldo restaurado,
comparación histórica y control de escrituras en un procedimiento separado.

## Indicadores, CSV y presentación

La línea comercial cuenta su precio una vez. El desglose de grupos no añade
importes ni demanda. Los reportes físicos usan obligación residual por SKU,
separan unidades en espera y activas y reconocen ajustes históricos explícitos.
Los movimientos de salidas parciales conservan la referencia al pedido. Estados,
entregas, aprobaciones y consumo mantienen sus períodos y trazabilidad anteriores.
Los filtros incluyen `despacho_parcial`. UTF-8, separador y protección CSV se
conservan. El catálogo distingue precio unitario de referencia de empaque histórica.

Se conserva la maquetación de dos notas por A4 horizontal, con anexos adicionales
de agrupaciones y composiciones históricas. El aviso de impresión distingue hojas
de notas y anexos. La prueba real de Chrome produjo dos páginas A4 horizontales:
dos notas en la primera y un anexo en la segunda, sin hojas vacías. Las páginas
se renderizaron con Poppler y se revisaron visualmente. El anexo separa las
unidades sueltas de los paquetes para evitar confundir su pertenencia.

## Resultados reales del corte

- Laravel completo: **663/663**, **4110 aserciones**, 53,863 s. Descubrimiento:
  33 escenarios Unit, 619 Feature y 11 Security, estas últimas incluidas una vez.
- JavaScript: **45/45**, sin omitidas ni fallos.
- Larastan nivel 5: **0 errores**, sin exclusiones generales nuevas.
- Pint: aprobado sobre los PHP modificados.
- Vite 8.3.4: compilación aprobada. Conserva el aviso opcional preexistente de
  `fontaine` para optimización de fuentes; no se instala otra dependencia a ciegas.
- Composer audit y npm audit: sin vulnerabilidades. `composer validate --strict
  --no-check-publish`: aprobado. Solo `symfony/mime` cambió de 7.4.19 a 7.4.22 en
  el lockfile; corrección compatible de CVE-2026-45070, sin actualizar otros paquetes
  ni ejecutar scripts contra el entorno operativo.
- MySQL 8.4.3 independiente: repetición final **70/70**, **1165 aserciones**,
  177,284 s, sobre `dhacer_eco_limp_test_r44f_1029a`, nueva y vacía al comenzar.
  Incluye los índices únicos, datos históricos, parciales y concurrencia.
  El servidor escucha en 3347 y no contiene ninguna base protegida.
- Selenium 4.52.0 / pytest 9.2.0: **seis escenarios distintos aprobados**.
  Campaña principal: 5/5 en 334,44 s. Repetición focalizada del caso 05 y nuevo
  caso 06: 2/2 en 164,65 s. No se presentan como siete casos distintos.
- Revisión visual: escritorio 1366 px y viewports efectivos 360/390/768/1024 px
  sin scroll horizontal del documento. El modal y el editor de paquetes también
  se revisaron en 360/390 px. Tablas anchas mantienen su contenedor desplazable.
- Impresión: **dos páginas A4 horizontales**, dos notas y un anexo, sin páginas
  en blanco; PDF y capturas con datos ficticios incluidos en la evidencia.
- El bloqueo inicial de arranque se resolvió con la intervención manual del
  usuario. El primer intento Selenium detectó un selector incorrecto del propio
  guion; se corrigió y se repitió la campaña completa, sin ocultar el fallo.
- GitHub: las ejecuciones del último commit se enlazan y actualizan en el PR44.
  CI y navegador tienen evidencias separadas; los resultados se enlazan en el PR.

La repetición MySQL detectó errores de preparación del laboratorio: reutilización
de una base que debía ser nueva, referencias repetidas, límite de conexiones y
nombre demasiado largo al añadir sufijos internos de pruebas. Se conservan esos
logs; se elige otra base vacía de nombre corto y no se limpia ninguna base ni se
relajan los guardas. Las operaciones nuevas de pruebas incorporan el ID de su
pedido para no colisionar entre escenarios. No se omiten los escenarios afectados.

## Reproducción y evidencia

```powershell
php -d auto_prepend_file=tests/bootstrap.php artisan test --compact
php -d auto_prepend_file=tests/bootstrap.php vendor/bin/phpstan analyse --no-progress --memory-limit=1G
php -d auto_prepend_file=tests/bootstrap.php vendor/bin/pint --dirty --format agent
node --test tests/js/*.test.cjs
npm run build
composer audit --locked
npm audit --package-lock-only --audit-level=low
```

MySQL exige `MYSQL_TEST_ENABLE=1`, credenciales privadas y una base independiente
**nueva y de nombre corto** con prefijo `dhacer_eco_limp_test_`; ejecutar PHPUnit
con `phpunit.mysql.xml` y el bootstrap seguro. La suite crea también bases
auxiliares con ese prefijo y comprueba que no contengan tablas ajenas. No usar
`migrate:fresh`, borrados ni restauraciones sobre bases protegidas.

El laboratorio de esta revisión escucha únicamente en 127.0.0.1:3347, con directorio
de datos fuera de Laragon operativo y UUID comprobado. No contiene las bases
protegidas. La base visual separada quedó con 47 migraciones, datos ficticios y
cuenta HTTP DML limitada a esa base. Manifiesto, credenciales y logs privados
quedan fuera del repositorio. Solo se versionan evidencias seleccionadas que
contienen datos ficticios y un resumen de saldos por etapa. Ver [instrucciones Selenium](../tests/Selenium/README_redisenio.md).

## Límites y condiciones de actualización

El archivo histórico de surtidos usa paginación de 20 formatos. La medición inicial
SQLite con 46 formatos fue de 6 consultas, 12 301 bytes HTML, aproximadamente 108 ms
y 56 MiB de pico PHP. No hay una medición anterior comparable: no se afirma una
mejora porcentual ni capacidad bajo carga por esos datos. La lista general conserva
la consolidación en memoria y el verificador acumula su informe; no certifican
volúmenes ilimitados. Las pruebas de indicadores tienen su propio escenario grande;
no equivalen a un ensayo integral de carga del formulario nuevo. La medición
focalizada SQLite del formulario autenticado con 60 SKU fue de 2 consultas,
11 512 bytes, 13,4 ms y 64 MiB de pico del proceso. La paginación devuelve 50 + 10
sin incrustar todos los SKU en HTML. Es una prueba autenticada con relaciones
preparadas, no una medición fría de producción ni un ensayo de estrés. Las
mediciones generales previas hasta 300 registros conservan su limitación.

La validación solicitada del rediseño está completada. La aprobación y el merge
son responsabilidad del usuario. La aplicación de las dos migraciones sobre
Laragon requiere otra autorización, respaldo restaurado y verificación previa.
No se han inferido precios unitarios para SKU históricos que carecen de ellos:
Operaciones deberá definirlos expresamente antes de vender esos SKU en pedidos
nuevos. No hay decisiones comerciales pendientes que impidan revisar este código.

Estas pruebas no son UAT con trabajadores reales, no sustituyen impresión física,
no certifican todos los navegadores ni un nuevo ensayo de carga del rediseño.
Los guiones anteriores deben consultarse junto con el flujo actualizado de este
informe y `docs/modulos.md`; no se modifican los archivos locales de la Fase 9C.

Las bases `software_gestion_web_microempresa` y `ecolimp_respaldo_prueba`, el `.env`
operativo, las migraciones históricas y los cambios locales del checkout original
permanecen fuera de estas operaciones. No hubo merge ni despliegue. El PR44 sigue
independiente, pendiente de revisión del usuario; no se inicia otro alcance.
