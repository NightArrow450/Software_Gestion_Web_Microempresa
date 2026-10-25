# Guiones para ocho videos

**Preparación, no evidencia de ejecución.** Grabar versiones definitivas después de Fase 9. Todos los resultados siguientes son esperados y deben contrastarse con el recorrido autorizado. No se han ejecutado estas escrituras como parte del PR 5.

## Preparación común

Seguir [aislamiento y autorización](demostracion.md). Comprobar rótulo DEMOSTRACIÓN y origen local independiente antes de cada toma. Desactivar notificaciones, autocompletado y herramientas que puedan mostrar contraseñas; no filmar .env, logs privados o consolas con secretos. Usar cuentas y clientes ficticios, no cuentas empresariales.

Hoja de datos sintéticos propuesta, que se revisará antes de inicializar:

- Producto ficticio Limpiador de demostración; aromas Alfa/Beta/Gamma; SKU distintos de 1 L y otra presentación 500 ml para mostrar orden.
- Precio unitario 1 L: S/ 2,00; empaque de 12: S/ 24,00; formato surtido de 12: S/ 24,00.
- Pedido mixto: una caja normal de 12 Alfa y dos surtidos de 12. Paquete 1: 5 Alfa, 4 Beta, 3 Gamma. Paquete 2: 2 Alfa, 6 Beta, 4 Gamma. Total comercial esperado S/ 72,00; demanda física Alfa 19, Beta 10, Gamma 7.
- Entradas ficticias autorizadas: 100 unidades de cada SKU de 1 L y 1.000 unidades de cada material utilizado. Documentar movimiento inicial, no editar saldos directamente.
- Alternativa de prueba Alfa 1 L: un envase DEMO y una etiqueta DEMO por unidad. Son consumos ilustrativos, no recetas ni compatibilidades de la empresa. Sin mangas.
- Fijar y anotar fechas de pedidos/producción dentro de los filtros del día de grabación. No cambiar el reloj del sistema operativo.

Si se repite un guion, comenzar con una nueva base demo autorizada o anotar claramente los saldos acumulados; no presentar los cálculos de una base limpia como si correspondieran a una repetida.

## 1. Acceso, panel y responsabilidades

**Objetivo:** presentar navegación y permisos. **Rol:** Administrador, luego cuentas ficticias Comercial y Producción/Reparto. **Requisitos:** tres cuentas privadas, roles/permisos revisados, entorno aprobado.

1. Abrir login del servidor demo y señalar el rótulo; pausar captura al introducir contraseña.
2. Entrar al panel. Mostrar grupos de accesos y los conteos administrativos reales de esa base ficticia.
3. Abrir Usuarios y Roles y Permisos; consultar, sin ampliar permisos para facilitar la grabación.
4. Cerrar sesión y entrar con Producción/Reparto. Comparar accesos; comprobar que no aparecen Usuarios o Auditoría. Mostrar cierre de sesión.

**Esperado:** cada rol ve solo accesos autorizados; el panel no muestra estadísticas inventadas. **Narración:** «El sistema organiza el trabajo por responsabilidad; ocultar un enlace no sustituye la protección de las rutas». **Precaución:** no mostrar claves, cookies ni datos personales reales.

## 2. Clientes, catálogo y presentaciones

**Objetivo:** identificar catálogo físico y comercial. **Rol:** Comercial o Administrador con permisos de crear/editar. **Requisitos:** hoja de datos y base ficticia inicializada.

1. En Clientes, registrar Cliente Demostración A con datos mínimos válidos, sin identificadores empresariales reales.
2. En Catálogo, registrar o consultar el producto ficticio, variantes Alfa/Beta/Gamma y presentaciones 500 ml/1 L.
3. Mostrar venta por unidad, tamaño de empaque y precio; registrar S/ 2,00 y caja de 12 para 1 L según la hoja.
4. Abrir Modalidades surtidas y habilitar el formato ficticio compatible para los tres SKU de 1 L. No combinar capacidades diferentes ni crear un producto por mezcla.

**Esperado:** SKU independientes y formato habilitado sin inventario o composición automática. **Narración:** «El catálogo define posibilidades; la distribución de aromas se define en cada pedido». **Precaución:** no modificar categorías empresariales ni atribuir consumos de prueba a la empresa.

## 3. Pedido normal, surtidos y composición pendiente

**Objetivo:** demostrar precio único y composiciones individuales. **Rol:** Comercial con pedidos.crear y surtidos.componer. **Requisitos:** cliente, SKU y formato del guion 2.

1. En Registrar pedido elegir cliente y fechas válidas. Añadir una caja normal de 12 Alfa y una línea de dos surtidos de 12.
2. Definir paquete 1: 5/4/3. Copiar al segundo, luego modificar solo el segundo a 2/6/4. Verificar que el primero no cambia y cada paquete suma 12.
3. Guardar; revisar dos líneas comerciales, S/ 72,00 total y desglose físico. Abrir nota imprimible y anexo; revisar páginas antes de imprimir.
4. En otro pedido ficticio separado, guardar composición pendiente sin asignar aromas. Mostrar las unidades sin distribuir y la restricción de cierre; no intentar forzar despacho.
5. Confirmar el pedido mixto con el rol autorizado para continuar el recorrido.

**Esperado:** 36 unidades físicas, precio sin duplicarse por aroma y composición histórica individual. **Narración:** «Dos paquetes del mismo tamaño pueden tener mezclas distintas; una composición pendiente no inventa SKU». **Precaución:** no confundir precio comercial con pago recibido.

## 4. Preparación, disponibilidad y orden de trabajo

**Objetivo:** separar demanda, stock y producción. **Rol:** Producción/Reparto con pedidos.ver y cumplimiento.preparar; entradas iniciales por usuario autorizado. **Requisitos:** pedido mixto confirmado y entradas sintéticas documentadas.

1. Abrir Lista General de Preparación; comprobar filtros del pedido y demanda Alfa 19/Beta 10/Gamma 7.
2. Mostrar productos juntos y presentaciones en orden físico. Revisar desglose de empaques y exportación a impresión.
3. Comparar las 100 unidades disponibles por SKU con la producción registrada en el período, inicialmente sin operaciones del guion 5.
4. Abrir Inventario/Movimientos y relacionar las entradas sintéticas. Volver a la lista sin modificar stock.
5. En Cumplimiento, iniciar preparación del pedido mixto; no finalizar hasta revisar composición y demanda.

**Esperado:** cobertura por stock no equivale a «envasado registrado». **Narración:** «Se trabaja producto por producto; el orden es visual y no obliga a bloquear otros trabajos». **Precaución:** confirmar filtros y no sumar producción otra vez al stock.

## 5. Envasados anticipados, vinculados y correcciones

**Objetivo:** seguir consumo y compensación. **Rol:** Administrador/Producción para registrar; Administrador para corregir. **Requisitos:** alternativa sintética Alfa, materiales suficientes y pedido confirmado/en preparación.

1. En presentación Alfa, abrir Materiales de envasado. Consultar alternativa con un envase y una etiqueta DEMO por unidad; confirmar que es ilustrativa.
2. En Envasados, registrar 6 unidades sin pedido; revisar vista previa antes de confirmar.
3. Desde la Lista General pulsar Registrar envasado para Alfa; registrar 4 unidades vinculadas, sin abandonar la lista. Revisar materiales y confirmar.
4. Mostrar producción, stock y vínculo sin reserva ni cambio automático del pedido.
5. Como Administrador, corregir 1 unidad por error administrativo, indicando que envase/etiqueta ficticios no se usaron y se recuperan. En otra corrección, registrar pérdida física de 1 unidad sin devolver materiales.
6. Consultar originales y compensaciones en detalle y Movimientos.

**Esperado con base indicada:** Alfa pasa 100→106→110→109→108. Cada material pasa 1.000→994→990→991 y permanece 991 tras pérdida sin recuperación. **Narración:** «Producción y consumo se confirman juntos; el error administrativo y la pérdida física no son equivalentes». **Precaución:** no introducir una segunda entrada manual de terminado ni devolver materiales realmente consumidos.

## 6. Despacho, entrega parcial y retorno autorizado

**Objetivo:** mostrar el circuito físico y la separación de responsabilidades. **Rol:** Producción/Reparto para preparación/despacho/resultado/recepción con permisos; Comercial o Administrador para aprobar. **Requisitos:** guiones previos, composición completa y saldos anotados.

1. Finalizar preparación y despachar el pedido mixto. Revisar movimientos y cantidades físicas por aroma.
2. Registrar rechazo de 2 Alfa y 1 Beta, aceptando el resto. Motivo neutral: «El receptor indica que estas unidades no corresponden a lo acordado». No atribuir culpas.
3. Comprobar entrega parcial y stock sin devolución automática.
4. Registrar recepción física: Alfa 1 apta/1 dañada y Beta 1 apta. Revisar que todavía no suba stock.
5. Cerrar sesión; Comercial/Administrador revisa y aprueba las aptas. Mostrar entrada trazable y cantidades dañadas sin reincorporar.
6. Abrir seguimiento e historial; demostrar que cantidades/precio originales se conservan. No rectificar por comodidad ni generar reembolsos.

**Esperado:** antes de despacho 108/100/100; después 89/90/93; rechazo y recepción mantienen esos saldos; aprobación deja 90/91/93. Aceptadas 33 y rechazadas 3. **Narración:** «Rechazo, recepción y aprobación son hechos diferentes». **Precaución:** Producción no aprueba; retornos no devuelven etiquetas/envases.

## 7. Indicadores, filtros y CSV

**Objetivo:** distinguir información comercial, física y económica. **Rol:** Comercial o Administrador con los tres permisos de indicadores. **Requisitos:** operaciones ficticias anteriores y fechas anotadas.

1. Abrir Indicadores y CSV; mostrar cabecera y período.
2. Filtrar Comercial por pedido mixto: observar S/ 72,00 sin multiplicarlo por componentes.
3. Consultar Composiciones, Físicas y Pendientes; comparar paquetes, envases y unidades sin distribuir.
4. Consultar entregas/retornos/aprobaciones y producción/materiales/movimientos. Explicar fecha de cada hecho y evitar sumar medidas superpuestas.
5. Exportar detalle del reporte y abrir en Excel verificando columnas, tildes y período. Mostrar lista de quince reportes; no afirmar que se abrieron todos si no se hizo.

**Esperado:** pantalla y CSV coherentes con el mismo filtro; diferencias económicas informativas, no cobros ni reembolsos. **Narración:** «Un paquete comercial no es un SKU físico; los reportes conservan esa distinción». **Precaución:** guardar CSV ficticios fuera de Git y no abrir archivos reales durante la captura.

## 8. Auditoría y trazabilidad

**Objetivo:** relacionar acciones, responsable y movimientos sin confundir módulos. **Rol:** Administrador con auditoria.ver; Seguimiento según permiso. **Requisitos:** acciones ficticias registradas durante los guiones.

1. Abrir Auditoría y filtrar fecha/entidad del envasado o configuración.
2. Mostrar ID de usuario, acción y detalle permitido; no hay edición/borrado.
3. Comparar una corrección con sus movimientos compensatorios y con el registro original.
4. En Seguimiento abrir pedido, estados e incidencias de entrega y retorno. Diferenciar esos eventos del canal privado de logs de seguridad.
5. Terminar con cierre de sesión y explicar pendientes de aceptación real de la empresa.

**Esperado:** trazabilidad por identificadores/fechas, sin alterar originales. **Narración:** «Auditoría responde quién y qué acción; Movimientos explica la variación de existencias». **Precaución:** minimizar IP/correos y no afirmar que todo clic se audita.
