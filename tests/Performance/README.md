# Fase 9B: preparación controlada, SIN EJECUTAR cargas

Estado: PREPARADO. No se creó infraestructura, no se ejecutó CargaSeeder, k6 ni ZAP. Estos archivos no autorizan instalaciones ni pruebas contra una aplicación. Leer [procedimiento completo](../../docs/pruebas/03_no_funcionales.md).

## Puertas obligatorias

1. Aprobación de instalación de herramientas e identidad del entorno independiente.
2. Crear infraestructura solo con autorización posterior, nunca sobre Laragon operativo ni su copia de respaldo.
3. Configuración privada externa, manifiesto con fecha límite, operaciones aprobadas y SHA-256 confirmado por el propietario.
4. Verificación de proceso/binario/argumentos, listeners exclusivamente loopback, checkout, Apache, MySQL efectivo, UUID/datadir/puerto/cuenta/base/roles y privilegios literales.
5. Autorización separada de inicialización, carga y medición/seguridad. Ningún ejemplo lleva una autorización utilizable.

Las pruebas de desarrollo solo validan lógica con metadatos simulados. No acreditan una instancia real. El PHP del seeder usa Tests/Performance, por lo que requiere dependencias de desarrollo; no es un seeder de producción y no se incorpora a DatabaseSeeder.

## Comandos FUTUROS, NO ejecutados

Ejecutar desde el checkout independiente después de la autorización correspondiente. RENDIMIENTO_CONFIG debe apuntar al archivo privado externo. No exportar sus secretos a logs ni pasar contraseñas por argumentos.

```powershell
$bootstrap = (Resolve-Path tests/Performance/configuracion/privada-bootstrap.php).Path
php -d "auto_prepend_file=$bootstrap" tests/Performance/verificar-entorno.php verificar
php -d "auto_prepend_file=$bootstrap" artisan demostracion:verificar --no-interaction
```

Solo tras aprobar inicialización, aplicar las 45 migraciones existentes en la base nueva, con identidad comprobada inmediatamente antes y después. No se agrega un instalador automático ni un comando destructivo. Presentar el listado exacto de rutas y comando de migración para aprobación antes de hacerlo. No invocar setup, DatabaseSeeder, AdminUserSeeder ni DemoSeeder.

Solo después de autorizar operación cargar y confirmar el esquema vacío:

```powershell
php -d "auto_prepend_file=$bootstrap" tests/Performance/verificar-entorno.php cargar
php -d "auto_prepend_file=$bootstrap" artisan db:seed --class=CargaSeeder --no-interaction
```

La carga deja una marca externa exclusiva antes de escribir. Un intento interrumpido no permite repetirla, reparar ni borrar automáticamente. Una carga completa queda persistida, con inventario verificado. La procedencia sintética se confirma privadamente solo después de revisar los datos; no activar esa declaración anticipadamente.

Solo después de autorizar medir, con manifiesto/SHA-256 y clave de control en el proceso, cuentas ficticias en un JSON privado y PEDIDO_FICTICIO_ID comprobado:

```powershell
./tests/Performance/ejecutar-k6.ps1 -Escenario humo -SalidaPrivadaNueva C:/privado/evidencias/ejecucion001-humo
./tests/Performance/ejecutar-k6.ps1 -Escenario carga -SalidaPrivadaNueva C:/privado/evidencias/ejecucion001-carga
./tests/Performance/ejecutar-k6.ps1 -Escenario estres -SalidaPrivadaNueva C:/privado/evidencias/ejecucion001-estres
```

Los nombres de salida son ejemplos: seleccionar una carpeta privada existente y un prefijo nuevo. Las variables de k6 se preparan en un proceso privado, nunca en un archivo versionado. El JSON de cuentas contiene 50 objetos email/password, una identidad por VU. No se publica.

El control HTTP usa Alias exclusivo del Apache de pruebas; NO se agrega a routes/. Comprueba DocumentRoot, identidad efectiva y firma HMAC de un nonce fresco antes de visitar módulos. La clave de control no se transmite. Un proceso o configuración cambiados bloquean. No usar los scripts k6 directamente omitiendo el lanzador y monitoreo.

## Interpretación

- Humo: 1 VU, una iteración.
- Carga: 20 VU durante 120 segundos; calentamiento previo identificado fuera de medición.
- Estrés: 0→10→20→35→50, sostener 50 durante un minuto, descenso a 1 y recuperación de un minuto. No reducir objetivos silenciosamente.
- p50 se publica como med. Separar HTTP, checks funcionales, control de identidad, infraestructura y fases de recuperación.
- El control añade sobrecarga real, incluida la inspección de procesos. No ocultarla: las latencias de listados se etiquetan aparte; el consumo de CPU incluye esas protecciones.
- Prohibido activar trazas HTTP con cuerpos/cookies. Reportes crudos privados; revisar/sanitizar antes de incorporarlos a docs/pruebas/evidencias/.

El lanzador no empieza con menos de 2048 MiB disponibles. El monitor termina únicamente su k6 ante menos de 512 MiB durante unas 15 muestras, fallo de observación o límite de 10 minutos. Las muestras tardan al menos 2 segundos, por lo que ese período no es un reloj exacto de 30 s. Conserva la interrupción y los VU alcanzados; no certifica haber completado 50 VU. Apache/MySQL y bases no se detienen, reinician ni reparan automáticamente.

La validación positiva de Apache, PHP, manifiesto, privilegios y el endpoint firmado requiere la infraestructura autorizada futura. Las plantillas son propuestas, no configuraciones ya certificadas.
