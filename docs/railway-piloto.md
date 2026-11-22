# Piloto de aceptación en Railway

La rama candidata parte de `origin/develop` en `312dc59`. Contiene el flujo vigente de venta por unidades y la guía de aceptación. El proyecto de Railway necesita dos servicios en el mismo entorno, una instancia Laravel y MySQL privado. El piloto usa datos y credenciales nuevos.

## Revisión de la rama de rendimiento

- `feat/performance-optimization` termina en `2a43dc3` y diverge de `main` desde `6f17cb3`. Una simulación de merge con `origin/main` (`993ae53`) mostró nueve conflictos, entre ellos modificaciones de surtidos sobre archivos eliminados en la versión vigente. Los siete commits exclusivos llevan tres hitos de rendimiento y cuatro cambios de Azure. Desplegar la rama completa revertiría la base de código del piloto al flujo anterior.
- El hito 1 es un candidato a trasladar como cambio puntual. En la candidata sigue presente la consulta `opcionesImpresion` y su única referencia adicional es el paso a Blade. El hito 2 se hizo sobre el flujo de surtidos retirado. El hito 3 se debe adaptar y medir contra las consultas de ventas por unidades y referencias físicas actuales, porque sus métricas comerciales cambiaron.
- Se revisaron archivos rastreados y los siete commits exclusivos con patrones para claves privadas, tokens, secretos base64 y enlaces o cuentas del piloto. No aparecieron coincidencias en ese alcance. El archivo SQLite, los accesos CSV, el ejecutable del túnel y la configuración real permanecen fuera del checkout. La guía y el paquete Azure pertenecen al experimento anterior y no se incluyen en esta candidata.
- La auditoría del lockfile de producción de la candidata reportó [CVE-2026-128083](https://github.com/advisories/GHSA-63hm-g96r-2m2q), de gravedad baja, en Laravel 13.35.0. La corrección está en Laravel 13.40. La búsqueda en `app`, `routes` y `config` no encontró uso de Concurrency o ProcessDriver. El lockfile conserva la versión vigente hasta autorizar y validar la actualización.

## Configuración en Railway

1. Verifica en el panel que el plan habilite ambos servicios con el crédito disponible. Crea un proyecto vacío para el piloto. Si pide activar Hobby, confírmalo antes de seguir.
2. Añade MySQL con volumen persistente en `/var/lib/mysql` y una base nueva llamada `dhacer_piloto`. Usa MySQL 8.4. Las migraciones tienen triggers y el usuario de migración debe poder crearlos. CI usa `log_bin_trust_function_creators=ON` en su instancia aislada. Si el template conserva binlog habilitado y la migración falla con error 1419, configura ese parámetro en el **servidor exclusivo del piloto** y vuelve a comprobar. No agregues un proxy TCP público.
3. Añade el servicio GitHub con la rama `chore/railway-pilot` una vez publicada. En **Settings → Deploy**, configura **Pre-deploy Command** con `php deploy/railway/predeploy.php`, **Healthcheck Path** con `/salud` y **Healthcheck Timeout** con 120 segundos. Apache escucha en 8080. Genera un dominio HTTPS solo para Laravel y usa 8080 como puerto de destino. La configuración se aplica desde el panel porque Railway deprecó `railway.json` para nuevas instalaciones.
4. Configura estos valores en **Variables** del servicio Laravel antes de desplegar. La tabla supone que el servicio de base se llama `MySQL`. Si cambias el nombre, actualiza las referencias.

| Variable | Valor |
|---|---|
| `RAILWAY_DOCKERFILE_PATH` | `deploy/railway/Dockerfile` |
| `PORT` | `8080` |
| `RAILWAY_PILOT` | `1` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | Clave nueva y privada en formato Laravel `base64:…`, generada con 32 bytes aleatorios |
| `APP_URL` | URL HTTPS del dominio asignado a Laravel |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | `${{MySQL.RAILWAY_PRIVATE_DOMAIN}}` |
| `DB_PORT` | `${{MySQL.MYSQLPORT}}`, comprobar que sea el puerto interno 3306 |
| `DB_DATABASE` | `dhacer_piloto` |
| `DB_USERNAME` | `${{MySQL.MYSQLUSER}}` o el usuario dedicado creado para esta base |
| `DB_PASSWORD` | Referencia a la contraseña correspondiente, como `${{MySQL.MYSQLPASSWORD}}` |
| `SESSION_DRIVER` / `CACHE_STORE` | `database` en ambas |
| `SESSION_SECURE_COOKIE` | `true` |
| `SESSION_COOKIE` | `dhacer_railway_piloto` |
| `MAIL_MAILER` | `array` |
| `QUEUE_CONNECTION` | `sync` |
| `ENVASADO_CONTROL_MATERIALES_EXACTO` | `false` |
| `LOG_CHANNEL` | `stderr` |

Deja `DB_URL` y `TRUSTED_PROXIES` sin definir. El predeploy verifica host privado, nombre de la base, modo de producción y clave antes de migrar. La configuración Apache interpreta HTTPS por la cabecera que entrega el ingreso HTTP de Railway. Mantén el servicio detrás de ese ingreso y verifica redirecciones, cookies y origen de las peticiones antes de compartir el dominio. Con proxies de Laravel sin configurar, la IP que Laravel ve puede corresponder al proxy.

## Inicialización y prueba

El despliegue inicial del 15/11/2026 se publicó directamente desde una exportación del commit `83c7340` mediante la CLI de Railway. El panel aplicó un comando de inicio que deshabilita `mpm_event` y `mpm_worker` y habilita `mpm_prefork` antes de ejecutar Apache. El Dockerfile ahora incorpora ese mismo comando como `CMD`. Al conectar GitHub, selecciona la rama revisada e introduce las variables de esta guía antes del primer despliegue. Los datos y cuentas creados en MySQL son independientes del código versionado.

Activa **Settings → Deploy → Enable Serverless** en Laravel y MySQL y aplica ambos cambios con un nuevo despliegue. Railway necesita contenedores nuevos para aplicar la suspensión. Conserva una réplica por servicio y la comunicación privada con MySQL. La suspensión suele ocurrir tras 5 a 10 minutos sin tráfico saliente. La primera petición puede demorar o devolver un 502 transitorio durante el arranque. Las conexiones activas a la base y una consola abierta pueden mantener los servicios despiertos. El volumen conserva sus datos y sigue generando consumo de almacenamiento. Referencia: [Serverless](https://docs.railway.com/deployments/serverless).

La revisión del piloto encontró permisos de envasado, indicadores y retornos pendientes de asignación. Verifica los permisos efectivos antes del recorrido por roles y utiliza los seeders específicos después de revisar su alcance. El seeder base solo ejecuta los seeders enumerados en `DatabaseSeeder`.

- Una vez migrada la base vacía, configura en privado `ADMIN_EMAIL` y `ADMIN_PASSWORD` nuevos. Desde la consola del servicio Laravel, ejecuta `php artisan db:seed --force --no-interaction` **una sola vez después de verificar el destino y que aún no hay usuarios**. En esta candidata el seeder base instala roles, permisos y el administrador inicial. El predeploy posterior ejecuta solo migraciones.
- Entra con el administrador, cambia su contraseña y retira las variables de inicialización cuando ya no se necesiten. Crea desde la interfaz las cinco cuentas con claves nuevas, tres de operaciones comerciales y dos de producción/reparto. Entrega cada clave por separado. El archivo SQLite y el CSV de accesos del piloto local quedan fuera del despliegue.
- Crea los clientes y productos ficticios que pide la [guía de aceptación](pruebas/04_aceptacion_UAT.md). Verifica `/salud`, `/login`, CSS, cookie segura y acceso por rol desde un teléfono antes de compartir. Prueba que comercial pueda guardar y consultar un pedido ficticio y que producción pueda dar continuidad al mismo pedido.
- Revisa el consumo en el panel y detén los servicios al terminar la prueba si ya no se necesitan. Conserva las evidencias requeridas antes de cancelar recursos.

## Validación local

Se compiló el Dockerfile inicial con PHP 8.4, Apache y activos de Vite. Una prueba con MySQL 8.4.3 en una red Docker independiente verificó la migración de una base vacía, su repetición sin migraciones pendientes, los seeders iniciales, `/salud`, enlaces HTTPS, cookie segura y redirección del primer ingreso a cambio de contraseña. La inspección de la imagen confirmó la ausencia de `.env`, `.git`, `node_modules`, archivos SQLite y archivos `ACCESOS*`. Las credenciales de esa prueba fueron temporales.

El 15/11/2026 Railway confirmó el arranque tras corregir el conflicto entre módulos MPM de Apache. Se verificaron `/salud` y `/login` con HTTP 200, la autenticación de las seis cuentas ficticias y su redirección al cambio de contraseña. Tras activar Serverless, ambos servicios completaron sus despliegues y las dos rutas volvieron a responder con HTTP 200. El recorrido completo de un pedido requiere su propia validación con los participantes. La inclusión del comando de inicio en el Dockerfile reproduce el comando validado en el panel. Su nueva compilación local queda pendiente mientras Docker Desktop esté detenido.

Referencias de Railway: [MySQL](https://docs.railway.com/databases/mysql), [Dockerfile](https://docs.railway.com/builds/dockerfiles), [predeploy](https://docs.railway.com/deployments/pre-deploy-command), [red pública](https://docs.railway.com/networking/public-networking).
