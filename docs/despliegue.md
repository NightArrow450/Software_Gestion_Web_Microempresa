# Preparación para producción y Railway

Esta fase prepara la aplicación. No realiza un despliegue ni modifica variables reales de Railway.

## Laragon

Conservar el .env local: APP_ENV=local, APP_DEBUG=true, APP_URL por HTTP y SESSION_SECURE_COOKIE=false. TRUSTED_PROXIES queda vacío y PROXY_MODE=standard. .env.example mantiene valores locales y campos vacíos para el administrador. No copiar el ejemplo de producción sobre el archivo local.

## Variables de producción

Usar .env.production.example como referencia para las variables del servicio, sin subir secretos. Crear una APP_KEY privada y estable para la instancia nueva; no regenerarla en una instancia existente. Completar la conexión MySQL y APP_URL con los valores reales al desplegar. No se incluyen credenciales reales en el repositorio.

APP_ENV=production activa HTTPS y, en la fase de cabeceras, HSTS. APP_DEBUG=false evita diagnósticos públicos. SESSION_SECURE_COOKIE=true requiere HTTPS. LOG_STACK=stderr,seguridad y LOG_LEVEL=warning mantienen los avisos de la aplicación; el canal de seguridad conserva también eventos informativos.

ADMIN_EMAIL y ADMIN_PASSWORD son obligatorios al ejecutar AdminUserSeeder en producción. La contraseña inicial exige al menos 10 caracteres, mayúsculas, minúsculas y números. El seeder asigna contraseña e indicador obligatorio SOLO al crear. Si encuentra la cuenta, conserva ambos valores y solo garantiza su rol y estado. No es una herramienta de restablecimiento.

DemoSeeder se ejecuta manualmente en desarrollo y rechaza producción. DatabaseSeeder no lo invoca. No contiene módulos adicionales ni cambia categorías de insumos.

## Proxy de Railway: decisión pendiente de verificación en el despliegue real

La documentación oficial del ingreso público de Railway especifica X-Real-IP como IP remota del cliente, X-Forwarded-Proto=https y X-Forwarded-Host como host original:
https://docs.railway.com/networking/public-networking/specs-and-limits

PROXY_MODE=railway toma X-Real-IP únicamente de un proxy confiable en producción y descarta X-Forwarded-For aportado por el cliente. Si X-Real-IP falta o es inválida, utiliza la dirección del par de red, sin inventar una IP. En modo standard utiliza las cabeceras habituales de proxies confiables.

TRUSTED_PROXIES acepta IP o rangos CIDR separados por comas. No se han verificado rangos de ingreso para este proyecto y no se inventan. Por eso el ejemplo lo deja vacío. Antes del despliegue:
1. Confirmar la topología y configurar IP/rangos reales si la plataforma los proporciona.
2. Si no se pueden conocer, se permite explícitamente TRUSTED_PROXIES=* SOLO con APP_ENV=production, PROXY_MODE=railway y todo el tráfico de usuarios entrando por el proxy HTTP público de Railway.
3. Documentar en la configuración de ese despliegue por qué se eligió * y comprobar IP, HTTPS y host con una solicitud externa real.

Riesgo de *: cualquier par puede presentar cabeceras confiables. No exponer el servidor HTTP mediante TCP Proxy ni permitir servicios privados no confiables que lo contacten directamente. Esas rutas evitan el ingreso público y podrían suplantar la IP usada por el limitador y la auditoría. No se elige * automáticamente; en local se ignora aunque se configure accidentalmente.

La redirección HTTPS conserva el método (308). /salud y /up permiten comprobaciones HTTP internas sin autenticación ni datos sensibles. Railway exige HTTP 200 en el healthcheck y permite configurar /salud como ruta de comprobación:
https://docs.railway.com/deployments/healthchecks

## Pasos posteriores, autorizados al decidir desplegar

Revisar y respaldar la base de datos. Ejecutar php artisan migrate --force y luego php artisan db:seed --class=AdminUserSeeder --force --no-interaction con variables válidas y roles ya instalados. La migración nueva solo añade debe_cambiar_password=false para cuentas existentes; no cambia hashes. Las cuentas iniciales nuevas deben cambiar su contraseña al ingresar. No ejecutar DemoSeeder en producción.

Compilar los recursos con npm ci y npm run build. Después de configurar variables, reconstruir la caché con php artisan config:cache y verificar que storage y bootstrap/cache sean escribibles. Usar un almacenamiento compartido de caché (database/Redis) entre réplicas para que el limitador no dependa del proceso. No usar el servidor de desarrollo de Artisan como servidor público.

Comprobar /salud, HTTPS, las cabeceras y los eventos de seguridad antes de habilitar usuarios. Los archivos de log diarios requieren almacenamiento persistente o exportación si deben sobrevivir reinicios; stderr queda disponible para el sistema de logs de Railway. No registrar contraseñas, cookies, tokens, cuerpos completos ni consultas con secretos.