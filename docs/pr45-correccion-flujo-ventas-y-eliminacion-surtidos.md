# PR #45 — Corrección mayor del flujo comercial y retiro de surtidos

> **Repositorio existente**, no crear un proyecto Laravel nuevo. Esta rama procede del último commit del PR #44 (cerrado sin fusionar); el destino es `develop`, **nunca `main`**.

## Problema de negocio y decisión definitiva

La empresa vende **exclusivamente unidades físicas de productos**. Una agrupación es una instrucción logística opcional del pedido; no es una modalidad de venta, no suma importes adicionales y no crea un SKU, una línea comercial ni un "surtido" independiente. Las presentaciones físicas (p. ej., 3.5 L, 3.8 L o 5 L) y las variantes reales del producto sí permanecen. Los pedidos admiten cantidades enteras positivas sin exigir múltiplos por empaque.

Operaciones Comerciales **acepta pedidos sin consultar inventario, envases, insumos químicos ni litros de bidones**. Preparación/Reparto comprueba después la viabilidad física y reporta incidencias; el sistema no administra litros de mezcla ni recetas químicas de bidones. El módulo de envasado registra únicamente cantidades efectivamente producidas y sus insumos de envasado configurados, nunca consumo por la mera edición comercial ni consumo de mangas por agrupar un pedido.

### Caso obligatorio de aceptación: 54 → 32 Quitagrasa

1. Operaciones registra y confirma pedido de **54 unidades**, sin verificación previa de stock.
2. Preparación/Reparto inicia preparación y confirma físicamente **32 unidades envasadas**; se descuentan materiales **una sola vez** en esa confirmación, de acuerdo con su configuración.
3. Se agota un insumo químico. Preparación/Reparto reporta imposibilidad de completar **22 unidades**.
4. Operaciones edita el pedido: **54 → 32**, con **motivo obligatorio y usuario/fecha**, sin borrar la demanda original.
5. Se conserva versión anterior **54 unidades** y versión actual **32 unidades**; precio e importe se recalculan conforme a la modificación autorizada. La preparación pendiente se recalcula: **0 unidades** restantes para el pedido corregido. La edición no crea envasados, no consume ni restaura insumos, ni altera movimientos físicos previos.
6. Se despachan **32 unidades**, no 54, mediante salida física idempotente y con registro de responsable/fecha.
7. En entrega se explica el ajuste. Si el cliente acepta, se registra **32 entregadas**. Si rechaza una parte o la totalidad, se registra **cantidad rechazada** y **retorno físico**, sin ingreso automático a stock hasta aprobación de condición y retorno por usuario autorizado.

**Invariantes:** solicitado originalmente=54; vigente=32; producido confirmado=32; despachado=32; entregado+rechazado=32 al cerrar la entrega, si se despacharon las 32; sin stock negativo ni dobles consumos. La cantidad de retorno puede depender de la recepción real; nunca asumir que todo rechazo fue recibido de vuelta.

## Edición permitida en todos los estados

| Estado | Operación permitida | Protección |
|---|---|---|
| Confirmado | Cambiar productos, variantes disponibles, aromas y cantidades | Crear nueva versión comercial; motivo y auditoría |
| En preparación | Reducir/aumentar cantidades, cambiar variantes según disponibilidad | No borrar producción física; recalcular trabajo pendiente |
| Preparado | Ajustar unidades que realmente se enviarán | Lo envasado no se deshace por editar |
| Despachado | Realizar ajuste controlado o incidencia | No reescribir ninguna salida física; conciliar obligaciones pendientes |
| Entregado | Rectificación/incidencia comercial | No cambiar retrospectivamente lo efectivamente entregado, rechazado o retornado |

Cambios de aroma solo en productos que realmente tengan variantes. En casos operativos puede sustituirse aroma y explicarse al cliente al entregar; si no acepta, registrar rechazo/retorno, sin inventar aprobación previa digital. Cambios posteriores al despacho **no reducen en silencio** el histórico, el importe ya comprobado o un pago: requieren ajuste/rectificación auditable.

## Retiro TOTAL del modelo antiguo de surtidos

Eliminar del **esquema limpio**, código activo, rutas, menús, permisos, seeders, servicios, controladores, vistas, JavaScript y pruebas basadas en la antigua modalidad comercial:

- `formatos_surtidos`
- `formato_surtido_skus`
- `pedido_paquetes_surtidos`
- `paquete_surtido_composiciones`
- `paquete_surtido_componentes`
- `pedido_despacho_componentes`

Eliminar las antiguas rutas/servicios de configuración y composición de surtidos; sustituir los consumidores de estas tablas por el **modelo por unidades** y las agrupaciones logísticas opcionales, conservando auditoría, despachos parciales e idempotencia. Revisar columnas/relaciones antiguas (`tipo_linea`, `formato_surtido_id`, `despacho_componente_id`, precios de "paquetes" como modalidad comercial), triggers y constraints. No basta con quitar el menú.

**Conservar** el mecanismo nuevo de agrupaciones opcionales del pedido (`pedido_agrupaciones`, `pedido_agrupacion_detalles`, `pedido_despacho_agrupaciones`) y la impresión de dos notas numeradas por hoja horizontal. No tratar agrupaciones como modalidades de venta ni generar consumo de mangas o materiales por la agrupación comercial.

## Estrategia de BD: mismo proyecto y usuarios INTACTOS

**Decisión vigente:** se mantiene la base de pruebas **`software_gestion_web_microempresa`** y el proyecto Laravel existente; no crear una base nueva ni reconstruir el proyecto.

### PROHIBICIONES EXPRESAS

- **NO tocar, modificar, fusionar, eliminar, renombrar, rebasar ni publicar cambios en la rama `JM_DEVELOP`**. Esta prohibición incluye operaciones directas y automáticas. No utilizarla como destino de PR, origen de cambios ni rama de pruebas.
- **NO borrar ni modificar ningún usuario existente**, sea administrador u otro rol, aunque se haya creado para pruebas.
- **NO cambiar datos de usuario**: identificadores, nombres, correos, teléfonos, hashes de contraseña, estados, roles, permisos asignados, preferencias, sesiones, tokens y demás información vinculada a sus cuentas. Conservar las relaciones de autorización y el acceso actual. No ejecutar seeders de usuarios, roles o permisos que alteren estos registros.
- **NO limpiar, vaciar, truncar ni reconstruir tablas de autenticación/autorización** (`users`, `roles`, `permisos`, `rol_permiso` y cualquier otra relación necesaria). Antes de cualquier limpieza de tablas comerciales, identificar dependencias con usuarios y protegerlas.
- **NO modificar `main`**; este PR está dirigido exclusivamente a `develop`.

### Datos operativos de PRUEBA

La empresa todavía no usa el sistema. Los datos **comerciales ficticios** (clientes, catálogo de prueba, precios, pedidos, inventarios, materiales, envasado, despachos, retornos y movimientos) se prepararán para ser eliminados **al terminar las etapas y antes de entregar el producto**, con una rutina que conserve las tablas funcionales vacías.

El usuario no requiere respaldo de esos datos ficticios. **La limpieza no se ejecutará mientras se desarrolla el PR #45.** No eliminar datos de cuentas de usuarios ni sus relaciones, aun si están en tablas dependientes. Cualquier registro que no pueda clasificarse con seguridad como dato comercial de prueba deberá permanecer hasta determinarlo.

### Retiro del surtido obsoleto

Eliminar código, consultas y tablas de la antigua modalidad comercial de surtidos, sin eliminar información de usuarios ni alterar sus permisos asignados. Los permisos antiguos que no se utilicen pueden quedar registrados hasta tener una decisión específica de depuración; retirar sus usos en rutas/interfaz no autoriza a borrar registros de autorización.

Conservar las **agrupaciones opcionales** del pedido y todas las funciones del nuevo flujo por unidades, preparación, envasado físico, despachos parciales, incidencias y retornos.

### Verificaciones de entrega

- Conteo y datos de **todos los usuarios** antes y después de pruebas y saneamiento: idénticos; hashes y asignaciones de roles/permisos preservados.
- Ninguna operación Git afecta `JM_DEVELOP` ni `main`.
- La estructura operacional queda disponible y sin datos comerciales ficticios cuando se autorice la limpieza final.
- La migración/limpieza se ensaya en un entorno aislado sin escribir en las tablas de cuentas de usuario.
- No subir a Git ningún `.env`, `APP_KEY`, credenciales, datos de usuarios ni volcados MySQL.
- Mantener `SESSION_DRIVER=file`, `CACHE_STORE=file` y `QUEUE_CONNECTION=sync` mientras continúan las etapas.

## Arquitectura / controles obligatorios

- Versiones comerciales **inmutables** y cambios con usuario, marca de tiempo, motivo y antes/después.
- Cálculos comerciales por unidades, independientes del envasado, consumos y existencias físicas.
- Inventario de terminados **solo** por confirmaciones físicas y salidas/retornos aprobados.
- Despachos parciales/retornos idempotentes, bloqueo transaccional para evitar doble movimiento, sin saldo negativo.
- Entregas/rechazos/retornos separados; aprobar reincorporación **solo** de unidades aptas, en permisos autorizados.
- Permisos: Operaciones registra/edita pedido; Preparación/Reparto registra preparación, envasado y ejecución física según permisos. Mantener roles originales y revisar su etiqueta operativa sin perder relaciones.
- Impresión de **dos pedidos distintos por hoja horizontal A4**, cada uno con su correlativo desde 000001; no imprimir cancelados.

## Pruebas de aceptación que deben quedar implementadas y aprobadas

1. 54 solicitadas → 32 envasadas → incidencia por falta de químico → 32 vigentes; versión original/usuario/motivo intactos; importe correcto.
2. Editar, volver a editar y actualizar navegador **no** altera stock ni genera movimientos duplicados.
3. Despachar 32 una sola vez; reintentar misma solicitud no duplica salida.
4. Aceptar 32; rechazar parcialmente o rechazar 32 y registrar recepción real, sin incremento prematuro de stock.
5. Ajuste después de despacho o entrega crea incidencia sin reescritura de operaciones físicas.
6. Todos los productos vendidos por unidades sin múltiplos, aromas solo cuando correspondan, agrupaciones opcionales mixtas o homogéneas sin facturación extra.
7. No existen referencias SQL a seis tablas de surtidos en migraciones ejecutables ni aplicación; esquema limpio MySQL/SQLite instala de cero.
8. Todos los usuarios existentes, sus datos de cuenta, credenciales, relaciones y autorizaciones permanecen idénticos; la limpieza final afecta únicamente datos comerciales ficticios verificables.
9. Autorización por roles, auditoría, cancelación, notas numeradas, doble impresión y tests existentes continúan aprobados.
10. GitHub CI, PHPUnit, pruebas MySQL, JS y validación estática sin omitir pruebas ni modificar bases originales.

## Orden de implementación

1. PR #44: cerrado **sin merge**. Rama de PR #45: partir exactamente de su último commit para conservar avances.
2. Abrir PR #45 como **borrador** hacia `develop`, especificar este contrato funcional y plan de limpieza.
3. Sustituir dependencias antiguas de surtidos e implementar pruebas del flujo.
4. Preparar y probar la rutina de saneamiento de registros comerciales de prueba en `software_gestion_web_microempresa`, conservando **TODOS los usuarios y sus datos**, roles y permisos. Ejecutarla únicamente al final de las etapas, sin alterar las tablas de cuentas ni la rama `JM_DEVELOP`.
5. Revisar CI y comprobaciones en entornos aislados. Pasar de borrador a listo y fusionar **solo a develop** después de aprobación técnica; **no tocar main**.

**Estado de este documento:** especificación funcional y criterios de aceptación. No acredita por sí mismo que todas las correcciones estén implementadas ni aprobadas.

## Avance verificado del 30/10/2026 — desacoplamiento de lecturas

- Rama existente del PR #45, sin modificar el checkout que contiene los cambios locales de Selenium.
- Línea base ejecutada: 663 pruebas Laravel, 4110 aserciones (128,399 s), SQLite en memoria.
- Se extrae `LecturaPedido`: nombres de SKU, agregado de despachos por línea y proyección física normal compartida. La relación `Pedido::detalles()` comprueba su columna de retiro lógico, no la existencia de una tabla de surtidos.
- Dos regresiones nuevas comprueban lecturas sin las seis tablas antiguas y la conservación completa de cuentas, autorizaciones, cantidades y movimientos. Suite posterior: 665 pruebas, 4122 aserciones (79,650 s).
- La prueba de compatibilidad elimina tablas únicamente en SQLite en memoria; no se ha conectado ni escrito en la base local del proyecto.
- **Pendiente:** sustituir los consumidores y escrituras antiguos restantes; retirar rutas e interfaz; adaptar indicadores; preparar y ensayar las migraciones y el saneamiento final. Este avance no acredita todavía el retiro completo ni autoriza marcar listo el PR.

### Regresión integral 54 → 32

`FlujoVentaUnidadesPr45Test` ejecuta las tres variantes: 32 aceptadas, 30 aceptadas/2 rechazadas y 32 rechazadas. Confirma envasado real de 32, reenvío idempotente del mismo formulario, consumo de envases 100 → 68, edición sin movimientos, fotografía original 54, vigente 32 e importe S/ 64,00, despacho único de 32 y retorno físico sin entrada hasta aprobar exclusivamente lo apto. Las cuatro tablas de cuentas y autorizaciones conservan todas sus columnas y filas.

Suite ejecutada tras esta ampliación: **668 pruebas, 4209 aserciones**, SQLite en memoria, 69,729 s. No es todavía el resultado final del PR: sigue pendiente el retiro integral de la modalidad anterior.

### Puente físico histórico, sin retirar tablas

La migración nueva `2026_10_30_002816_create_pedido_referencias_fisicas_table.php` conserva los identificadores, SKU, cantidades, valoración informativa y fechas de los componentes ya fotografiados al despachar. La tabla `pedido_referencias_fisicas` no crea una modalidad comercial, no genera existencias ni convierte precios. Es el puente para desacoplar entregas y retornos; el retiro de las seis estructuras anteriores seguirá separado y no se ejecuta en la base local.

`MigracionReferenciasFisicasTest` compara todas las tablas y columnas anteriores, incluidas cuentas y autorizaciones, y prueba la inmutabilidad de la copia. Resultado: 669 pruebas Laravel / 4264 aserciones / 81,397 s. MySQL 8.4.3 independiente en 127.0.0.1:3347: 70 pruebas / 1168 aserciones / 164,068 s. La comprobación de identidad verifica ejecutable, proceso, archivo de configuración, datadir, UUID y ausencia de las bases protegidas. Se utilizó una base nueva con prefijo exclusivo de pruebas. En el ensayo escalonado se conservan las 41 migraciones iniciales, después 45, 47 y 48; ninguna se ejecutó sobre la base local del proyecto.

### Retiro manual y comprobación de motores

La migración `database/migrations/retiro_controlado/2026_10_30_003639_retirar_estructuras_surtidos_vacias.php` está deliberadamente fuera del glob ordinario de migraciones. No elimina filas: exige que las seis tablas antiguas estén vacías y que ninguna línea conserve referencias comerciales anteriores. Exige además una autorización explícita de entorno y la identidad de la base permitida. Si detecta una dependencia externa o un índice compuesto/único inesperado, aborta antes de iniciar DDL.

Reasigna las referencias físicas de entregas y retornos al puente independiente, conserva el índice generado que protege referencias NULL y restablece sus triggers. En SQLite conserva y restablece los triggers externos durante la reconstrucción de `pedido_detalles`. Retira exclusivamente las seis tablas obsoletas y la columna `formato_surtido_id`, conservando agrupaciones, versiones, despachos, cuentas y permisos.

Ensayo MySQL 8.4.3: una base auxiliar nueva y vacía, 48 migraciones ordinarias → 49 con el retiro explícito, 52 → 46 tablas. Se compararon las columnas anteriores y todas sus filas, claves foráneas y triggers. Después se registraron siete unidades por S/ 14,00 sin las seis estructuras anteriores, manteniendo idénticas las cuatro tablas de autorización. El ensayo individual pasó (183 aserciones); su repetición junto a los dos escenarios MySQL de unidades pasó: 3 pruebas, 213 aserciones, 37,481 s.

Regresiones SQLite del retiro: falta de autorización, datos no vacíos, dependencia externa inesperada y retiro vacío con registro posterior por unidades. Todas pasaron. Suite Laravel completa de este punto: 673 pruebas, 4289 aserciones, 76,760 s. Larastan nivel 5: cero errores, sin supresiones nuevas. **No se ejecutó el retiro ni limpieza alguna en la base local existente.** El saneamiento comercial final y el retiro del código antiguo siguen pendientes en este borrador.


## Estado de cierre técnico — PR45 (30/10/2026)

Este apartado sustituye los pendientes de los cortes anteriores; aquellos resultados se conservan como evidencia de avance, no como resultados finales.

### Correcciones implementadas

- Se retiran controladores, servicios, rutas, vistas y JavaScript de la modalidad comercial surtida. No hay consultas a sus seis tablas en los módulos operativos. Las únicas referencias conservadas son las migraciones históricas, el puente de copia, el retiro controlado y la clasificación de solo lectura para el saneamiento.
- Pedidos nuevos: SKU físicos y precios unitarios; las agrupaciones homogéneas o mixtas no añaden precio, demanda ni stock. El editor identifica nombre y SKU, permite copiar sin compartir referencias y no inventa cantidades.
- Lecturas de preparación, disponibilidad, envasado e indicadores comparten `LecturaPedido`. Las líneas históricas no verificables se señalan para revisión; no generan demanda física ficticia. Los pedidos normales anteriores conservan sus reglas y valores originales.
- Los resultados y retornos históricos utilizan `pedido_referencias_fisicas`. La copia conserva identificadores, cantidades, fechas y valor informativo originales. La clave textual histórica de eventos se conserva por compatibilidad; no habilita una modalidad comercial.
- El puente ordinario traslada claves foráneas y triggers de entregas/retornos hacia la referencia independiente. El retiro manual solamente elimina estructuras vacías; en SQLite se preservan también los triggers externos durante las reconstrucciones.
- Se corrige una edición exclusivamente logística que no incrementaba la versión del pedido cuando su cabecera no cambiaba. Cada ajuste auditado incrementa una vez la versión; una actualización concurrente obsoleta se rechaza.
- Se corrige el tratamiento de interbloqueos MySQL: los middleware propagan la excepción original hasta la transacción con reintentos acotados. No se desactiva ningún bloqueo, movimiento único, control de permisos ni idempotencia. El ensayo real de dos pedidos que compiten por stock devuelve una aceptación y un rechazo por insuficiencia, sin salida parcial duplicada.
- Los 15 CSV conservan BOM UTF-8, separador de punto y coma, filtros compartidos y neutralización de fórmulas. El reporte de composiciones muestra agrupaciones logísticas; no agrega otra demanda. El de pendientes distingue espera conocida y revisión histórica.
- `SurtidosPermisoSeeder` queda como compatibilidad sin escrituras. No crea, asigna ni elimina permisos; los registros de autorización anteriores permanecen intactos.
- La preparación de datos ficticios de rendimiento se adapta al modelo por unidades. No se ejecutaron `CargaSeeder`, k6 ni ZAP en esta campaña y no se extrapolan sus resultados anteriores al nuevo flujo.

### Validación local reproducible

Todas las pruebas con escritura utilizan SQLite `:memory:` o el servidor independiente MySQL 8.4.3 en `127.0.0.1:3347`, con UUID, ejecutable, configuración, datadir y ausencia de bases protegidas comprobados antes de habilitar la conexión. Se utilizaron bases nuevas con prefijo `dhacer_eco_limp_test_`; no se limpiaron las bases del proyecto.

- Laravel completo, después de la guarda final: **684 pruebas / 4.895 aserciones / 85,503 s**. Descubrimiento PHPUnit: 33 ejecuciones Unit, 640 Feature y 11 Security; los proveedores de datos cuentan como ejecuciones independientes. Seguridad se incluye una sola vez.
- MySQL integral tras el ajuste de compatibilidad normal histórica: **71 pruebas / 1.350 aserciones / 242,687 s**. Ensayo adicional ampliado de retiro y flujo físico: **1 prueba / 191 aserciones / 75,169 s**, sin tablas antiguas, con despacho idempotente, rechazo, recepción y aprobación; compara cuentas y ejecuta el manifiesto de saneamiento de solo lectura. La repetición integral local con el ensayo ampliado pasó **71 pruebas / 1.358 aserciones / 222,966 s**. Después se añadió la guarda de dependencias SQL externas: su ensayo MySQL aislado final pasó **1 prueba / 194 aserciones / 54,567 s**. GitHub ejecuta de nuevo las 71 sobre el último commit antes de integrar.
- Flujo 54 → 32: aceptación completa, rechazo parcial, rechazo total y repetición sin las seis tablas retiradas. Esta última ejecuta también las 15 pantallas/CSV y comprueba que las consultas no utilicen estructuras retiradas. Los cuatro escenarios pasaron, conservando las cuatro tablas de cuentas y autorizaciones.
- JavaScript: **45 aprobadas, 0 fallidas, 0 omitidas**.
- Larastan nivel 5: **0 errores**, sin supresiones nuevas. Pint aplicado a los PHP modificados; PHP lint sin errores.
- Vite: compilación correcta. Composer audit y npm audit: **0 vulnerabilidades**.
- Los escenarios con nombres históricos `Surtidos*` se conservan para trazabilidad y se adaptan al contrato legítimamente sustituto: unidades y agrupaciones. No se conservaron servicios productivos antiguos dentro de los tests para simular un resultado verde. El ensayo escalonado de migraciones mantiene sus datos históricos ficticios.

### Migraciones pendientes para el proyecto local: no ejecutadas

1. `database/migrations/2026_10_29_033324_create_versiones_y_agrupaciones_pedidos.php`.
2. `database/migrations/2026_10_29_033326_create_despachos_parciales_pedido.php`.
3. `database/migrations/2026_10_30_002816_create_pedido_referencias_fisicas_table.php`.

Sobre el esquema previo de 45 migraciones / 44 tablas, las ordinarias dejan 48 / 52. No crean ni modifican usuarios, roles o permisos; no convierten precios ni generan movimientos.

Separadamente, `database/migrations/retiro_controlado/2026_10_30_003639_retirar_estructuras_surtidos_vacias.php` no se descubre mediante el glob ordinario. Exige autorización explícita, identidad permitida, las seis tablas vacías, ausencia de referencias comerciales antiguas y dependencias conocidas. Su ensayo aislado alcanza 49 migraciones / 46 tablas. No se debe ejecutarla mientras conserve datos comerciales antiguos. Sus `down` no restauran ni borran historia automáticamente.

No se ha ejecutado ninguna de estas operaciones sobre `software_gestion_web_microempresa` ni `ecolimp_respaldo_prueba`.

### Procedimiento de saneamiento final preparado, NO ejecutado

1. Detener escrituras y confirmar servidor, puerto, UUID, base exacta y directorio de datos. El contexto actual solo declara ficticios los registros comerciales: **todas las cuentas siguen protegidas**.
2. Ejecutar primero, en una copia independiente verificada, `php artisan comercio:preparar-saneamiento --base=BASE_EXACTA --uuid=UUID_VERIFICADO`. El comando es exclusivamente de lectura: inventaría las tablas, cuenta registros, calcula SHA-256 de contenido sin mostrar filas o hashes de contraseñas, clasifica cuentas y calcula el orden de dependencias hijas antes que padres.
3. Cualquier tabla sin clasificar, ciclo, identidad distinta o dependencia desde cuentas debe detener el procedimiento. Guardar el manifiesto fuera de Git y comparar de nuevo inmediatamente antes de la operación final, sin escritores concurrentes.
4. El manifiesto **no es un comando de borrado ni una autorización para ejecutarlo**. Usuarios, roles, permisos, pivote, sesiones y datos asociados a cuentas se conservan íntegros. Auditoría e idempotencia quedan protegidas completas; sus entradas comerciales requieren clasificación explícita separada si se quiere retirarlas sin perder evidencia de cuentas.
5. La ejecución futura debe preparar y ensayar las eliminaciones comerciales de acuerdo con el manifiesto y los triggers inmutables existentes. No desactivar claves foráneas de manera general ni desproteger triggers desde solicitudes web. El ejecutor destructivo queda deliberadamente fuera de este PR; no debe improvisarse una secuencia SQL contra la base local. Requiere el punto de control final solicitado por el usuario y la verificación previa de todas las dependencias.
6. Solo cuando las seis tablas antiguas estén vacías y las referencias anteriores resueltas se podrá ejecutar el retiro manual con ruta exacta y variable `DHACER_RETIRO_SURTIDOS_AUTORIZADO=RETIRAR_SOLO_ESTRUCTURAS_SURTIDOS_VACIAS`, primero en la copia. No usar rollback automático ni restauraciones indiscriminadas.
7. Verificar conteos comerciales vacíos, integridad de las estructuras operativas y huellas idénticas de todas las tablas protegidas. Cualquier diferencia detiene la entrega sin reparar cuentas ni datos automáticamente.

El modo preparatorio está cubierto por cuatro pruebas: conservación de todas las filas, ausencia de datos de cuenta en la salida, base distinta, tabla desconocida y dependencia de cuentas. No se afirma que se haya eliminado ningún registro comercial en la base local.

### Entorno y límites

Se ajustaron únicamente las tres variables locales expresamente solicitadas: `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`. El resto de `.env` se comparó byte a byte tras normalizar esas tres líneas y quedó intacto; el archivo no se versiona. Las credenciales, usuarios y bases existentes no se modificaron. El checkout original conserva sus cambios locales de Selenium.

La consolidación de preparación y el informe del verificador siguen acumulando resultados en memoria; las mediciones generales previas llegan a 300 registros y las mayores pruebas específicas de indicadores no certifican capacidad ilimitada. La repetición visual no equivale a UAT con trabajadores ni a impresión física. No se realizaron despliegues ni cambios de infraestructura operativa.

### Evidencia visual del PR45

Selenium completo: **6/6 escenarios / 518,67 s**, Chrome 146, MySQL aislado. Escritorio y 360/390/768/1024 px comprobados; consola sin errores graves. El PDF tiene dos páginas A4 horizontales: dos notas diferentes en la primera y anexo de agrupaciones en la segunda, sin hojas en blanco. Capturas, saldos por etapa, PDF renderizado y SHA-256 en [la evidencia PR45](pruebas/evidencias/pr45_unidades/README.md). No se reutilizan cifras del PR44 como si fueran de esta campaña.

### Guarda final y entrega del código

El retiro manual inspecciona también vistas y triggers externos: si dependen de alguna estructura retirada o su definición no puede leerse, aborta antes del primer DDL. Las regresiones comprueban que las tablas, columnas y objetos originales permanecen cuando aborta. Pasaron SQLite y MySQL; no se modificaron migraciones históricas fusionadas.

Los archivos principales son `LecturaPedido`, `LeerAgrupaciones`, `GestionarPedidoUnidades`, `AjustarPedidoHistorico`, `AtenderPedidoUnidades`, servicios de entrega/retorno e indicadores, controladores y vistas de pedidos/cumplimiento, editor JS, las tres migraciones ordinarias nuevas y el retiro manual. El detalle completo se consulta en Files changed del PR45.

No se ha ejecutado saneamiento local. El manifiesto preparatorio es de solo lectura y el retiro de tablas exige que ya estén vacías; no se presenta como un borrado comercial ejecutado ni como un ejecutor destructivo listo para usar a ciegas. El paso destructivo final necesita resolver las dependencias e inmutabilidad sobre el estado que exista entonces, preservando todas las cuentas.

La publicación de este informe no ejecuta migraciones al fusionar. El código nuevo necesita las tres migraciones ordinarias antes de utilizar sus módulos contra una base que aún tenga 45; la autorización actual no se usa para aplicarlas al proyecto local. Los cambios locales ajenos permanecen en el checkout original.

### Commits del PR anteriores a esta actualización documental

- `d7f0c04` docs(redisenio): registrar diagnostico y estrategia de coexistencia
- `304aba8` perf(surtidos): paginar archivo sin multiplicar formularios por SKU
- `b3eeb82` feat(pedidos): incorporar base versionada por unidades y despachos parciales
- `cbf1bb9` docs(redisenio): registrar validaciones fallos y pendientes del borrador
- `b082566` fix(migraciones): limitar nombre del indice de agrupaciones para MySQL
- `89311e4` fix(integridad): comparar origenes sin colisiones de collation y probar concurrencia
- `8cdd365` test(pedidos): adaptar atencion fisica y comprobar migraciones historicas por etapas
- `9e10fc7` fix(security): actualizar symfony mime al parche compatible 7.4.22
- `d5153f6` feat(pedidos): conservar tarifas historicas con ajustes versionados explicitos
- `c609e3d` feat(despachos): trazar paquetes y sueltas por salida sin reutilizar agrupaciones
- `6f292c6` fix(preparacion): separar espera y demanda activa y mostrar ajustes historicos
- `3273fc8` test(web): preparar escenarios aislados y documentar validaciones reales del PR44
- `b89be0b` test(selenium): usar rutas reales y comprobar anchos efectivos de captura
- `2b67157` test(redisenio): validar Selenium movil y trazabilidad con evidencia de impresion
- `0253980` docs: especificar PR45 de correccion integral ventas y retiro de surtidos
- `bd006a0` docs(pr45): utilizar BD actual y exigir respaldo antes de limpieza irreversible
- `3af9b3b` docs(pr45): fijar BD de pruebas y entrega con unico admin roles permisos
- `f6cfe42` docs(pr45): proteger JM_DEVELOP y conservar todos los usuarios intactos
- `e6e6fc4` refactor(pr45): desacoplar lecturas fisicas y detalles de surtidos
- `f78ab18` test(pr45): verificar produccion real y flujo de 54 a 32 unidades
- `92816a4` refactor(pr45): preparar referencias fisicas historicas independientes
- `cb6ca66` refactor(pr45): preparar retiro controlado sin eliminar datos locales
- `570591d` test(pr45): aislar escenarios de unidades del catalogo de surtidos
- `ba9bb62` fix(pr45): reintentar interbloqueos sin duplicar operaciones
- `bcc5f24` fix(pr45): incrementar version en ajustes exclusivamente logisticos
- `6cc2dda` refactor(pr45): conservar integridad de referencias fisicas al retirar tablas
- `654de80` refactor(pr45): retirar modalidad surtida y operar ventas por unidades
- `6673f36` refactor(pr45): calcular indicadores y csv sin modalidades retiradas
- `20c5760` feat(pr45): preparar manifiesto de saneamiento sin eliminar registros
- `b49757a` test(pr45): validar despacho y retorno mysql sin tablas retiradas
- `f0c932e` test(pr45): conservar evidencias al repetir la revision visual aislada
- `49f3be0` docs(pr45): registrar validacion integral y procedimiento de saneamiento pendiente
- `b5c907a` fix(pr45): bloquear retiro ante triggers o vistas externas
