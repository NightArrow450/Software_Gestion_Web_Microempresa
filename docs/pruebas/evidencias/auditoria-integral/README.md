# Evidencias visuales ficticias de la auditoría

[Informe completo](../../../auditoria-integral-calidad-ux.md). [Ejecución visual aprobada](https://github.com/NightArrow450/Software_Gestion_Web_Microempresa/actions/runs/41704906854).

Referencia anterior: `ecc76506b3283d71332d87b0769dab60b31f259e`. Código funcional posterior: `07439372eff8dcf851732ae31ba35f8f7eee2c6e`; arnés final: `547c9fa05c7ab08af2a15b6d73c3987f50d740cf`. El commit posterior de documentación conserva estos archivos originales, sin editar las capturas.

Los cuatro trabajos terminaron correctamente: Chromium y Firefox, antes y después, 137 casos y 154 capturas por trabajo, en 360/390/768/1024/1366 px. «Antes aprobado» significa que la reproducción confirma los defectos esperados, no que la versión anterior carezca de problemas. Se registran las medidas del cuerpo/documento; después no se admiten desbordamientos. A 360 px, cumplimiento pasa de 384 a 360 px de ancho de documento. Las tablas con desplazamiento interno permanecen utilizables.

## Comparaciones conservadas

| Hallazgo | Antes | Después |
|---|---|---|
| Error duplicado | [Captura](360-validacion-antes.png) | [Captura](360-validacion-despues.png) |
| Localización del pedido antiguo | [Captura](360-busqueda-pedido-antes.png) | [Captura](360-busqueda-pedido-despues.png) |
| Foco programático del aviso | [Captura](360-foco-error-antes.png) | [Captura](360-foco-error-despues.png) |
| Título con SKU largo a 360 px | [Captura](antes-360-preparacion-despacho-incidencias.png) | [Captura](despues-360-preparacion-despacho-incidencias.png) |
| Confirmación única tras envasar | [Captura](antes-1366-envasado-integrado-confirmado.png) | [Captura](despues-1366-envasado-integrado-confirmado.png) |

[Resultados anteriores](antes-resultados.json) y [posteriores](despues-resultados.json), sin secretos ni información comercial real. Se generaron usuarios y operaciones ficticias exclusivamente en SQLite temporal del runner, con MySQL bloqueado. No es UAT.

## Impresión

[PDF ficticio](notas.pdf), [vista previa renderizada](notas-a4-horizontal.png) y [propiedades comprobadas](pdf-info.txt). Una página A4 horizontal, dos notas distintas y números independientes. El PDF se obtiene antes del retiro comercial; por eso la primera nota conserva las 54 unidades vigentes en ese momento. [Verificación posterior del retiro](retiro-verificado.json): original 54, vigente 32 y retiradas 22.

Se inspeccionaron visualmente las capturas seleccionadas y la página renderizada. El resto del recorrido tiene comprobaciones automatizadas y capturas disponibles; no se presenta como una inspección humana individual de las 616 imágenes ni como evaluación WCAG completa.

## Artefactos completos descargables

- [Antes / Chromium](https://github.com/NightArrow450/Software_Gestion_Web_Microempresa/actions/runs/41704906854/artifacts/13265532232).
- [Después / Chromium](https://github.com/NightArrow450/Software_Gestion_Web_Microempresa/actions/runs/41704906854/artifacts/13263788777).
- [Antes / Firefox](https://github.com/NightArrow450/Software_Gestion_Web_Microempresa/actions/runs/41704906854/artifacts/13263768748).
- [Después / Firefox](https://github.com/NightArrow450/Software_Gestion_Web_Microempresa/actions/runs/41704906854/artifacts/13265343078).

Retención configurada: 14 días. Las evidencias seleccionadas de esta carpeta permanecen versionadas. Firefox comprueba los estilos y el DOM de impresión; el PDF procede de Chromium. Chrome/Edge de marca, impresoras físicas y aceptación por trabajadores: PENDIENTE DE EJECUCIÓN.
