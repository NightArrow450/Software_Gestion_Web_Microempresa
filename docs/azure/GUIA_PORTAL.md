# Desplegar DHACER en Azure Portal

Preparado el 29/10/2026. Destino: **App Service Linux, PHP 8.4, MySQL Flexible Server**. Se mantiene Laravel/Blade/Vite. No requiere Docker local, Redis, un dominio comprado ni cambiar la arquitectura.

**Estado:** archivos preparados localmente; todavía no publicados en GitHub, workflow no ejecutado en GitHub y ningún recurso Azure creado por esta preparación. No se ha verificado tu suscripción. Los 30 minutos son un límite para el intento, no una garantía de finalización.

## 1. Antes de crear servicios

En [Azure Portal](https://portal.azure.com), busca **Subscriptions / Suscripciones** y selecciona Azure for Students. Comprueba que esté activa, el saldo disponible y su vencimiento; si el portal no presenta el saldo, revisa el acceso de crédito indicado en la oferta estudiantil. No convertir a pago por uso.

En **Cost Management**, revisa costos y configura una alerta de presupuesto acorde al crédito disponible. Una alerta no limita automáticamente el gasto. App Service y MySQL pueden consumir crédito aunque nadie visite la aplicación. Las ofertas gratuitas dependen de la elegibilidad y su plazo; no asumir que B1 de App Service sea gratis porque B1ms de MySQL tenga una oferta.

Usaremos un grupo exclusivo, por ejemplo `rg-dhacer-tesis`, para identificar recursos y costos. Antes de confirmar cualquier creación revisa el desglose completo: plan App Service, MySQL, almacenamiento, respaldos y componentes de red. No hay un precio fijo verificado para tu región en esta guía.

Si faltan permisos, cuota o disponibilidad y no se resuelve en los primeros 10 minutos, guarda el estado y retoma la entrega local; no encadenar intentos en muchas regiones creando recursos duplicados.

## 2. Preparar el paquete Linux en GitHub

Los archivos nuevos están en el repositorio local, rama `develop`. Revisa el diff y publica únicamente estos cambios en tu repositorio GitHub mediante tu flujo habitual. **Todavía no se hizo commit ni push.** No subas `.env`, `.local-dev`, contraseñas, SQLite ni carpetas ajenas al repo.

Cambios que debes incluir:

- `.github/workflows/azure-paquete.yml`
- `docs/azure/` (esta guía, scripts, Nginx y plantilla)
- Enlace añadido en `docs/despliegue.md`.

Después del push a `develop`, en GitHub abre **Actions → Azure - preparar paquete Linux**. Se ejecutará automáticamente y solo construirá un ZIP; no crea ni publica servicios Azure. También existe ejecución manual cuando GitHub reconozca el workflow desde la rama predeterminada. No hace falta modificar `main` solo para el primer intento: el push a `develop` ya lo activa.

El workflow instala PHP 8.4 y dependencias de producción desde los lockfiles, compila Vite con la acción existente, valida Nginx y crea el ZIP. Espera resultado verde y revisa también la ejecución de **Calidad e integridad** del mismo commit. GitHub puede limitar los minutos de Actions según el plan; no afirmar que es ilimitado.

En la ejecución verde descarga el artefacto `azure-php84-<commit>`. **Extrae el ZIP descargado:** contiene `azure-release.zip` y su archivo `.sha256`. El archivo que se publicará es `azure-release.zip`, no el contenedor del artefacto ni un ZIP del repositorio. Guarda el commit y conserva ese paquete para recuperación.

Si falla el build, corrígelo antes de desplegar. No ignorar requisitos de PHP ni eliminar el lockfile para lograr que pase.

## 3. Crear App Service y MySQL en el portal

Ruta preferida: busca **App Services → Create → Web App** (algunas vistas muestran **Web App + Database**). Usa la opción de crear una base desde el asistente si está disponible, siguiendo el [tutorial oficial](https://learn.microsoft.com/en-us/azure/app-service/tutorial-php-mysql-app).

En **Basics / Datos básicos**:

| Campo | Selección |
| --- | --- |
| Subscription | Tu Azure for Students activa |
| Resource group | `rg-dhacer-tesis` o un nombre exclusivo |
| Name | Nombre disponible, por ejemplo `dhacer-tesis-<sufijo>` |
| Publish | Code / Código |
| Runtime stack | PHP 8.4, con parche vigente |
| Operating system | Linux |
| Region | Una región permitida por tu cuenta con ambos servicios disponibles |
| Linux plan | Plan nuevo exclusivo; Basic B1 como punto de partida, sujeto a cuota/costo |

En **Database**:

- Crear **MySQL Flexible Server**, no Azure SQL ni PostgreSQL.
- Seleccionar tamaño Burstable B1ms si el asistente lo permite y es suficiente/disponible. Revisar almacenamiento, respaldos y estimación; no aceptar un tamaño General Purpose caro por defecto.
- Preferir MySQL 8.4 compatible si está disponible; el proyecto se valida en CI con MySQL 8.4.3. Si solo aparece MySQL 8.0, registrar la diferencia y comprobar migraciones: no declararlo probado de antemano.
- No añadir Redis. No activar alta disponibilidad o redundancia geográfica para esta prueba sin una necesidad y costo revisados.
- Conservar la conexión privada que prepara el asistente: VNet/integración de App Service, endpoint privado y DNS privado. Revisar su costo adicional en el resumen. No habilitar acceso global a MySQL para resolver un problema de conexión.

En **Deployment**, mantener el despliegue continuo desactivado: ya tenemos nuestro workflow de empaquetado. No crear un segundo workflow automático ni habilitar autenticación básica de publicación.

En **Review + create**, revisa **todos** los recursos y su precio antes de confirmar. Si el asistente no permite el tamaño previsto, faltan cuotas o la red privada presenta bloqueos, detente y guarda la información; esta guía no autoriza aceptar un plan mayor sin revisar costos.

Al finalizar, registra grupo, app, región, plan, servidor MySQL, nombre exacto de la base y nombres de los recursos de red. Copia la URL HTTPS desde **Overview → Default domain**: los nombres reales pueden incorporar región/sufijos. No construirla por suposición.

## 4. Revisar MySQL antes de migrar

En el servidor MySQL → **Server parameters**:

- Mantener `require_secure_transport=ON` y TLS vigente.
- Verificar `log_bin_trust_function_creators=ON`: las migraciones crean triggers de integridad y Azure no concede privilegio SUPER. Guardar el parámetro si fue necesario cambiarlo.

Identifica las credenciales y la base creadas por el asistente. App Service puede recibir `AZURE_MYSQL_*` o una cadena `AZURE_MYSQL_CONNECTIONSTRING`; **esta aplicación espera `DB_*`**, así que introduce su equivalencia explícitamente en el paso siguiente. No pegues la cadena ni la contraseña en un chat.

El usuario empleado para migrar necesita permisos de DDL y TRIGGER sobre la base exclusiva. Antes de abrir el acceso a terceros, configura un usuario de ejecución limitado a esa base; conserva credenciales de migración separadas y no otorgues permisos globales. Si el asistente ya creó un usuario limitado, verifica sus permisos en lugar de asumir que es administrador o volver a crearlo.

## 5. Variables y arranque en App Service

Abre la **aplicación**, no el plan ni MySQL:

1. **Configuration / General settings**: activa **HTTPS Only**, TLS mínimo 1.2 o superior y **Always On** si el plan lo permite. Mantén una sola instancia para este primer despliegue.
2. En **Configuration → Stack settings**, verifica Linux/PHP 8.4 y canal de actualización Standard o Latest.
3. En **Startup Command**, introduce exactamente:

```bash
bash /home/site/wwwroot/docs/azure/startup.sh
```

4. En **Settings → Environment variables → App settings**, agrega individualmente los valores de [variables.env.example](variables.env.example). Reemplaza todos los marcadores; los comentarios/comillas del archivo no forman parte del valor en el formulario. No importes ese archivo como JSON.

Valores clave:

| Variable | Valor |
| --- | --- |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` |
| `APP_URL` | URL HTTPS exacta del portal |
| `APP_KEY` | Clave nueva para remoto, privada y estable |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Datos reales de la base exclusiva |
| `MYSQL_ATTR_SSL_CA` | `/etc/ssl/certs/ca-certificates.crt` |
| `LARAVEL_STORAGE_PATH` | `/home/dhacer-storage` |
| `SESSION_DRIVER` / `CACHE_STORE` | `database` / `database` |
| `QUEUE_CONNECTION` | `sync` para esta primera configuración |
| `DEMO_ACTIVA` | `false` |
| `PROXY_MODE` | `standard`, no `railway` |
| `TRUSTED_PROXIES` | Sin valor; no usar `*` |
| `SCM_DO_BUILD_DURING_DEPLOYMENT` | `false` (paquete ya compilado) |
| `WEBSITES_ENABLE_APP_SERVICE_STORAGE` | `true` |

No añadir `DB_URL` ni `SESSION_DOMAIN` para el dominio inicial. Si ya existen variables de un intento anterior, revisar que no anulen los valores individuales. No establecer `WEBSITE_RUN_FROM_PACKAGE`: usamos extracción ZIP y directorios escribibles.

Para obtener `APP_KEY` sin tocar el entorno local, puedes usar **Cloud Shell → Bash**:

```bash
printf 'base64:'; openssl rand -base64 32
```

Ese resultado es secreto: cópialo únicamente al campo `APP_KEY` y a tu gestor privado. No ejecutar `key:generate` contra el `.env` local ni regenerar esta clave después de guardar datos.

`ADMIN_EMAIL` y `ADMIN_PASSWORD` serán temporales para el primer administrador. La contraseña requiere al menos 10 caracteres, mayúsculas, minúsculas y números. Introducirlos solo en el portal, nunca en comandos compartidos. Aplicar/guardar cambios.

La configuración Nginx traduce `X-Forwarded-Proto` del ingreso de App Service a la señal HTTPS de PHP. No modifica el middleware de Laravel ni confía en cualquier proxy. Se usa exclusivamente detrás del ingreso Azure con HTTPS Only. La IP observada por la aplicación puede ser la del proxy: validar auditoría y límites de acceso antes de afirmar trazabilidad individual por IP; no resolverlo confiando en cabeceras arbitrarias.

## 6. Publicar el ZIP desde Cloud Shell del portal

En **Cloud Shell → Bash**, selecciona la suscripción correcta y usa **Manage files → Upload** para subir `azure-release.zip` y `azure-release.zip.sha256`. Si Cloud Shell pide crear almacenamiento persistente, revisa su costo o usa la sesión temporal si está disponible. No crear almacenamiento adicional sin necesidad.

Sustituye los nombres de los ejemplos. Estos comandos no contienen contraseñas:

```bash
az account show --query '{name:name,state:state}' --output table
# Solo si la suscripción mostrada no es la correcta:
# az account set --subscription 'NOMBRE_O_ID_DE_TU_SUSCRIPCION'

grupo='rg-dhacer-tesis'
app='NOMBRE_REAL_DE_TU_APP'
sha256sum -c azure-release.zip.sha256
az webapp deploy --resource-group "$grupo" --name "$app" \
  --src-path azure-release.zip --type zip --restart true
```

No usar un archivo comprimido con una carpeta contenedora ni el ZIP del artefacto externo. La raíz debe contener `artisan`, `app/`, `vendor/` y `public/`.

Si aparece error de permisos, comprueba rol de la cuenta y suscripción. No habilites credenciales básicas ni subas un publish profile a GitHub como solución automática. Si hay timeout, revisa Deployment Center/logs antes de repetir: la publicación puede continuar aunque el comando deje de esperar.

## 7. Inicializar la base nueva una sola vez

En la aplicación → **Development Tools → SSH**, abre una sesión **dentro de App Service**. Esta sección NO se ejecuta en PowerShell local ni Cloud Shell: la base tiene red privada.

```bash
cd /home/site/wwwroot
php --version
php -m
php artisan about --only=environment
```

Confirma PHP 8.4 compatible, `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `ctype`, `dom`, `xml`, `tokenizer` y demás requisitos del lock. No publiques una página `phpinfo()`.

Antes de modificar nada, ejecuta esta comprobación de solo lectura. Exige MySQL remoto de Azure y una base sin tablas; si falla, **no continuar** ni vaciarla:

```bash
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c = config('database.connections.mysql');
if (! $app->environment('production') || config('database.default') !== 'mysql'
    || ! str_ends_with($c['host'], '.mysql.database.azure.com')
    || ! empty($c['url']) || empty($c['database'])) {
    throw new RuntimeException('Destino inesperado: revisar variables antes de migrar.');
}
$db = Illuminate\Support\Facades\DB::connection();
$count = $db->selectOne('SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = DATABASE()');
if ((int) $count->total !== 0) {
    throw new RuntimeException('La base no está vacía. Detenerse y revisar, sin borrar datos.');
}
echo "Conexión remota y base vacía comprobadas.\n";
PHP
```

Solo con la comprobación correcta, ejecutar migraciones y los seeders específicos de roles/permisos/administrador, en este orden:

```bash
php artisan migrate --force --no-interaction
for seeder in RoleSeeder PermisoSeeder RolPermisoSeeder AuditoriaPermisoSeeder \
  EnvasadoPermisoSeeder SurtidosPermisoSeeder EntregaRetornoPermisoSeeder \
  IndicadoresPermisoSeeder AdminUserSeeder; do
  php artisan db:seed --class="$seeder" --force --no-interaction || break
done
php artisan migrate:status --no-interaction
```

Verifica que cada comando termine correctamente, incluido `AdminUserSeeder`. No ejecutar `DatabaseSeeder`, `DemoSeeder`, `CargaSeeder`, `migrate:fresh`, `migrate:refresh` ni `composer setup`. Si una migración falla, conserva su nombre y el error sin secretos: no reinicies la base automáticamente. La comprobación de base vacía bloqueará correctamente un segundo intento sobre una inicialización parcial; revisar el estado antes de continuar.

Inicia sesión con el administrador y cambia la contraseña cuando lo solicite. Después elimina **ADMIN_EMAIL y ADMIN_PASSWORD** de Environment variables y guarda; no son una vía para restablecer contraseñas. Para este primer despliegue no generamos caché de configuración en el build ni en el arranque, de modo que los valores privados se leen en destino y se evita arrastrar configuración del entorno de compilación.

### Usuario MySQL de ejecución, después de migrar

Si `DB_USERNAME` todavía es el administrador del servidor, crea un usuario exclusivo antes de compartir la URL. En la misma sesión **SSH de App Service**, introduce una contraseña nueva de forma oculta; no se escribe en el historial:

```bash
export AZURE_RUNTIME_USER=dhacer_app
read -rsp 'Contraseña nueva para dhacer_app: ' AZURE_RUNTIME_PASSWORD; printf '\n'
export AZURE_RUNTIME_PASSWORD
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c = config('database.connections.mysql');
if (! $app->environment('production') || config('database.default') !== 'mysql'
    || ! str_ends_with($c['host'], '.mysql.database.azure.com') || ! empty($c['url'])
    || ! preg_match('/\A[A-Za-z0-9_]+\z/', $c['database'])) {
    throw new RuntimeException('Destino inesperado.');
}
$user = getenv('AZURE_RUNTIME_USER');
$password = getenv('AZURE_RUNTIME_PASSWORD');
if ($user !== 'dhacer_app' || strlen($password ?: '') < 16) {
    throw new RuntimeException('Usar dhacer_app y contraseña nueva de al menos 16 caracteres.');
}
$pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
$account = $pdo->quote($user)."@'%'";
$pdo->exec('CREATE USER '.$account.' IDENTIFIED BY '.$pdo->quote($password));
$pdo->exec('GRANT SELECT, INSERT, UPDATE, DELETE ON `'.$c['database'].'`.* TO '.$account);
echo "Usuario de ejecución creado con permisos sobre la base exclusiva.\n";
PHP
unset AZURE_RUNTIME_PASSWORD AZURE_RUNTIME_USER
```

Si el usuario ya existe, `CREATE USER` falla deliberadamente: revisa el intento anterior en lugar de sobrescribir su contraseña. Si no tienes permiso para crearlo, usa la cuenta administradora de MySQL en un canal privado autorizado; no concedas privilegios globales a la aplicación para evitar ese paso.

Conserva la contraseña nueva en tu gestor privado y cambia en **App Service → Environment variables** `DB_USERNAME=dhacer_app` y `DB_PASSWORD` a ese valor. Guarda y comprueba `/salud` y login nuevamente. La cuenta administradora del servidor permanece reservada para migraciones futuras; el usuario de ejecución no podrá migrar. No elimines el usuario que actúa como DEFINER de los triggers sin revisar esas dependencias.

## 8. Comprobar antes de compartir la URL

- `/salud`: debe responder HTTP 200 y `{"estado":"ok"}`; comprueba conexión MySQL. `/up` por sí solo no demuestra acceso a la base.
- `/login`: debe cargar estilos, fuentes y JavaScript sin errores 404, contenido mixto o redirecciones repetidas.
- HTTP debe redirigir a HTTPS; login, cambio de contraseña y cierre de sesión deben funcionar con cookies seguras.
- `/.env` y `/composer.json` deben responder 403/404; no deben mostrar archivos de la raíz.
- Crea datos ficticios identificables, registra y busca un pedido, verifica permisos con los perfiles previstos. No atribuir estos pasos a participantes reales de tesis si no los realizaron.
- Reinicia desde el portal y verifica que la información MySQL sigue allí. Si el sistema utiliza archivos subidos, guarda uno mediante su flujo real y comprueba que sigue accesible tras reinicio **y una segunda publicación del mismo paquete**. El almacenamiento se encuentra en `/home/dhacer-storage`, fuera de `wwwroot`.
- Desde SSH ejecuta `php artisan inventario:verificar --json` cuando existan datos de prueba; es diagnóstico, no corrige saldos.
- Conserva una copia independiente de los datos antes de futuras migraciones y comprueba un procedimiento de restauración en otro destino. La persistencia no sustituye un respaldo.

Hasta completar esas comprobaciones, el estado es «despliegue pendiente de validación», no producción validada ni aceptación empresarial.

## 9. Tarea diaria existente y alcance inicial

`routes/console.php` sí registra `inventario:verificar` a las 02:00, America/Lima. Este paquete **no instala un programador permanente**: la guía de demostración permite ejecutar el diagnóstico manual desde SSH. Registrar esa limitación. Para operación continua hay que configurar y supervisar una ejecución de `schedule:run` cada minuto mediante un mecanismo compatible con el plan; no asumir que Always On ejecuta el scheduler.

No se localizaron clases `app/Jobs` en esta revisión. La configuración inicial `QUEUE_CONNECTION=sync` evita prometer un worker que no se ha instalado. Si posteriormente se añaden trabajos o correo real, revisar su ejecución y secretos antes de usarlos.

## 10. Errores habituales y recuperación

| Síntoma | Revisar |
| --- | --- |
| 502/503 al arrancar | App Service → Log stream; variables obligatorias y Startup Command; `nginx -t` |
| `/salud` 503 | `DB_*`, red privada/DNS y certificado TLS; no desactivar TLS ni abrir MySQL a todos |
| Error 1419 / SUPER al migrar | `log_bin_trust_function_creators=ON` y permiso TRIGGER |
| Error 500 antes de login | `APP_KEY`, dependencias, tablas de sesiones/caché y directorios persistentes |
| Redirecciones HTTPS repetidas | Runtime nativo PHP Linux, configuración Nginx aplicada y HTTPS Only; no poner `TRUSTED_PROXIES=*` |
| 419 en login | Dominio exacto, cookies seguras, esquema HTTPS y tabla sessions; probar sesión nueva |
| Assets ausentes | El paquete contiene `public/build/manifest.json`, no `public/hot`; verificar build y URL |
| Datos de build o credenciales locales | Detener publicación: el artefacto correcto no lleva `.env`, SQLite ni caché config.php |

Republicar el ZIP anterior revierte código, **no revierte la base**. Si hay cambios de esquema, analizar compatibilidad/restauración antes; no ejecutar rollback o restauración general a ciegas. El comando de arranque no migra ni borra datos.

Al pausar, guarda nombres, región, plan, commit, URL, último paso correcto, fallo pendiente y recursos que consumen crédito. Detener la aplicación no elimina el costo del App Service Plan; MySQL y almacenamiento también requieren revisión. No borrar el grupo hasta decidir que los datos son prescindibles o tener un respaldo verificado. Revisar Cost Management al terminar la prueba.

## Fuentes oficiales

- [Laravel/PHP + MySQL en App Service](https://learn.microsoft.com/en-us/azure/app-service/tutorial-php-mysql-app)
- [PHP Linux y configuración Nginx](https://learn.microsoft.com/en-us/azure/app-service/configure-language-php)
- [Publicación ZIP](https://learn.microsoft.com/en-us/azure/app-service/deploy-zip)
- [Parámetros MySQL y triggers](https://learn.microsoft.com/en-us/azure/mysql/flexible-server/concepts-server-parameters)
- [TLS MySQL](https://learn.microsoft.com/en-us/azure/mysql/security/security-tls-how-to-connect)
- [Persistencia App Service](https://github.com/projectkudu/kudu/wiki/understanding-the-azure-app-service-file-system)
- [Oferta para estudiantes](https://azure.microsoft.com/en-us/free/students/)
- [Precios App Service Linux](https://azure.microsoft.com/en-us/pricing/details/app-service/linux/)
- [Precios MySQL](https://azure.microsoft.com/en-us/pricing/details/mysql/flexible-server/)

La guía adapta estas referencias al código existente; no copiar del tutorial una APP_KEY pública de ejemplo ni APP_DEBUG=true.
