# Incidente: formularios despues de Preparar campos

El boton modifica las definiciones compartidas de WPForms de los retos 2026:
identifica preguntas de indicadores y galardones y agrega cantidades faltantes.
No elimina directamente entradas de `gn_reto_entries` ni archivos de evidencias.

La version anterior guardaba JSON sin `wp_slash`, aunque WordPress elimina un
nivel de escapes al persistirlo. Textos con comillas, HTML y otros escapes pueden
quedar corruptos. Ademas podia mostrar preparado aun cuando fallaban guardados.
Se reprodujo localmente; falta confirmar los registros concretos en produccion.

## Diagnostico de solo lectura

Desde la raiz de WordPress, con Guardianes activo y WP-CLI:

```sh
wp eval-file wp-content/plugins/guardianes-formularios/tools/diagnose-impact-forms.php "Salvador Villar" 2026
```

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
