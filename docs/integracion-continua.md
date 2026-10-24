# Integración continua

El workflow `.github/workflows/calidad.yml` ejecuta cinco trabajos independientes en runners estándar `ubuntu-24.04` de GitHub:

| Trabajo | Comprobaciones |
| --- | --- |
| Laravel y seguridad | PHP 8.3, SQLite en memoria, suite habitual y comprobación de que los once escenarios de seguridad aparecen una sola vez. |
| MySQL 8.4.3 y concurrencia | Servicio `mysql:8.4.3` desechable, comprobación previa de conexión y las 48 pruebas actuales, sin omisiones. |
| Larastan y Pint | Nivel 5 sin exclusiones generales; Pint en modo verificación sobre los PHP del cambio y, siempre, modelos, fábricas y archivos de arranque aislado. |
| JavaScript y Vite | Node 22, instalación desde `package-lock.json`, compilación real y las diez pruebas JavaScript actuales. |
| Auditorías Composer y npm | Dependencias de producción y desarrollo de ambos lockfiles, sin correcciones automáticas ni avisos ignorados. |

Los mínimos de pruebas detectan omisiones accidentales y permiten agregar escenarios nuevos. Las pruebas SQLite y MySQL siguen separadas. Un fallo de instalación, auditoría, compilación o pruebas hace fallar el trabajo; no se utiliza `continue-on-error`.

## Eventos y ejecución manual

Se ejecuta en los Pull Requests dirigidos a `develop` y en los pushes a `develop`. Una nueva ejecución cancela la anterior de la misma rama o PR. Cada trabajo tiene un tiempo máximo de 10, 15 o 20 minutos.

La rama predeterminada del repositorio es **`main`**. Aunque se declara `workflow_dispatch`, GitHub exige que el archivo del workflow exista en la rama predeterminada para habilitar ese evento. Mientras solo exista en esta rama de trabajo o en `develop`, no estará habilitada la ejecución manual mediante `workflow_dispatch`. Los eventos de PR permiten verificar Linux antes del merge. No se cambia la rama predeterminada ni se fusiona `develop` con `main` como parte de este PR.

Referencia: [ejecución manual de workflows](https://docs.github.com/en/actions/how-tos/manage-workflow-runs/manually-run-a-workflow).

## Aislamiento y protección de datos

- No se utiliza ningún secreto de producción ni se copia un archivo `.env`. El arranque falla si encuentra uno.
- Composer instala con `--no-scripts --no-plugins`; nunca se ejecuta `composer setup`.
- Antes de las pruebas se comprueba PHP 8.3, `APP_ENV=testing` y una conexión SQLite `:memory:`. `tests/bootstrap.php` mantiene sus bloqueos de conexiones y sus cachés por proceso.
- MySQL es un contenedor nuevo por trabajo, con puerto asignado por GitHub. Antes de conectarse se verifica el identificador, la imagen y el puerto del servicio. Antes de migrar se comprueba la versión exacta 8.4.3 y una base de pruebas todavía vacía.
- El usuario `ci_pruebas` recibe una contraseña aleatoria, enmascarada antes de añadirla al entorno. Solo tiene acceso de escritura a bases con el prefijo `dhacer_eco_limp_test_`; los permisos de lectura de bloqueos y `PROCESS` sirven para comprobar la concurrencia. El administrador sin contraseña existe exclusivamente dentro del contenedor temporal y solo se usa mediante `docker exec` para crear ese usuario.
- Las bases llevan el identificador y el intento de ejecución. Los helpers existentes comprueban prefijo, conexión y marcadores de propiedad antes de preparar esquemas; no se ejecutan migraciones de la aplicación fuera de esos helpers.
- MySQL 8.4.3 activa el registro binario por defecto. Solo dentro del contenedor se establece `log_bin_trust_function_creators=1` para que el usuario limitado pueda crear los triggers históricos sin recibir `SUPER`. La comprobación previa exige ese valor. No se omiten ni modifican triggers, restricciones o pruebas; esta configuración no se aplica a Laragon ni se prescribe para producción. [Referencia MySQL](https://dev.mysql.com/doc/refman/8.4/en/stored-programs-logging.html).
- Las bases, datos y credenciales del contenedor se descartan con el runner. No se publican volcados ni reportes JUnit: estos últimos permanecen en el directorio temporal de cada trabajo.
- No hay conexión a Laragon, ejecutores locales, despliegues, túneles, artefactos ni cachés persistentes.

El token del workflow tiene únicamente `contents: read`, y checkout no conserva credenciales. No se utiliza `pull_request_target`. Las acciones externas están fijadas a SHA completos, contrastados con sus repositorios oficiales el 24 de octubre de 2026:

| Acción | Referencia verificada | SHA |
| --- | --- | --- |
| `actions/checkout` | `v6` | `d23441a48e516b6c34aea4fa41551a30e30af803` |
| `actions/setup-node` | `v6` | `249970729cb0ef3589644e2896645e5dc5ba9c38` |
| `shivammathur/setup-php` | `v2` | `eb7c497e18156a6bbabfef1d3a82760b9eda3962` |

Al actualizar una acción se debe verificar nuevamente el SHA y revisar sus cambios. No se insertan títulos, nombres de rama ni otros textos de un PR directamente en comandos de shell.

## Costos y límites

El repositorio es público. GitHub ofrece ejecución gratuita con runners estándar en repositorios públicos. No se usan runners grandes ni almacenamiento adicional. Si cambia la visibilidad del repositorio o el tipo de runner, se debe revisar el presupuesto antes de continuar.

Referencia: [facturación y uso de GitHub Actions](https://docs.github.com/en/actions/concepts/billing-and-usage).

## Limitaciones conocidas

- Pint ya detectaba problemas de estilo en 41 archivos anteriores a este PR, entre ellos migraciones históricas. Este PR no los reformatea ni crea una exclusión global: verifica los PHP modificados y los componentes indicados en la tabla. La normalización del resto requiere un cambio independiente.
- Composer, npm y las fuentes de Vite requieren acceso a sus servicios públicos. Un fallo externo se investiga; no se sustituyen las comprobaciones por resultados ficticios.
- Las auditorías pueden detectar vulnerabilidades nuevas sin que cambie el código. Se debe informar su gravedad y preparar una corrección independiente cuando corresponda, sin ocultar el fallo ni actualizar dependencias automáticamente.
- No se modifican reglas comerciales, migraciones, seeders, permisos, inventarios ni datos históricos.
