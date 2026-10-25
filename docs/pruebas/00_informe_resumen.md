# Fase 9A: informe técnico parcial

Fecha: 25/10/2026, America/Lima. Base: develop, integración fa437077fb7cdb7d8a1c9437353d1fa394ad6b42. Rama: feature/fase9a-pruebas-funcionales-unitarias.

**La Fase 9 NO está aprobada en conjunto.** Las suites predeterminadas pasan, pero existe un defecto observado D9A-01 con reproducción fallida, pendiente de autorización de corrección. 9B y 9C no se han iniciado.

## Fuente y alcance

Los catorce requisitos originales están [archivados con identificación y hash de fuente](evidencias/requisitos_originales_fase9.md). 9A evalúa 1–7, incluidos 3A/3B/3C, y prepara este informe del 14. 8–11 corresponden a 9B; 12–13 a 9C. No se omiten ni sustituyen.

La entrevista, Anexo 7 y cuestionario X1–X12 no fueron proporcionados: trazabilidad académica PENDIENTE. Se continúa la evaluación técnica sin inventar equivalencias.

## Resultados reales

| Ejecución | Casos | Aprobados | Observados/fallidos | Pendientes |
| --- | ---: | ---: | ---: | --- |
| Línea base Laravel, develop | 524 | 524 | 0 | Ninguno dentro de esa suite |
| Laravel habitual final | 579 | 579 | 0 | Cobertura porcentual pendiente |
| MySQL 8.4.3 independiente | 68 | 68 | 0 | Sin omisiones en la suite |
| JavaScript | 19 | 19 | 0 | Navegadores completos en 9B |
| Reproducción D9A-01, ejecución explícita | 1 | 0 | 1 | Corrección autorizada y regresión |
| Carga/estrés, ZAP, compatibilidad y usabilidad finales | Sin ejecutar | — | — | 9B |
| UAT con trabajadores | Sin ejecutar | — | — | 9C |

Los grupos no son cantidades de reglas únicas ni deben sumarse como funcionalidades distintas. Los quince CSV se verifican dentro de un caso MySQL, no son quince casos adicionales al total 68. La reproducción D9A-01 no forma parte de los 579 aprobados.

Larastan nivel 5: cero errores. Pint del alcance: aprobado. Vite 8.3.4: compilación aprobada. Auditorías de Composer/npm: cero vulnerabilidades en esta ejecución. Se conservan los avisos no bloqueantes de fontaine opcional y, cuando aparece, tiempos de plugins de compilación; no equivalen a fallos de negocio ni se agregaron dependencias para ocultarlos.

Ver [casos y matriz](02_casos_funcionales.md), [salida Laravel](evidencias/laravel_9a.json), [salida MySQL](evidencias/mysql_9a.json) e [inventario descubierto](evidencias/inventario_laravel_9a.json).

## Métodos, casos y cobertura

| Suite | Archivos | Métodos de prueba | Casos con proveedores |
| --- | ---: | ---: | ---: |
| Unit sin kernel Laravel ni BD | 8 | 26 | 33 |
| Feature | 61 | 357 | 535 |
| Security | 1 | 11 | 11 |
| Laravel total | 70 | 394 | 579 |

Unit partía de 6 métodos/casos en dos archivos. Se agregaron los 18 métodos previstos y dos sobre factores históricos de empaque: 26 métodos, 33 casos. La matriz de estados comprueba 121 pares dentro de un método; no se anuncian 121 funcionalidades nuevas.

Feature incluye HTTP, servicios con framework, persistencia, validaciones y configuración: 535 casos no son 535 flujos completos. Security se ejecuta una sola vez. No se eliminó ninguna prueba anterior ni se restauró el ejemplo Unit eliminado.

Las unitarias emplean clases/modelos existentes cuando no necesitan kernel ni conexión. Los errores que construyen ValidationException se prueban en Feature, porque usan el validador Laravel. Los descuentos, bloqueos, transacciones y reversión se conservan como integración real, sin mocks de stock.

PHP 8.3.36 no tiene PCOV ni Xdebug cargados. **Cobertura porcentual de líneas/ramas: PENDIENTE DE EJECUCIÓN.** No se deduce un porcentaje a partir del número de pruebas.

## Aislamiento comprobado

- Antes de la línea base se ejecutó EntornoPruebasSeguroTest: 4 casos, 26 aserciones, aprobados.
- tests/bootstrap.php fuerza APP_ENV=testing, SQLite :memory:, servicios en memoria y cachés por PID. Su resolver rechaza otras bases SQLite y MySQL no habilitado con prefijo de pruebas.
- RolesEscenario comprueba driver sqlite y base :memory: antes de los seeders específicos de roles/permisos en sus fixtures. No invoca DatabaseSeeder, AdminUserSeeder ni DemoSeeder.
- MySQL se ejecutó en un proceso independiente, puerto 3312, datadir temporal de pruebas, versión 8.4.3, base prefijada dhacer_eco_limp_test_f9a_8c42 y derivados. Un bootstrap privado verificó versión/puerto/datadir efectivos mediante lectura antes de permitir los helpers de migración. Credenciales ficticias limitadas al prefijo de pruebas, nunca publicadas.
- Los workers de concurrencia conservan las protecciones existentes, conexiones heredadas y marcadores de propiedad. No se sustituyeron por mocks.
- No se accedió para estas escrituras a software_gestion_web_microempresa ni a ecolimp_respaldo_prueba. No se modificó .env, configuración operativa, código productivo, migraciones ni seeders.

## Defectos

| ID | Severidad | Resultado observado | Estado y propuesta |
| --- | --- | --- | --- |
| D9A-01 | Media / P2 | Cuenta desactivada con sesión conservada accede a GET /dashboard: HTTP 200. Las rutas con permiso sí la expulsan. | Abierto. Validar estado activo en todas las rutas autenticadas, cerrar sesión/invalidate/regenerar token y redirigir a login. Requiere autorización; no se cambió producción. |

La causa es la diferencia entre rutas que aplican RoleMiddleware/PermisoMiddleware, que validan status, y dashboard con solo auth. El alcance comprobado es dashboard; no se afirma acceso a pedidos ni escalamiento de permisos. Ver [reproducción y propuesta](evidencias/D9A-01.md).

La prueba del defecto se conserva en evidencias y se ejecuta explícitamente con salida 1. No está dentro de las suites predeterminadas, cuya configuración no se cambió. Esto se declara para no confundir CI verde con ausencia de defectos. Al autorizar la corrección deberá incorporarse la regresión a la suite habitual y pasar.

## Límites de cierre

R4 queda parcialmente cubierto por D9A-01. R5 tiene unitarias de contratos aislables e integración real de servicios transaccionales; no se presenta el descuento de BD como función pura. R6 admite redirecciones legítimas y rutas públicas; no se modifica el sistema para forzar 200 o 403 donde su contrato es otro.

9B deberá ejecutar k6, ZAP, compatibilidad y tiempos con autorización previa del entorno. 9C deberá incorporar los documentos académicos y resultados reales de trabajadores. Se mantienen [las limitaciones de calidad previas](../calidad-y-pendientes.md), incluidas memoria del verificador y mediciones de volumen que no certifican carga HTTP.

**Conclusión:** ampliación técnica de 9A preparada para revisión, con defecto pendiente explícito. No constituye aceptación de empresa ni cierre de Fase 9.
