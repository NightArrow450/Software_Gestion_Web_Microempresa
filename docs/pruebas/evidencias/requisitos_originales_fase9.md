# Especificación original de Fase 9 (fuente archivada)

Adjunto del propietario 66aabe76-6df7-40b7-9fb7-9879b7abe01d. SHA-256 del archivo íntegro: 5ec4668bb6dbdb5409705953f5fee9f02e7af132667936bee0a0cf1220aa391e.

Se conserva el texto original. Sus referencias a Railway/Laragon NO autorizan utilizar la base real ni desplegar: rigen las condiciones actuales de instancia independiente y aprobación previa. Los puntos 8–13 no se ejecutan en 9A.

---

FASE 9. PRUEBAS FINALES: FUNCIONALES, NO FUNCIONALES Y DE ACEPTACIÓN
==============================================================
Rama sugerida: feature/fase9-pruebas-finales
(Recuerda: primero análisis breve de los archivos actuales, los cambios
propuestos y los riesgos, y espera mi OK antes de modificar código,
especialmente en migraciones o cambios sensibles.)

OBJETIVO: cumplir con 3 tipos de pruebas distintos, reproducibles y
con evidencia, para el capstone. Todo va en docs/pruebas/. Esta fase
es de EVALUACIÓN GENERAL y DOCUMENTACIÓN DE EVIDENCIAS: no agrega
funcionalidad nueva. El envasado ya fue implementado en la Fase 7 y
aquí solo se prueba; si una prueba revela un defecto, se corrige
como defecto (con su ID en el informe) sin ampliar el alcance.

TIPO 1. PRUEBAS FUNCIONALES (automatizadas, PHPUnit)
  Incluye integración (flujos completos) y unitarias de servicios.
1. Cubre los módulos que hoy NO tengan pruebas: login/autenticación
   (éxito, fallo, usuario inactivo, logout), usuarios, roles y
   permisos, productos y categorías (CRUD, validaciones, permisos).
   Verifica primero qué existe y no dupliques pruebas ya hechas.
2. Prueba de flujo completo (integración): registrar pedido ->
   confirmar -> preparar -> despachar (descuenta stock y crea el
   movimiento vinculado) -> entregar -> consultar seguimiento e
   historial; verifica estados, historial, movimientos y stock.
3A. Flujo de ENVASADO POR PEDIDO (respeta el orden de estados):
   configurar materiales de la presentación -> cargar insumos
   (envases, etiquetas, contratapas) -> registrar pedido -> confirmar
   -> registrar el envasado vinculado al pedido en un estado que lo
   permita (descuenta insumos, suma producto terminado, NO cambia el
   estado del pedido) -> preparar -> listo para despacho ->
   despachar (descuenta solo productos terminados, no toca insumos) ->
   entregar -> consultar seguimiento e historial. Incluye varios
   envasados parciales del mismo pedido y presentación. Verifica
   también que no se puede vincular un envasado a un pedido en un
   estado no permitido (sin confirmar, cancelado, despachado o
   entregado).
3B. Flujo de ENVASADO ANTICIPADO (sin pedido, orden distinto):
   configurar materiales -> cargar insumos -> registrar envasado
   anticipado (sube producto terminado, baja insumos) -> DESPUÉS
   registrar pedido -> confirmar -> preparar -> despachar (consume
   el stock ya envasado, sin nuevo descuento de insumos) -> entregar.
   Verifica que el envasado anticipado no exigió pedido y que el
   despacho no registró otro envasado.
3C. Casos de corrección y casos negativos de envasado: insumo
   insuficiente, presentación sin configuración, doble envío,
   anulación doble, anulación por error administrativo vs pérdida
   física (materiales devueltos o no según corresponda), anulación
   bloqueada por stock disponible insuficiente (sin afirmar que
   unidades específicas fueron despachadas), anulación permitida con
   stock suficiente, y `inventario:verificar` sin diferencias nuevas
   tras cada flujo (considerando los saldos iniciales). Verifica que
   el stock previo cargado manualmente no se duplica.
4. Matriz de permisos: cada rol recibe 403 en lo que no le
   corresponde (incluidos envasado.ver, envasado.registrar,
   envasado.corregir, envasado.reportar y
   materiales.envasado.configurar); todo `permiso:*` de las rutas
   existe en el seeder y el Administrador lo tiene; usuario inactivo
   con sesión abierta.
5. Unitarias de servicios: CalculadoraPedido, conversión de empaques
   a unidades, DescontarInventarioPorDespacho, transiciones de
   estado y los servicios de envasado (cálculo de consumo de
   materiales, descuento, reversión).
6. Prueba de humo: todas las rutas GET (incluidas las de envasado y
   de configuración de materiales) responden 200 con admin y
   redirigen o dan 403 sin sesión.
7. Documento docs/pruebas/02_casos_funcionales.md: tabla con ID,
   módulo, requisito (indicador X1-X12 del cuestionario), precondición,
   pasos, resultado esperado, resultado obtenido y estado; con los
   casos de envasado e integración con inventario y despacho. Pega
   la salida real de `php artisan test` y, si hay PCOV/Xdebug, la
   cobertura (si no, indícalo; no inventes el porcentaje). Incluye
   el resultado de las pruebas de concurrencia reales en MySQL de la
   Fase 6 o, si no se pudieron ejecutar, su estado PENDIENTE DE
   EJECUCIÓN con el comando exacto.

TIPO 2. PRUEBAS NO FUNCIONALES (carga, seguridad, compatibilidad)
8. CARGA Y RENDIMIENTO con k6: scripts en tests/Performance/
   (humo con 1 usuario virtual; carga de 20 usuarios virtuales
   durante 2 minutos; estrés con rampa hasta 50). Flujo: login ->
   listado de pedidos -> buscador de productos -> ver pedido ->
   lista de preparación -> listado de envasados. Las operaciones de
   escritura de envasado se prueban en un escenario aparte y solo
   en local o staging. Metas: p95 < 1 s en listados y < 1% de
   errores. CargaSeeder (SOLO local o staging, nunca producción):
   300 clientes, 150 productos con presentaciones, 3.000 pedidos en
   distintos estados con sus movimientos, y materiales configurados
   con un volumen razonable de envasados históricos. Ejecutarlo
   contra la URL de Railway o Laragon con servidor real;
   `php artisan serve` es monoproceso y no representa la carga real:
   déjalo anotado.
9. SEGURIDAD: OWASP ZAP baseline contra la URL desplegada
   (zap-baseline.py, reporte HTML), revisión de cabeceras, y pruebas
   automáticas: login bloqueado tras 5 fallos, POST sin token CSRF
   da 419 (incluido el POST de envasado), acceso sin sesión
   redirige, HTML escapado (XSS) en nombres, notas, observaciones y
   observaciones de envasado, texto tipo ' OR 1=1 -- en los
   buscadores no produce error 500 ni resultados indebidos,
   `composer audit` y `npm audit`.
10. COMPATIBILIDAD Y USABILIDAD: lista de verificación de las
    pantallas principales (incluidas las de envasado y
    configuración de materiales) a 360, 390, 768, 1024 y 1366 px en
    Chrome y Firefox (y Edge si es posible), sin scroll horizontal
    del body; tiempo para registrar un pedido de 5 líneas (meta
    <= 90 s). Mide y reporta el tiempo para registrar un envasado,
    sin meta numérica hasta acordarla con la empresa.
11. Documento docs/pruebas/03_no_funcionales.md con metodología,
    herramientas y versiones, metas, resultados REALES y
    conclusiones; guarda reportes en docs/pruebas/evidencias/. Si
    algo no se pudo ejecutar, déjalo como PENDIENTE DE EJECUCIÓN con
    el comando exacto para correrlo.

TIPO 3. PRUEBAS DE ACEPTACIÓN (UAT)
12. docs/pruebas/04_aceptacion_UAT.md: casos por rol redactados en
    lenguaje de la empresa (ej. «el vendedor registra un pedido que
    llegó por llamada», «producción revisa lo que debe preparar»,
    «producción registra las botellas que envasó y el sistema
    descuenta envases, etiquetas y contratapas», «producción
    corrige un envasado mal anotado», «reparto despacha y entrega»,
    «ventas consulta el estado de un pedido», «el administrador
    configura los materiales de una presentación»), con criterios
    de aceptación ligados a los requisitos de la entrevista (Anexo
    7) y a los ítems X1-X12.
13. Plantilla de acta de aceptación: fecha, participante, rol, caso,
    tiempo medido, resultado (Aprobado / Observado), comentarios y
    firma o nombre. Tabla resumen. Solo prepara el formato: la
    ejecución la hago yo con usuarios reales, no inventes
    resultados.

CIERRE
14. docs/pruebas/00_informe_resumen.md: tabla por tipo (casos,
    aprobados, observados, pendientes), defectos encontrados y
    corregidos (ID, severidad, estado) y conclusión. Si queda algún
    caso PENDIENTE DE EJECUCIÓN, indícalo con claridad. Todo el
    informe se basa en resultados reales.