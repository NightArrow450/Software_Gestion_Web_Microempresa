# Demostración aislada: preparación y autorización pendiente

**Preparado, sin inicializar:** no se creó una base demo ni se ejecutó un seeder de demostración. Se requiere autorización específica antes de hacerlo.

Prohibidas para estos recorridos: software_gestion_web_microempresa y ecolimp_respaldo_prueba.

## Entorno propuesto

- Checkout separado con public/, storage, cachés, sesiones y clave propios.
- Instancia MySQL independiente, solo 127.0.0.1, puerto distinto de 3306 (por ejemplo 3313, previa comprobación) y UUID verificado.
- Base exacta con prefijo dhacer_eco_limp_demo_; cuenta demo_ con contraseña privada y privilegios limitados a esa base literal. Sin privilegios globales, roles heredados ni GRANT OPTION.
- Sin túneles, hosting público, copias de usuarios o respaldos empresariales.

No se incorpora un script de borrado ni inicialización automática.

## Configuración privada del checkout nuevo

Nunca modificar el .env operativo. Los marcadores siguientes no son credenciales utilizables:

```text
APP_ENV=demo
APP_DEBUG=false
DEMO_ACTIVA=true
DEMO_PROYECTO_OPERATIVO=<ruta existente del checkout operativo>
DEMO_PUERTO_MYSQL=<puerto independiente>
DEMO_SERVIDOR_UUID=<UUID comprobado de esa instancia>
DEMO_DATOS_SINTETICOS_CONFIRMADOS=false
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=<mismo puerto independiente>
DB_DATABASE=dhacer_eco_limp_demo_<identificador>
DB_USERNAME=demo_<cuenta>
DB_PASSWORD=<secreto privado>
DB_URL=
DB_SOCKET=
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

Usar APP_URL y nombre de cookie exclusivos, distintos del operativo. No compartir directorios de sesiones/caché ni configuración cacheada. Evitar envíos reales de correo u otros servicios externos.

## Verificación previa de solo lectura

Desde el checkout separado, después de autorizar la preparación de infraestructura independiente:

```powershell
php artisan demostracion:verificar --no-interaction
```

El comando no migra, crea usuarios, siembra ni cambia permisos. Comprueba:

1. APP_ENV=demo y activación expresa.
2. Ruta operativa existente, diferente al checkout actual.
3. MySQL sin DB_URL, socket ni conexiones alternativas de lectura/escritura.
4. Host local, puerto independiente confirmado, base/cuenta permitidas y contraseña no vacía.
5. DATABASE(), puerto, UUID, CURRENT_USER() y versión MySQL 8.4 efectivos.
6. SHOW GRANTS: solo USAGE global y concesiones sobre la base exacta, sin roles/delegación.

Si partial_revokes está desactivado, MySQL interpreta guiones bajos de concesiones como patrones: deben escaparse para conceder únicamente la base literal. El verificador rechaza patrones amplios. Con partial_revokes activado admite el nombre literal. No cambiar esa variable en Laragon operativo.

La salida no imprime contraseñas ni concesiones completas. Una infraestructura todavía inexistente no podrá validarse. No omitir el fallo ni convertirlo en éxito.

## Puerta de autorización antes de inicializar

Presentar al propietario rutas de checkouts, binario/datadir/PID independientes, puerto/UUID, base/cuenta y concesiones limitadas, sin secretos. Solicitar autorización para crear el esquema vacío con migraciones existentes y ejecutar solo seeders revisados para esa base nueva. No usar DatabaseSeeder ni DemoSeeder por comodidad.

Después, introducir datos sintéticos mediante formularios y servicios existentes, en el orden de los guiones. La creación de infraestructura, inicialización y recorridos con escritura están **PENDIENTES DE AUTORIZACIÓN Y EJECUCIÓN**. No son el CargaSeeder ni las pruebas de estrés.

## Datos ficticios previstos

| Datos | Uso |
| --- | --- |
| Tres cuentas ficticias, una por rol, contraseñas privadas | Accesos y restricciones. |
| Cliente Demostración A y B, sin documentos/teléfonos reales | Pedidos y seguimiento. |
| Limpiador de demostración, aromas Alfa/Beta/Gamma, 500 ml y 1 L | Catálogo y SKU independientes. |
| Surtido de 12 para 1 L, dos composiciones diferentes | Demanda y precio por paquete. |
| Envase DEMO, etiqueta DEMO y material complementario DEMO | Configuraciones manuales, sin compatibilidades empresariales inventadas. |
| Entradas sintéticas con motivo Demostración | Stock para los recorridos. |
| Pedidos normal, mixto y con composición pendiente | Reglas comerciales y físicas. |
| Envasados anticipado/vinculado y correcciones separadas | Materiales e historial. |
| Entrega parcial, retorno apto/dañado y aprobación | Stock solo tras autorización. |

Anotar cantidades sintéticas de materiales y saldos antes de grabar. No atribuirles validez empresarial. No configurar mangas a partir de pedidos.

Tras revisar que todos los datos son sintéticos, el propietario puede autorizar DEMO_DATOS_SINTETICOS_CONFIRMADOS=true en ese checkout. Es una declaración de procedencia del operador, no una deducción automática por nombre de base.

El middleware verifica identidad/privilegios y esa confirmación antes de sesiones; si falla responde 503. El rótulo DEMOSTRACIÓN aparece en login y páginas del layout solo después de validar. El entorno normal no añade lecturas MySQL ni muestra el rótulo.

Para repetir, preparar otra base demo identificada con autorización, o restaurar un respaldo exclusivamente sintético verificado en otra base demo. No hay reset destructivo. Registrar el identificador de cada recorrido.

## Pendientes explícitos

- Validación positiva con instancia/cuenta demo real: PENDIENTE DE AUTORIZACIÓN Y EJECUCIÓN.
- Inicialización, escrituras, recuperación sintética: PENDIENTES.
- Los tests del guard usan rechazos y metadatos simulados; no acreditan una infraestructura aún no creada.
- Videos definitivos: después de las validaciones de Fase 9.
