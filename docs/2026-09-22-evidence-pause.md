# Galardones y evidencia en pausa

- Excelencia general se muestra como Estrella Dorada; el reconocimiento alimentario se muestra como Estrella Turquesa. Se conservan las claves persistidas y requisitos de las dos rubricas. Se normalizan tambien los resultados guardados y asignados: corregir nombres no invalida una asignacion existente.
- La revision admite `pausar` con estado `en_pausa`, causa obligatoria (`reto_inconcluso`, `subir_requisito`, `no_concluyente`) y nota opcional. Se conservan permisos, autoria y auditoria. Puede cambiarse desde y hacia aprobada/rechazada. Editar comentario conserva el estado.
- Interpretacion provisional pendiente de confirmar: la pausa excluye la evidencia del puntaje y de requisitos proyectados/validados; no impone penalizacion. Otra evidencia activa del mismo campo puede seguir dando los puntos del campo. Al aprobar se recalcula, sin duplicar puntos.
- Las pausas tienen contador separado, causa visible al docente y no permiten completar la revision para descargar reporte final. El PDF incluye estado y causa. No se agregan notificaciones para docentes: se mantiene la politica previa de solo rechazos.
- Se elimina el texto tecnico sobre el wizard en la pantalla de retos.

## Verificacion

Pruebas PHP incluyen transiciones del endpoint con persistencia simulada, permisos, causas invalidas, comentarios, puntajes, requisitos y compatibilidad de galardones asignados. No hay WordPress ni base de datos reales en este entorno.

Pruebas locales de UI, fuera de las entradas del build:

- `/preview-evidence-review.html`: componente real de revision con API simulada.
- `/preview-docente.html?paused=1`: contador de pausas.
- `/preview-docente.html?paused=1&p=formularios&reto_id=1`: causa y nota al docente.
