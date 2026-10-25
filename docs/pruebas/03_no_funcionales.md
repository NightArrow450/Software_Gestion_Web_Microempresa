# Fase 9B: pruebas no funcionales — etapa 1

Fecha: 25/10/2026. Base develop/50ac59e. Rama feature/fase9b-pruebas-no-funcionales.

**Estado: PREPARADO, NO COMPLETADO.** Esta etapa desarrolla herramientas y controles. No autoriza instalar, crear/arrancar infraestructura local, inicializar bases, ejecutar CargaSeeder, carga/estrés ni ZAP. El propietario autorizó separadamente solo la CI efímera habitual de GitHub. 9C sigue pendiente.

## Requisitos y estados

| Requisito original | Preparación | Estado de ejecución real |
| --- | --- | --- |
| 8, k6/carga/estrés/datos coherentes | Seeder protegido, manifiesto, recorrido, lanzador y monitor | PENDIENTE DE EJECUCIÓN Y AUTORIZACIÓN |
| 9, seguridad | Plan ZAP nativo pasivo y protocolo HTTP efectivo | PENDIENTE DE EJECUCIÓN Y AUTORIZACIÓN |
| 10, compatibilidad/usabilidad | Matriz 12 áreas × 3 navegadores × 5 anchos y procedimientos | PENDIENTE DE EJECUCIÓN |
| 11, informe/evidencias | Este documento y formatos sin métricas inventadas | PREPARADO; informe final pendiente |

No confundir PREPARADO con EJECUTADO Y APROBADO. Cuando se ejecute se registrará EJECUTADO Y APROBADO o EJECUTADO CON OBSERVACIONES, con datos y evidencia; una interrupción también se conserva. Las suites de desarrollo no son resultados de estrés, ZAP ni aceptación humana.

## Herramientas e instalación pendiente

Diagnóstico: Windows 10 10.0.19045, i7-6700HQ, 4 núcleos/8 hilos, unos 12 GiB RAM y 2.3 GiB libres en la lectura inicial. PHP 8.3.36 ZTS, Laravel 13.35.0, Apache 2.4.68 WinNT, MySQL 8.4.3, Node 22.23.2 disponibles. Registrar de nuevo el equipo y memoria antes de ejecutar.

Chrome 146.0.7680.80 y Edge 156.0.4314.42 se localizaron por binario; no son revisiones visuales. Firefox, k6, ZAP, Java y Docker no se localizaron; WSL no tiene distribución registrada utilizable. No se instalaron.

Después de autorización: descargar k6 local de [Grafana](https://grafana.com/docs/k6/latest/set-up/install-k6/), ZAP nativo de [ZAP](https://www.zaproxy.org/download/) y Java de un distribuidor oficial enlazado por ZAP; verificar hashes/versiones antes de ejecutar. La versión ZAP consultada 2.17.0 requiere Java 17 o superior. Elegir/fijar una versión Java mantenida compatible al instalar. Firefox desde [Mozilla](https://www.mozilla.org/firefox/). Sin Docker, cuentas cloud ni servicios de pago. No desactivar antivirus para instalar.

## Arquitectura exacta propuesta, aún no creada

Raíz privada candidata: C:/Users/Stalin/AppData/Local/DhacerRendimiento/f9b_001/. Checkout en checkout/, MySQL en mysql/datos/, configuración privada y reportes crudos fuera de Git. No reutilizar respaldos ni datos de software_gestion_web_microempresa o ecolimp_respaldo_prueba.

| Componente | Propuesta |
| --- | --- |
| Apache | Binario existente 2.4.68, configuración propia, 127.0.0.1:8089, WinNT 64 hilos, logs/PID propios |
| PHP | 8.3.36 ZTS con php8apache2_4.dll, php.ini separado, 128 MiB por solicitud, errores a archivo privado |
| MySQL | 8.4.3, 127.0.0.1:3313, mysqlx desactivado, datadir/UUID/PID nuevos, 80 conexiones, buffer InnoDB 256 MiB |
| Base | dhacer_eco_limp_demo_f9b_001, nueva y exclusivamente sintética |
| Cuenta aplicación | demo_f9b_001 limitada literalmente a esa base; sin roles ni privilegios globales/delegación |
| Almacenamiento | storage, sesiones y cachés exclusivos del checkout; cookie dhacer_f9b_001 y APP_KEY propios |
| Servicios | Cola sync, correo log, sin túneles ni integraciones externas reales |

Las plantillas están en tests/Performance/configuracion/. 8449 queda como candidato para un perfil HTTPS futuro; la plantilla actual configura solo HTTP 8089. No se afirma validación de TLS o Secure cookies en HTTP. Comprobar nuevamente puertos libres y probar httpd -t con la configuración privada autorizada antes de arrancar. No cambiar archivos/configuración del Apache o MySQL operativo.

La cuenta de migración futura necesitará CREATE/ALTER/INDEX/REFERENCES/TRIGGER y DML sobre la base exacta; la cuenta web solo DML requerido. La creación de cuentas/base corresponde a una operación administrativa específica sobre la instancia nueva, autorizada por separado. No conceder privilegios globales para evitar un rechazo del verificador. Si se quiere observar performance_schema con privilegios adicionales, usar un observador separado y pedir autorización; nunca ampliar la cuenta web.

## Identidad: cierre ante cualquier discrepancia

1. Antes de crear infraestructura: aprobar rutas, binarios, puertos, cuentas y límites. Luego, solo con autorización, crear la instancia nueva. No hay instalador ni reset automático en este PR.
2. Antes de inicializar el esquema: consultar en la instancia identificada VERSION(), @@port, @@datadir, @@server_uuid, CURRENT_USER(), USER(), CURRENT_ROLE() y SHOW GRANTS. Comparar además PID, binario, argumentos y listeners desde el sistema operativo. Esa primera consulta administrativa no usa las credenciales de Laragon ni una base protegida.
3. Manifiesto externo: checkout real distinto del operativo, identidad exacta, fecha de caducidad, operaciones explícitas, SHA-256 del manifiesto/configuración Apache y argumentos de ambos procesos. No basta con nombre de base, APP_ENV o una variable aislada.
4. Después de crear la base vacía autorizada: verificar también DATABASE() desde la conexión real de Laravel. EntornoDemostracion comprueba concesiones literales, sin GRANT OPTION/globales/patrones amplios; AutorizacionRendimiento añade datadir efectivo, versión 8.4.3, roles NONE y procesos/listeners.
5. Configuración privada mediante auto_prepend_file, sin modificar .env operativo. Manifiesto y configuración deben estar fuera del checkout; limitar ACL al operador. Sus huellas deben ser revisadas por el propietario, no autogenerarse como aprobación.
6. Comprobar identidad de nuevo antes de migraciones, seeder y cada ensayo. La carga revalida cada 100 pedidos. Si hay discrepancia, conservar evidencia y detener; no hacer rollback, reintento, restauración ni reparación automática.

El Alias /__control_rendimiento existe solo en la plantilla Apache aislada. Comprueba DocumentRoot, el manifiesto, procesos y MySQL; responde con HMAC de un nonce nuevo. k6 verifica firma y origen antes de credenciales y módulos. No se agrega ninguna ruta pública productiva.

Limitación: las comprobaciones son una defensa contra errores operativos, no una protección frente a un administrador local malicioso capaz de cambiar código, manifiesto y sistema. Debe mantenerse sin cambios la instancia durante cada ensayo.

## Datos sintéticos y CargaSeeder

Preparado, NO EJECUTADO: 300 clientes, 150 productos con tres aromas/SKU cada uno, 3000 pedidos, 50 cuentas comerciales de VU y cuentas separadas de administración/producción. Nombres sintéticos y correos .invalid; sin documentos, teléfonos ni datos personales reales.

Los insumos de prueba tienen cantidades explícitamente sintéticas; configuraciones por presentación con envase/etiqueta/contratapa. No constituyen recetas reales ni consumen mangas. Stock inicial identificado; movimientos posteriores generados por servicios existentes, no reconciliados artificialmente.

Pedidos normales por unidad y empaque de 12/15, surtidos con composiciones distintas, algunos pendientes sin demanda ficticia. Estados recorridos mediante TransicionesPedido; producción mediante RegistrarEnvasado; desglose físico/entregas/retornos/aprobaciones mediante servicios actuales. Precio comercial único, ningún componente suma su precio de nuevo. Se generan envasados anticipados y vinculados y retornos aptos/dañados; el inventario se verifica al terminar.

Las fechas de pedidos cubren hasta 30 días; movimientos/envasados son operaciones sintéticas registradas en la fecha de ejecución, no se alteran timestamps de movimientos inmutables para aparentar otra antigüedad. Si se necesitan períodos históricos adicionales, acordar otro dataset antes de ejecutarlo.

La base debe contener solo el esquema vacío: 44 tablas, 45 migraciones y ninguna fila en las demás tablas, incluidas categorías/configuraciones. Una marca exclusiva fuera de Git impide repetir una carga iniciada, incluso si falla. No se modifica DatabaseSeeder ni se llama DemoSeeder/AdminUserSeeder. La ejecución positiva está PENDIENTE; no se invocó run() ni en una prueba de desarrollo. El test negativo comprueba solamente la puerta común anterior a cualquier acceso DB.

## Comandos futuros por autorización

Desde el checkout aislado, con RENDIMIENTO_CONFIG privado preparado y sin credenciales en argumentos:

```powershell
$bootstrap = (Resolve-Path tests/Performance/configuracion/privada-bootstrap.php).Path
php -d "auto_prepend_file=$bootstrap" tests/Performance/verificar-entorno.php verificar
php -d "auto_prepend_file=$bootstrap" artisan demostracion:verificar --no-interaction
```

Estos comandos están PENDIENTES sobre infraestructura real. Las migraciones existentes se aplicarán solo después de aprobar su procedimiento exacto y comprobar esa identidad; no hay nuevas migraciones en 9B. Luego, con operación cargar autorizada:

```powershell
php -d "auto_prepend_file=$bootstrap" tests/Performance/verificar-entorno.php cargar
php -d "auto_prepend_file=$bootstrap" artisan db:seed --class=CargaSeeder --no-interaction
```

Si falla, no continuar ni repetir. Conservar marca, registros y salida privada. Revisar conteos, integridad e inventario antes de declarar datos sintéticos confirmados.

## Rendimiento: método y salidas

Calentamiento: humo independiente registrado, sin mezclarlo con los 120 segundos de carga. Humo 1 VU; carga constante 20 VU/2 minutos; estrés por 10/20/35/50, sostener 50 un minuto y recuperar con 1 VU. El script no ajusta objetivos al equipo.

Cada VU usa su cuenta y cookie propia; noCookiesReset conserva sesión entre iteraciones. Obtiene CSRF real, login con redirección al dashboard y consulta pedidos, productos, detalle, preparación y envasados. No envía escrituras comerciales; login/sesiones/logs son efectos previstos del recorrido. Las escrituras de envasado se reservan a un ensayo separado aún no autorizado.

```powershell
./tests/Performance/ejecutar-k6.ps1 -Escenario humo -SalidaPrivadaNueva C:/privado/evidencias/run001-humo
./tests/Performance/ejecutar-k6.ps1 -Escenario carga -SalidaPrivadaNueva C:/privado/evidencias/run001-carga
./tests/Performance/ejecutar-k6.ps1 -Escenario estres -SalidaPrivadaNueva C:/privado/evidencias/run001-estres
```

PENDIENTES: k6 y autorización de medir. Variables privadas: manifiesto/SHA-256, RENDIMIENTO_CLAVE_CONTROL, RENDIMIENTO_CUENTAS (JSON email/password de 50 cuentas) y PEDIDO_FICTICIO_ID real de esa carga. No guardar ni imprimir esos valores. No invocar directamente el script evitando el lanzador.

Salidas nuevas: resumen JSON, series JSON, stdout/stderr privados y recursos CSV. Medir VU efectivos, solicitudes/segundo, promedio, med=p50, p90/p95, máximo, errores HTTP y checks. Tags distinguen control/consulta, endpoint y estrés/descenso/recuperación. Carga exige p95 listados <1000 ms, errores HTTP globales/consulta <1% y checks 100%; una redirección a login falla el check aunque HTTP sea 302.

El monitor recoge cada aproximadamente dos segundos CPU total, memoria libre, CPU y memoria por PID, incluidos procesos hijos del servidor. No empieza con menos de 2048 MiB libres. Detiene solo su k6 si hay menos de 512 MiB durante 15 muestras, fallo de observación o 10 minutos. El período supera 30 s si las consultas CIM tardan. Conserva interrupciones y VU alcanzados como observación, sin declarar completado el estrés. No apaga Apache/MySQL ni toca bases.

El modelo cerrado por VU puede reducir throughput al ralentizarse. Servidor/generador comparten equipo; Windows no acredita capacidad Linux/producción. El guard de demostración y las inspecciones de identidad tienen sobrecarga incluida. No usar artisan serve para certificar concurrencia. Registrar procesos ajenos, OPcache, calentamiento y memoria; no apagar aplicaciones operativas sin autorización.

## Seguridad HTTP real

PENDIENTE DE EJECUCIÓN. Usar sesiones ficticias separadas y URL autorizada, sin PHPUnit que omita CSRF:

| Control | Procedimiento futuro | Resultado esperado |
| --- | --- | --- |
| Login | Cinco claves erróneas para cuenta ficticia; sexto intento; revisar Retry-After | Bloqueo conforme al controlador, sin debilitar limitador |
| D9A-01 | Dos sesiones, desactivar solo cuenta ficticia del entorno autorizado | Próxima solicitud protegida expulsa; JSON 401 |
| Invitado/roles | Matriz por tres roles y rutas reales | Redirección/403/200 según permiso |
| CSRF | POST login/envasado sin token y con token inválido | 419 y ningún envasado/movimiento nuevo |
| XSS | Valores sintéticos en nombres/notas/observaciones | Texto escapado; no ejecución de código |
| Buscadores | Entrada especial tipo ' OR 1=1 -- y términos con/sin coincidencia | Sin 500 ni ampliación indebida de resultados |
| Sesiones | Cookies, logout, sesión anterior y cuenta inactiva | Sin acceso posterior; revisar HttpOnly/SameSite |
| Cabeceras | Respuestas correctas y errores | Nosniff, frame/referrer y CSP según código |
| Dependencias | composer audit --locked; npm audit --package-lock-only | Registrar gravedad, versión y salida actual |

CSP es Report-Only: no llamarla prevención activa de XSS. HSTS solo en producción; HTTP local no certifica TLS/Secure cookies. Pruebas que crean contenido XSS o desactivan cuentas son escrituras sintéticas y requerirán autorización expresa. Ningún hallazgo nuevo se corrige en producción sin aprobación.

## ZAP nativo: análisis pasivo con Automation Framework

No es ejecución literal de zap-baseline.py. Se usa la alternativa aprobada sin Docker. Plan preparado: tests/Performance/seguridad/zap-pasivo.example.yaml; sin activeScan ni spider automático. La espera de 120 segundos permite ejecutar recorrido-pasivo.php mediante el proxy: verifica identidad firmada, hace login ficticio y seis GET de rutas fijas, sin seguir redirecciones, enlaces, scripts ni recursos externos. El contexto limita el origen y rutas, excluye logout y formularios comerciales. Este recorrido no acredita JavaScript ni todas las pantallas. Si posteriormente se usa un navegador, deberá ser un perfil exclusivo sin cookies reales, plugins ni navegación externa, con bloqueo externo previamente verificado.

Tras instalar/verificar Java y ZAP con autorización, comprobar identidad con operación seguridad y revisar una copia privada del plan. Comando futuro, NO EJECUTADO:

```powershell
php -d "auto_prepend_file=$bootstrap" tests/Performance/verificar-entorno.php seguridad
& 'C:/Program Files/ZAP/Zed Attack Proxy/zap.bat' -cmd -host 127.0.0.1 -port 8090 -dir C:/privado/zap-f9b -autorun C:/privado/zap-plan.yaml
```

Mientras el plan privado espera, desde otra terminal privada autorizada y con las variables de cuentas/control preparadas:

```powershell
php -d "auto_prepend_file=$bootstrap" tests/Performance/seguridad/recorrido-pasivo.php
```

PENDIENTE DE EJECUCIÓN: requiere instalación y autorización de seguridad. No usar k6 ni un navegador operativo para poblar el proxy. Mantener aislado el perfil ZAP para no incluir capturas de otras aplicaciones.

La ruta instalada y addons se verificarán entonces; el comando exacto se actualizará si el instalador usa otra carpeta. Puerto 8090 y perfil privados sin API expuesta a red; mantener clave API habilitada. Plan: configurar pasivo solo en alcance, esperar recorridos, esperar cola pasiva, emitir HTML y código de salida por severidad. No ignorar avisos para conseguir verde. [Delay](https://www.zaproxy.org/docs/desktop/addons/automation-framework/job-delay/) y [exitStatus](https://www.zaproxy.org/docs/desktop/addons/automation-framework/job-exitstatus/).

Comprobar que el reporte contiene mensajes de las rutas recorridas; un reporte vacío NO aprueba seguridad. Si 120 s no alcanzan, registrar la interrupción y acordar otra duración, no inventar cobertura. Conservar original HTML/JSON privado, sanitizar antes de Git y documentar hallazgo, gravedad, reproducción y corrección propuesta. El análisis pasivo no demuestra ausencia de vulnerabilidades lógicas.

## Compatibilidad y usabilidad

Plantilla compatibilidad_9b.csv: doce áreas, Chrome/Firefox/Edge, anchos 360/390/768/1024/1366, 180 combinaciones PENDIENTES. Registrar versión real, altura/zoom, rol, navegación, formularios/tablas, menú móvil, scroll del body, permisos, capturas nuevas y resultado. Pedidos incluye editor surtido/impresión; preparación incluye modal; entregas incluye rechazo, recepción y aprobación de retornos. No usar una captura Chromium como evidencia de otro navegador.

Plantilla usabilidad_9b.csv: separar medición técnica de trabajador/9C. Inicio: formulario listo con tarea/datos entregados; fin: confirmación válida y resultado visible. Pedido cinco líneas, objetivo <=90 s; envasado sin meta inventada. Registrar ensayo, errores, ayudas y reintentos. Medir con reloj monotónico, sin descontar silenciosamente correcciones. No inventar participantes, tiempos ni aceptación.

## Evidencias y próximo control

Ver evidencias/preparacion_9b.json para resultados reales de desarrollo. Mantener privados manifiestos, claves, cuentas, cookies, reportes crudos y logs; no publicar respaldos ni datos reales. Archivos versionados son plantillas o salidas sanitizadas sin secretos.

Próximo paso: presentar para aprobación instalaciones/rutas exactas, instancia/datadir/UUID/PID/puertos, cuentas y concesiones, perfil Apache/PHP, recursos disponibles, manifiesto/huellas, procedimiento de inicialización y ejecución de carga. Solo después se ejecutarán las pruebas reales. No cerrar 9B ni iniciar 9C con esta preparación.
