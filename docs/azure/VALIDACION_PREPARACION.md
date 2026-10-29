# Validación de la preparación Azure

Fecha: 29/10/2026. Revisión local; no equivale a un despliegue remoto.

Comprobado:

- YAML del workflow interpretado correctamente; ejecución por push a develop y permisos contents:read.
- Sintaxis Bash de los scripts y de los bloques del workflow.
- Guardas de los scripts: rechazan arrancar fuera de producción/MySQL o empaquetar fuera del runner Linux previsto, antes de tocar el sistema.
- Empaquetador Python probado con una carpeta sintética: genera ZIP con rutas relativas y SHA256; rechaza archivos .env y un paquete sin manifest Vite.
- Sintaxis PHP de los dos bloques de la guía (comprobación de base y creación de usuario limitado).
- Scripts guardados con finales de línea LF; diff sin errores de espacios.

Pendiente en GitHub/Azure:

- Ejecutar el workflow completo con instalación no-dev, build Vite, nginx -t en Linux y generación del artefacto real.
- Revisar precio, crédito, permisos, cuotas y región de la suscripción.
- Publicar el artefacto, validar red privada, MySQL/TLS, permisos, migraciones y seeders.
- Comprobar HTTPS, sesiones, funcionalidades, persistencia tras reinicio/republicación y restauración independiente.
- Configurar ejecución permanente del scheduler para operación continua; la guía inicial documenta ejecución manual del diagnóstico de inventario.

No se modificaron controladores, modelos, middleware, migraciones, lockfiles ni el .env local. La aplicación local conserva sus pruebas anteriores; no se volvió a ejecutar la suite completa porque esta preparación agrega exclusivamente archivos de despliegue y documentación.
