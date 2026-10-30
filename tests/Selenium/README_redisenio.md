# Revisión integral del rediseño con Selenium (PR44 / PR45)

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

## Seis escenarios verificados

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

6. Modal desde la lista en 360/390 px sin confirmar producción: consumos visibles,
   Escape cierra y stock/movimientos no cambian. Editor de agrupaciones ya enviadas
   en móvil y distribución por salida e historial 54 → 32 visibles en escritorio.

Los snapshots de stock se obtienen mediante consultas de lectura sobre la base
visual verificada. Las capturas y el PDF contienen exclusivamente datos ficticios
y se guardan en `evidencia/` del laboratorio. Se versiona una selección sin secretos para la revisión. El PDF debe
revisarse visualmente y contarse; no inferir su legibilidad por la prueba HTML.

**Resultado real:** seis casos distintos aprobados. Campaña principal 5/5 en
334,44 s; repetición más estricta del caso 05 y nuevo caso 06, 2/2 en 164,65 s.
Las cuatro pruebas no repetidas en la segunda campaña ya estaban aprobadas;
no se omiten fallos. El intento inicial erró por el selector de envío del propio
guion y se conservó su log. El usuario inició manualmente el servidor autorizado.
PDF: dos páginas A4 horizontales, dos notas y un anexo, sin hojas vacías. Véase
[la evidencia seleccionada](../../docs/pruebas/evidencias/redisenio_pr44/README.md).
No equivale a UAT real, pruebas de estrés ni revisión en dispositivos físicos.

## Repetición PR45

El manifiesto admite `evidencia_visual` para guardar otra campaña en una carpeta nueva y conservar los ID y capturas anteriores. Preparar siempre una base vacía nueva; no reutilizar una campaña ya ejecutada. El seeder de surtidos ya no se ejecuta. Los nombres `@pr44.test` y `/_pr44/identidad` son identificadores ficticios del arnés existente, no accesos al sistema operativo. Los resultados actuales se registran en el informe del PR45.
