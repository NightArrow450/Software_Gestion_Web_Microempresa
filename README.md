# DHACER ECO LIMP

Sistema web de gestión para una microempresa de productos de limpieza: pedidos, producción por envasado, inventarios físicos y distribución con trazabilidad.

**Estado:** cierre del desarrollo funcional ampliado (Fase 8). La Fase 9 de pruebas finales y aceptación de la empresa sigue pendiente y es obligatoria. Esta entrega no certifica rendimiento de producción ni aceptación UAT.

## Alcance y evolución

El alcance inicial comprendía autenticación, usuarios, roles/permisos, clientes, catálogo y pedidos. Posteriormente se incorporaron inventarios, preparación, envasados, despachos, entregas parciales, retornos autorizados, auditoría, surtidos variables e indicadores. Estas ampliaciones no se atribuyen retroactivamente a los requisitos originales.

Por decisión de la empresa no se controla el volumen de mezclas preparadas ni líquidos almacenados en bidones. No existen recetas automáticas de líquidos. Se conservan las categorías de insumos existentes.

## Módulos disponibles

| Proceso | Funciones |
| --- | --- |
| Acceso y administración | Autenticación, cuentas activas, contraseña inicial, usuarios, roles y permisos. |
| Catálogo y clientes | Clientes, productos, categorías, aromas, presentaciones, unidades y empaques. |
| Pedidos | Ventas nuevas por unidades, agrupaciones opcionales, edición auditada, históricos conservados, confirmación y notas. |
| Archivo de surtidos | Composiciones, versiones y pendientes de pedidos históricos. Los nuevos agrupan dentro del pedido, sin otro precio comercial. |
| Preparación | Consolidación por producto, presentación y aroma; demanda, stock y producción diferenciados. |
| Inventarios | Productos terminados e insumos físicos, movimientos, ajustes, mínimos e historial. |
| Envasados | Materiales configurables, anticipados o vinculados, consumo y entrada atómicos, correcciones. |
| Distribución | Despachos, resultados parciales, rechazos, retornos y reincorporaciones autorizadas. |
| Auditoría | Acciones sensibles, responsables, entidades y detalle permitido. |
| Indicadores | Información comercial, física y económica; quince reportes CSV filtrados. |

Los importes comerciales no son dinero efectivamente cobrado. Las referencias históricas incompletas no se reconstruyen sin sustento.

## Documentación

- [Instalación, mantenimiento y recuperación](docs/operacion-local.md).
- [Módulos, navegación y permisos](docs/modulos.md).
- [Rediseño por unidades, históricos y validación visual del PR44](docs/redisenio-ventas-unidad.md).
- [Envasados y materiales](docs/envasados.md).
- [Auditoría y trazabilidad](docs/auditoria.md).
- [Demostración aislada y controles previos](docs/demostracion.md).
- [Dos guiones de video: funcionamiento y pruebas](docs/guiones-demostracion.md).
- [Calidad, inventario de pruebas y pendientes](docs/calidad-y-pendientes.md).
- [Integración continua](docs/integracion-continua.md).
- [Preparación neutral para un despliegue futuro](docs/despliegue.md).

## Entorno y verificación

Laravel 13, PHP 8.3, MySQL 8.4 (integración con 8.4.3), Node 22, Vite 8, Tailwind 4 y CSS propio. Las versiones exactas están en los lockfiles. Laragon se conserva como entorno local; no se requiere hosting de pago.

En un checkout nuevo: instalar dependencias desde lockfiles sin scripts, preparar una configuración privada propia y verificar la base antes de autorizar inicializaciones. **No ejecutar composer setup:** incluye migraciones. No copiar el .env operativo ni datos reales.

Las pruebas fuerzan SQLite en memoria y cachés por proceso:

```powershell
composer test
composer analyse
node --test tests/js/*.test.cjs
npm run build
composer audit --locked
npm audit --package-lock-only --audit-level=low
```

MySQL requiere habilitación y servidor independiente conforme a la guía de CI. Pint se aplica al alcance modificado; existe deuda anterior documentada. No publicar contraseñas, sesiones, archivos privados, SQL ni evidencia empresarial.
