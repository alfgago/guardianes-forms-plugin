# Incidente: formularios despues de Preparar campos

El boton modifica las definiciones compartidas de WPForms de los retos 2026:
identifica preguntas de indicadores y galardones y agrega cantidades faltantes.
No elimina directamente entradas de `gn_reto_entries` ni archivos de evidencias.

La version anterior guardaba JSON sin `wp_slash`, aunque WordPress elimina un
nivel de escapes al persistirlo. Textos con comillas, HTML y otros escapes pueden
quedar corruptos. Ademas podia mostrar preparado aun cuando fallaban guardados.
Se reprodujo localmente. El diagnostico compartido el 8 de octubre confirma
JSON invalido en Jardines y Polinizadores (formulario 77530) y 55 evidencias
activas con sus 55 archivos presentes en el centro Salvador Villar. La copia
empieza a mitad del reporte; no permite evaluar todas las definiciones.

## Diagnostico de solo lectura

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

## Recuperacion controlada por WP-CLI

La herramienta `tools/recover-wpforms-form.php` restaura un solo formulario
ilegible desde una revision explicitamente seleccionada. No reemplaza
formularios validos, no modifica las entradas ni mueve/elimina archivos.

Primero comprobar la revision candidata con simulacion (no hace escrituras):

```sh
wp eval-file wp-content/plugins/guardianes-formularios/tools/recover-wpforms-form.php 2026 77530 87979
```

Estos IDs corresponden a Jardines y Polinizadores y a la revision valida mas
reciente que figura en el reporte recibido. Para otros retos se deben usar
sus propios IDs del resumen, no estos valores.

Despues de respaldar la base de datos y revisar que la revision elegida es la
correcta, la aplicacion explicita se solicita agregando `aplicar`:

```sh
wp eval-file wp-content/plugins/guardianes-formularios/tools/recover-wpforms-form.php 2026 77530 87979 aplicar
```

Verifica en lotes de 200 las entradas de TODOS los centros asociados a ese
formulario/anio, no solo el centro del diagnostico. Bloquea la recuperacion
si faltan IDs usados por respuestas/evidencias activas, si hay tipos de archivo
incompatibles o datos ilegibles. Conserva el contador historico de IDs para
evitar reutilizarlos. Antes de guardar conserva el contenido actual en una
opcion privada sin autoload y luego comprueba el JSON realmente persistido.
Una simulacion compatible no demuestra que una pregunta conservara exactamente
su significado; por eso es obligatorio revisar la revision antes de aplicar.

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
