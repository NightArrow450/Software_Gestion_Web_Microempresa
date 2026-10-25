# Fase 9A: casos funcionales, integración y trazabilidad

Fuente principal: [especificación original de catorce puntos](evidencias/requisitos_originales_fase9.md). Resultado de la suite habitual: [579 aprobados](evidencias/laravel_9a.json). Hay una reproducción observada aparte, no incluida entre aprobados. La cobertura de la tabla es cobertura de requisitos por evidencia, no cobertura porcentual de código.

## Matriz de requisitos originales

| ID | Descripción y módulo | Prueba/archivo principal | Tipo | Resultado real | Estado | Observaciones |
| --- | --- | --- | --- | --- | --- | --- |
| 1 | Autenticación, usuarios, roles, permisos, catálogo/categorías | SeguridadAccesosTest, AdministradorInicialTest, ValidacionUsuariosTest, AdministracionFuncionalTest, ValidacionCatalogoTest | HTTP + SQLite | Escenarios incluidos en 579 aprobados | Cubierto para operaciones existentes | Las bajas admitidas son desactivación o borrado lógico; no se inventan rutas DELETE de productos/usuarios. |
| 2 | Pedido → confirmar → preparar → despachar → entregar → seguimiento | FlujosEnvasadoCompletoTest, dataset normal | Integración HTTP/SQLite | Aprobado; stock 7→2, un despacho, historial completo | Cubierto | No envasado ni doble descuento. |
| 3A | Envasado vinculado y parcial antes de preparar | FlujosEnvasadoCompletoTest vinculado; RegistroEnvasadoTest | Integración HTTP/SQLite | Aprobado; 2+3 producidas, tres insumos 100→95, terminado 7→12→7 | Cubierto | El vínculo no cambia el estado ni reserva. Estados no permitidos cubiertos por RegistroEnvasadoTest. |
| 3B | Producción anticipada anterior al pedido | FlujosEnvasadoCompletoTest anticipado | Integración HTTP/SQLite | Aprobado; producción con cero pedidos, luego entrega sin consumir insumos otra vez | Cubierto | No se exige ni inventa pedido al producir. |
| 3C | Correcciones, anulaciones, negativos, stock previo y verificador | CorreccionEnvasadoTest, RegistroEnvasadoTest, IntegracionEnvasadoTest, ConcurrenciaEnvasadoTest | SQLite + MySQL | Aprobados en sus suites; verificador sin inconsistencias nuevas en flujos nuevos | Cubierto | Se conserva diferencia entre recuperación de materiales y pérdida física, stock fungible y saldo inicial. |
| 4 | Permisos por rol, permisos de rutas y sesión inactiva | PermisosRutasTest, PermisosMaterialesEnvasadoTest; CuentaInactivaDiagnosticoTest | Seeders específicos/HTTP + diagnóstico | Matrices aprobadas; dashboard inactivo devuelve 200 | Parcialmente cubierto | D9A-01 abierto; no se declara aprobado el requisito completo. |
| 5 | Servicios: importes, empaques, estados, materiales, descuento/reversión | tests/Unit; EmpaqueVariablePorLineaTest; RegistroEnvasadoTest; CorreccionEnvasadoTest; DespachoPedidosTest | Unit sin kernel + integración | 33 Unit aprobadas, integración aprobada | Parcialmente cubierto en sentido literal de unitarias | Servicios de stock con BD se verifican por integración/concurrencia, no mediante mocks que eliminen garantías. |
| 6 | Humo GET principales, incluidas configuración/envasados | HumoRutasTest | HTTP/SQLite | 55 GET con admin y 55 con visitante, respuestas esperadas | Cubierto con excepciones explícitas | /, login autenticado y GET de compatibilidad redirigen; salud/login públicas no exigen sesión. storage firmado se excluye por ser infraestructura sin fixture de archivo firmado. |
| 7 | Casos, salidas, cobertura, concurrencia y vínculo académico | Este documento y evidencias | Documental + resultados ejecutados | Salidas reales adjuntas; MySQL 68 aprobadas | Parcialmente cubierto | X1–X12/Anexo 7 y porcentaje PCOV/Xdebug pendientes. |
| 8 | k6 humo/carga/estrés y CargaSeeder | tests/Performance/ reservado | No funcional | PENDIENTE DE EJECUCIÓN | Pendiente / 9B | Sin inicialización ni carga en 9A. Instancia independiente, no Laragon real. |
| 9 | ZAP, CSRF efectivo, XSS/buscadores, cabeceras, auditorías | Seguridad existente; nueva ejecución integral en 9B | No funcional/seguridad | Auditorías aprobadas; ZAP/CSRF efectivo pendientes | Parcialmente cubierto / 9B | Los POST PHPUnit no acreditan CSRF, que el framework omite normalmente al probar. |
| 10 | Compatibilidad y usabilidad | Lista de verificación reservada a 9B | Navegadores/personas | PENDIENTE DE EJECUCIÓN | Pendiente / 9B | 360/390/768/1024/1366, Chrome/Firefox y Edge si disponible; pedido 5 líneas ≤90 s; envasado sin meta aún. |
| 11 | Informe no funcional y reportes | 03_no_funcionales.md reservado | Documental | PENDIENTE DE EJECUCIÓN | Pendiente / 9B | No se crea un informe ficticio. |
| 12 | Casos UAT ligados a entrevista y cuestionario | 04_aceptacion_UAT.md reservado | Aceptación | PENDIENTE DE EJECUCIÓN | Pendiente / 9C | Faltan fuentes académicas y participación real. |
| 13 | Actas, participantes, tiempos y firma | Plantillas de 9C | Aceptación | PENDIENTE DE EJECUCIÓN | Pendiente / 9C | Sin firmas o tiempos inventados. |
| 14 | Informe por tipos, defectos y cierre | 00_informe_resumen.md | Documental | Informe parcial real | Parcialmente cubierto | D9A-01 y 9B/9C impiden cierre global. |

Los puntos 3A/3B/3C son subdivisiones del punto 3 original, no tres requisitos añadidos para inflar la cobertura.

## Casos ejecutados y resultados

Para todas las filas siguientes, indicador X1–X12 y requisito de Anexo 7: **PENDIENTE de documentos originales**. Ese estado no se sustituye por una equivalencia supuesta.

| Caso | Módulo / requisito | Precondición | Pasos | Esperado | Obtenido | Estado |
| --- | --- | --- | --- | --- | --- | --- |
| F9A-01 | Acceso y usuarios / R1 | SQLite vacío, roles/permisos específicos, admin ficticio | Crear cuenta, consultar, editar y desactivar | Guarda cambios admitidos y conserva hash al omitir contraseña | Comprobado por AdministracionFuncionalTest | Aprobado |
| F9A-02 | Roles/permisos / R1,R4 | Admin ficticio | Crear permiso/rol, asignar, retirar, editar y dar baja lógica | Mantener roles base; bajas lógicas conservan registros | Comprobado; sin borrados físicos | Aprobado |
| F9A-03 | Catálogo/categorías / R1 | Admin y Producción ficticios | Crear/editar/desactivar producto/categoría; enviar código/nombre inválidos e intentar escritura sin permiso | Persistencia válida; 422/403; presentaciones conservadas | Comprobado | Aprobado |
| F9A-04 | Pedido normal / R2 | Terminado inicial 7, pedido de 5 | Registrar, confirmar, preparar, despachar, entregar, consultar seguimiento/historial | Terminado 2, un movimiento de despacho, seis estados | Comprobado | Aprobado |
| F9A-05 | Envasado por pedido / R3A | Terminado inicial 7; tres materiales con entrada 100 cada uno | Configurar por HTTP; registrar/confirmar pedido; producir 2 y 3; preparar/despachar/entregar | Insumos 95; terminado 7→12→7; vínculo mantiene confirmado | Comprobado, verificador correcto durante el flujo | Aprobado |
| F9A-06 | Anticipado / R3B | Igual al caso anterior, cero pedidos al producir | Producir 2 y 3 antes de registrar pedido; completar entrega | Dos envasados sin vínculo, sin producción/descuento de insumos adicional al despacho | Comprobado | Aprobado |
| F9A-07 | Negativos/correcciones / R3C | Fixtures de pruebas existentes | Ejecutar RegistroEnvasadoTest, CorreccionEnvasadoTest e integración | Insuficiencia revierte; doble operación se rechaza; pérdidas no devuelven materiales | Suite aprobada; escenarios existentes referenciados, no duplicados | Aprobado |
| F9A-08 | Permisos / R4 | Seeders específicos en memoria | Comparar permisos de middleware con catálogo y Admin; revisar cinco de envasado por rol | Ningún permiso de ruta inexistente; distribución aprobada | Comprobado | Aprobado |
| F9A-09 | Sesión inactiva / R4 | Usuario autenticado, luego desactivado | GET pedidos y reproducción GET dashboard | Expulsión de rutas protegidas | Pedidos redirige; dashboard 200 | Observado, D9A-01 |
| F9A-10 | Servicios / R5 | Sin kernel/BD para Unit; framework aislado para errores | 18 métodos previstos + dos de factor histórico; errores de formatos en Feature | Exactitud, estados admitidos/prohibidos, factor histórico, sin fórmula CSV ejecutable | 33 casos Unit aprobados; 12 de validación focalizada aprobados | Aprobado en alcance indicado |
| F9A-11 | Rutas / R6 | Fixtures de usuario, permiso, categoría, producto, presentación, pedido y envasado | Recorrer GET con parámetros válidos; visitante y admin; forzar lectura CSV | Respuesta según contrato y movimientos sin cambios | 55 rutas por sesión comprobadas | Aprobado con excepciones de matriz |
| F9A-12 | Surtidos y distribución ampliada | Fixtures existentes de composición variable y stock | SurtidosTest y SurtidosMySqlTest: normal+surtido, cierre pendiente, despacho, rechazo/retorno por aroma | Precios únicos, historial y reincorporación apta autorizada | Suites aprobadas | Aprobado |
| F9A-13 | Quince CSV y auditoría | MySQL aislado; pedidos normal/surtido/pendiente/cancelado, producción/corrección, entrega/retorno/aprobación | Exportar todos; comprobar filas no vacías, encabezados, UTF-8/BOM, separador y filtros; contrastar existencias | Consistencia con fuente; no generar movimientos por leer | QuinceExportacionesMySqlTest aprobado; stock ficticio 104 y número de movimientos conservados tras lectura | Aprobado |

Auditoría conserva además AuditoriaPersistenteTest/AuditoriaSeguridadTest: autorización, responsables, campos permitidos y reversión de stock si falla la auditoría. Indicadores mantiene los casos de SKU histórico y continuidad del PR #40. Los CSV comparan estructura/filas con su fuente y controles conocidos; esto no acredita todas las combinaciones posibles de filtros ni una apertura manual de todos en Excel.

## Comandos reproducibles y salidas

Desde la raíz del checkout, con dependencias del lock instaladas:

```powershell
php -d auto_prepend_file=tests/bootstrap.php artisan test --compact tests/Feature/EntornoPruebasSeguroTest.php
php -d auto_prepend_file=tests/bootstrap.php artisan test --compact
php -d auto_prepend_file=tests/bootstrap.php vendor/bin/phpunit --list-tests
php -d auto_prepend_file=tests/bootstrap.php vendor/bin/phpstan analyse --memory-limit=1G --no-progress
php -d auto_prepend_file=tests/bootstrap.php vendor/bin/pint --dirty --format agent
node --test tests/js/*.test.cjs
npm run build
composer audit --locked
npm audit --package-lock-only --audit-level=low
```

Salida real de la línea base, develop antes de modificar pruebas:

```json
{"tool":"phpunit","result":"passed","tests":524,"passed":524,"assertions":2869,"duration_ms":70928}
```

Salida real de Laravel ampliado:

```json
{"tool":"phpunit","result":"passed","tests":579,"passed":579,"assertions":3664,"duration_ms":55700}
```

La evidencia JSON registra la ejecución de cierre, cuya duración puede diferir. No se cambian cantidades para aparentar más cobertura.

MySQL local requiere primero instancia independiente, identidad verificada, MYSQL_TEST_ENABLE=1 y variables MYSQL_TEST_HOST/PORT/DATABASE/USERNAME/PASSWORD privadas. Usar el prefijo dhacer_eco_limp_test_ y un identificador nuevo por ejecución integral, porque las pruebas de migración histórica requieren una base inicial propia. Nunca usar valores de Laragon o la copia de respaldo. El bootstrap privado añade la comprobación de versión/puerto/datadir y después carga tests/bootstrap.php; no se publica porque lee las credenciales privadas de prueba.

```powershell
php -d auto_prepend_file=<ruta-privada-del-bootstrap-verificado> vendor/bin/phpunit --configuration phpunit.mysql.xml
```

En GitHub, el workflow existente prepara/verifica un contenedor MySQL 8.4.3 temporal y ejecuta la suite con variables efímeras: [procedimiento CI](../integracion-continua.md). Es la alternativa reproducible sin configuración local privada. No apuntar este comando a una base existente no verificada ni habilitarlo con credenciales empresariales.

Salida real MySQL independiente, incluyendo concurrencia y quince CSV:

```json
{"tool":"phpunit","result":"passed","tests":68,"passed":68,"assertions":1089,"duration_ms":112586}
```

## Límite de aceptación

D9A-01 sigue abierto. El requisito 9 necesita comprobar CSRF efectivo y ZAP en 9B; los POST de pruebas no lo demuestran. Compatibilidad, carga y aceptación humana no se ejecutaron aquí. Ninguna aprobación de esta tabla representa la aceptación UAT de trabajadores.
