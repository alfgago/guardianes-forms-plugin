# Panel de Impacto: operación

## Actualización

Los resultados estadísticos se conservan en una opción no autoload por año.
El cron `gnf_report_snapshots_tick` solicita una actualización cada dos horas.
Al cargar el plugin se reemplaza automaticamente la recurrencia anterior de
cuatro horas, sin duplicar eventos.
Cada evento `gnf_refresh_report_snapshot` procesa hasta 200 centros inscritos y
publicados; guarda su avance y programa el siguiente lote. Solo al completar
todos los lotes publica el nuevo resultado. Un error no borra el último corte.
Los puntajes y estados operativos de las evidencias siguen actualizándose al
guardar una revisión: el corte estadístico no cambia esa lógica.

La primera instalación muestra que se están preparando los datos, no totales
cero ficticios. Administración puede solicitar una actualización desde el panel.
Se recomienda configurar un cron real cada minuto: WP-Cron depende de visitas.
Desde el directorio de WordPress, como el usuario del sitio:

```sh
* * * * * cd /ruta/a/wordpress && wp cron event run --due-now --quiet
```

Alternativa sin WP-CLI: ejecutar PHP CLI sobre `/ruta/a/wordpress/wp-cron.php`
cada minuto. Usar la misma versión y extensiones PHP del sitio. Solo después de
comprobar el cron real configurar `DISABLE_WP_CRON` como `true` en `wp-config.php`.
Este repositorio no modifica el cron del servidor de producción.

Verificar la primera ejecución y su duración con WP-CLI:

```sh
wp cron event list --fields=hook,next_run_gmt,recurrence
wp cron event run gnf_report_snapshots_tick
wp cron event run gnf_refresh_report_snapshot
wp option get gnf_report_snapshot_2026_v1 --format=json
```

Repetir el último evento mientras haya un lote pendiente. No copiar la salida
de la opción a registros públicos: contiene información administrativa de los
centros, matrículas y cuentas docentes. Revisar memoria y tiempos con el volumen
real del servidor antes de dar por cumplido un objetivo de rendimiento.

Los PDF colectivos se preparan mediante `gnf_run_impact_pdf_job`. La pantalla de
descarga consulta su disponibilidad cada cinco segundos; una preparación que no
termina en quince minutos ofrece reintentar. El evento queda listo inmediatamente
y se solicita un arranque no bloqueante de WP-Cron, salvo que el sitio lo haya
deshabilitado o use cron alternativo. Esto no garantiza que el servidor permita
peticiones internas: configurar y comprobar el cron real sigue siendo necesario.
`gnf_cleanup_impact_pdf_job` elimina los temporales privados al cabo de dos
horas. Una descarga no borra el PDF: se reutiliza para el mismo usuario, corte,
filtros y alcance autorizado. Un cambio de permisos o territorio invalida su clave.
Comprobar también esos
eventos al configurar el cron del servidor. XLSX se genera por streaming desde
el último corte disponible, sin volver a recorrer las evidencias originales.

Diagnostico compacto y sin respuestas, usuarios o rutas privadas:

```sh
wp eval-file wp-content/plugins/guardianes-formularios/tools/diagnose-impact-exports.php 2026
wp cron event run gnf_run_impact_pdf_job
```

`queued` con evento vencido significa que el worker aun no arranco; `working`
con lock activo indica generacion en curso; `ready` permite reutilizar el archivo.
La consulta muestra hasta 100 trabajos recientes; los antiguos sin anio conocido
se identifican con `year: null`. Ejecutar el segundo comando procesa los eventos
de PDF programados y puede tardar; no ejecutarlo repetidamente en paralelo.

El frontend conserva cada combinacion de filtros diez minutos. Consulta cada
dos horas con un corte listo; consulta cada cinco segundos solamente durante
la primera preparacion sin datos o despues de pulsar Actualizar. Un refresco
automatico del servidor no dispara consultas rapidas sobre un corte utilizable.
Las descargas siguen activas durante consultas en segundo plano. Cambiar filtros
sin cache provoca una consulta nueva; un error de autorizacion bloquea descargas.

## Cache de paneles

Los GET de resumen, retos y pasos docentes y de resumen/listado supervisor usan
transients privados de dos horas. Cada clave incluye usuario, centro, anio,
filtros, roles, permisos y asignaciones territoriales vigentes. Las respuestas
HTTP son `private, no-store`; React conserva estos resultados en memoria diez
minutos, sin almacenamiento persistente en el navegador. La primera consulta
sin transient calcula el resultado; las siguientes reutilizan los datos.

Guardar matricula, subir/retirar evidencias, revisar o modificar el centro
invalida los datos de ese centro para sus docentes. La consulta de notificaciones
existente (cada minuto) lleva una revision ligera en encabezados: los cambios de
otra cuenta refrescan los resumenes docentes sin recargar el formulario abierto.
Las preguntas, respuestas precargadas, matricula editable y detalle de revision
NO se almacenan en esta cache de respuestas. Ninguna accion modifica WPForms.

Los supervisores conservan sus resumenes hasta dos horas; sus propias revisiones
o el boton Actualizar consultan datos recientes. Actualizar en Panel de Impacto
(solo administracion) tambien invalida las caches supervisoras. Los cambios de
rol, asignacion regional/circuito o territorio de un centro invalidan el alcance
sin esperar dos horas. No se almacenan enlaces firmados ni nonces: se reconstruyen
en cada consulta. `X-GNF-Panel-Cache: hit/miss` permite verificar el uso del transient.

Los snapshots estadisticos siguen en opciones no autoload, no en transients:
deben conservar el ultimo corte valido incluso si el cron falla. Los transients
pueden desaparecer antes de su TTL; en ese caso se recalcula el panel. La cache
de definiciones WPForms usa object cache de dos horas con huella del contenido;
Redis/Memcached la hacen persistente, pero no son necesarios para los transients.

## Acceso y descargas

Administración consulta todas las DRE. DRE/comité y supervisores activos están
limitados a sus regiones y circuitos vigentes. El alcance se comprueba de nuevo
en cada consulta y descarga, incluso con una URL firmada. Las opciones almacenadas
no contienen URLs firmadas, contraseñas, tokens ni imágenes de evidencias.

Si hay caché de página o CDN, excluir los paneles autenticados, las rutas REST
`/gnf/v1/reports/*` y las descargas de `admin-post.php`. La caché de resultados
no sustituye la autorización vigente de cada usuario.

La misma fecha de corte y filtros alimentan pantalla, XLSX y PDF. El público
solo recibe los indicadores aprobados habilitados en configuración. Los reportes
colectivos no sustituyen el reporte individual por centro.

No sumar unidades distintas ni interpretar "Sin datos" como cero. Los conteos de
estudiantes de actividades representan participaciones, no personas únicas, salvo
los campos demográficos de matrícula. Si faltan campos cuantificables, revisar
la preparacion controlada por WP-CLI en `diagnostico-formularios-impacto.md`;
el boton fue retirado de Guardianes Configuracion. No inventar resultados históricos.

## Prueba del reporte individual

El botón "Descargar Reporte" está oculto para docentes ordinarios. Solo aparece
durante una impersonación administrativa válida. Los documentos previos al cierre
anual llevan aviso provisional. Esta restricción también se aplica a la descarga,
no solo al botón. No habilitarlo para todos sin una nueva decisión del cliente.

El estado provisional depende del cierre **del programa anual**, no de cerrar
la matrícula de una escuela ni de completar sus retos. El indicador anual es
`gnf_program_year_closed_<año>`; mientras no exista o sea falso, el PDF y el panel
mostrarán el aviso provisional. No marcarlo como cerrado hasta confirmar el cierre
oficial. Este indicador no publica galardones ni habilita el botón a los docentes.
