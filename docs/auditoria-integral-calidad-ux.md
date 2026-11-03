# Auditoría integral de calidad, integridad y experiencia de uso

## Alcance y método

Referencia: develop `ecc76506b3283d71332d87b0769dab60b31f259e`, posterior al PR50. Trabajo en `feature/auditoria-integral-calidad-ux`, en checkout separado del proyecto operativo. Se leyeron AGENTS.md y CLAUDE.md; no existe .ai/rules. Laravel 13.35.0 / PHP 8.3, Larastan 3.13.2, PHPUnit 12.5.38 y Vite 8. No se reinstaló Boost, ya disponible. La guía testing-best-practices citada por AGENTS no está disponible en las habilidades instaladas; se mantienen las convenciones y guardas existentes.

Esto es revisión de código, regresiones, pruebas de integración y navegador con datos ficticios; no demuestra ausencia universal de errores ni aceptación por trabajadores. Las operaciones que escriben datos y las suites se ejecutan exclusivamente en runners de GitHub. No se consultó ni modificó la base de Laragon durante esta auditoría. Sin migraciones nuevas, cambios de permisos, dependencias permanentes o despliegues.

## Hallazgos y decisiones

| ID | Clasificación / criticidad | Evidencia y causa | Corrección y regresión |
|---|---|---|---|
| AI-01 | Defecto confirmado / media | ProductoController, store (líneas 74–77 de la referencia) guarda la imagen sin compensación al fallar SQL; update (149–160) borra el archivo antiguo antes del commit. Un fallo ficticio deja el puntero anterior sin archivo o una carga huérfana. | Imagen antigua retirada con afterCommit; imagen nueva retirada con afterRollBack de Laravel, incluidas transacciones externas de los middleware. Fallos de limpieza se registran, sin ocultar el fallo SQL ni borrar la imagen vigente. Siete escenarios de crear/reemplazar/quitar, éxito, fallo y fallo posterior al controlador. |
| AI-02 | Defecto confirmado / baja | orders/units-form:9 y components/alerts:31 imprimen la misma lista de errores; el patrón se repite en categorías, roles, cumplimiento, envasado, indicadores y seguimiento. El navegador anterior muestra dos veces el error de cliente obligatorio. | Resumen único en el layout, también para confirmaciones duplicadas de categorías y cumplimiento reproducidas tras envasar; roles status/alert accesibles. Se conservan errores locales de campos y del modal contextual (este permite comprender el fallo al reabrirse y tiene alternativa global sin JS). No se altera validación ni se ocultan errores. |
| AI-03 | Defecto confirmado / baja | pedidos-unidades.js:147 llama mensaje.focus(); units-form:40 no admitía foco. La reproducción real deja el foco fuera del aviso. | tabindex=-1 en el destino existente; no entra en el orden normal de Tab. Navegador comprueba el foco al impedir un pedido sin líneas. |
| AI-04 | Mejora de usabilidad justificada / media | DisponibilidadInventarioController:53 limita el selector a 20 por página; los pedidos anteriores existen, pero hay que recorrer páginas sin un filtro específico de pedido. | Búsqueda por número o cliente en todos los estados pendientes ya existentes, parcial y sin tildes, máximo 100 caracteres. Conserva paginación de 20, filtros y selección. Regresión SQLite/MySQL con 23 pedidos; caso visual con 27 pendientes. |
| AI-05 | Consultas innecesarias confirmadas por patrón Eloquent / baja | PermisoMiddleware:35 de la referencia recargaba role.permisos en cada middleware de permiso: dos comprobaciones efectúan dos cargas completas. | Primera carga obligatoria por solicitud y reutilización mediante atributo privado del Request; ninguna caché entre solicitudes. Prueba contrasta el patrón anterior (4 SELECT) con dos comprobaciones actuales (2 SELECT) y exige 403 tras revocar un permiso en una solicitud nueva. No cambia asignaciones ni criterios de autorización. |
| AI-06 | Prevención de exposición accidental / baja | Los respaldos BD*.sql de la raíz y variantes privadas de .env no tenían una exclusión general específica. No se encontró un respaldo o credencial versionado en los archivos examinados. | Ignorar únicamente respaldos de la raíz y .env.*, conservando .env.example. No se abre, borra ni modifica el respaldo local. Esto no sustituye revisión de secretos o control de archivos ya rastreados. |
| AI-07 | Defecto de accesibilidad confirmado / baja | inventory/index.blade.php:41 del PR (icono antes de la corrección): el botón Consultar de Disponibilidad expone el nombre interno del icono: «search Consultar». El navegador no lo reconoce con su nombre visible exacto. | aria-hidden=true únicamente en el icono decorativo. La interacción real exige encontrar el botón por el nombre accesible Consultar en los cinco anchos. |
| AI-08 | Defecto responsive confirmado / baja | Cumplimiento, encabezado de la tarjeta por SKU: Chromium detectó 3–4 px de scroll horizontal en 360 px, dependiente de las letras del código ficticio. El h3 no permitía partir una cadena larga. | overflow-wrap:anywhere solo en títulos de tarjetas de cumplimiento en pantalla ≤650 px. El escenario usa ahora SKU largos deterministas; antes reproduce el desbordamiento a 360 px en las dos rutas que comparten la tarjeta y después exige que desaparezca; 390 px conserva la comprobación normal. No afecta tablas desplazables, escritorio o impresión. |

La primera ejecución de reproducción fue `41695253099`: foco, búsqueda y las tres operaciones de imagen fallaron contra código sin corregir. La primera fixture de errores no inyectaba correctamente el bag en la petición; se corrigió la fixture y se verificó el defecto con render de la vista y POST real en navegador. El único diagnóstico Larastan inicial era el tipo DOM de una aserción nueva; se resolvió con assertInstanceOf, sin exclusiones. No se presentan fallos de instrumentación como defectos productivos. Las siguientes iteraciones del arnés corrigieron expectativas de roles, guardas de fábrica, el texto interno de iconos y mayúsculas aplicadas por CSS; no se ampliaron permisos ni se eliminaron comprobaciones funcionales.

## Matriz por módulo

| Módulo | Revisión técnica y funcional | Evidencia / alcance |
|---|---|---|
| Autenticación, usuarios y contraseña | Login normaliza correo, verifica status/hash, limita intentos, regenera sesión; logout invalida y regenera CSRF. VerificarUsuarioActivo retira sesiones inactivas. Cambio exige contraseña actual y política vigente. | SesionUsuarioActivoTest, Security y administración existentes; UI de usuarios/roles y login ficticio. No se usaron contraseñas reales. |
| Roles, permisos y acceso directo | Rutas combinan auth/role/permiso; primera carga de relaciones por Request. Revocaciones vuelven a consultarse en la siguiente solicitud. | Nueva regresión de consulta/revocación; suites de permisos y accesos denegados; navegador usa roles ficticios. No se alteró el mapa aprobado. |
| Productos, variantes, presentaciones e imágenes | Validación y guardado de SKU, estado y precios conservados. Versionado impide edición obsoleta. Corregido ciclo de archivos ante rollback. MIME raster permitido, tamaño limitado, nombres generados; SVG no admitido. | ValidacionCatalogoTest y nuevas pruebas de imágenes con Storage::fake. Vistas de catálogo/formulario en cinco anchos. |
| Clientes | Correo nullable/email:rfc: sigue opcional. Normalización y unicidad histórica de documentos no se cambian. | ClientesTest, ValidacionClientesTest y buscador existente en SQLite/MySQL; pantalla de alta. |
| Pedidos, versiones y agrupaciones | Cantidades físicas, espera, retiro y precio histórico se conservan. Agrupar no añade importes. Retiro parcial usa versión, motivo, transacción e idempotencia; no reescribe despachos. | UsabilidadComercialTest, AjustePedidoHistoricoTest, IntegridadHistoricaTest y flujos PR45 existentes; copia de agrupaciones y retiro real ficticio en navegador. |
| Impresión | Dos notas distintas, numeración independiente, exclusión de cancelados incluso por URL directa. No se modificó el código de impresión. | Pruebas existentes y PDF Chromium: una página A4 horizontal con ambas notas; Firefox comprueba DOM/estilos de impresión, no genera PDF. Impresora física pendiente. |
| Envasado y materiales | Configuración habitual sin materiales; cantidades reales positivas. Transacción y bloqueos conservados; corrección administrativa y pérdida real diferenciadas. | EnvasadoUnidadesTerminadasTest, CorreccionEnvasadoTest, concurrencia MySQL; modal integrado con vista previa y confirmación ficticia. |
| Inventario y disponibilidad | No se presenta stock como reserva. Insumos permanecen históricos/opcionales, sin cuadrar saldos artificialmente. Falta/espera/activo/stock separados; mejorada localización de pedidos. | DisponibilidadInventarioTest y nuevas búsquedas SQLite/MySQL; tarjetas y botones móviles conservados. |
| Preparación y despacho | Preparar no genera stock; despachar exige unidades disponibles y descuenta solo salidas efectivas. Bloqueos ordenados, referencias y movimientos de origen se mantienen. | AtenderPedidoUnidades, DescontarInventarioPorDespacho; suites de flujos e inventario/concurrencia existentes, modal y pantallas en navegador. |
| Entregas, rechazos y retornos | Rechazo no aumenta stock. Recepción, clasificación y aprobación son operaciones diferentes; autorización, versión y referencia única por evento permiten aprobaciones parciales legítimas. | EntregasRetornosTest, ConcurrenciaRetornosTest y lectura de AprobarRetornoPedido; pantallas de entrega e historial. No se reconstruyen entregas antiguas no verificables. |
| Indicadores y quince CSV | Misma fuente/filtros para pantalla y exportación, importes comerciales separados de componentes físicos, correcciones con signo y movimientos únicos. CSV UTF-8/BOM, punto y coma y neutralización de fórmulas. | QuinceExportacionesMySqlTest, IndicadoresMySqlTest y pruebas de CSV existentes. No se reabrió manualmente Excel ni se certifican todas sus versiones. |
| Auditoría e incidencias | Eventos y movimientos originales conservados; motivos y responsables siguen trazables. Persistencia, permisos y categorías existentes no se cambian. | AuditoriaPersistenteTest, AuditoriaSeguridadTest y pantalla ficticia autorizada. |
| Esquema e instalaciones | Se conserva íntegro el cierre ordinario del PR50: nueva 46 tablas/50 ordinarias; saneada conserva retiro registrado; histórica no se fuerza a 46; parcial se bloquea. | Matriz CierreEsquemaSurtidos en SQLite/MySQL 8.4.3 y suites completas. Ninguna migración se modificó o ejecutó localmente. |

## Seguridad: riesgos y descartes

- **Riesgo potencial medio:** CSP se entrega en Report-Only. No impide por sí misma un XSS. La política vigente admite inline por los scripts/estilos/handlers actuales; no se activa una política restrictiva sin inventariar nonces y probar todas las pantallas. Queda como trabajo separado de endurecimiento, no vulnerabilidad explotada demostrada.
- **Riesgo potencial bajo:** fuentes Google externas, sin SRI estable para la hoja dependiente del navegador. Opcional futuro: alojarlas localmente con licencias y política definidas. No se debilita CSP para ocultar alertas.
- ZAP histórico (`zap_resumen_9b.json`, 25/10/2026) es pasivo, no un pentest actual: CSP, SRI, redirección grande, cookie sin HttpOnly y versión de servidor. XSRF-TOKEN es deliberadamente accesible a JS, mientras la cookie de sesión debe conservar HttpOnly: no se cambia CSRF para silenciar esa alerta. Las alertas informativas de atributo controlable no demuestran XSS; Blade escapa datos y el editor usa textContent. No se afirma explotación sin payload y contexto.
- La cabecera Server pertenece al servidor HTTP; el runner de desarrollo no representa un servidor Linux de producción. Pendiente revisar configuración del futuro alojamiento sin modificar Laragon.
- Consultas examinadas usan parámetros; BusquedaCatalogo solo acepta identificadores internos y liga el texto. Las expresiones SQL de reportes son nombres internos, no ORDER BY libre del usuario.
- No se encontró necesidad de cambiar permisos, sesiones, transacciones, idempotencia ni protecciones de stock. **Descartado** el presunto desbordamiento de envasado: RegistrarEnvasado ya valida 4294967295 antes de registrar nada; se conserva y prueba el límite.
- No hay nuevos escaneos ZAP, pruebas de penetración activa o certificación de seguridad completa. Su ejecución actual está **PENDIENTE DE EJECUCIÓN** sobre un entorno independiente acordado.

## Límites de la revisión de usabilidad

La ausencia de desbordamiento se exige al cuerpo/documento, no a las tablas que tienen desplazamiento interno deliberado. Los historiales extensos conservan ese patrón; convertirlos todos a tarjetas o reducir el formulario de atención mediante pasos sería una mejora opcional de prioridad baja, que requiere validar el flujo con trabajadores. No se ocultan columnas ni se cambia el significado de las operaciones para acortar pantallas.

Los campos de fecha conservan el control nativo del navegador: su presentación puede depender del idioma del navegador/sistema, mientras el valor enviado es ISO y el servidor usa Lima. El PDF y los textos formateados por la aplicación se revisan por separado. No se declara certificación WCAG ni se sustituyen dispositivos reales por cambiar el ancho de viewport.

## Rendimiento y límites

La mejora de permisos tiene medición de SELECT en regresión; no se inventan latencias o memoria nuevas. Las suites MySQL mantienen escenarios de 3 y 300 registros. La búsqueda parcial normalizada usa funciones SQL y puede requerir recorrer candidatos: no se añade un índice sin EXPLAIN y medición independiente que demuestren utilidad.

Se conservan límites documentados: verificador acumula el informe en memoria; la conciliación lee continuidad anterior al período; escenarios de Indicadores hasta 1.500 filas no certifican toda la aplicación. No se ejecutó carga contra Laragon ni se preparó infraestructura local.

`cierre_9b.json` contiene evidencia histórica de estrés50-cierre-003 completado con 50 VU y 65 muestras a 50, con 0% errores, y ensayos anteriores incompletos que permanecen registrados. No equivale a medir esta versión. Repetición de k6, p95 actuales, estrés y recuperación: **PENDIENTE DE EJECUCIÓN**; requiere entorno de carga independiente y recursos definidos. No se rebajan metas ni se declara aprobado por ejecutar PHPUnit.

## Evidencias y resultados

Las ejecuciones finales y artefactos se enlazan en el PR51. El workflow visual compara `ecc7650` con el código del PR usando el mismo arnés ficticio, en runners separados. Chromium y Firefox, 360/390/768/1024/1366 px: navegación, formularios, búsquedas, validaciones, foco, tarjetas, agrupaciones, retiro, modal, impresión y desbordamiento horizontal. Los SKU largos se fijan para reproducir el desbordamiento; otros identificadores sintéticos independientes pueden variar. Cantidades y distribución del escenario se mantienen.

Antes/después muestra la desaparición de la segunda lista de errores y la nueva búsqueda del selector. El aviso de pedido vacío recibe ahora el foco; no se rediseña el sistema ni se alteran escritorio/impresión por una regla CSS global. Artefactos incluyen PNG, resultados.json, index.html, mediciones y PDF de Chromium. Capturas seleccionadas y resultados originales están en [el índice de evidencias](pruebas/evidencias/auditoria-integral/README.md), con enlaces a los cuatro artefactos completos (retención de 14 días).

**PENDIENTE DE EJECUCIÓN:** Chrome/Edge de marca, dispositivos físicos, lector de pantalla, impresora real, evaluación instrumental completa de contraste/WCAG y apertura manual del nuevo CSV en distintas versiones de Excel. Chromium no se presenta como Google Chrome o Microsoft Edge. UAT con trabajadores reales no realizada, sin firmas, tiempos o aprobaciones inventados.

Operaciones locales: lectura de código, lint de PHP/JS y Pint; no suites completas, navegador, instalaciones pesadas, migraciones, seeders, limpieza ni conexiones a la base operativa. No se tocó main/JM_DEVELOP, .env, servidor 8000, MySQL 3306, usuarios o respaldos originales. La carpeta principal permanece en develop; el PR se entrega sin fusionar.

## Resultados automatizados actuales

Código funcional validado: `07439372eff8dcf851732ae31ba35f8f7eee2c6e`; arnés de navegador final: `547c9fa05c7ab08af2a15b6d73c3987f50d740cf`. [Ejecución de calidad](https://github.com/NightArrow450/Software_Gestion_Web_Microempresa/actions/runs/41704907055).

| Trabajo | Resultado real |
|---|---|
| Laravel y Security, SQLite en memoria | 733 casos aprobados, 6.221 aserciones. Security no se ejecuta dos veces. |
| MySQL 8.4.3 temporal e integración/concurrencia | 90 casos aprobados, sin omisiones. Incluye la matriz de instalación nueva, saneada, histórica, parcial y dependencias desconocidas. |
| JavaScript y Vite | 53 pruebas aprobadas y compilación terminada. |
| Larastan y Pint | Nivel 5: cero errores, sin exclusiones nuevas; Pint verifica los PHP del cambio y los archivos de referencia existentes. |
| Composer y npm audit | Sin avisos de vulnerabilidades en los manifiestos bloqueados de esta ejecución. No certifica ausencia universal de vulnerabilidades. |

[Ejecución visual final aprobada](https://github.com/NightArrow450/Software_Gestion_Web_Microempresa/actions/runs/41704906854): cuatro trabajos, 137 casos y 154 capturas por trabajo; reproducción antes y regresión después en Chromium/Firefox. Los nueve trabajos relevantes están aprobados. El antiguo workflow exclusivo del PR49 no aplica a este PR y aparece omitido por su condición original; no se han desactivado suites para conseguir resultados verdes.

La comparación determinista de cumplimiento a 360 px registra documento de 384 px antes y 360 px después. Confirmaciones y errores pasan de dos apariciones a una; el foco queda en el aviso; el pedido antiguo se encuentra por cliente sin tildes y muestra sus 24 unidades pendientes activas.

Se incorporan trece casos Laravel y un caso MySQL; se conservan las pruebas previas. En permisos, dos comprobaciones pasan de cuatro SELECT a dos, y una solicitud nueva debe rechazar el permiso revocado. No se extrapola esta medida a latencias de toda la aplicación.

La instalación nueva verificada termina con 46 tablas y 50 migraciones ordinarias; la base saneada que ya registró el retiro controlado conserva 51. Las históricas conservan sus 52 tablas cuando corresponde y admiten actualizaciones posteriores: no se confunde una migración registrada con un retiro realizado. Estos escenarios pertenecen exclusivamente a CI, no a una nueva ejecución sobre Laragon.

## Archivos modificados

- Código productivo: `app/Http/Controllers/ProductoController.php`, `app/Http/Controllers/DisponibilidadInventarioController.php` y `app/Http/Middleware/PermisoMiddleware.php`.
- Responsive: `public/css/sprint4.css`, únicamente títulos de tarjetas en pantallas pequeñas.
- Mensajes y foco: `resources/views/components/alerts.blade.php`, `orders/units-form.blade.php`, `categories/partials/form.blade.php`, `categories/index.blade.php`, `categories/show.blade.php`, `roles/create.blade.php`, `roles/index.blade.php`, `cumplimiento/show.blade.php`, `cumplimiento/lista-preparacion.blade.php`, `envasado/create.blade.php`, `envasado/materiales.blade.php`, `envasado/show.blade.php`, `indicadores/index.blade.php` y `seguimiento/historial.blade.php`, todos bajo `resources/views/`.
- Consulta de pedidos: `resources/views/inventory/index.blade.php`.
- Regresiones: `tests/Feature/AuditoriaIntegralTest.php`, `tests/UsabilidadEscenario.php`, `tests/MySql/UsabilidadMySqlTest.php`.
- Navegador y evidencias: `.github/workflows/auditoria-visual.yml`, `tests/Visual/preparar.php`, `tests/Visual/navegador.cjs` y capturas seleccionadas bajo `docs/pruebas/evidencias/auditoria-integral/`.
- Protección y documentación: `.gitignore`, este informe y `docs/calidad-y-pendientes.md`.

No cambian migraciones, modelos, servicios de inventario, precios, estados comerciales, permisos asignados, dependencias o configuración operativa. El usuario comercial puede consultar envasados y reportar; el registro corresponde a Administrador y Producción/Reparto según el seeder existente. La primera expectativa del navegador para ese rol era incorrecta y se corrigió en el arnés, sin ampliar permisos productivos.
