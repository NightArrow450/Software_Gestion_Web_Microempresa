# Preparación neutral para un despliegue futuro

No existe despliegue público contratado o realizado por esta fase. Laragon continúa como entorno operativo local. No se requiere Railway ni un proveedor específico.

## Futuro servidor Linux

Revisar PHP 8.3 y extensiones desde composer.lock, MySQL compatible (validación actual con 8.4.3), servidor HTTP con raíz public/ y PHP-FPM, HTTPS y permisos de storage/bootstrap/cache. Instalar desde lockfiles y compilar Vite con Node 22. No usar artisan serve como servidor público ni subir node_modules o secretos.

Preparar variables privadas: APP_ENV=production, APP_DEBUG=false, APP_URL HTTPS, APP_KEY estable y privada, cookies seguras, conexión exclusiva y proxies explícitamente confiables. No copiar estos valores al .env local. La configuración de demostración debe estar desactivada.

Las opciones PROXY_MODE=railway existentes son una compatibilidad previa y opcional, no una dependencia ni una autorización de despliegue. El modo estándar se conserva. Nunca confiar indiscriminadamente en proxies; verificar la topología y las cabeceras en el destino real antes de activarlas.

## Persistencia y operación

Mantener MySQL en almacenamiento persistente privado con usuario limitado y copias verificadas. No exponer el puerto de base de datos públicamente. storage y archivos subidos deben persistir si se utilizan; no asumir persistencia de un filesystem efímero.

Definir almacenamiento de sesiones/caché adecuado a una o varias instancias. Revisar QUEUE_CONNECTION y tareas realmente registradas antes de instalar workers o cron; no inventar jobs inexistentes. Programar rotación de logs y copias con restauración independiente. Los logs de seguridad no deben incluir claves, cookies o cuerpos sensibles.

/salud y /up permiten comprobaciones; una respuesta puntual no certifica todos los flujos de negocio. Verificar HTTPS, permisos, compilación y módulos después de una actualización autorizada.

## Actualización y recuperación

Ver [operación local](operacion-local.md) para respaldo, aplicación individual de cambios autorizados y recuperación. Mantener una versión anterior compatible y analizar DDL antes de revertir código. No restaurar automáticamente ni ejecutar seeders generales sobre información existente.

.env.production.example es solo referencia; AdminUserSeeder requiere credenciales privadas y no es herramienta de restablecimiento. DatabaseSeeder no debe ejecutarse como actualización general. DemoSeeder no es un control de aislamiento y no se usará para inicializar producción.

Una prueba remota o un despliegue futuro requerirá autorización, presupuesto revisado si aplica y datos sintéticos al inicio. No usar clientes reales en servicios gratuitos de prueba. La Fase 9 precede a cualquier afirmación de aceptación o rendimiento de producción.
