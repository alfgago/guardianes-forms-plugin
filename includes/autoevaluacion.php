<?php
/**
 * Autoevaluación — eliminada.
 *
 * El puntaje ahora se calcula automáticamente en gnf_calcular_puntaje_por_campos()
 * (puntajes.php) basado en field_points de configuracion_por_anio (ACF).
 *
 * Las funciones de este archivo se mantienen como stubs vacíos para compatibilidad
 * con plantillas existentes; no realizan ninguna lógica.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stub: devuelve null (autoevaluación eliminada).
 *
 * @param object $entry_row Fila de wp_gn_reto_entries.
 * @return null
 */
function gnf_get_autoevaluacion_detalle( $entry_row ) {
	return null;
}

/**
 * Stub: devuelve false (autoevaluación eliminada).
 *
 * @param object $entry_row Fila de wp_gn_reto_entries.
 * @return false
 */
function gnf_is_autoevaluacion_confirmada( $entry_row ) {
	return false;
}
