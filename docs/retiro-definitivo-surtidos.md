# Retiro definitivo de las estructuras comerciales de surtidos

## Alcance autorizado

El PR45 sustituyó la modalidad comercial por ventas de unidades físicas y agrupaciones logísticas opcionales. El PR46 retiró documentación de videos ajena al alcance. Este cambio completa exclusivamente el retiro físico del esquema antiguo; no ejecuta Fase 9C ni una limpieza comercial general.

La inspección de Laragon del 31/10/2026 encontró MySQL 8.4.3, 52 tablas, 48 migraciones y las seis tablas obsoletas vacías. La única FK entrante desde una tabla vigente era `pedido_detalles.formato_surtido_id` → `formatos_surtidos`. No había vistas, rutinas ni eventos en esa base. Las entregas y retornos ya referencian `pedido_referencias_fisicas`.

## Migración específica y límites

Ruta: `database/migrations/retiro_controlado/2026_10_31_174658_retirar_definitivamente_surtidos_obsoletos.php`.

Es deliberadamente **manual**: una instalación limpia ejecuta primero las migraciones históricas ordinarias y luego esta ruta explícita, con autorización. No se modifican migraciones fusionadas ni se incorpora un DROP automático al actualizar código. La migración manual anterior, limitada a tablas vacías, y sus regresiones se conservan como historial compatible; no ejecutar ambas rutas.

Se retiran, en orden de hijas antes que padres:

1. `pedido_despacho_componentes`.
2. `paquete_surtido_componentes`.
3. `paquete_surtido_composiciones`.
4. `pedido_paquetes_surtidos`.
5. `formato_surtido_skus`.
6. `formatos_surtidos`.

También se retiran la columna `pedido_detalles.formato_surtido_id`, su FK e índice de soporte. Los índices, restricciones y triggers que pertenecen exclusivamente a las seis tablas desaparecen con sus tablas. Sus datos, si existen y no bloquean las verificaciones, se descartan por autorización expresa; no se tocan datos de otras tablas.

Se mantienen expresamente:

- `pedido_agrupaciones`, `pedido_agrupacion_detalles`, `pedido_despacho_agrupaciones`, versiones, preparaciones y salidas por unidades.
- `pedido_referencias_fisicas` y las FK de entregas y retornos hacia ella. El nombre histórico `despacho_componente_id` ahora identifica estas referencias físicas vigentes: eliminar esa columna rompería entregas y retornos.
- `tipo_linea` y `retirado_en` en detalles: los consumidores actuales utilizan el discriminador normal y el retiro lógico. No mantienen el negocio antiguo ni crean surtidos. Los precios y tamaños de empaques de pedidos históricos permanecen intactos.
- Usuarios, roles, permisos, pivotes, auditoría, idempotencia, sesiones y demás registros vigentes.

Las referencias textuales a los nombres antiguos quedan restringidas a migraciones históricas, guardas de retiro, clasificación del plan de saneamiento, documentación y regresiones. No son consultas comerciales activas a tablas eliminadas.

## Guardas previas al primer DDL

- Autorización de retiro independiente de la antigua autorización de tablas vacías.
- SQLite en memoria o MySQL con prefijo de pruebas únicamente en testing; en Laragon exige base exacta, host 127.0.0.1, puerto 3306, versión 8.4.3, UUID y datadir explícitos del proceso autorizado.
- Esquema vigente de unidades completo; un retiro parcial se bloquea sin reparaciones ni reintentos. Un esquema ya retirado completamente no se vuelve a modificar.
- Ninguna línea comercial vinculada al formato antiguo ni marcada con una modalidad de línea retirada.
- Fotografía física equivalente para cualquier componente de salida antiguo antes de descartar su tabla; no se inventan referencias ni se reescriben movimientos.
- Ninguna FK externa inesperada, dependencia de cuentas o índice compuesto/único sobre la columna eliminada.
- Inspección de triggers, vistas, rutinas, eventos, columnas generadas y CHECK. Objetos ilegibles o dependientes bloquean el retiro. Se revisan también referencias calificadas desde otros esquemas visibles y FK entrantes desde otras bases.
- MySQL usa un bloqueo asesor para impedir dos retiros simultáneos; no desactiva claves foráneas ni triggers de integridad vigentes.

La visibilidad de metadatos debe ser suficiente: usar una cuenta administradora local autorizada, nunca una cuenta limitada que oculte objetos externos. El bloqueo asesor no bloquea solicitudes comerciales. Antes de aplicar, repetir las lecturas y comprobar conexiones activas; si hay escritores o usuarios utilizando el sistema, posponer únicamente el DDL. No se detiene el servidor Laravel. La espera por metadatos se limitará en la conexión de actualización para no mantener el sitio esperando indefinidamente.

MySQL no ofrece una transacción que revierta toda esta secuencia DDL. Si falla un paso, detenerse, conservar el estado real y no ejecutar rollback, restauración ni reparación automática. `down()` rechaza reconstruir datos descartados. No se promete reversibilidad.

## Aplicación controlada después de CI y merge

1. Revisar el SHA final y los cinco trabajos de GitHub Actions; actualizar solamente develop.
2. Comprobar conexión, tablas, migraciones y dependencias; guardar conteos y huellas de todos los registros que permanecen, especialmente las cuentas. No mostrar hashes de contraseñas ni credenciales.
3. Confirmar que no haya escrituras concurrentes y que los datos coincidan con la inspección. No continuar si cambió una dependencia necesaria.
4. En un proceso temporal, configurar `DHACER_RETIRO_SURTIDOS_AUTORIZADO=RETIRAR_ESTRUCTURAS_SURTIDOS_OBSOLETAS`, `DHACER_RETIRO_MYSQL_UUID` y `DHACER_RETIRO_MYSQL_DATADIR` con la identidad recién comprobada, sin modificar `.env`.
5. Ejecutar exclusivamente:

```powershell
php artisan migrate --path=database/migrations/retiro_controlado/2026_10_31_174658_retirar_definitivamente_surtidos_obsoletos.php --force --no-interaction
```

6. Retirar las variables temporales y verificar 46 tablas, 49 migraciones y conservación del resto de filas. Los 48 registros históricos de migraciones no se borran.
7. Comprobar FK, índices, triggers y ausencia de referencias SQL rotas; ejecutar solo los verificadores de inventario y despachos de lectura y consultas GET autorizadas. No crear ventas, envasados, retornos ni usuarios ficticios en Laragon.

## Validación de bajo consumo

No se ejecutan suites PHPUnit/MySQL, Selenium, navegadores, servidores adicionales, Docker ni cargas en la laptop. Localmente: inspección, lecturas y sintaxis/formato de los archivos cambiados. La ejecución completa queda en los runners temporales existentes de GitHub Actions.

Las regresiones conservan el retiro antiguo y agregan: autorización propia, descarte de catálogo obsoleto, repetición segura, rechazo de dependencias de cuenta, vistas/triggers, índices inesperados, líneas antiguas no resueltas y retiro parcial. MySQL prueba instalación limpia ordinaria seguida del retiro, datos obsoletos existentes, bloqueo de rutinas/triggers, cuentas idénticas y el flujo completo de venta, preparación, despacho idempotente, rechazo, recepción y aprobación. SQLite repite además 54 → 32 y los quince reportes/CSV sin consultas a tablas retiradas. No se anuncian resultados hasta que CI los produzca.

## Limpieza comercial general: preparada, NO ejecutada

Es una tarea separada. `comercio:preparar-saneamiento --base=BASE_EXACTA --uuid=UUID_VERIFICADO` solo calcula conteos, huellas y orden de dependencias; se adapta a las tablas que realmente existan y funciona también después del retiro. No es un ejecutor de borrado.

Para la entrega futura: autorizar específicamente la limpieza, comprobar identidad y ausencia de escritores, clasificar tablas y referencias, proteger íntegramente cuentas y autorizaciones, resolver triggers inmutables y dependencias, ensayar el procedimiento aprobado y comparar las huellas protegidas después. Auditoría, idempotencia, sesiones y tablas relacionadas con cuentas permanecen protegidas; no se borran indiscriminadamente como “datos de prueba”. No desactivar FK ni triggers a ciegas, no recrear la base ni ejecutar seeders generales. Si no puede distinguirse un registro comercial de información de cuentas, se conserva. Este PR no implementa ni ejecuta esa eliminación general.
