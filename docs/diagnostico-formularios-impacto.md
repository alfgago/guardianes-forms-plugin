# Incidente: formularios despues de Preparar campos

El boton modifica las definiciones compartidas de WPForms de los retos 2026:
identifica preguntas de indicadores y galardones y agrega cantidades faltantes.
No elimina directamente entradas de `gn_reto_entries` ni archivos de evidencias.

La version anterior guardaba JSON sin `wp_slash`, aunque WordPress elimina un
nivel de escapes al persistirlo. Textos con comillas, HTML y otros escapes pueden
quedar corruptos. Ademas podia mostrar preparado aun cuando fallaban guardados.
Se reprodujo localmente. El diagnostico completo compartido el 8 de octubre
confirma ocho definiciones ilegibles y 55 evidencias activas con sus 55 archivos
presentes en el centro Salvador Villar. Son formularios compartidos por los
centros, no definiciones exclusivas de esa escuela. Esta verificacion no prueba
la integridad de todos los archivos historicos ni de otros centros.

## Diagnostico de solo lectura

### Auditoria global, independiente de centros y respuestas

Para revisar el alcance completo, no diagnosticar escuelas una por una:

```sh
wp eval-file wp-content/plugins/guardianes-formularios/tools/audit-wpforms.php 2026
```

Lee TODOS los formularios WPForms que no esten en papelera o auto-draft en lotes
de 200, incluyendo formularios que no estan asociados a retos. Informa la salud
de la definicion actual, numero de campos, estado del post y conteos agregados
de centros/entradas para cada reto vigente asociado al anio. Tambien informa
retos vigentes con enlaces faltantes o que no apuntan a un formulario consultado.
No inspecciona respuestas individuales ni archivos, no escribe datos y no
considera que una pregunta retirada vuelva invalida la definicion actual.
Un borrador sin preguntas no prueba que el panel docente este afectado: revisar
el estado y las asociaciones del formulario. Para salida estructurada, agregar
`json` como segundo argumento.

Los formularios son compartidos. Un JSON invalido de Agua puede afectar cientos
de centros que usan ese mismo ID; restaurar la definicion corrige esas preguntas
para todos ellos. Los conteos por reto no deben sumarse como centros unicos.
El alcance comprobado hasta ahora son ocho definiciones ilegibles; no afirmar
que todos los demas formularios del sitio estan sanos sin ejecutar la auditoria.
Un resultado OK solo prueba estructura y preguntas guardadas: si el HTML sigue
vacio, investigar tambien renderizado WPForms, plugins, permisos y precarga.

### Verificacion de datos de un centro

Desde la raiz de WordPress, con Guardianes activo y WP-CLI:

```sh
wp eval-file wp-content/plugins/guardianes-formularios/tools/diagnose-impact-forms.php "Salvador Villar" 2026
```

Para evitar que la terminal corte el resultado, agregar `resumen`:

```sh
wp eval-file wp-content/plugins/guardianes-formularios/tools/diagnose-impact-forms.php "Salvador Villar" 2026 resumen
```

Ejecutar desde la raiz de WordPress, especialmente si `wp-config.php` incluye
`wp-salt.php` usando una ruta relativa. No publicar ni regenerar las claves.

El archivo puede ejecutarse por separado sin desplegar las otras mejoras
pendientes. Consulta todos los retos activos y las entradas del centro en ese
anio. Incluye centros duplicados/inactivos, IDs de formularios y usuarios,
integridad JSON, cantidades de evidencias, correspondencia de campos, archivos
locales y hasta las 50 revisiones mas recientes de cada formulario. No muestra
respuestas, rutas de archivos, correos ni configuracion privada del formulario.
El script no realiza escrituras; WordPress y sus plugins se cargan normalmente.

- `campos_validos: false`: falta la definicion o no se puede leer correctamente.
- Evidencias activas y archivos encontrados con formulario invalido: compatible
  con un problema de visualizacion, no con perdida de esos archivos contados.
- `campos_archivo_ausentes`: IDs de archivos guardados sin pregunta correspondiente.
- `no_encontrados`: ruta local sin archivo accesible; revisar permisos, migraciones
  y respaldos. No demuestra por si solo una eliminacion.
- `no_verificables`: ubicacion externa/desconocida; el script no hace peticiones HTTP.

## Recuperacion

1. No volver a preparar campos, ejecutar seeders, recrear formularios ni guardar
   los formularios vacios durante el diagnostico.
2. Respaldar el estado actual de la base de datos y uploads antes de restaurar.
3. Comparar las revisiones validas anteriores al incidente con la configuracion
   anual y los IDs presentes en las entradas. Revisar particularmente Huerta y
   Limpiezas; no asumir que el problema afecta solamente al centro de ejemplo.
4. Si entradas y archivos siguen intactos, restaurar solamente las definiciones
   afectadas desde revisiones de WPForms o un respaldo validado, conservando sus
   IDs de preguntas y de formulario. No restaurar toda la base de datos sobre
   las nuevas evidencias recibidas.
5. Probar como docente e impersonado que aparecen respuestas y archivos.
6. Desplegar la correccion preventiva antes de cualquier nueva preparacion.

La correccion protege los escapes, conserva el contador historico de IDs, crea
un primer respaldo previo a futuras modificaciones y valida el guardado antes
de marcar el proceso como preparado. Ese respaldo nuevo no recupera por si solo
los cambios realizados previamente; tampoco reconstruye formularios invalidos.

La revision del codigo encontro el mismo guardado JSON sin `wp_slash` en el
seeder de formularios. Tambien se corrige y se valida el contenido realmente
guardado antes de devolver el ID para asociarlo a un reto. Esa correccion es
preventiva: NO ejecutar el seeder para reparar formularios existentes.
Desplegar el plugin corregido completo, no solamente las herramientas de CLI.
La eliminacion legitima de preguntas no bloquea la carga del formulario actual:
las respuestas y evidencias historicas se conservan separadamente. Esto tiene
pruebas especificas en el cache, endpoint y fusion de guardados.

## Recuperacion controlada por WP-CLI

La herramienta `tools/recover-wpforms-form.php` restaura un solo formulario
ilegible desde una revision explicitamente seleccionada. No reemplaza
formularios validos, no modifica las entradas ni mueve/elimina archivos.

Primero comprobar la revision candidata con simulacion (no hace escrituras):

```sh
wp eval-file wp-content/plugins/guardianes-formularios/tools/recover-wpforms-form.php 2026 77468 88128 simular
```

Estos IDs corresponden a Residuos. Para otros retos se deben usar sus propios
IDs del resumen, no estos valores.

Despues de respaldar la base de datos y revisar que la revision elegida es la
correcta, la aplicacion explicita se solicita agregando `aplicar`:

```sh
wp eval-file wp-content/plugins/guardianes-formularios/tools/recover-wpforms-form.php 2026 77468 88128 aplicar
```

Verifica en lotes de 200 las entradas de TODOS los centros asociados a ese
formulario/anio, no solo el centro del diagnostico. Bloquea la recuperacion
si faltan IDs usados por respuestas/evidencias activas, salvo referencias
historicas explicitamente validadas como se describe abajo, si hay tipos de
archivo incompatibles o datos ilegibles. Conserva el contador historico de IDs para
evitar reutilizarlos. Antes de guardar conserva el contenido actual en una
opcion privada sin autoload y luego comprueba el JSON realmente persistido.
Una simulacion compatible no demuestra que una pregunta conservara exactamente
su significado; por eso es obligatorio revisar la revision antes de aplicar.

### Simulaciones recibidas el 8 de octubre de 2026

| Reto | Formulario | Revision | Resultado |
| --- | --- | --- | --- |
| Agua | 77446 | 88125 | Bloqueado: faltan campos 7 y 8 |
| Electricidad | 77457 | 88127 | Compatible: 338 entradas verificadas |
| Residuos | 77468 | 88128 | Compatible: 329 entradas verificadas |
| Limpiezas | 77479 | 87509 | Bloqueado: falta campo 17 |
| Siembra de Arboles | 77490 | 87625 | Compatible: 168 entradas verificadas |
| Eco Gira | 77528 | 87703 | Bloqueado: falta campo 3 |
| Bienestar Animal | 77542 | 87976 | Compatible: 66 entradas verificadas |
| Jardines y Polinizadores | 77530 | 87979 | Bloqueado: falta campo 6 |

No se han aplicado restauraciones desde este entorno. No ignorar campos
ausentes ni combinar preguntas de revisiones diferentes automaticamente.
Un ID que falte puede corresponder a una pregunta retirada legitimamente o
a una revision incompleta para los datos guardados; es necesario investigarlo.

### Diagnostico de revisiones bloqueadas

Actualizar `tools/recover-wpforms-form.php` en el servidor y ejecutar desde la
raiz de WordPress:

```sh
tool="wp-content/plugins/guardianes-formularios/tools/recover-wpforms-form.php"
wp eval-file "$tool" 2026 77446 88125 diagnosticar
wp eval-file "$tool" 2026 77479 87509 diagnosticar
wp eval-file "$tool" 2026 77528 87703 diagnosticar
wp eval-file "$tool" 2026 77530 87979 diagnosticar
```

Este modo no escribe formularios, respaldos ni entradas. Cuenta las respuestas
vacias, respuestas con valor y evidencias activas de cada campo incompatible,
y muestra hasta tres IDs de entradas como referencia, nunca sus valores ni
nombres/rutas de archivos. Cero y falso cuentan como valores, no como vacios.
Las respuestas vacias siguen bloqueando la restauracion: no se descartan.

Revisa todas las revisiones disponibles en lotes de 200, incluidas las anteriores
a las 50 que muestra el diagnostico general. Devuelve hasta diez revisiones
compatibles con todos los IDs usados y los tipos de archivo, y hasta tres
variantes de tipo/etiqueta por campo incompatible para su revision manual.
Ignora las revisiones ilegibles como candidatas y cuenta las que encuentra.
No selecciona ni restaura ninguna revision automaticamente. Las fechas de
revision y etiquetas se deben contrastar con las preguntas y reglas originales
antes de decidir. Si no existe una revision compatible, revisar respaldos antes
de reconstruir; no reasignar IDs ni ejecutar seeders.

### Referencias historicas explicitas

La siguiente salida recibida confirma que el operador restauro Electricidad,
Residuos, Siembra de Arboles y Bienestar Animal. Cada operacion creo un respaldo
privado de la definicion ilegible anterior y verifico el contenido guardado.
Aun falta comprobar visualmente esos cuatro formularios en el panel docente.

El diagnostico de los otros cuatro encontro estos campos en versiones previas:

| Formulario | Campo | Revision original | Uso guardado |
| --- | --- | --- | --- |
| Agua 77446 | 7 | 86826 | 118 respuestas con valor, 0 evidencias |
| Agua 77446 | 8 | 86876 | 87 respuestas con valor, 0 evidencias |
| Limpiezas 77479 | 17 | 86893 | 46 respuestas vacias, 0 evidencias |
| Eco Gira 77528 | 3 | 86761 | 33 respuestas con valor, 0 evidencias |
| Jardines 77530 | 6 | 86108 | 5 respuestas con valor, 6 evidencias activas |

Estos campos ya faltaban en las ultimas revisiones validas: no demuestra que
Preparar campos los haya eliminado. No hay una revision que contenga todos los
IDs historicos de Agua, Eco Gira y Jardines; restaurar una version muy antigua
podria perder preguntas nuevas. Limpiezas tiene revisiones compatibles de junio,
pero tampoco es necesario retroceder sus preguntas actuales hasta esa fecha.

Si se confirma que esas preguntas se retiraron legitimamente, se puede usar
`historicos=<campo>:<revision_original>,...` para reconocer cada ID conservado
en las entradas, sin reinsertarlo en el formulario. No es una opcion general
de forzado. Requiere una revision valida del mismo formulario, anterior a la
que se restaura y con ese campo. Si hay evidencias activas, su tipo original
debe ser `file-upload`. Cualquier otro ID ausente o cambio de tipo sigue
bloqueando la recuperacion. Los IDs historicos tampoco se podran reutilizar.

Primero actualizar la herramienta y simular:

```sh
tool="wp-content/plugins/guardianes-formularios/tools/recover-wpforms-form.php"
wp eval-file "$tool" 2026 77446 88125 simular "historicos=7:86826,8:86876"
wp eval-file "$tool" 2026 77479 87509 simular "historicos=17:86893"
wp eval-file "$tool" 2026 77528 87703 simular "historicos=3:86761"
wp eval-file "$tool" 2026 77530 87979 simular "historicos=6:86108"
```

El resultado debe ser `simulacion` e incluir `campos_historicos`. No realiza
escrituras. Revisar el formulario actual propuesto y confirmar que esos IDs
son de preguntas retiradas antes de sustituir `simular` por `aplicar`, siempre
con respaldo actual de base de datos y uploads.

La aplicacion solo escribe la definicion del formulario y su respaldo, en el
que tambien registra las referencias originales. No cambia las respuestas,
evidencias, revisiones de evidencias ni puntajes almacenados. El guardado normal
ya fusiona respuestas y evidencias: las de preguntas retiradas se conservan
cuando se actualizan las preguntas vigentes. Esto esta cubierto por pruebas.
Las preguntas historicas no reaparecen para recibir nuevas respuestas. Las
evidencias conservadas siguen formando parte de la entrada; comprobar que el
panel derecho, la revision y los reportes las muestran. Esta opcion no cambia
rubricas ni restaura un puntaje de otra fecha. Si una pregunta retirada debe
volver a ser editable, debe resolverse como una decision separada del formulario.

## Cache y panel

La definicion compartida usa la cache de objetos de WordPress por formulario,
con caducidad de cuatro horas cuando existe un backend persistente como Redis
o Memcached. Sin ese backend se reutiliza dentro de cada peticion, sin agregar
consultas a `wp_options`. El resumen indica si la cache persistente esta activa.
Una edicion, restauracion o dano
cambia la firma y deja de usar la version anterior inmediatamente. La carga
de assets del panel y las reglas condicionales reutilizan esa definicion.
No se cachean de forma compartida HTML, nonces, respuestas ni evidencias.
El HTML se sigue renderizando en el contexto del usuario actual; acelerar
la inicializacion completa de WordPress requiere medir tambien el servidor.

El panel muestra un aviso y Reintentar en vez de una zona vacia, conservando
el resumen y las evidencias. Deshabilita Guardar ahora cuando faltan preguntas.
El servidor tambien bloquea autosaves de formularios ilegibles o con un ID
anual distinto, incluidos intentos desde pestanas abiertas antes del incidente.
