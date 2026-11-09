# Instalación local, mantenimiento y recuperación

## Checkout nuevo

Requisitos: PHP 8.3 con extensiones requeridas por Composer (incluidas PDO MySQL/SQLite, mbstring, intl, XML, curl y fileinfo), Composer, Node 22/npm y MySQL 8.4.3 para reproducir integración. Conservar Laragon operativo.

1. Clonar en una ruta nueva, sin sobrescribir archivos existentes.
2. Instalar desde lockfiles: composer install --no-scripts y npm ci. No usar composer setup: incluye inicializaciones.
3. Solo en el checkout nuevo, preparar configuración privada basada en el ejemplo y clave propia. No copiar el archivo operativo, sus credenciales o datos.
4. Verificar identidad de servidor y base vacía antes de autorizar migraciones y seeders específicos. Revisar los seeders de roles/permisos; no ejecutarlos indiscriminadamente sobre bases existentes.
5. Compilar con npm run build. Asegurar escritura de storage y bootstrap/cache del checkout nuevo.
6. Servir public/ con Laragon u otro servidor local. Artisan serve es para desarrollo, no certifica capacidad de producción.

Estas inicializaciones no se ejecutaron como parte del PR 5. Para demostraciones seguir [el procedimiento separado](demostracion.md).

## Proyecto operativo existente

Revisar Git, preservar cambios locales, comprobar el commit aprobado y su diff antes de actualizar. No sobrescribir .env ni ejecutar seeders generales o comandos de borrado.

El esquema de referencia tras surtidos tiene 45 migraciones y 44 tablas. #40 y PR 5 no agregan migraciones. Una base de pruebas puede tener marcadores adicionales. Estos números documentados no sustituyen una comprobación real autorizada.

## Respaldo y recuperación

Antes de un cambio autorizado:

1. Detener escrituras; comprobar conexiones, procesos y motores compatibles con respaldo consistente.
2. Crear SQL completo, nuevo y fechado con mysqldump --single-transaction --quick --result-file. Incluir estructura, datos y triggers; revisar eventos/rutinas existentes. No incluir CREATE DATABASE ni USE de la base original.
3. Verificar tamaño/SHA-256; restaurar en una instancia/base independiente y comparar estructura efectiva y contenido, no solo conteos.
4. Conservar archivos y credenciales fuera de Git y de videos.
5. Aplicar únicamente migraciones/seeders específicos autorizados, verificando cada resultado y comparando historia y saldos.

Ante fallo DDL, detenerse: MySQL puede haber aplicado parte del cambio. No reintentar a ciegas, ejecutar rollback ni restaurar automáticamente. Conservar evidencia y solicitar autorización para recuperar.

Recuperar primero en otra base, verificarla y planificar la conexión. Restaurar sobre la original requiere autorización específica y control de escrituras. Revertir código no revierte el esquema.

## Verificación y mantenimiento

inventario:verificar --json e inventario:revisar-despachos-previos diagnostican; no autorizan reparar historia. El verificador conserva resultados del informe en memoria.

Revisar espacio, rotación y permisos de logs. No publicar secretos, sesiones ni respaldos. Las auditorías de dependencias pueden cambiar sin modificaciones de código; investigar vulnerabilidades sin ocultarlas ni actualizar automáticamente.

composer test y composer analyse usan tests/bootstrap.php: SQLite en memoria, servicios aislados y cachés por PID. MySQL se configura por separado conforme a [CI](integracion-continua.md). Nunca apuntar suites al entorno operativo.

Las pruebas finales de carga, seguridad, navegadores y aceptación pertenecen a Fase 9.
