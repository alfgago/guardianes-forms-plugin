# Resumen docente y cierre 2026

## Cambios

- El resumen cuenta archivos de evidencia activos de todo el centro, dentro de sus retos seleccionados para el anio. Excluye reemplazados y conserva los archivos con cero puntos.
- La barra presenta pendientes de revision, aprobadas y rechazadas. El porcentaje corresponde a evidencias aprobadas. Rechazadas abre las notificaciones.
- Las cinco estrellas permanecen vacias hasta la asignacion administrativa. La respuesta del dashboard docente no incluye proyecciones de galardon.
- Los docentes reciben solo notificaciones de rechazo. La lista omite archivos eliminados, reemplazados o aprobados posteriormente, conserva el mensaje mas reciente por evidencia y sigue buscando en lotes cuando los primeros resultados ya se resolvieron.
- Los campos de archivo rotulados REQUISITO se resaltan en Agua, Electricidad y Residuos, sin alterar sus condiciones de WPForms.
- La rubrica verifica cada evidencia obligatoria, no solo la existencia de cualquier archivo en el reto. Se conserva provisionalmente como opcional el planeamiento indicado con "si lo tienen"; esta interpretacion debe confirmarse con Guardianes.
- Las rutas de impacto se registran durante rest_api_init. Anteriormente estaban dentro de la consulta del dashboard supervisor.

## Operacion

1. Galardones, reportes PDF e indicadores estan siempre habilitados para todos los centros. Se retiraron los controles de lanzamiento piloto; sus valores antiguos se ignoran sin modificar ni borrar datos. La publicacion de indicadores en la web conserva la seleccion explicita de metricas publicas.
2. En el panel React administrativo, abrir Centros > detalle de un centro y revisar el resultado validado.
3. Usar Asignar galardon y confirmar. La accion requiere manage_options, usa autenticacion REST con nonce y queda auditada por centro y anio.
4. Retirar asignacion oculta las estrellas nuevamente. Si cambia el resultado validado, el servidor invalida la asignacion al consultarla; requiere asignacion nueva.
5. Reporte final se habilita para el docente cuando cada reto seleccionado tiene evidencias y todas estan aprobadas. El estado antiguo del reto no bloquea una revision individual completa.
6. Administradores y supervisores conservan los borradores. El PDF no muestra estrellas ni reconocimientos provisionales: hasta la asignacion indica Pendiente de asignacion.

## Prueba local

Desde app: `npm run dev -- --host 127.0.0.1 --port 5186 --strictPort`.

- `/preview-docente.html`: panel real con respuestas API ficticias, resultado pendiente.
- `/preview-docente.html?assigned=1`: tres estrellas asignadas y descarga habilitada (destino simulado).
- `/preview-docente.html?empty=1`: sin evidencias.
- `/preview-docente.html?p=formularios&reto_id=1`: requisito resaltado en formulario simulado.

La pagina de prueba no forma parte de las entradas del build de produccion. No escribe en WordPress.

Verificacion inicial: 23 archivos de pruebas PHP, 358 comprobaciones; sintaxis PHP y build de produccion. Pruebas de navegador sobre los componentes reales con datos ficticios en escritorio y movil. La disponibilidad general agrega pruebas de regresion con opciones antiguas de ACF y WordPress. Falta validar la integracion con la instalacion real de WordPress y sus formularios WPForms antes del despliegue.
