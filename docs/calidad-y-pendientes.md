# Calidad y pendientes de cierre

## Inventario previo al PR 5

Referencia: código posterior a #40. Casos recolectados con proveedores de datos, no reglas de negocio únicas:

| Suite | Archivos | Casos |
| --- | ---: | ---: |
| Unit sin arranque Laravel | 2 | 6 |
| Feature: HTTP, servicios, BD y configuración | 54 | 476 |
| Security | 1 | 11 |
| Laravel habitual | 57 | 493 |
| MySQL independiente | 8 | 67 |
| JavaScript | 5 | 19 |

Security aparece una sola vez. No sumar otra vez seguridad distribuida por Feature. Las 493 no son todas flujos completos. Las nuevas regresiones de PR 5 se informan aparte con resultados reales en su descripción.

MySQL: concurrencia envasado 6, inventario 7, retornos 5; entregas/retornos 24, indicadores 12, lista 3, rendimiento 3, surtidos 7. Los archivos Concurrencia incluyen restricciones; surtidos e indicadores tienen concurrencia adicional ya contada.

## Limitaciones conservadas

- PCOV/Xdebug no estaban cargados: porcentaje de cobertura de líneas/ramas PENDIENTE DE EJECUCIÓN.
- Mediciones originales hasta 300 registros; escenarios comerciales de Indicadores hasta 1.500 filas. No certifican todo el sistema bajo carga.
- El verificador acumula resultados del informe en memoria. La continuidad de saldos lee el historial del destino antes del filtro de fechas.
- Deuda de formato previa en 41 archivos, sin normalización masiva.
- Aviso opcional de fontaine en Vite, sin agregar dependencias para ocultarlo.
- workflow_dispatch requiere workflow en main, que sigue como rama predeterminada. No cambiarla ni fusionar develop con main sin autorización.
- Importes no acreditan cobros. Históricos incompletos no se reconstruyen como exactos.
- CSV automatizado no equivale a apertura manual en todas las versiones de Excel.

## Fase 9 obligatoria, no implementada aquí

Conservar los catorce puntos originales como requisitos de entrada, sin reducirlos a esta lista. Antes de cerrar la matriz se necesitan el documento original, entrevista, Anexo 7 y definiciones X1–X12. No inventar texto, correspondencias ni participación.

Tres PR controlados: funcionales/unitarias/integración; no funcionales; aceptación UAT e informe. Propuesta inicial a revisar: 32 casos unitarios adicionales, sin cuota ni redundancias (8 de importes/precios, 8 de milésimas, 6 de estados, 8 de CSV y 2 de identidad física).

No funcionales obligatorias: k6, humo 1 VU, carga 20 VU durante 2 minutos, estrés gradual hasta 50 con seguridad/recuperación. CargaSeeder aproximado de 300 clientes, 150 productos con presentaciones y 3.000 pedidos. Metas: p95 listados <1 s y errores HTTP <1 %. Servidor concurrente; no certificar producción con artisan serve.

ZAP Baseline solo contra instancia independiente autorizada. Compatibilidad 360/390/768/1024/1366 px, Chrome/Firefox y Edge cuando sea posible. Medir usabilidad de pedidos/envasados. Revisar CSRF efectivo, autenticación, permisos, XSS, entradas SQL maliciosas, cabeceras y auditorías.

Estructura reservada: docs/pruebas/00_informe_resumen.md, 02_casos_funcionales.md, 03_no_funcionales.md, 04_aceptacion_UAT.md, evidencias/ y tests/Performance/. No se crean ni ejecutan esos scripts en PR 5.

Lo no ejecutado se declara PENDIENTE DE EJECUCIÓN. No inventar firmas, tiempos, aprobaciones o resultados de trabajadores. Los guiones no sustituyen UAT.
