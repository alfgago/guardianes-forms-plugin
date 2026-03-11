<?php
/**
 * Utilidades para modelo anual de centro (ACF). Sin soporte legacy.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * No-op: ya no existe migración legacy. Mantiene firma para compatibilidad con flujos de reinicio/seed.
 *
 * @param array $args Configuración (ignorada).
 * @return array
 */
function gnf_migrate_center_annual_data( $args = array() ) {
	return array(
		'centers_scanned' => 0,
		'rows_migrated'   => 0,
		'rows_skipped'    => 0,
		'errors'          => array(),
	);
}

/**
 * Valida consistencia multi-año: puntaje en BD de entradas vs modelo ACF por año.
 *
 * @param int[] $years Años a validar.
 * @param array $args  Args opcionales (logger).
 * @return array
 */
function gnf_validate_multi_year_consistency( $years = array( 2025, 2026 ), $args = array() ) {
	global $wpdb;

	$args   = wp_parse_args(
		$args,
		array(
			'logger' => null,
		)
	);
	$logger = is_callable( $args['logger'] ) ? $args['logger'] : null;
	$log    = function ( $msg, $type = 'info' ) use ( $logger ) {
		if ( $logger ) {
			call_user_func( $logger, $msg, $type );
		}
	};

	$years = array_values( array_unique( array_map( 'gnf_normalize_year', (array) $years ) ) );
	sort( $years );

	$center_ids = get_posts(
		array(
			'post_type'      => 'centro_educativo',
			'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);
	$center_ids = array_map( 'absint', (array) $center_ids );

	$table_entries = $wpdb->prefix . 'gn_reto_entries';
	$result        = array(
		'years' => array(),
	);

	foreach ( $years as $year ) {
		$year_stats = array(
			'year'                  => $year,
			'centers_with_activity' => 0,
			'puntaje_mismatch'      => 0,
		);

		$centers_with_activity = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT centro_id FROM {$table_entries} WHERE anio = %d",
				$year
			)
		);
		$year_stats['centers_with_activity'] = count( (array) $centers_with_activity );

		foreach ( $center_ids as $centro_id ) {
			if ( ! $centro_id ) {
				continue;
			}

			$db_score    = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(puntaje), 0) FROM {$table_entries} WHERE centro_id = %d AND anio = %d AND estado = 'aprobado'",
					$centro_id,
					$year
				)
			);
			$model_score = gnf_get_centro_puntaje_total( $centro_id, $year );
			if ( $db_score !== $model_score ) {
				$year_stats['puntaje_mismatch']++;
			}
		}

		$result['years'][] = $year_stats;
		$log(
			sprintf(
				'Año %d: centros con actividad=%d, desajuste puntaje=%d',
				$year_stats['year'],
				$year_stats['centers_with_activity'],
				$year_stats['puntaje_mismatch']
			),
			'info'
		);
	}

	return $result;
}
