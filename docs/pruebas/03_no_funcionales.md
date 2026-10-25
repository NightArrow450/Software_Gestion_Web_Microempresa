# Fase 9B: pruebas no funcionales — etapa 1

Fecha: 25/10/2026. Base develop/50ac59e. Rama feature/fase9b-pruebas-no-funcionales.

**Estado: EJECUTADO CON OBSERVACIONES Y PENDIENTES; NO APROBACIÓN INTEGRAL.** La autorización integral y la corrección posterior de rutas permitieron migrar y cargar la base ficticia, ejecutar k6, seguridad HTTP y ZAP. El estrés no alcanzó 50 VU, la meta de latencia no se cumplió y la revisión visual quedó pendiente por bloqueo del control de interfaz. Ver los resultados reales al final. Las secciones históricas de preparación y bloqueo no describen el estado actual. No se tocaron las bases protegidas. 9C sigue pendiente de su etapa documental posterior al merge y de participación empresarial.

## Requisitos y estados

| Requisito original | Preparación | Estado de ejecución real |
| --- | --- | --- |
| 8, k6/carga/estrés/datos coherentes | Dataset verificado y ensayos ejecutados | OBSERVADO: latencia e interrupciones; pico final de estrés 31/50 VU |
| 9, seguridad | HTTP real y ZAP nativo pasivo | 54/54 HTTP; ZAP con advertencias, sin altos; auditorías sin vulnerabilidades |
| 10, compatibilidad/usabilidad | Matriz 180 combinaciones y medición HTTP | Visual PENDIENTE por control GUI; tiempos HTTP ejecutados, no UAT |
| 11, informe/evidencias | Reportes sanitizados, JSON/CSV y guiones | PUBLICADO CON OBSERVACIONES Y PENDIENTES |

No confundir PREPARADO con EJECUTADO Y APROBADO. Cuando se ejecute se registrará EJECUTADO Y APROBADO o EJECUTADO CON OBSERVACIONES, con datos y evidencia; una interrupción también se conserva. Las suites de desarrollo no son resultados de estrés, ZAP ni aceptación humana.

## Herramientas e instalación pendiente

Diagnóstico original de etapa 1: Windows 10 10.0.19045, i7-6700HQ, 4 núcleos/8 hilos, unos 12 GiB RAM y 2.3 GiB libres en la lectura inicial. PHP 8.3.36 ZTS, Laravel 13.35.0, Apache 2.4.68 WinNT, MySQL 8.4.3, Node 22.23.2 disponibles. Registrar de nuevo el equipo y memoria antes de ejecutar.

Chrome 146.0.7680.80 y Edge 156.0.4314.42 se localizaron por binario; no son revisiones visuales. Firefox, k6, ZAP, Java y Docker no se localizaron; WSL no tiene distribución registrada utilizable. En la etapa 1 no se instalaron; la instalación local autorizada de etapa 2 y sus versiones se documentan al final.

Después de autorización: descargar k6 local de [Grafana](https://grafana.com/docs/k6/latest/set-up/install-k6/), ZAP nativo de [ZAP](https://www.zaproxy.org/download/) y Java de un distribuidor oficial enlazado por ZAP; verificar hashes/versiones antes de ejecutar. La versión ZAP consultada 2.17.0 requiere Java 17 o superior. Elegir/fijar una versión Java mantenida compatible al instalar. Firefox desde [Mozilla](https://www.mozilla.org/firefox/). Sin Docker, cuentas cloud ni servicios de pago. No desactivar antivirus para instalar.

## Arquitectura exacta propuesta, aún no creada

Raíz privada candidata: C:/Users/Stalin/AppData/Local/DhacerRendimiento/f9b_001/. Checkout en checkout/, MySQL en mysql/datos/, configuración privada y reportes crudos fuera de Git. No reutilizar respaldos ni datos de software_gestion_web_microempresa o ecolimp_respaldo_prueba.

| Componente | Propuesta |
| --- | --- |
| Apache | Binario existente 2.4.68, configuración propia, 127.0.0.1:8089, WinNT 32 hilos, logs/PID propios |
| PHP | 8.3.36 ZTS con php8apache2_4.dll, php.ini separado, 128 MiB por solicitud, errores a archivo privado |
| MySQL | 8.4.3, 127.0.0.1:3313, mysqlx desactivado, datadir/UUID/PID nuevos, 64 conexiones, buffer InnoDB 256 MiB |
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

El monitor recoge cada aproximadamente dos segundos CPU total, memoria libre, CPU y memoria por PID, incluidos procesos hijos del servidor. Exige 2048 MiB libres para humo, 5120 para carga y 6144 para estrés. Detiene solo su k6 si hay menos de 1024 MiB durante cinco muestras, fallo de observación o 10 minutos. El período de cinco muestras supera 10 s si las consultas CIM tardan. Conserva interrupciones y VU alcanzados como observación, sin declarar completado el estrés. No apaga Apache/MySQL ni toca bases.

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

## Actualización: etapa 2, herramientas y configuración privada

25/10/2026, America/Lima. Autorizada la instalación local, SIN autorizar inicialización ni pruebas. Se verificó que las cuatro herramientas no estaban en PATH/ubicaciones habituales ni registradas como aplicaciones. Se extrajeron paquetes oficiales en C:/Users/Stalin/AppData/Local/DhacerRendimiento/herramientas/, sin instaladores globales, servicios, reinicio, cambios de PATH/JAVA_HOME de Windows ni cambios en Laragon.

| Herramienta | Versión iniciada mediante consulta CLI | Fuente y verificación |
| --- | --- | --- |
| k6 | 2.3.0, commit e088784614 | Release oficial grafana/k6; SHA-256 del asset y ayuda run verificados |
| Temurin JRE | 21.0.12.1+1, Java 21 LTS | API Adoptium y paquete oficial; SHA-256 verificado |
| ZAP | 2.17.0 con Java privado | Crossplatform oficial; SHA-256 verificado; -version sin objetivo ni plan |
| Firefox | 158.0.1, win64/es-ES | Paquete Mozilla, SHA256SUMS y firma Authenticode Mozilla válidos; --headless --version |

Las URLs, huellas y rutas se registran en [evidencia sanitizada](evidencias/entorno_9b_etapa2.json). No son ejecuciones de navegación ni escaneo. ZAP creó solo un perfil privado para comprobar versión, sin instalar su CA en Windows ni apuntar a una aplicación. Incluye Automation Framework 0.58.0 y Reports 0.43.0; avisó que su catálogo no se había actualizado durante más de tres meses. Revisar/actualizar desde fuentes oficiales antes del escaneo autorizado; no se oculta ese aviso.

Firefox no requirió ejecutar su instalador: se extrajo la carga del instalador firmado. Tar de Windows no admite ese LZMA; se usó 7zr 26.05 del repositorio oficial ip7z/7zip, con SHA-256 verificado, como auxiliar local sin registro. Su aviso de bytes tras el archivo corresponde a datos añadidos del ejecutable firmado; el Firefox extraído tiene firma Mozilla válida y responde con su versión. No se ejecutó Maintenance Service.

Checkout independiente: C:/Users/Stalin/AppData/Local/DhacerRendimiento/f9b_001/checkout, creado desde 90ded69 y actualizado por avance rápido al commit final de esta preparación (solo documentación/configuración/guardas). No se copió .env operativo. Dependencias instaladas del lock con composer install --no-scripts y npm ci --ignore-scripts; Vite compiló en 2.73 s dentro del checkout privado. No se ejecutó composer setup, Artisan migrate ni ningún seeder. Un primer intento de comprobación antes de terminar Composer falló por faltar autoload; después de concluir la instalación se repitió y se obtuvo el bloqueo previsto del manifiesto, salida 1, antes de consultar procesos/MySQL.

Apache con archivo privado pasó httpd -t: Syntax OK, sin arrancar. PHP con php.ini privado cargó PDO MySQL, mbstring, OpenSSL, fileinfo, curl, intl y zip; composer check-platform-reqs aprobó. Credenciales específicas ficticias, APP_KEY y clave HMAC preparadas fuera de Git; ACL del directorio limita acceso al usuario actual y SYSTEM. No se muestran, versionan ni reutilizan contraseñas reales.

### Recursos y perfil para aprobación

Cinco muestras reales de preparación: CPU 8–24 % (promedio 15.8 %), RAM libre 3331–3455 MiB. La lectura final queda en la evidencia; son instantáneas, no una certificación de capacidad. Antes de cada servicio/ensayo deberá medirse de nuevo.

El perfil inicial de 64 × 128 MiB permitiría hasta 8 GiB de memoria PHP por solicitudes simultáneas, sin contar memoria nativa ni MySQL: no es adecuado como supuesto seguro con unos 3 GiB libres. Se propone Apache 32 hilos, PHP 128 MiB, MySQL 64 conexiones y buffer InnoDB 256 MiB. Apache aún permitiría hasta 4 GiB de memoria PHP; esas cuotas no son reservas ni límites totales del proceso. Medir el uso real tras autorización.

Se mantienen 20 VU/2 minutos y estrés hasta 50 VU: reducir hilos del servidor no reduce usuarios virtuales; se observarán las colas reales. No se ha ejecutado ninguna carga. El lanzador queda más conservador: mínimos 2/5/6 GiB para humo/carga/estrés; detener solo su k6 ante menos de 1 GiB durante cinco muestras, pérdida de observación o diez minutos. El equipo observado NO cumple hoy el mínimo de carga/estrés. Solicitar liberar recursos o autorizar una alternativa, sin bajar VU ni ocultar incumplimientos.

ZAP se usará en otra ventana, con Java propuesto -Xms64m -Xmx512m y memoria nativa adicional, nunca simultáneamente con k6/estrés sin nueva evaluación. Tampoco mantener navegadores de compatibilidad abiertos durante la medición. No se cerró ninguna aplicación del usuario automáticamente.

### Manifiesto privado previo a inicialización

Archivo: C:/Users/Stalin/AppData/Local/DhacerRendimiento/f9b_001/manifiesto.json.

SHA-256 de esta propuesta: **8c49dc7ca9966ae65b3e74c1d10be1e7ba20e0d63ae03f78d444c76a4ed8efcd**.

Contiene checkout operativo/aislado, MySQL 3313, datadir exclusivo, binarios, Apache 8089 y huella de su configuración, cuenta demo_f9b_001 y base dhacer_eco_limp_demo_f9b_001. Estado PREPARADO_NO_INICIALIZADO; operaciones vacías, PID 0 y UUID/argumentos PENDIENTES. Son campos deliberadamente bloqueantes: PID, UUID y huellas de argumentos efectivos no pueden certificarse antes de iniciar. No se inventan. Tras autorizar esa operación se capturarán y se presentará una nueva huella antes de autorizar escrituras posteriores.

Configuraciones privadas: httpd.conf, mysql.ini y php/php.ini bajo esa raíz. Datadir mysql/datos vacío; sin listeners en 3313/8089/8449/8090. No se establecieron conexiones SQL a las bases protegidas. Esto NO afirma que otras aplicaciones del usuario no tengan conexiones operativas: no se consultó ni intervino su servidor.

### Comandos exactos previstos, NO ejecutados

**Puerta A: nueva autorización para inicializar y arrancar solo la instancia independiente.** No incluye migraciones, carga o pruebas. Confirmar otra vez puertos, RAM, binario, huellas y datadir vacío antes de ejecutar:

```powershell
& 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysqld.exe' '--defaults-file=C:/Users/Stalin/AppData/Local/DhacerRendimiento/f9b_001/mysql.ini' --initialize
```

El log privado contendrá la contraseña temporal del root NUEVO; no publicarlo. No se usa --initialize-insecure ni contraseña vacía. Luego, únicamente si se autoriza también arrancar:

```powershell
Start-Process -FilePath 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysqld.exe' -ArgumentList '--defaults-file=C:/Users/Stalin/AppData/Local/DhacerRendimiento/f9b_001/mysql.ini' -WindowStyle Hidden -PassThru
Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort 3313 -State Listen
```

Verificar PID REAL del listener (puede diferir del lanzador), executable/argumentos/datadir y UUID de auto.cnf. Conservar evidencia privada y detener si difiere. No crear aún base ni cuentas. La verificación positiva sigue PENDIENTE.

**Puerta B: autorización de bootstrap de cuentas/base en esa identidad ya comprobada.** El cliente-root-pendiente.ini es privado y tiene contraseña marcador; solo después de autorización se completará con la temporal del servidor nuevo. La rotación preparada cambia únicamente root@localhost de ESA instancia:

```powershell
& 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe' '--defaults-file=C:/Users/Stalin/AppData/Local/DhacerRendimiento/f9b_001/cliente-root-pendiente.ini' --protocol=TCP --host=127.0.0.1 --port=3313 --connect-expired-password --execute='SOURCE C:/Users/Stalin/AppData/Local/DhacerRendimiento/f9b_001/rotacion-root-pendiente.sql'
```

Después de rotar, consultar identidad efectiva antes de cualquier CREATE:

```powershell
& 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe' '--defaults-file=C:/Users/Stalin/AppData/Local/DhacerRendimiento/f9b_001/cliente-root-posterior.ini' --protocol=TCP --host=127.0.0.1 --port=3313 --execute='SELECT VERSION(), @@port, @@datadir, @@server_uuid, CURRENT_USER(), CURRENT_ROLE()'
```

Solo si coincide y está autorizado crear base/cuentas:

```powershell
& 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe' '--defaults-file=C:/Users/Stalin/AppData/Local/DhacerRendimiento/f9b_001/cliente-root-posterior.ini' --protocol=TCP --host=127.0.0.1 --port=3313 --execute='SOURCE C:/Users/Stalin/AppData/Local/DhacerRendimiento/f9b_001/cuentas-pendientes.sql'
```

Ese SQL privado contiene únicamente CREATE DATABASE de la base nueva y dos cuentas: aplicación SELECT/INSERT/UPDATE/DELETE; migrador además CREATE/ALTER/INDEX/REFERENCES/TRIGGER sobre la base literal. Sin roles, privilegios globales ni GRANT OPTION. Se escapan los guiones bajos para no conceder patrones amplios; verificar @@partial_revokes y SHOW GRANTS reales antes de usar las cuentas. Root de bootstrap no es la cuenta de la aplicación. No inicializa tablas Laravel ni crea datos de empresa.

**Puerta C: autorización de arranque Apache aislado y verificación positiva.** Sintaxis ya comprobada; comando futuro, no ejecutado:

```powershell
Start-Process -FilePath 'C:/laragon/bin/apache/httpd-2.4.68-260617-Win64-VS18/bin/httpd.exe' -ArgumentList '-f C:/Users/Stalin/AppData/Local/DhacerRendimiento/f9b_001/httpd.conf' -WindowStyle Hidden -PassThru
```

Capturar identidad efectiva y concesiones, actualizar manifiesto/SHA privado y confirmar autoridad de la operación antes del verificador completo. Las migraciones, CargaSeeder, k6, escaneos y datos ficticios siguen requiriendo otra autorización; no se ejecutan como continuación automática de ninguno de estos comandos.

### Ejecución integral autorizada y bloqueo previo al esquema

25/10/2026, hasta las 12:17 America/Lima. La autorización integral del propietario sustituyó las puertas de aprobación intermedia, pero ordenó detenerse si fallaba un control de identidad, permisos, rutas o aislamiento. No se modificaron ni desactivaron esos controles.

- La instancia MySQL 8.4.3 se inicializó con el comando `--defaults-file=.../f9b_001/mysql.ini --initialize` ya documentado, sobre el directorio privado vacío. Se inició exclusivamente en 127.0.0.1:3313.
- Identidad SQL comprobada: puerto 3313, datadir exclusivo `C:/Users/Stalin/AppData/Local/DhacerRendimiento/f9b_001/mysql/datos/`, UUID `772cebd8-d097-11f1-89cd-086266ba1fba`, cuenta administrativa local y sin roles activos. No se conectó a 3306 ni se utilizaron credenciales operativas.
- La primera rotación recibió el error 1820 al procesar el comentario inicial del archivo SOURCE con una contraseña expirada. Se retiró únicamente ese comentario del SQL privado y la instrucción ALTER USER posterior terminó correctamente. No se publicó ninguna contraseña.
- Se creó únicamente `dhacer_eco_limp_demo_f9b_001` y las cuentas ficticias previstas. La inspección final de information_schema confirmó **0 tablas** en esa base. Los privilegios efectivos de las cuentas de aplicación/migración aún no cuentan con certificación positiva del verificador completo.
- Apache independiente pasó `httpd -t` y arrancó en 127.0.0.1:8089. Esto no acredita todavía funcionamiento de los módulos: no existe esquema Laravel ni datos sintéticos.
- El comando del checkout separado `php -d auto_prepend_file=tests/Performance/configuracion/privada-bootstrap.php tests/Performance/verificar-entorno.php verificar`, con configuración privada externa, terminó bloqueado. El diagnóstico de lectura identificó `RuntimeException: Destino, operación o identidad de configuración no autorizados.` en AutorizacionRendimiento.php:89.

**Causa observada:** realpath devuelve `C:\Users\Stalin\AppData\Local\DhacerRendimiento\f9b_001\checkout` y `C:\laragon\www\Software_Gestion_Web_Microempresa`, mientras que los campos checkout y operativo del manifiesto usan `/`. La comparación strcasecmp no normaliza separadores. Base, usuario, puerto, URL, vigencia y operación coincidieron. No es evidencia de acceso a un destino ajeno; tampoco permite declarar aprobada la validación completa, que se detuvo antes de las etapas siguientes.

**Propuesta mínima, NO aplicada:** registrar en el manifiesto privado las rutas canónicas de realpath verificadas, renovar su SHA-256 y repetir el control sin modificar sus condiciones. Si requiere cambio del verificador en vez de configuración, deberá tratarse expresamente; no ampliar permisos ni omitir controles para avanzar.

**Estado final seguro:** se cerraron únicamente los procesos ficticios identificados. `httpd -k stop` no pudo usarse porque el proceso no estaba instalado como servicio (AH00436); se terminaron sus PID exclusivos, sin peticiones comerciales en curso. MySQL independiente se cerró mediante mysqladmin con su archivo cliente privado y puerto explícito 3313. Puertos 3313/8089 sin listeners al finalizar. El listener operativo 3306 conservó el PID 12220 y no se reinició.

La RAM libre antes de inicializar era aproximadamente 5273 MiB; una lectura posterior mostró unos 5823 MiB. No se midió consumo bajo carga ni se modificaron los umbrales preventivos o los objetivos de 20/50 VU.

**PENDIENTE DE EJECUCIÓN:** migraciones ficticias, CargaSeeder, inventario del dataset, HTTP funcional, humo/carga/estrés/recuperación, escritura de envasado, seguridad HTTP, ZAP, matriz de navegadores y usabilidad. No hay métricas de estos ensayos. Los dos guiones definitivos y la preparación utilizable para grabar siguen pendientes de contar con datos y recorridos comprobados; no se presentan como vídeos listos ni aceptación humana. No se ejecutaron suites locales nuevas en este intento. La CI efímera, si se ejecuta para este commit documental, es validación de desarrollo y no sustituye estas pruebas.

Las bases protegidas no se consultaron ni modificaron durante estas operaciones. No se tocaron el .env, los servicios operativos ni el código productivo. Evidencia sanitizada: [ejecucion_9b_bloqueo_rutas.json](evidencias/ejecucion_9b_bloqueo_rutas.json). Fase 9B incompleta; 9C no iniciada.

### Resultados reales de ejecución 9B

25/10/2026, America/Lima. Autorización integral del propietario, seguida de autorización expresa para corregir rutas. Se conserva el historial de intentos anteriores; no se sustituyen sus fallos por salidas exitosas.

**Entorno:** checkout privado f9b_001, MySQL 8.4.3 en 127.0.0.1:3313 con UUID 772cebd8-d097-11f1-89cd-086266ba1fba, Apache 2.4.68 en 127.0.0.1:8089, PHP 8.3.36 ZTS con configuración privada, k6 2.3.0, Java 21 y ZAP 2.17.0. Los binarios se comparten con instalaciones existentes, pero configuraciones, datadir, logs, sesiones, claves y datos de la aplicación son independientes. El aislamiento protege contra errores operativos; no es una sandbox frente al mismo administrador Windows.

#### Correcciones de herramientas y protección

| ID | Causa y efecto | Solución / estado |
| --- | --- | --- |
| D9B-01 | Comparación literal de separadores `/` y `\` bloqueaba rutas Windows equivalentes. | CORREGIDO: normalización consistente, rutas canónicas exactas existentes y rechazo de enlaces/junctions en toda la cadena de directorios. Regresiones con una junction real Windows y symlink en Linux. No se omiten procesos, puertos, datadir, UUID ni privilegios. |
| D9B-02 | La migración histórica 2026_10_12_200002 crea un permiso técnico; el seeder confundía el esquema nuevo con una base comercial previamente cargada. | CORREGIDO en CargaSeeder de desarrollo: admite exclusivamente la fila pedidos.cancelar con descripción y es_sistema exactos; rechaza cualquier permiso adicional y sigue exigiendo el resto vacío. No se borraron filas ni se editaron migraciones. El intento fallido precedió a la marca de carga. |
| D9B-03 | Dotenv unsafe usaba putenv durante peticiones Apache con hilos; una petición podía perder variables privadas mientras otra finalizaba. El control se cerró de forma segura. | CORREGIDO solo en herramientas: Apache usa configuración local de petición; CLI conserva la carga de variables de su proceso. El verificador prioriza $_ENV. Regresión local y repetición real de humo/carga. No se cambiaron autenticación, permisos ni servicios empresariales. |
| D9B-04 | Bajo carga, las inspecciones PowerShell/CIM por iteración alcanzaron su timeout de 15 s, con CPU del equipo al 100 %. | OBSERVADO, severidad media para la validez del ensayo. No se relajó el control ni se certifica capacidad de producción. Registrar menos frecuencia o una arquitectura de observación diferente necesitaría un diseño que conserve el aislamiento; no se implementó para obtener verde. |
| D9B-05 | El primer plan rampaba hasta 50 sin sostener 50 un minuto. | CORREGIDO: etapa explícita adicional de un minuto a 50 y etiquetas de descenso/recuperación coherentes. La ejecución corregida se interrumpió antes de llegar a esa etapa. |

Pruebas focalizadas finales del aislamiento: **30/30, 31 aserciones**. Los metadatos simulados no se presentan como verificación de un servidor real. La comprobación real posterior sí aprobó antes de migraciones, carga, HTTP y ensayos.

#### Datos y escritura real sobre la base ficticia

Se aplicaron las 45 migraciones existentes únicamente en la base ficticia. CargaSeeder completó su ejecución en **242,498 s**: 300 clientes, 150 productos, 450 SKU y 3000 pedidos con empaques normales, surtidos, composiciones pendientes, envasados anticipados/vinculados, entregas y retornos. El seeder usa servicios existentes y una marca externa impide repetir una carga iniciada. No se ejecutaron DatabaseSeeder, DemoSeeder ni seeders operativos sobre Laragon.

El ensayo de escritura HTTP creó después **un pedido de cinco líneas (3001)** y **un envasado de dos unidades**. Para ese SKU: stock 10010 → 10012; cada uno de los tres materiales bajó en 2; se generaron cuatro movimientos. Repetir exactamente el POST con el mismo token de operación no creó otro envasado ni otros movimientos. No se descontaron mangas ni se registró una entrada manual adicional.

Ver [snapshot final de conteos](evidencias/estado_ficticio_final.json), [resultados sanitizados](evidencias/resultados_9b.json) e informe HTML. La verificación final de inventario aprobó sin inconsistencias ni huérfanos; el comando de despachos previos encontró **0 pedidos sin salida vinculada**. Los saldos iniciales sintéticos se conservan como tales, no se consideran automáticamente errores.

#### k6: resultados y límites

| Ejecución | VU pico muestreado | Solicitudes | p95 listados | Errores HTTP | Resultado |
| --- | ---: | ---: | ---: | ---: | --- |
| Humo inicial | 1 | 8 | 1,821 s | 0 % | 6/6 checks; meta de latencia no alcanzada |
| Carga inicial | 20 | 93 | 1,741 s | 1,075 % | Interrumpida a 19,9 s por control de identidad |
| Estrés inicial | 2 (3 observados en consola) | 9 | Sin muestras suficientes | 11,111 % | Interrumpido; variables del control no disponibles |
| Humo tras corregir configuración | 1 | 8 | 1,459 s | 0 % | 6/6 checks; meta de latencia no alcanzada |
| Carga repetida, 20 VU durante 120 s | 20 | 524 | 4,599 s | 0,191 % | 420/420 checks devueltos; terminó a 121,5 s con 20 iteraciones interrumpidas por timeout del control |
| Estrés con sostenimiento corregido | 23 | 232 | 2,906 s | 0,431 % | Interrumpido a 67,9 s; no llegó a 50 ni a recuperación programada |
| Recuperación separada, humo de 1 VU | 1 | 8 | 1,833 s | 0 % | Login y cinco consultas correctos; no sustituye el minuto de recuperación del estrés completo |

La carga repetida registró **4,317 solicitudes/s**, incluyendo autenticación y control de identidad; los errores de las consultas empresariales observadas fueron 0 %. Las verificaciones HMAC abortan fuera del contador checks; por ello 100 % de checks devueltos no equivale a escenario completo aprobado. Las 81 iteraciones de la métrica k6 y las 80 completas de la consola tienen semánticas distintas al abortar; se conservan ambas fuentes.

Los percentiles de listados se calculan sobre las muestras etiquetadas fase=consulta y listado=si, separadas del control. En estrés se derivaron de las series porque el resumen sin umbral no incluye automáticamente esa submétrica. Método: ordenar valores, posición (n−1)×p e interpolación lineal. En los JSON se conservan cantidad de muestras, medias, p50/p90/p95, máximos, throughput y hashes de los resúmenes originales.

**Recursos:** RAM mínima 3580 MiB durante la carga repetida y 4182 MiB en el estrés repetido; CPU muestreada máxima 100 %. El laboratorio tiene 12 GB instalados y comparte servidor, generador e inspector. No fue un agotamiento de memoria ni prueba de capacidad Linux. El límite inicial de estrés se ajustó a 3072 MiB tras observar el consumo de 20 VU; permanecieron el corte bajo 1024 MiB durante cinco muestras, el máximo de diez minutos y el timeout de identidad de 15 s. No se cerraron aplicaciones personales, no se redujeron los 20/50 VU y no se forzó la continuación tras fallos de identidad. Las series por proceso no incluyen todo el coste de los inspectores PowerShell; la CPU/RAM del equipo sí lo refleja.

**Metas:** p95 <1 s NO ALCANZADA; error HTTP <1 % en carga repetida ALCANZADO NUMÉRICAMENTE, con interrupción final que impide aprobar íntegramente el ensayo; 50 VU y recuperación integrada NO COMPLETADOS. La recuperación separada verifica disponibilidad, no estabilidad prolongada.

#### Seguridad HTTP y ZAP

**54/54 comprobaciones HTTP aprobaron**, incluidas 19 lecturas de páginas/formularios/impresión y 15 exportaciones CSV con HTTP 200, UTF-8 válido, BOM y punto y coma. No es una apertura manual en Excel ni revisión visual. Las comprobaciones restantes cubren invitado, roles, cabeceras, cookies, CSRF 419 sin envasado nuevo, entradas especiales, HTML almacenado escapado, D9A-01 con dos sesiones (302/401), otro módulo, cuenta inactiva, logout y sexto intento limitado. El limitador real responde 302 con Retry-After; no se inventa un 429.

Los cambios de observación y estado para reproducir XSS/D9A-01 se hicieron solo en filas ficticias y se restituyeron en finally. No se crearon movimientos o pedidos sobre las bases protegidas.

ZAP nativo se ejecutó con **-cmd -silent**, heap máximo 512 MiB, proxy solo en 127.0.0.1:8090 y Automation Framework. No hubo spider, activeScan ni navegación a enlaces externos. El recorrido hizo login y seis GET fijos a través del proxy. La opción -silent evita peticiones no solicitadas, según la [documentación oficial](https://www.zaproxy.org/docs/desktop/cmdline/). Se produjeron HTML y JSON privados no vacíos. La primera ejecución no conservó correctamente el código del proceso; la segunda lo capturó: **2, advertencias**, sin ocultarlas. Las reglas instaladas advirtieron que su catálogo podía estar desactualizado; no se descargaron reglas silenciosamente para cambiar el resultado.

| ID de revisión | Hallazgo | Gravedad / interpretación |
| --- | --- | --- |
| D9B-S01 | CSP aplicada solo en Report-Only; ZAP 10038 | Media reportada; limitación ya conocida. No hay política CSP obligatoria. Requiere revisión específica antes de producción, sin afirmar XSS explotable por esta alerta sola. |
| D9B-S02 | Recursos sin SRI, ZAP 90003 | Media reportada; revisar las inclusiones correspondientes y si deben servirse localmente. No se cambiaron recursos productivos sin autorización. |
| D9B-S03 | Redirección grande, ZAP 10044 | Baja; revisión manual pendiente de posible información en cuerpo de redirección. No se declara fuga confirmada ni falso positivo sin análisis. |
| D9B-S04 | Server expone versión de Apache, ZAP 10036 | Baja, configuración del laboratorio; no se cambió Apache operativo. |
| D9B-S05 | Cookie sin HttpOnly, ZAP 10010 | Baja reportada; las cinco instancias corresponden a XSRF-TOKEN. La cookie de sesión sí aprobó HttpOnly/SameSite. No atribuir esta alerta a la cookie de sesión ni desactivar CSRF. |

Resultado: **2 tipos de alerta media, 3 bajos y 5 informativos; 0 altos**. Ver [resumen sanitizado](evidencias/zap_resumen_9b.json). No prueba ausencia de vulnerabilidades. HTTP local no acredita TLS, Secure cookies ni HSTS; CSP Report-Only no bloquea XSS. No se modificó código productivo sensible para suprimir alertas.

#### Compatibilidad y usabilidad

La herramienta de control visual se detuvo porque no pudo determinar con suficiente confianza la URL de Chrome. Se respetó el bloqueo, sin continuar por otro mecanismo de interfaz. No se tomaron capturas de aprobación ni se usaron capturas antiguas. Permanecen **180 combinaciones pendientes**: 12 áreas × Chrome/Firefox/Edge × 360/390/768/1024/1366 px. Las versiones instaladas no se presentan como pruebas realizadas. Ver la matriz CSV con la causa por fila. La lectura HTTP de páginas y notas no verifica JavaScript, modal, menús, scroll, PDF ni número de páginas.

Mediciones técnicas con hrtime, desde formulario listo hasta confirmación HTTP y lectura válida: pedido de cinco líneas **1,1247512 s**, envasado **2,4248575 s**. Datos y selección preparados por script; no incluyen tecleo, comprensión ni tiempo de trabajadores. La meta humana de 90 s no se considera validada. No se registraron marcas ISO individuales de inicio/fin; se conserva duración monotónica y fecha del ensayo. Las filas UAT siguen **PENDIENTE DE EJECUCIÓN — VALIDACIÓN CON LA EMPRESA**.

#### Reproducción y archivos privados

Para volver a abrir la demostración, usar el lanzador verificado del checkout privado:

```powershell
& 'C:/Users/Stalin/AppData/Local/DhacerRendimiento/f9b_001/checkout/tests/Performance/iniciar-demostracion.ps1' -RaizPrivada 'C:/Users/Stalin/AppData/Local/DhacerRendimiento/f9b_001'
```

No repetir migraciones ni CargaSeeder. El lanzador no los ejecuta; solo inicia los servicios independientes si faltan, verifica configuración/identidad y renueva la vigencia de su manifiesto. No usar Laragon Stop All ni reiniciar el servidor operativo. No hay servicio Windows instalado para Apache de pruebas.

Comandos reales utilizados desde ese checkout, con RENDIMIENTO_CONFIG apuntando al archivo privado externo y las variables de control/cuentas cargadas únicamente en el proceso:

```powershell
php -d auto_prepend_file=tests/Performance/configuracion/privada-bootstrap.php tests/Performance/verificar-entorno.php verificar
php -d auto_prepend_file=tests/Performance/configuracion/privada-bootstrap.php artisan inventario:verificar --json --no-interaction
php -d auto_prepend_file=tests/Performance/configuracion/privada-bootstrap.php artisan inventario:revisar-despachos-previos --no-interaction
./tests/Performance/ejecutar-k6.ps1 -Escenario humo -SalidaPrivadaNueva '<raiz-privada>/evidencias-privadas/humo-002'
./tests/Performance/ejecutar-k6.ps1 -Escenario carga -SalidaPrivadaNueva '<raiz-privada>/evidencias-privadas/carga20-002'
./tests/Performance/ejecutar-k6.ps1 -Escenario estres -SalidaPrivadaNueva '<raiz-privada>/evidencias-privadas/estres50-002'
php -d auto_prepend_file=tests/Performance/configuracion/privada-bootstrap.php tests/Performance/seguridad/http-verificaciones.php
php -d auto_prepend_file=tests/Performance/configuracion/privada-bootstrap.php tests/Performance/usabilidad-tecnica.php
```

Los prefijos anteriores ya existen: para un ensayo adicional autorizado elegir otros, sin sobrescribir. Los dos últimos scripts reproducen verificaciones con efectos ficticios; el de usabilidad crea un pedido y un envasado nuevos. No ejecutarlos para grabar el video de resultados ni dirigirlos a otro entorno.

ZAP se invocó con Java del directorio herramientas/java, `-Xmx512m -Djava.awt.headless=true -jar <jar-ZAP-2.17.0> -cmd -silent -host 127.0.0.1 -port 8090 -dir <raiz-privada>/zap-ejecucion-002 -autorun <raiz-privada>/zap-plan-002.yaml`. El plan es copia del YAML versionado con nombre de reporte nuevo. Durante su delay se ejecutó seguridad/recorrido-pasivo.php con pedido ficticio 3 y cuentas privadas. El proceso ZAP terminó y no dejó listener 8090.

Los originales están bajo `C:/Users/Stalin/AppData/Local/DhacerRendimiento/f9b_001/evidencias-privadas/`: resúmenes/series/stdout/stderr/CSV de recursos de cada ensayo, seguridad-http-final.json, usabilidad-http.json, inventario-final.json, estado-ficticio-final-002.json, zap-pasivo-002.html y zap-pasivo-002.json. Los reportes crudos de ZAP pueden incluir tokens: no publicarlos ni abrirlos durante la grabación. Usar el [informe sanitizado para mostrar resultados](evidencias/informe_9b.html). Los hashes públicos permiten vincularlo a los originales sin publicar secretos.

La base real y la copia protegida no se consultaron ni modificaron. El listener operativo conservó su PID 12220; no se reinició. Los módulos ficticios permanecen disponibles en 8089. No se hicieron cambios de .env operativo, migraciones históricas, credenciales reales, infraestructura pagada ni despliegues.

#### Validaciones de desarrollo y siguiente paso

Ejecución local final: **618 Laravel, 3799 aserciones, 80358 ms**, repartidas en **33 unitarias puras + 574 Feature + 11 Security**. JUnit: cero errores, fallos y omitidas. JavaScript: **28/28**. Larastan nivel 5: **0 errores**, sin nuevas supresiones. Pint del alcance aprobado. Vite compiló en **3,85 s**; se conservan los avisos de fontaine opcional y tiempos de plugins. Composer audit y npm audit: **0 vulnerabilidades**. MySQL/concurrencia de desarrollo se valida en la CI efímera independiente, no contra la base de rendimiento que contiene el dataset; su resultado final se publica en la descripción y checks del PR y se verifica antes de la entrega.

Siguen documentadas la deuda de 41 archivos de formato y la limitación de workflow_dispatch mientras el workflow no exista en la rama predeterminada main. No se cambió la rama predeterminada ni el workflow para ocultar fallos.

**El PR requiere revisión con estas observaciones; no acredita todas las metas de 9B.** Después del merge manual podrá prepararse el PR documental 9C separado. No se inventaron participantes, actas, firmas ni tiempos UAT. Los [dos guiones](../guiones-demostracion.md) distinguen funcionamiento y pruebas; no hacen pasar pendientes por aprobaciones.

#### Cierre con inspector agrupado y perfil PHP comparativo

Las cifras anteriores conservan los primeros intentos. El cierre posterior añadió lecturas agrupadas de ambos procesos/puertos, sin caché ni omitir comprobaciones. Tres muestras aisladas redujeron la mediana del inspector de 1492 a 1308 ms; los hashes efectivos coincidieron y un PID incorrecto fue rechazado con código 1. No se amplió el timeout de 15 s. Ver inspector_9b.json.

Se detectó OPcache desactivado en el PHP privado. Se conservó ese INI y se ensayó una variante únicamente en Apache ficticio: OPcache 64 MiB, CLI desactivado, cache_id exclusivo `dhacer_f9b_001_20261025`, validate_timestamps=1 y revalidate_freq=0. Se mantuvieron 32 hilos y memory_limit=128M. PHP documenta que en Windows el ID separa las instancias compartidas por usuario/SAPI: [manual oficial](https://www.php.net/manual/en/opcache.configuration.php). La identidad HTTP y los valores efectivos del perfil se comprobaron; no se cambió PHP ni Apache operativo. Ver perfil_opcache_9b.json y la plantilla php-opcache.example.ini.

| Ejecución de cierre | VU pico | Solicitudes | Solicitudes/s | p95 listados | Errores HTTP | Iteraciones completas / interrumpidas |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| Carga agrupada sin OPcache | 20 | 640 | 5,014 | 4,805 s | 0 % | 100 / 0 |
| Estrés agrupado sin OPcache | 29 | 298 | 3,760 | 2,870 s | 0,336 % | 38 / 29 |
| Recuperación agrupada sin OPcache | 1 | 8 | 0,691 | 1,524 s | 0 % | 1 / 0 |
| Humo inicial con OPcache | 1 | 8 | Ver JSON | 1,166 s | 0 % | 1 / 0 |
| **Carga final con OPcache** | **20** | **946** | **7,110** | **3,384 s** | **0 %** | **151 / 0** |
| **Estrés final con OPcache** | **31** | **431** | **5,181** | **2,170 s** | **0,232 %** | **59 / 31** |
| Recuperación final separada con OPcache | 1 | 8 | Ver JSON | 1,291 s | 0 % | 1 / 0 |

La fase activa de cada carga fue de 120 s; el cierre normal permitió completar las iteraciones existentes (127,7 s sin OPcache y 133,1 s con OPcache). La carga final tuvo **775/775 checks correctos, ningún error HTTP y ninguna interrupción**. Los dos perfiles de cierre usaron los mismos 3001 pedidos. Los primeros ensayos anteriores a la medición de usabilidad tenían 3000; se identifican por separado en el JSON. No es un ensayo estadístico de múltiples repeticiones independientes ni prueba de capacidad productiva.

El estrés final volvió a detenerse por timeout del inspector a 83,3 s. Hubo 341 checks devueltos correctos, pero el aborto impide aprobar el escenario; **no se acreditan 50 VU ni el sostenimiento/recuperación integrados**. La memoria libre mínima fue **4512 MiB en la carga final y 4910 MiB en el estrés final**, con CPU máxima muestreada 100 %. No se forzaron más intentos. La recuperación separada confirmó disponibilidad con seis checks y cero errores, no el minuto programado del estrés completo. Los prefijos crudos son humo-opcache-001, carga20-opcache-001, estres50-opcache-001 y recuperacion-opcache-001.

Los umbrales finales de admisión son 2048 MiB para humo, 4096 para carga y 3072 para estrés, basados en el consumo observado y con autorización para revisar los preventivos. Se conserva el corte bajo 1024 MiB durante cinco muestras y el máximo de diez minutos. No se alteraron los objetivos de usuarios, errores o latencia. El JSON incluye máximos individuales de working set y memoria privada de los procesos muestreados; no sumar working sets como si fueran RAM física exclusiva.

**D9B-P01 — N+1 de preparación con surtidos (media, pendiente de corrección autorizada).** El diagnóstico CLI de solo lectura encontró **1270 consultas para 180 pedidos surtidos** del día, frente a **8 para 100 normales** de otra fecha. No se comparan sus totales ni se usa este tiempo CLI como latencia HTTP. Las lecturas de paquetes y componentes se repiten 360 veces cada una; lineasFisicas obtiene además presentaciones/productos/variantes por pedido. Fuentes: CumplimientoPedidoController::listaPreparacion y GestionarSurtidos::pendientes/lineasFisicas/paquetes. Ver perfil_preparacion_9b.json.

Propuesta mínima, NO implementada: cargar paquetes activos y su versión actual, componentes y SKU/relaciones por lote para el conjunto filtrado; compartir esa fotografía solo dentro de la consulta entre pendientes y líneas físicas. Conservar composiciones pendientes, histórico, unidades comerciales/físicas, precios y filtros; no introducir caché entre peticiones ni tocar despacho/stock. No se propone índice nuevo sin medirlo. Requiere autorización para cambiar código empresarial y comparar totales antes/después. Este hallazgo contribuye a la meta de latencia incumplida; el inspector también añade sobrecarga bajo concurrencia.

El diagnóstico está versionado como perfilar-preparacion.php. Solo mide controlador y agregación sin renderizar Blade ni emular una sesión HTTP completa. El primer intento de render CLI carecía de la variable errors aportada por el middleware HTTP; se conserva ese error de instrumentación y no se clasifica como fallo de la página (que respondió 200 en el recorrido HTTP). Otro snapshot de lectura inicial consultó origen en vez de origen_tipo; se corrigió solo la consulta temporal tras revisar el modelo, sin modificar datos. Ambos intentos originales quedaron privados.

Después del perfil final, **54/54 verificaciones HTTP volvieron a aprobar**, incluido D9A-01, roles y las quince exportaciones. Inventario de cierre: correcto, cero inconsistencias/huérfanos. No se repitió el script que crea pedido/envasado: la medición de escritura y sus tiempos pertenecen al perfil inicial. Las cantidades finales continúan en 3001 pedidos, 3005 líneas, 181 envasados y 2464 movimientos; los tres materiales suman 295224 unidades y los productos 4487912. Son únicamente saldos ficticios, no cifras de la empresa.

**Balance definitivo de esta entrega:** carga de 20 usuarios completada y error HTTP objetivo cumplido; latencia objetivo NO cumplida; estrés hasta 50 y compatibilidad visual PENDIENTES/OBSERVADOS; ZAP con advertencias; UAT pendientes de empresa. No se declara 9B plenamente aprobada. La documentación 9C no se mezcla con este PR y requiere primero su merge manual. La demostración sigue utilizable mediante el lanzador probado.
