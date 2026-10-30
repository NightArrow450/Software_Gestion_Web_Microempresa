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

## Estrategia de BD: MISMA BD operativa, sin perder accesos

**Decisión actual del usuario:** se conserva el nombre y conexión a la **base existente `software_gestion_web_microempresa`**. **NO** utilizar `ecolimp_v2` ni reconstruir el proyecto Laravel en otro repositorio.

**ADVERTENCIA CRÍTICA:** limpiar datos comerciales de esa misma base implica borrar registros reales (clientes, inventarios, pedidos, movimientos, etc.). Hasta que exista un respaldo íntegro **y se haya comprobado su restauración en un entorno aislado**, la base original es de **solo lectura** para cualquier acción de saneamiento o rediseño. No ejecutar limpieza directa ni migraciones destructivas en la BD original durante el desarrollo del PR.

- Continuar implementando el código en este mismo repositorio y rama de PR, evitando tocar `main`.
- Diseñar y probar en una **copia de ensayo** temporal el procedimiento idempotente de saneamiento/esquema compatible, preservando la BD de operación durante desarrollo. El uso de copia temporal para pruebas **no cambia** la decisión de utilizar `software_gestion_web_microempresa` como base final.
- El objetivo del saneamiento, **solo tras respaldo verificado y autorización explícita para ejecutar eliminación irreversible**, será conservar registros de acceso de `roles`, `users`, `permisos` y `rol_permiso` (IDs, asociaciones y hashes); cualquier tabla de soporte de autenticación/estructural se recreará vacía si es necesaria.
- Vaciar los datos operativos de clientes, productos, existencias, inventarios, insumos, pedidos, envasados, movimientos, despachos, entregas, retornos, reportes y auditorías según dependencias y políticas aplicables; nunca borrar sin documentar efectos y validación de integridad referencial.
- Retirar todas las tablas y dependencias del modelo antiguo de surtidos; mantener las nuevas agrupaciones logísticas como estructuras **vacías** en el esquema resultante.
- No dejar información sensible en Git: sin `.env`, `APP_KEY`, volcados completos de MySQL, hashes exportados ni credenciales. Evitar seeders que reemplacen usuarios/roles/permisos preservados.
- No ejecutar `DROP DATABASE`, `migrate:fresh`, `db:wipe`, `TRUNCATE` o `DELETE` masivos directamente en `software_gestion_web_microempresa` durante la preparación. El eventual saneamiento deberá ser planificado, con copia restaurable y aprobación específica, una vez que todo esté validado.
- Mantener `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync` hasta finalizar todas las etapas.

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
8. Migración selectiva replica accesos y ningún dato operativo; contraseñas anteriores funcionan con los hashes preservados.
9. Autorización por roles, auditoría, cancelación, notas numeradas, doble impresión y tests existentes continúan aprobados.
10. GitHub CI, PHPUnit, pruebas MySQL, JS y validación estática sin omitir pruebas ni modificar bases originales.

## Orden de implementación

1. PR #44: cerrado **sin merge**. Rama de PR #45: partir exactamente de su último commit para conservar avances.
2. Abrir PR #45 como **borrador** hacia `develop`, especificar este contrato funcional y plan de limpieza.
3. Sustituir dependencias antiguas de surtidos e implementar pruebas del flujo.
4. Preparar y verificar el saneamiento controlado de la base existente `software_gestion_web_microempresa` en una copia de ensayo, conservando datos de acceso. No ejecutarlo sobre la base real durante el desarrollo.
5. Revisar CI y comprobaciones en entornos aislados. Pasar de borrador a listo y fusionar **solo a develop** después de aprobación técnica; **no tocar main**.

**Estado de este documento:** especificación funcional y criterios de aceptación. No acredita por sí mismo que todas las correcciones estén implementadas ni aprobadas.
