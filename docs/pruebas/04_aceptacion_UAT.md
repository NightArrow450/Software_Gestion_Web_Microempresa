# Aceptación con trabajadores: guía de recorrido

Estado: **PREPARADA, NO EJECUTADA**. Referencia funcional: `v1.0.0-rc.1`, integración `993ae53a83bf99261de1fb427067543b01298b06`. Fecha de preparación: 08/11/2026, America/Lima.

Esta guía sirve para que personas de DHACER ECO LIMP prueben el sistema. Los recorridos automatizados anteriores no sustituyen su participación ni aprueban esta aceptación.

## Antes de invitar a los trabajadores

El coordinador debe completar esta lista. Si falta una comprobación, no empezar a guardar datos:

- [ ] Existe un checkout de demostración separado de `C:\laragon\www\Software_Gestion_Web_Microempresa`, con su propia configuración, clave, sesiones, cachés y archivos.
- [ ] La dirección de demostración está anotada y es distinta del operativo `http://127.0.0.1:8000`. No se publicará en Internet.
- [ ] Un responsable técnico comprobó ejecutable, proceso, directorio de datos, puerto, UUID de MySQL, cuenta efectiva y privilegios de la base temporal. Comprobar solo el nombre no es suficiente.
- [ ] La base y la cuenta son exclusivas de demostración; no tienen acceso a `software_gestion_web_microempresa`, `ecolimp_respaldo_prueba` ni otras bases empresariales. No se copiaron respaldos ni datos reales.
- [ ] Hay tres cuentas ficticias con los roles Administrador, Operaciones Comerciales y **Producción/Reparto**. Cada participante recibe únicamente la contraseña de su cuenta de prueba, por un medio privado. No se anotan contraseñas en las fichas.
- [ ] Los permisos proceden de la distribución oficial revisada. No se amplían para conseguir que un escenario termine.
- [ ] Está deshabilitado el control exacto de materiales. No se exigen configuraciones, existencias ni consumos de insumos.
- [ ] El coordinador verificó los datos de partida de la tabla siguiente y preparó una ficha por participante.
- [ ] Se revisaron los recursos disponibles antes de habilitar cualquier servidor independiente. No se detuvo el puerto 8000 ni se modificó Laragon operativo.

**Esta tarea no crea ese entorno, no inicializa MySQL y no ejecuta migraciones ni seeders.** El responsable técnico deberá preparar y verificar la infraestructura independiente antes de la sesión, con las autorizaciones correspondientes. No usar la base operativa como alternativa si la demostración no está lista.

Requisitos técnicos mínimos para el responsable: código de la versión indicada; PHP 8.3 y extensiones requeridas por el lockfile; dependencias de Composer y recursos Vite compilados en el checkout independiente; MySQL 8.4.3 temporal con cuenta limitada; navegador actual de escritorio y un teléfono o vista de 360/390 px. Node 22 solo es necesario para compilar los recursos. No se certifica una cantidad de RAM con esta guía: comprobar el equipo antes de iniciar servicios, sin cargarlo con herramientas de estrés. No hacen falta Docker, alojamiento público ni servicios de pago.

Para los controles de identidad, consultar [preparación de demostración](../demostracion.md). Sus ejemplos de datos comerciales son históricos: **no reutilizar las referencias antiguas a surtidos, precios por paquete o consumo obligatorio de materiales**. Los recorridos vigentes son los de esta guía y [Módulos y navegación](../modulos.md).

## Datos de partida exclusivamente ficticios

| Elemento | Preparación del coordinador |
| --- | --- |
| Cliente | Cliente Demostración A, activo, sin documentos, teléfonos o direcciones reales. |
| Producto | Limpiador de demostración, con aromas Alfa y Beta, ambos activos. |
| Presentación utilizada | Alfa, botella de 1 L, SKU de demostración identificable, precio unitario S/ 2.00. |
| Stock inicial de ese SKU | 0 unidades terminadas en una base temporal nueva. No poner en cero existencias de una base anterior. |
| Pedido a registrar | 54 unidades de ese mismo SKU, con fechas del día de la prueba. |
| Agrupaciones opcionales | Dos paquetes de capacidad 12: uno contiene 5 unidades y otro 3; ambos expresamente incompletos. Las 46 restantes quedan sueltas. |
| Producción simulada | 32 unidades terminadas. No se registran litros, químicos ni existencias de materiales. |

Si hay otros pedidos o movimientos ficticios en el entorno, registrar sus saldos y recalcular las expectativas antes de empezar; no borrar ni ajustar saldos para hacerlos coincidir. Para reproducir exactamente los números siguientes, utilizar una base temporal nueva que cumpla los controles anteriores. Si se repite la sesión, el coordinador preparará un escenario nuevo identificado; no se limpia ni reinicia una base automáticamente.

Dirección de demostración: ____________________

Identificador del entorno y commit comprobados por el coordinador: ____________________

Fecha prevista: __________ Participantes/códigos: __________ Número del pedido de prueba (al registrarlo): __________

## Cómo realizar la sesión

El trabajador utiliza la interfaz y explica qué entiende. El observador deja que lo intente primero, anota si pidió ayuda y ofrece ayuda cuando sea necesaria. No atribuir al trabajador un error que podría proceder del sistema o de instrucciones confusas. No hay tiempos de respuesta o porcentajes de satisfacción inventados.

Usar [la ficha de evaluación](05_ficha_evaluacion_UAT.md). Cada trabajador completa su propia ficha; puede marcar «No ejecutado» lo que no realizó. Los pasos de un rol se retoman en el mismo pedido al cambiar al siguiente rol. No compartir sesiones: cerrar sesión con el botón del sistema antes de ingresar con otra cuenta ficticia.

| Escenario | Rol previsto | Estado inicial |
| --- | --- | --- |
| 1. Registrar pedidos | Operaciones Comerciales | No ejecutado |
| 2. Envasar unidades | Producción/Reparto | No ejecutado |
| 3. Preparar pedidos | Producción/Reparto | No ejecutado |
| 4. Despachar pedidos | Producción/Reparto | No ejecutado |
| 5. Entregas e incidencias | Producción/Reparto; aprobación por Operaciones Comerciales o Administrador | No ejecutado |
| 6. Administración | Administrador | No ejecutado |

## 1. Registrar un pedido

1. Abrir la **dirección de demostración**, ingresar con la cuenta ficticia de Operaciones Comerciales y comprobar el rol indicado en la cabecera.
2. Desde el Panel, pulsar **Registrar pedido**. También puede entrar a Gestión de Pedidos y elegir Nuevo pedido.
3. En Buscar y seleccionar cliente, escribir parte de «Cliente Demostración A» y seleccionar la coincidencia que aparece debajo. Escribir el nombre sin seleccionar una coincidencia no asocia al cliente.
4. En Buscar y seleccionar producto, buscar el limpiador ficticio, seleccionar la coincidencia y elegir aroma Alfa y presentación de 1 L. Comprobar el precio S/ 2.00.
5. Indicar 54 en Cantidad de unidades y pulsar **Añadir unidades**. Antes de agrupar, comprobar 54 unidades físicas, 54 sueltas, 0 agrupadas y 0 paquetes.
6. Activar **Configurar agrupaciones opcionales**, pulsar Crear paquete y Añadir variante al paquete. Elegir el mismo SKU; indicar capacidad 12 y 5 unidades dentro.
7. Pulsar **Copiar este paquete** y cambiar únicamente el segundo a 3 unidades. Volver al primero para comprobar que conserva 5. No crear otro producto o precio por paquete.
8. Revisar el resumen: **54 físicas, 46 sueltas, 8 agrupadas, 2 paquetes, 0 en espera e importe S/ 108.00**. Comprobar que los paquetes incompletos son comprensibles y no añaden cargos.
9. Revisar cliente, fechas y condición de pago; pulsar **Guardar pedido por unidades** una sola vez. Anotar el número mostrado, consultar el detalle y comprobar cantidades, agrupaciones e importe.
10. En el detalle, pulsar **Confirmar pedido** y aceptar la confirmación para continuar su atención. Consultar el stock del SKU: sigue en **0**, porque registrar y confirmar no producen ni despachan unidades.
11. Repetir la consulta del pedido en teléfono. No guardar otra copia para probar la pantalla móvil.

Observar: ¿encontró las coincidencias?, ¿distinguió unidades de paquetes?, ¿entendió el resumen?, ¿necesitó ayuda? Una agrupación no reserva stock y no cambia el precio por unidad.

## 2. Registrar unidades terminadas

1. Cerrar la sesión comercial e ingresar con la cuenta ficticia de Producción/Reparto.
2. Abrir **Envasados** y la acción para registrar un envasado nuevo.
3. Seleccionar exactamente el SKU Alfa de 1 L. Indicar **32 unidades realmente envasadas**. Dejar Pedido opcional sin selección para comprobar producción anticipada.
4. Pulsar **Revisar unidades antes de confirmar**. La vista previa debe indicar stock actual 0 y posterior 32, y explicar que no se registran consumos de insumos.
5. Marcar la confirmación de producción simulada y pulsar **Confirmar unidades terminadas** una sola vez.
6. Consultar el detalle y Volver al historial. Comprobar responsable, fecha, SKU, 32 unidades registradas y stock terminado **32**. Recargar el detalle no debe crear otro envasado.
7. Comprobar que Producción/Reparto no dispone de la acción administrativa de corregir/anular. Si necesita informar un error, distinguir Comunicar error de una compensación de inventario; no enviar un reporte si no forma parte del escenario acordado.

No activar el control exacto de materiales ni inventar entradas de envases, etiquetas, químicos o líquidos. No registrar manualmente otra entrada por esta misma producción.

## 3. Preparar parcialmente

1. Desde el Panel, abrir **Lista General de Preparación** y revisar los filtros: deben incluir el pedido y las fechas del escenario.
2. Localizar el producto y su presentación Alfa de 1 L: la lista consolida por producto/SKU, no muestra necesariamente una fila por pedido. Debe distinguir 54 solicitadas pendientes, 32 en stock y 22 faltantes; la producción registrada ya está incluida en el stock.
3. Para atender el pedido individual, abrir desde el Panel **Cumplimiento de pedidos** (en el menú, Preparación, despacho y entrega), buscar el número anotado o el cliente y abrir el detalle.
4. En Preparación y atención por unidades, indicar **32** en Unidades físicamente preparadas por despachar. Si se simula una incidencia de disponibilidad, elegir una categoría existente y escribir una explicación de prueba sin atribuir culpas.
5. Escribir el motivo de comprobación y pulsar **Registrar preparación real o incidencia**.
6. Consultar el estado y el historial. Deben quedar 32 preparadas por despachar y 22 aún pendientes. La preparación no despacha, no reserva stock ni registra otra producción: el stock sigue siendo **32**.

No pulsar Finalizar preparación completa si todavía faltan 22. El flujo parcial se continúa con el formulario de despacho. Comprobar que el trabajador distingue pendiente total, unidades en espera y pendiente activo; en este ejemplo no se dejaron unidades en espera.

## 4. Despachar parcialmente

1. En el mismo detalle de Cumplimiento, localizar **Registrar esta salida física**.
2. Seleccionar los dos paquetes declarados: contienen 5 + 3 = **8** unidades, aunque su capacidad sea 12 cada uno.
3. Indicar **24 unidades sueltas** que salen ahora. El total de este SKU en esta salida debe ser **32**. No escribir 32 como sueltas además de marcar los paquetes: eso pediría despachar 40.
4. Escribir el motivo/instrucciones ficticias y pulsar **Confirmar despacho de estas unidades** una sola vez.
5. Revisar el estado **Despacho parcial**, las 32 despachadas y las **22 pendientes**. Consultar las salidas físicas registradas y su distribución: dos paquetes incompletos y 24 sueltas.
6. Abrir Movimientos o Seguimiento para localizar la salida de 32, su responsable y el pedido. El stock de ese SKU debe ser **0**, nunca negativo. Recargar no debe generar otra salida.

Si el sistema informa falta de stock, no intentar resolverlo alterando saldos. Anotar el problema y avisar al coordinador.

## 5. Entrega, rechazo y retorno

1. Con Producción/Reparto, volver al pedido en Cumplimiento y abrir **Registrar cantidades aceptadas, rechazadas o pendientes**.
2. Registrar **30 aceptadas y 2 rechazadas**, en unidades físicas de las 32 despachadas. Escribir un motivo de rechazo ficticio de al menos diez caracteres y revisar la fecha del hecho.
3. Pulsar **Registrar resultado sin modificar stock**. El stock sigue en 0 y las 22 nunca despachadas no se convierten en una entrega. No marcar falsamente todo el pedido como entregado.
4. Abrir **Registrar retorno físico o no recuperación**. Simular la recepción de **1 apta y 1 dañada**; dejar 0 en evaluación y no recuperadas. Escribir las observaciones del conteo y pulsar **Registrar recepción sin reincorporar existencias**.
5. Consultar el historial: el stock sigue en **0**. La unidad apta espera aprobación; la dañada no puede aumentar existencias. Producción/Reparto no debe tener la acción de aprobación.
6. Cerrar sesión e ingresar con Operaciones Comerciales o Administrador. Abrir el mismo pedido y desplegar **Aprobar reincorporación** de esa recepción.
7. Indicar **1** unidad apta, escribir el motivo de comprobación y pulsar **Autorizar entrada de estas unidades al inventario**. Comprobar stock **1**, movimiento trazable e historial. Recargar no debe generar otra entrada.
8. Operaciones Comerciales no debe disponer de la rectificación de resultados de entrega reservada al Administrador. Si hay un error de registro, anotarlo para revisión autorizada, sin intentar alterar lo ocurrido.

La unidad dañada no vuelve a stock. No se devuelven envases ni etiquetas y no se modifican automáticamente pagos o comprobantes. Las 22 pendientes conservan su compromiso comercial; retirarlas o dejarlas en espera requiere una decisión comercial separada, que este escenario no ejecuta.

## 6. Consulta de administración

1. Cerrar sesión e ingresar con la cuenta ficticia Administrador.
2. Desde el Panel, abrir **Usuarios** y revisar el listado. No cambiar nombres, contraseñas, roles o estados.
3. Abrir **Roles y permisos** y consultar las autorizaciones, sin editarlas. Verificar que Producción/Reparto registra envasados/resultados/retornos pero no aprueba reincorporaciones; Operaciones Comerciales puede aprobar y Administrador puede además rectificar resultados.
4. Consultar el catálogo y las presentaciones ficticias, sin cambiar precios ni productos.
5. Consultar Disponibilidad y Movimientos del SKU probado. Distinguir stock de producción, y materiales históricos de existencias físicas realmente controladas.
6. Abrir **Indicadores y CSV**, revisar los filtros y el período del escenario. Un importe del pedido no acredita un cobro. No es obligatorio exportar archivos para completar esta consulta.
7. Abrir **Auditoría**, localizar operaciones del escenario y comprobar responsable, fecha y trazabilidad, sin eliminar registros.
8. Cerrar sesión y comprobar que vuelve al inicio de sesión.

Comprobar además que los otros dos roles no ven ni pueden usar las funciones administrativas restringidas. Un mensaje de acceso no autorizado en una función restringida es el comportamiento esperado, no una razón para cambiar permisos.

## Registro de resultados y cierre

El observador registra hechos en la ficha, separando errores del sistema, dificultades para encontrar una acción e instrucciones confusas. Anotar ayudas concretas y conservar solo capturas ficticias necesarias, sin contraseñas ni sesiones.

- **Aprobado:** el trabajador completó las comprobaciones del escenario sin errores relevantes; registrar cualquier ayuda recibida igualmente.
- **Aprobado con observaciones:** completó el recorrido, pero hay dificultades o sugerencias documentadas que no comprometen datos, cantidades o permisos.
- **No aprobado:** no pudo completarlo o apareció un error de cantidades, precios, stock, permisos o pérdida de información. Conservar evidencia y revisar antes de repetir.
- **No ejecutado:** no se realizó, falta el entorno o no participó el rol correspondiente.

Ante una cuenta con acceso indebido, una cantidad incoherente o riesgo para datos: detener el escenario, no reintentar repetidamente, no corregir inventario/roles para conseguir aprobación y avisar al responsable técnico. No hacer restauraciones o reparaciones automáticas.

La aceptación global queda **PENDIENTE** hasta recoger las fichas reales, resolver defectos relevantes y documentar la revisión del responsable de la empresa. No inferir satisfacción, firmas o aceptación a partir de CI ni de esta guía. No sustituye la entrevista, Anexo 7 o cuestionario académico X1–X12 si todavía están pendientes.
