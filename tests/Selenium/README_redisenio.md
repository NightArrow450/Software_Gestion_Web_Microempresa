# Revisión integral del PR44 con Selenium

Esta revisión es independiente de los archivos Selenium locales de la Fase 9C.
No sustituye ni sobrescribe `test_flujos_webdriver.py` ni su lanzador.

## Aislamiento obligatorio

- Checkout independiente sin `.env` operativo.
- MySQL 8.4.3 distinto del operativo, puerto distinto de 3306, UUID y directorio
  de datos propios. No debe contener ninguna de las dos bases protegidas.
- Base visual nueva `dhacer_eco_limp_test_visual_*` y cuenta HTTP con permisos
  DML exclusivamente sobre esa base. Cuenta administrativa privada solo para
  inicializar el esquema vacío, nunca para servir solicitudes.
- Manifiesto y credenciales fuera de Git, en el directorio indicado mediante
  `DHACER_RED_ENV`. No copiar datos de clientes ni respaldos reales.
- `entorno_redisenio.php` comprueba checkout, dirección, puerto, UUID, versión,
  directorio de datos y base efectiva antes de permitir las operaciones.
  El bootstrap de pruebas bloquea conexiones a bases no autorizadas.
- Servidor PHP en `127.0.0.1:8026`, router protegido. No publicar ni usar este
  servidor en producción. Sesiones, cookies y logs separados del sistema real.
- El navegador exige una respuesta de identidad firmada con un nonce antes de
  enviar las credenciales ficticias. Utiliza un perfil nuevo, sin credenciales
  guardadas ni extensiones del usuario.

## Preparación y ejecución

Preparar la instancia y el manifiesto privados exige verificar previamente su
identidad. El manifiesto contiene `checkout`, `escucha`, `puerto`, `datadir`,
`uuid` y `base_visual`; el archivo privado separado contiene las claves del
laboratorio. No se incluyen valores de contraseñas en este repositorio.

En la base visual **vacía**, una sola vez:

```powershell
php tests/Selenium/preparar_redisenio.php preparar
```

El script rechaza bases que ya contengan tablas. Ejecuta las migraciones y los
seeders de permisos explícitos solamente después de comprobar la identidad.
Crea datos marcados como ficticios: tres roles, tres usuarios, un cliente,
SKU y materiales de ensayo, y un pedido histórico de dos empaques de 12 a
S/24 por empaque, distinto del precio unitario actual del catálogo.

Servidor, en otra terminal del mismo checkout y con `DHACER_RED_ENV` configurado:

```powershell
php -S 127.0.0.1:8026 -t public tests/Selenium/servidor_redisenio.php
```

Instalar `requirements_redisenio.txt` en un entorno virtual privado, sin
modificar Python global. Configurar `DHACER_CHROMEDRIVER` con la ruta del driver
compatible con Chrome; `DHACER_CHROME` permite indicar otro ejecutable de Chrome.

```powershell
python -m pytest tests/Selenium/test_redisenio_webdriver.py -v -x
```

No hay limpieza automática ni recreación de bases. Para repetir una campaña
completa se prepara otro laboratorio vacío verificado. Si se interrumpe, los ID
ficticios quedan en `evidencia/escenario-ids.json`; revisar las operaciones ya
registradas antes de reanudar un caso individual. No repetir escrituras a ciegas.

## Cinco escenarios preparados

1. Venta de 53 unidades a S/2: 5 Lavanda sueltas, paquete de 24 Frutos y paquete
   mixto de 8 BB, 8 Manzana y 8 Limón; copia y edición independiente. Total S/106.
   Otros pedidos: 7 Limpiavidrios a S/3 y 54 Quitagrasa a S/6, sin stock inicial.
2. Preparación física y dos salidas: 29 y 24 unidades. Saldos por aroma:
   95, 76, 92, 92 y 92, partiendo de 100 por SKU.
3. Rechazo de 2 BB, recepción de 1 apta y 1 dañada; sin entrada hasta aprobación.
   Operaciones autoriza 1: BB queda en 93. Reparto no puede aprobar ni rectificar.
4. Envasado confirmado de 32 Quitagrasa desde el modal de preparación: consume
   32 envases, etiquetas y contratapas ficticios configurados; manga sin consumo.
   Edición comercial 54 → 32 sin duplicar producción y salida posterior de 32.
5. Escritorio, 360, 390, 768 y 1024 px sin desborde del documento; nota histórica
   sin conversión; dos notas por hoja y anexo de agrupaciones, PDF y consola.

Los snapshots de stock se obtienen mediante consultas de lectura sobre la base
visual verificada. Las capturas y el PDF contienen exclusivamente datos ficticios
y se guardan fuera del repositorio, en `evidencia/` del laboratorio. El PDF debe
revisarse visualmente y contarse; no inferir su legibilidad por la prueba HTML.

**Estado actual:** preparación completada; Selenium, capturas y revisión final
de impresión **PENDIENTES DE EJECUCIÓN**. La revisión automática bloqueó iniciar
el servidor web desde la herramienta. Se ha solicitado su arranque manual;
no se ha intentado eludir ese bloqueo ni se presentan resultados simulados.
