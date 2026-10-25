# Dos videos de DHACER ECO LIMP

Guiones para grabación manual, con datos exclusivamente ficticios. No se graban automáticamente. Los recorridos HTTP y servicios se comprobaron en 9B; la revisión visual multibrowser quedó PENDIENTE por el bloqueo del control de interfaz. No afirmar que esa revisión o las UAT ya aprobaron.

## Preparación común

- Utilizar únicamente `http://127.0.0.1:8089`, checkout independiente y base `dhacer_eco_limp_demo_f9b_001`. Nunca grabar sobre la aplicación operativa de Laragon.
- Abrir PowerShell y ejecutar el lanzador probado:

```powershell
& 'C:/Users/Stalin/AppData/Local/DhacerRendimiento/f9b_001/checkout/tests/Performance/iniciar-demostracion.ps1' -RaizPrivada 'C:/Users/Stalin/AppData/Local/DhacerRendimiento/f9b_001'
```

- El lanzador valida rutas, rechaza enlaces/junctions, comprueba configuraciones, inicia solo los procesos independientes cuando sea necesario, actualiza sus identidades y verifica MySQL/HTTP. No inicializa, migra ni siembra datos. Su sesión documental vence en cuatro horas; volver a invocarlo renueva ese plazo. No alterar políticas de seguridad si Windows bloquea el script.
- Confirmar el rótulo de demostración antes de grabar. La configuración privada permanece fuera del checkout; no abrirla en cámara ni mostrar logs, cookies, tokens o contraseñas.
- Cuentas ficticias: `admin@carga.invalid` (Administrador), `vu1@carga.invalid` (Operaciones Comerciales), `produccion@carga.invalid` (Producción/Reparto). La contraseña está en `usuarios_carga_clave` del archivo privado `credenciales-preparadas.json`, bajo la raíz del entorno. Consultarla fuera de cámara; no copiarla al repositorio o al guion.
- Datos comprobados al terminar la carga: 300 clientes, 150 productos, 450 SKU, 3000 pedidos. La medición HTTP posterior agregó el pedido 3001, de cinco líneas, y un envasado de dos unidades. No borrar registros para repetir tomas: anotar los nuevos números y saldos.
- Producto de ejemplo existente: `Producto sintético 0`, aromas Alfa/Beta/Gamma, presentación 1 L, SKU `CARGA_0_ALFA`, `CARGA_0_BETA`, `CARGA_0_GAMMA`; unidad S/ 2,00 y paquete de 12 S/ 24,00. El formato surtido y la alternativa de materiales de Alfa ya están configurados. Son consumos ilustrativos ficticios; no recetas de la empresa. No se consumen mangas.
- Preparar Excel fuera de cámara si se mostrará un CSV. La codificación/separador se comprobaron por HTTP; una apertura nueva en Excel y la legibilidad visual requieren comprobación manual.

## Video 1 — Funcionamiento de la página web

Las operaciones siguientes son las tareas previstas para grabar, no nuevas operaciones que el agente haya registrado como parte de este guion. Conservar el número del pedido que se cree y usarlo en las etapas posteriores. Pausar la grabación al cambiar de cuenta.

| Orden | Navegación y rol | Operación / resultado que mostrar | Narración sugerida |
| --- | --- | --- | --- |
| 1 | `/login` → `/dashboard`, Administrador | Iniciar sesión y señalar el entorno ficticio. Mostrar el panel y los accesos reales. | «Esta es una demostración aislada; no contiene información de clientes reales». |
| 2 | `/usuarios` → `/roles-permisos`, Administrador | Consultar los tres roles y sus permisos sin modificarlos. | «Los accesos se controlan en las rutas y en el servidor, además del menú». |
| 3 | `/clientes` → `/productos`, Administrador o Comercial | Consultar un cliente sintético y Producto sintético 0. Abrir sus tres SKU y precios. | «Cada aroma tiene inventario físico propio; el catálogo no crea un producto por cada combinación surtida». |
| 4 | `/modalidades-surtidas`, Comercial | Consultar el formato compatible de doce unidades, sin crear combinaciones fijas. | «El formato define tamaño y precio; los aromas se distribuyen en cada pedido». |
| 5 | `/pedidos/nuevo`, Comercial | Seleccionar un cliente sintético. Añadir una caja normal de 12 Alfa y una línea con dos surtidos de 12. Paquete 1: 5 Alfa, 4 Beta, 3 Gamma. Copiar al paquete 2 y modificarlo a 2 Alfa, 7 Beta, 3 Gamma. Cada paquete debe sumar 12. Guardar y anotar el número generado. | «Podemos copiar para agilizar y después modificar cada paquete de forma independiente». |
| 6 | Detalle `/pedidos/{id}` | Comprobar total S/ 72,00: una caja normal de S/ 24 más dos surtidas de S/ 24. Demanda física: Alfa 19, Beta 11, Gamma 6; total 36 unidades. Consultar la nota y el anexo; revisar la vista previa manualmente antes de imprimir. | «Los componentes no vuelven a sumar sus precios. El paquete conserva su precio comercial». |
| 7 | Detalle del pedido, Comercial | Confirmar mediante el control existente y consultar `/seguimiento/{id}`. | «El pedido original y las transiciones quedan en el historial». |
| 8 | `/inventario` → `/inventario/movimientos`, Producción/Reparto | Consultar stock de los SKU y los tres materiales. Anotar el saldo actual de Alfa, llamado S0. | «Una existencia disponible no significa producción nueva ni reserva exclusiva para un cliente». |
| 9 | `/envasados/nuevo`, Producción/Reparto | Elegir Alfa, su alternativa configurada y 2 unidades, sin pedido. Consultar consumos y confirmar solo esta producción ficticia. Esperar Alfa S0+2 y consumo de 2 envases, 2 etiquetas y 2 contratapas, según la configuración sintética. | «El consumo y la entrada de producto terminado se realizan juntos; no debemos registrar otra entrada manual». |
| 10 | `/cumplimiento/lista-preparacion`, Producción/Reparto | Buscar el producto. Mostrar demanda por SKU, stock, producción registrada y faltante. Si se usa el modal, mostrarlo y cerrarlo sin confirmar otro envasado. | «Se trabaja producto por producto. Stock y envasado registrado son conceptos distintos; el orden visual no bloquea otras tareas». |
| 11 | `/cumplimiento/{id}`, Producción/Reparto | Iniciar y finalizar preparación del pedido, luego despacharlo. Anotar saldos antes/después: Alfa −19, Beta −11, Gamma −6, una sola vez. | «El despacho descuenta los SKU físicos exactos de las cajas normales y surtidas». |
| 12 | Mismo detalle de Cumplimiento, Producción/Reparto | Registrar aceptación de 35 unidades y rechazo de 1 Beta. Usar el desglose físico correspondiente: Beta aceptadas 10 y rechazada 1, resto completo. Indicar motivo neutral. | «El rechazo no cambia el pedido original ni devuelve automáticamente stock». |
| 13 | Retornos del mismo pedido, Producción/Reparto | Registrar la recepción física de esa unidad Beta como apta. Mostrar que el stock todavía no sube. | «La recepción física y la autorización comercial son controles separados». |
| 14 | Mismo detalle, Comercial o Administrador | Revisar y aprobar la unidad apta. Esperar Beta +1 respecto al saldo después del despacho; revisar movimiento trazable. No rectificar otros resultados. | «Solo la aprobación reincorpora lo apto. Los materiales del envasado no regresan automáticamente; no se generan reembolsos». |
| 15 | `/indicadores`, Comercial o Administrador | Elegir un período que incluya la fecha actual y, en los reportes que lo admiten, filtrar por el pedido anotado. Comparar cantidades comerciales, físicas, entregas y retornos en sus reportes respectivos. Exportar CSV y abrirlo fuera de cualquier diálogo que muestre secretos. | «Los reportes mantienen sus fuentes y períodos; no sumamos dos veces paquetes, componentes o movimientos». |
| 16 | `/auditoria`, Administrador | Consultar las acciones del recorrido; cerrar sesión. | «El sistema conserva responsables e historial para revisar las operaciones». |

Si una validación impide continuar, mostrar el mensaje real y detener esa toma. No ajustar saldos, estados o permisos directamente para hacer coincidir el guion. Si cambian las cantidades del pedido, recalcular sus efectos; las cifras anteriores solo corresponden al ejemplo 12 + 12 + 12.

## Video 2 — Pruebas del software

Utilizar los reportes guardados; no repetir carga o estrés durante la grabación. No presentar automatización HTTP como participación de trabajadores.

| Orden | Qué mostrar | Interpretación y narración |
| --- | --- | --- |
| 1 | `docs/pruebas/00_informe_resumen.md` y los 14 requisitos originales archivados | «Se separaron pruebas funcionales, no funcionales y aceptación. Los pendientes permanecen identificados». |
| 2 | Resultado Laravel final y los archivos `tests/Unit`, `tests/Feature`, `tests/Security` | «La ejecución local final aprobó 618 casos: 33 unitarios puros, 574 Feature y 11 de seguridad, sin duplicar estos últimos». Mostrar ejemplos de cantidades, empaques, surtidos y estados. |
| 3 | La ejecución final de GitHub Actions del PR #43, especialmente MySQL | Mostrar la cifra real del trabajo temporal y sus controles de concurrencia; no usar una salida anterior como si fuera la actual. La base de rendimiento y la base efímera de CI son distintas. |
| 4 | `tests/Feature/SesionUsuarioActivoTest.php` y el informe D9A-01 | «Se reprodujo y corrigió el acceso de una cuenta desactivada con sesión abierta». En 9B el HTTP real verificó dos sesiones, otro módulo, JSON 401 y rechazo de login inactivo. |
| 5 | `docs/pruebas/evidencias/resultados_9b.json` o el informe HTML sanitizado | «Se ejecutó humo de un usuario, carga de veinte por dos minutos y estrés progresivo». Mostrar solicitudes, p95, errores e interrupciones reales. |
| 6 | Filas de carga y estrés | «La carga registró 946 solicitudes, 0 % de errores HTTP y p95 de listados de 3,384 s en el perfil final con OPcache. La meta de un segundo no se alcanzó. El estrés llegó a 31 usuarios y se interrumpió al vencer el control de identidad; no acreditamos 50». |
| 7 | Recuperación separada de un usuario | «Después de la interrupción, el login y las cinco consultas volvieron a funcionar, sin errores HTTP. Esto no sustituye el minuto de recuperación previsto para el escenario completo». |
| 8 | `zap_resumen_9b.json`, informe sanitizado y comprobaciones HTTP | «ZAP fue exclusivamente pasivo y local: dos tipos de alerta media, tres bajos y cinco informativos, sin altos. Hay observaciones; no afirmamos ausencia de vulnerabilidades». No abrir reportes crudos que puedan incluir tokens. |
| 9 | Matriz `compatibilidad_9b.csv` | «La comprobación visual en Chrome, Firefox y Edge quedó pendiente porque el control de interfaz no pudo acreditar la URL. Las lecturas HTTP no sustituyen una revisión responsive». No afirmar que se probaron las 180 combinaciones. |
| 10 | `usabilidad_9b.csv` y resultado de envasado | «El pedido de cinco líneas tardó 1,125 s y el envasado 2,425 s en automatización HTTP. Son tiempos técnicos del perfil inicial, no tiempos de trabajadores. El envasado aumentó dos unidades y un doble envío no duplicó movimientos». |
| 11 | Estado de 9C en el informe | «La aceptación empresarial se conserva y está PENDIENTE DE EJECUCIÓN — VALIDACIÓN CON LA EMPRESA. Después del merge manual de 9B se preparará su PR documental separado: casos por rol, criterios, trazabilidad, actas y resultados. No hay firmas ni participantes inventados». Actualizar este segmento solo cuando ese PR exista. |
| 12 | Conclusión técnica | «Las pruebas aportan evidencia reproducible, distinguiendo aprobaciones, observaciones y pendientes. No se usaron las bases reales ni se desplegó el sistema públicamente». |

## Límites para ambas grabaciones

No filmar credenciales, APP_KEY, cookies, archivos privados o respaldos. No afirmar UAT realizadas, revisión visual completa, estrés de 50 usuarios aprobado, p95 inferior a un segundo o ausencia de vulnerabilidades. Si el entorno no acredita su identidad, detenerse sin ejecutar migraciones, limpiezas, seeders ni cambios en Laragon operativo.