# Envasados y configuración de materiales

Registra producción física por presentación. No controla mezclas, litros en bidones ni recetas de líquidos. Tampoco reserva unidades para un cliente.

## Permisos existentes

| Operación | Administrador | Operaciones Comerciales | Producción/Reparto |
| --- | --- | --- | --- |
| Consultar | Sí | Sí | Sí |
| Configurar, editar, activar/desactivar materiales | Sí | Sí | Sí |
| Registrar producción | Sí | No por defecto | Sí |
| Reportar incidencia | Sí | Sí | Sí |
| Corregir/anular mediante compensaciones | Sí | No por defecto | No por defecto |

Refleja EnvasadoPermisoSeeder; la autorización efectiva depende de los permisos actuales y no se modifica al documentarlos. No ejecutar el seeder para grabar videos.

## Configuración y registro

Desde una presentación, el usuario autorizado define una alternativa y selecciona insumos existentes, función, base y cantidad consumida. Contratapas y compatibilidades se configuran expresamente; no se deducen por nombre o color. No hay consumos recomendados ni recetas precargadas.

Las mangas no se descuentan por pedidos: necesitan empaques realmente armados y cantidades de consumo configuradas y validadas. La configuración tiene versión; las ediciones obsoletas se rechazan y no cambian fotografías de consumos anteriores.

1. Seleccionar presentación y alternativa activa compatible.
2. Introducir unidades realmente envasadas y los datos admitidos por el formulario.
3. Revisar la vista previa de materiales y disponibilidad.
4. Confirmar y consultar el detalle.

Puede ser anticipado, sin pedido, o vinculado a un pedido confirmado/en preparación. Se permiten parciales sucesivos. Vincular no cambia estados ni reserva stock. La Lista General permite registrar sin abandonar la pantalla según permiso.

RegistrarEnvasado consume insumos y aumenta terminado en una transacción, con idempotencia, bloqueos y origen único. Si falta material no queda producción parcial. No registrar otra entrada manual por la misma producción.

## Correcciones y reportes

Reportar comunica una incidencia sin modificar por sí solo stock. Corregir requiere otra autorización:

- Error administrativo: compensar lo registrado erróneamente y devolver solo materiales realmente no utilizados o recuperados.
- Pérdida física: registrar la pérdida real, sin devolución ficticia de materiales.
- Conservar originales, responsable, motivo y movimientos compensatorios. No borrar historia.

Se respetan cantidades registradas, stock disponible y concurrencia. No recuperar un material consumido únicamente por cambiar una cifra administrativa.

## Consulta y relación con inventario

Listado por pedido/fechas; detalle con presentación histórica, consumos, correcciones y neto acumulado vinculado cuando corresponde. La producción general no equivale a demanda pendiente.

El despacho consume terminado, no otra vez envases/etiquetas. Los retornos tampoco restituyen automáticamente insumos. Las acciones sensibles van a Auditoría; las variaciones físicas, a Movimientos. Consultar e imprimir no registra producción.
