# Rendimiento: operacion y reversibilidad

## Cambios de aplicacion

- Las notificaciones consultan un resumen autenticado cada minuto. El historial
  completo se carga al abrir Notificaciones. El resumen conserva el criterio de
  las 50 notificaciones elegibles, deduplicadas, incluidas rechazadas y en pausa.
- El resumen evita recorrer evidencias cuando no cambian notificaciones,
  permisos o revisiones de los centros. Insertar/refrescar/marcar leida una
  notificacion invalida el resumen del destinatario.
- Los paneles conservan frescura de dos horas. Un solo proceso regenera cada
  clave. Otro lector puede usar el ultimo resultado durante cinco minutos
  adicionales SOLO con el mismo usuario, permisos y revision de datos. Sin
  resultado previo recibe 503/Retry-After; el cliente reintenta con espera acotada.
- No se cachean respuestas de formularios, editores de matricula ni detalle de
  revision. Los enlaces firmados y permisos de acciones se reconstruyen.
- Los conteos de evidencias se agregan por lotes y se reutilizan en el listado,
  sin una lectura de evidencias adicional por escuela. Se conservan el formato
  del listado y los filtros territoriales; no cambia su navegacion.
- Los galardones reutilizan el calculo hasta dos horas, con huella de sus fuentes
  y revisiones. Asignar/revocar y comprobar el resultado asignado sigue siendo
  una operacion en vivo. No se inventan puntos para actividades sin puntaje.
- Se serializan los generadores PDF colectivos: un trabajador a la vez. Los
  restantes conservan su trabajo y se reprograman sin ampliar su plazo original.
- Los paneles no cargan Contact Form 7 ni su validador SWV. No se retiran WPForms
  ni los recursos compartidos de captcha. Las paginas publicas no cambian.

## Perfil REST opcional

Instalar `tools/mu-plugins/gnf-rest-performance.php` en
`wp-content/mu-plugins/gnf-rest-performance.php`. Este archivo necesita cargarse
antes que los plugins ordinarios, no desde el bootstrap de Guardianes.

El perfil solo se activa con `GNF_REST_PLUGIN_PROFILE=true` en wp-config.php.
Excluye trece plugins concretos de tienda, traduccion y formularios ajenos
UNICAMENTE en las rutas REST operativas de Guardianes. No modifica la opcion
persistida de plugins activos. No se aplica a WP-CLI, cron, sitio publico,
paneles HTML, registro/login, wp-admin, WooCommerce REST ni CF7 REST.
ACF, WPForms y sus complementos, seguridad, auditoria, correo, Redis y Elementor
permanecen disponibles. Es un perfil de este proyecto, no una lista universal.

Desactivacion inmediata, desde el directorio WordPress:

```sh
wp config set GNF_REST_PLUGIN_PROFILE false --raw
```

Antes de activarlo tras cambiar plugins o sus versiones, comparar los handlers
con y sin perfil. `tools/performance-smoke.php` se ejecuta con PHP CLI, nunca por
HTTP. Carga WordPress desde la ruta indicada, usa una cuenta existente y solo
permite rutas GET enumeradas. No modifica evidencias, revisiones ni matriculas;
los handlers conservan sus escrituras normales de cache y recuperacion legacy.
La salida contiene costos y huellas, no las respuestas ni datos personales.

```sh
php tools/performance-smoke.php /ruta/wordpress /gnf/v1/docente/dashboard 123 baseline
php tools/performance-smoke.php /ruta/wordpress /gnf/v1/docente/dashboard 123 profile
```

Comparar estado y dataHash con las mismas fuentes. Un cambio concurrente de
datos puede producir huellas distintas; contrastarlo antes de atribuirlo al
perfil. Los tiempos CLI no equivalen a latencia del navegador ni prueban por
si solos una mejora bajo el mismo volumen de trafico.

## Redis en produccion

El 27 de octubre de 2026 se habilito Redis Object Cache 3.0.0, cliente PhpRedis,
en la base 9817 (comprobada vacia antes de asignarla) con prefijo exclusivo
`guardianes:wzmgbjxedw:`. MaxTTL 86400 segundos limita claves sin expiracion;
los transients conservan sus TTL mas cortos. Timeout y read timeout: 0.5 segundos.
No se cambio el limite de memoria global ni se vaciaron bases de otros sitios.

El backend y los assets compilados tambien se desplegaron por archivo, con
comprobacion previa contra la version base y reemplazo del manifiesto al final.
Los assets antiguos se conservaron para las pestanas abiertas. Respaldos privados:

- `/home/master/gnf-performance-20261027/`: config inicial y archivos de plugin.
- `/home/master/applications/movimientoguardianes/tmp/gnf-performance-20261027/`:
  config inicial, manifiesto de despliegue, respaldos y herramienta de prueba.

El repositorio remoto tenia cambios staged previos en
`feature/merge-duplicate-centros`; se conservaron sin reset, checkout ni pull.
No hacer un pull forzado sobre ese checkout. Conciliar primero los cambios
existentes y el despliegue por archivos con la siguiente publicacion de Git.

El plugin no define DONOTCACHEOBJECT ni DONOTCACHEDB. Las paginas privadas y
respuestas REST siguen excluidas de cache de pagina/CDN mediante no-store.

Comprobar o desactivar solo este conector:

```sh
wp redis status
wp redis disable
```

No ejecutar FLUSHALL. No compartir la base 9817 con otras aplicaciones. Antes
de retirar Redis, considerar transients anteriores en la base SQL: las caches
versionadas de paneles usan ahora v3 y no reutilizan las v2 anteriores.

## Hosting: ajustes pendientes de soporte

Las credenciales de aplicacion y maestras no permiten leer el override del pool
FPM ni modificar servicios como root. No se intentaron cambios globales sin
esas comprobaciones. Solicitar a Cloudways:

1. Medir PSS/memoria privada por worker, cola y concurrencia de cada aplicacion.
   Dimensionar pm.max_children para un servidor compartido de 6 CPU/16 GB;
   el include compartido observado permite 60, pero falta verificar el override.
2. Revisar OPcache: configuracion general observada 128 MB/10000 archivos.
   Medir cache_full, ocupacion, hits/misses y reinicios; ajustar memoria y numero
   de archivos segun el conjunto de aplicaciones y hacer recarga controlada.
3. Confirmar limites PHP efectivos del pool. No reducir memoria a ciegas: probar
   primero el reporte mas grande. La CLI observada tiene memory_limit=-1 y
   WP_MEMORY_LIMIT/WP_MAX_MEMORY_LIMIT de 2048M; esto no prueba el limite FPM.
4. Verificar que el cron administrado del hosting no solape ejecuciones de la
   misma aplicacion y escalonar trabajos entre sitios. WP-Cron por visitas ya
   esta deshabilitado y existe log de cron real; no crear un segundo scheduler.

Medir en ventanas de trafico comparables. Una bajada de la carga global no debe
atribuirse por completo a Guardianes: el servidor aloja otras aplicaciones.
