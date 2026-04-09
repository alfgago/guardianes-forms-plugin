<?php
/**
 * Lógica de puntajes y estrellas.
 *
 * El puntaje de cada reto entry se calcula a partir de field_points
 * almacenados en la configuracion_por_anio del reto (ACF repeater).
 * Cualquier tipo de campo puede tener puntos asignados. Los puntos
 * se acumulan si el campo tiene respuesta válida al enviar el formulario.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calcula puntaje automático de un entry basándose en field_points de ACF.
 *
 * Reglas por tipo de campo:
 *   file-upload  → suma si hay al menos 1 archivo subido
 *   radio/select → suma si hay alguna opción seleccionada
 *   checkbox     → suma si al menos 1 opción marcada
 *   text/textarea/number → suma si el campo no está vacío
 *
 * @param object $entry_row Fila de wp_gn_reto_entries.
 * @return int Puntaje calculado.
 */
function gnf_calcular_puntaje_por_campos( $entry_row ) {
	if ( empty( $entry_row->reto_id ) ) {
		return 0;
	}

	$anio         = absint( $entry_row->anio ?? 0 );
	$field_points = gnf_get_reto_field_points( $entry_row->reto_id, $anio ?: null );

	if ( empty( $field_points ) ) {
		return 0;
	}

	// Leer resumen de campos respondidos almacenado en data.__fields__.
	$data_raw       = json_decode( $entry_row->data ?? '{}', true );
	$fields_summary = $data_raw['__fields__'] ?? array();

	// Index evidencias by field_id, excluding rejected and replaced ones.
	$evidencias_raw = json_decode( $entry_row->evidencias ?? '[]', true );
	$files_by_field = array();
	foreach ( (array) $evidencias_raw as $ev ) {
		if ( ! empty( $ev['field_id'] ) && empty( $ev['replaced'] ) && 'rechazada' !== ( $ev['estado'] ?? '' ) ) {
			$files_by_field[ (int) $ev['field_id'] ] = true;
		}
	}

	$puntaje     = 0;
	$puntaje_max = 0;
	foreach ( $field_points as $field_id => $info ) {
		$field_id = (int) $field_id;
		$puntos   = absint( $info['puntos'] ?? 0 );
		$tipo     = $info['tipo'] ?? '';
		$puntaje_max += $puntos;

		if ( in_array( $tipo, array( 'file-upload', 'file' ), true ) ) {
			$has_file = ! empty( $files_by_field[ $field_id ] )
				|| ( isset( $fields_summary[ $field_id ] ) && $fields_summary[ $field_id ] );
			if ( $has_file ) {
				$puntaje += $puntos;
			}
		} else {
			$value = $fields_summary[ $field_id ] ?? '';
			if ( '' !== $value && null !== $value && false !== $value && '0' !== (string) $value ) {
				$puntaje += $puntos;
			}
		}
	}

	return $puntaje_max > 0 ? min( $puntaje, $puntaje_max ) : $puntaje;
}

/**
 * Recalcula puntaje de un reto entry.
 * Usa scoring automático por campo (ACF field_points).
 *
 * @param object $entry_row Fila de wp_gn_reto_entries.
 * @return int Puntaje calculado.
 */
function gnf_recalcular_puntaje_reto( $entry_row ) {
	if ( empty( $entry_row->reto_id ) ) {
		return 0;
	}
	return gnf_calcular_puntaje_por_campos( $entry_row );
}

/**
 * Recalcula y persiste el puntaje de un reto entry.
 *
 * @param object $entry_row Fila de wp_gn_reto_entries.
 * @param bool   $refresh_centro Si también recalcula agregados del centro.
 * @return int Puntaje persistido.
 */
function gnf_refresh_reto_entry_score( $entry_row, $refresh_centro = true ) {
	global $wpdb;

	if ( empty( $entry_row->id ) ) {
		return 0;
	}

	$puntaje = gnf_recalcular_puntaje_reto( $entry_row );
	$wpdb->update(
		$wpdb->prefix . 'gn_reto_entries',
		array( 'puntaje' => $puntaje ),
		array( 'id' => (int) $entry_row->id ),
		array( '%d' ),
		array( '%d' )
	);

	if ( $refresh_centro && ! empty( $entry_row->centro_id ) && ! empty( $entry_row->anio ) ) {
		gnf_recalcular_puntaje_centro( (int) $entry_row->centro_id, (int) $entry_row->anio );
		if ( function_exists( 'gnf_clear_admin_stats_cache' ) ) {
			gnf_clear_admin_stats_cache( (int) $entry_row->anio );
		}
	}

	return $puntaje;
}

/**
 * Legacy: obtiene puntaje máximo de un formulario WPForms leyendo gnf_field_points del JSON.
 * Ya no es la fuente principal — usar gnf_get_reto_max_points() en su lugar.
 *
 * @param int $form_id ID del formulario WPForms.
 * @return int Total de puntos posibles.
 */
function gnf_get_form_max_points( $form_id ) {
	$form_id = absint( $form_id );
	if ( ! $form_id ) {
		return 0;
	}

	$form_post = get_post( $form_id );
	if ( ! $form_post ) {
		return 0;
	}

	$form_content = json_decode( $form_post->post_content, true );
	$field_points = $form_content['settings']['gnf_field_points'] ?? array();

	if ( empty( $field_points ) || ! is_array( $field_points ) ) {
		return 0;
	}

	$total = 0;
	foreach ( $field_points as $info ) {
		$total += absint( $info['puntos'] ?? 0 );
	}

	return $total;
}

/**
 * Obtiene el puntaje máximo posible de un reto para un año.
 *
 * Lee field_points desde ACF configuracion_por_anio. Fallback a WPForms legacy.
 *
 * @param int      $reto_id ID del reto.
 * @param int|null $anio    Año (usa activo si null).
 * @return int Total de puntos posibles.
 */
function gnf_get_reto_max_points( $reto_id, $anio = null ) {
	$anio         = function_exists( 'gnf_normalize_year' ) ? gnf_normalize_year( $anio ) : absint( $anio ?: gmdate( 'Y' ) );
	$field_points = gnf_get_reto_field_points( $reto_id, $anio );
	if ( ! empty( $field_points ) ) {
		$total = 0;
		foreach ( $field_points as $info ) {
			$total += absint( $info['puntos'] ?? 0 );
		}
		return $total;
	}

	// Legacy fallback: read from WPForms JSON.
	$form_id = gnf_get_reto_form_id_for_year( $reto_id, $anio );
	return gnf_get_form_max_points( $form_id );
}

/**
 * Recalcula puntaje total y estrella de un centro para un año.
 *
 * @param int      $centro_id ID del centro.
 * @param int|null $anio      Año (usa activo si null).
 * @return array { anio, total, estrella }
 */
function gnf_recalcular_puntaje_centro( $centro_id, $anio = null ) {
	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';
	$anio  = function_exists( 'gnf_normalize_year' ) ? gnf_normalize_year( $anio ) : (int) ( $anio ?: gnf_get_active_year() );

	// Sum puntaje across all entries (not just 'aprobado' — points accumulate per-evidence).
	$total = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT SUM(puntaje) FROM {$table} WHERE centro_id = %d AND anio = %d",
			$centro_id,
			$anio
		)
	);

	$estrella = gnf_calcular_estrella_por_puntaje( $total );
	gnf_set_centro_score( $centro_id, $anio, $total, $estrella );

	set_transient( 'gnf_total_' . $centro_id . '_' . $anio, $total, DAY_IN_SECONDS );
	delete_transient( 'gnf_aprobados_' . $centro_id . '_' . $anio );

	return array(
		'anio'     => $anio,
		'total'    => $total,
		'estrella' => $estrella,
	);
}

/**
 * Calcula estrella según rangos configurados.
 *
 * @param int $puntaje Puntaje total.
 * @return int Estrella 0-5.
 */
function gnf_calcular_estrella_por_puntaje( $puntaje ) {
	// Read star ranges from ACF group field 'rangos_estrella' on options page.
	$group = function_exists( 'get_field' ) ? get_field( 'rangos_estrella', 'option' ) : null;

	$rangos = array();
	for ( $i = 1; $i <= 5; $i++ ) {
		$min_key = 'rango_estrella_' . $i . '_min';
		$max_key = 'rango_estrella_' . $i . '_max';

		// Try group sub-fields first, fallback to individual option reads.
		if ( is_array( $group ) && ( isset( $group[ $min_key ] ) || isset( $group[ $max_key ] ) ) ) {
			$rangos[ $i ] = array(
				'min' => (int) ( $group[ $min_key ] ?? 0 ),
				'max' => (int) ( $group[ $max_key ] ?? 0 ),
			);
		} else {
			$rangos[ $i ] = array(
				'min' => (int) gnf_get_option( $min_key, 0 ),
				'max' => (int) gnf_get_option( $max_key, 0 ),
			);
		}
	}

	$estrella = 0;
	foreach ( $rangos as $i => $range ) {
		if ( $range['min'] <= 0 && $range['max'] <= 0 ) {
			continue;
		}
		if ( $puntaje >= $range['min'] && ( 0 === $range['max'] || $puntaje <= $range['max'] ) ) {
			$estrella = $i;
			break;
		}
	}

	return $estrella;
}
