# Fase 9A: informe técnico parcial

Fecha: 25/10/2026, America/Lima. Base: develop, integración fa437077fb7cdb7d8a1c9437353d1fa394ad6b42. Rama: feature/fase9a-pruebas-funcionales-unitarias.

**La Fase 9 NO está aprobada en conjunto.** D9A-01 fue corregido con autorización específica y su reproducción ahora es una regresión permanente aprobada. En 9B se inicializó MySQL independiente y se creó únicamente la base ficticia vacía con sus cuentas limitadas. El control de rutas Windows bloqueó el avance antes de migraciones y CargaSeeder; los servidores ficticios quedaron detenidos. No se ejecutaron cargas ni escaneos. Ver el [registro de ejecución y bloqueo](03_no_funcionales.md#ejecución-integral-autorizada-y-bloqueo-previo-al-esquema). 9C no se ha iniciado. Las cifras de este informe corresponden al cierre de 9A; no se reutilizan como carga/estrés.

## Fuente y alcance

Los catorce requisitos originales están [archivados con identificación y hash de fuente](evidencias/requisitos_originales_fase9.md). 9A evalúa 1–7, incluidos 3A/3B/3C, y prepara este informe del 14. 8–11 corresponden a 9B; 12–13 a 9C. No se omiten ni sustituyen.

La entrevista, Anexo 7 y cuestionario X1–X12 no fueron proporcionados: trazabilidad académica PENDIENTE. Se continúa la evaluación técnica sin inventar equivalencias.

## Resultados reales

| Ejecución | Casos | Aprobados | Observados/fallidos | Pendientes |
| --- | ---: | ---: | ---: | --- |
| Línea base Laravel, develop | 524 | 524 | 0 | Ninguno dentro de esa suite |
| Laravel habitual tras corregir D9A-01 | 588 | 588 | 0 | Cobertura porcentual pendiente |
| MySQL 8.4.3 independiente | 68 | 68 | 0 | Sin omisiones en la suite |
| JavaScript | 19 | 19 | 0 | Navegadores completos en 9B |
| Reproducción y regresiones D9A-01, ejecución focalizada | 9 | 9 | 0 | Incluidas en los 588; no sumar otra vez |
| Carga/estrés, ZAP, compatibilidad y usabilidad finales | Sin ejecutar | — | — | 9B |
| UAT con trabajadores | Sin ejecutar | — | — | 9C |

Los grupos no son cantidades de reglas únicas ni deben sumarse como funcionalidades distintas. Los quince CSV se verifican dentro de un caso MySQL, no son quince casos adicionales al total 68. La reproducción D9A-01 falló originalmente y ahora aprueba dentro de los 588; el resultado histórico se conserva en su informe.

Reejecución tras corregir D9A-01: Laravel 588/3768 aserciones (64625 ms), MySQL 68/1089 (146496 ms), JavaScript 19 (254.0974 ms). Larastan nivel 5: cero errores. Pint del alcance: aprobado. Vite 8.3.4: compilación aprobada (2.79 s). Auditorías de Composer/npm: cero vulnerabilidades en esta nueva ejecución. Se conservan los avisos no bloqueantes de fontaine opcional y, cuando aparece, tiempos de plugins de compilación; no equivalen a fallos de negocio ni se agregaron dependencias para ocultarlos.

Ver [casos y matriz](02_casos_funcionales.md), [salida Laravel](evidencias/laravel_9a.json), [salida MySQL](evidencias/mysql_9a.json) e [inventario descubierto](evidencias/inventario_laravel_9a.json).

## Métodos, casos y cobertura

| Suite | Archivos | Métodos de prueba | Casos con proveedores |
| --- | ---: | ---: | ---: |
| Unit sin kernel Laravel ni BD | 8 | 26 | 33 |
| Feature | 63 | 364 | 544 |
| Security | 1 | 11 | 11 |
| Laravel total | 72 | 401 | 588 |

Unit partía de 6 métodos/casos en dos archivos. Se agregaron los 18 métodos previstos y dos sobre factores históricos de empaque: 26 métodos, 33 casos. La matriz de estados comprueba 121 pares dentro de un método; no se anuncian 121 funcionalidades nuevas.

Feature incluye HTTP, servicios con framework, persistencia, validaciones y configuración: 544 casos no son 544 flujos completos. Security se ejecuta una sola vez. No se eliminó ninguna prueba anterior ni se restauró el ejemplo Unit eliminado.

Las unitarias emplean clases/modelos existentes cuando no necesitan kernel ni conexión. Los errores que construyen ValidationException se prueban en Feature, porque usan el validador Laravel. Los descuentos, bloqueos, transacciones y reversión se conservan como integración real, sin mocks de stock.

PHP 8.3.36 no tiene PCOV ni Xdebug cargados. **Cobertura porcentual de líneas/ramas: PENDIENTE DE EJECUCIÓN.** No se deduce un porcentaje a partir del número de pruebas.

## Aislamiento comprobado

- Antes de la línea base se ejecutó EntornoPruebasSeguroTest: 4 casos, 26 aserciones, aprobados.
- tests/bootstrap.php fuerza APP_ENV=testing, SQLite :memory:, servicios en memoria y cachés por PID. Su resolver rechaza otras bases SQLite y MySQL no habilitado con prefijo de pruebas.
- RolesEscenario comprueba driver sqlite y base :memory: antes de los seeders específicos de roles/permisos en sus fixtures. No invoca DatabaseSeeder, AdminUserSeeder ni DemoSeeder.
- MySQL se ejecutó en un proceso independiente, puerto 3312, datadir temporal de pruebas, versión 8.4.3, base nueva prefijada dhacer_eco_limp_test_d9a01_25a y derivados. Un bootstrap privado verificó versión/puerto/datadir efectivos mediante lectura antes de permitir los helpers de migración. Credenciales ficticias limitadas al prefijo de pruebas, nunca publicadas. El primer intento se bloqueó por conexión rechazada mientras arrancaba ese servidor; no inició pruebas. Tras confirmar ready for connections e identidad, la nueva ejecución completa aprobó.
- Los workers de concurrencia conservan las protecciones existentes, conexiones heredadas y marcadores de propiedad. No se sustituyeron por mocks.
- No se accedió para estas escrituras a software_gestion_web_microempresa ni a ecolimp_respaldo_prueba. No se modificó .env, migraciones ni seeders. El único cambio productivo autorizado es VerificarUsuarioActivo y su registro/prioridad en bootstrap/app.php.

## Defectos

| ID | Severidad | Resultado observado | Estado y propuesta |
| --- | --- | --- | --- |
| D9A-01 | Media / P2 | Antes: dashboard 200 con cuenta inactiva. Después: sesión invalidada, HTML redirige a login y JSON responde 401. | Corregido y verificado. Comprobación común de status antes de los controles protegidos; reproducción permanente y sesiones simultáneas aprobadas. |

La causa era la diferencia entre rutas que aplican RoleMiddleware/PermisoMiddleware, que validan status, y dashboard con solo auth. No se afirmó acceso indebido a pedidos ni escalamiento de permisos. Ver [reproducción, corrección y regresiones](evidencias/D9A-01.md).

La prueba se trasladó a tests/Feature conservando su reproducción y aserciones. Ahora pasa y se ejecuta una sola vez dentro de la suite habitual; no se modificó phpunit.xml. El resultado fallido original permanece documentado. Las pruebas nuevas comprueban también login real, invalidación del almacenamiento y token, dos sesiones existentes, JSON, roles activos y rutas públicas.

## Límites de cierre

R4 tiene la regresión de sesión inactiva corregida y sus matrices existentes aprobadas. R5 tiene unitarias de contratos aislables e integración real de servicios transaccionales; no se presenta el descuento de BD como función pura. R6 admite redirecciones legítimas y rutas públicas; no se modifica el sistema para forzar 200 o 403 donde su contrato es otro.

9B deberá ejecutar k6, ZAP, compatibilidad y tiempos con autorización previa del entorno. 9C deberá incorporar los documentos académicos y resultados reales de trabajadores. Se mantienen [las limitaciones de calidad previas](../calidad-y-pendientes.md), incluidas memoria del verificador y mediciones de volumen que no certifican carga HTTP.

**Conclusión:** ampliación técnica de 9A preparada para revisión, con D9A-01 corregido. No constituye aceptación de empresa ni cierre de Fase 9.
