<?php
/** Read-only global audit. No file contents, personal information or score writes. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit; }

function gnf_audit_evidence_review( $year, $center = 0 ) {
	global $wpdb;
	$report = array( 'anio' => $year, 'entradas' => 0, 'evidencias_activas' => 0, 'revisables_sin_puntaje' => 0, 'anexos_informativos' => 0, 'centros_afectados' => 0, 'retos' => array() );
	$cursor = 0; $points = array(); $affected = array();
	do {
		$wpdb->last_error = '';
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, centro_id, reto_id, evidencias FROM {$wpdb->prefix}gn_reto_entries WHERE anio = %d AND id > %d AND (%d = 0 OR centro_id = %d) ORDER BY id ASC LIMIT 200",
			$year, $cursor, $center, $center
		) );
		if ( $wpdb->last_error ) { throw new RuntimeException( 'No se pudieron consultar las evidencias.' ); }
		foreach ( (array) $rows as $entry ) {
			$cursor = (int) $entry->id; $report['entradas']++;
			$reto = (int) $entry->reto_id;
			if ( ! isset( $points[ $reto ] ) ) { $points[ $reto ] = gnf_get_reto_field_points( $reto, $year ); }
			foreach ( (array) json_decode( (string) $entry->evidencias, true ) as $evidence ) {
				if ( ! is_array( $evidence ) || ! empty( $evidence['replaced'] ) ) { continue; }
				$report['evidencias_activas']++;
				if ( ! gnf_evidence_is_reviewable( $evidence ) ) { $report['anexos_informativos']++; continue; }
				$fid = (int) ( $evidence['field_id'] ?? 0 );
				if ( null !== ( $evidence['puntos'] ?? null ) || isset( $points[ $reto ][ $fid ] ) ) { continue; }
				$report['revisables_sin_puntaje']++;
				$affected[ (int) $entry->centro_id ] = true;
				if ( ! isset( $report['retos'][ $reto ] ) ) {
					$report['retos'][ $reto ] = array( 'reto_id' => $reto, 'formulario_id' => gnf_get_reto_form_id_for_year( $reto, $year ), 'evidencias' => 0, 'campos_sin_puntaje' => array() );
				}
				$report['retos'][ $reto ]['evidencias']++;
				$report['retos'][ $reto ]['campos_sin_puntaje'][ $fid ] = $fid;
			}
		}
	} while ( $rows );
	$report['centros_afectados'] = count( $affected );
	foreach ( $report['retos'] as &$reto ) { $reto['campos_sin_puntaje'] = array_values( $reto['campos_sin_puntaje'] ); }
	unset( $reto );
	$report['retos'] = array_values( $report['retos'] );
	return $report;
}

$year = (int) ( $args[0] ?? 2026 ); $center = (int) ( $args[1] ?? 0 );
if ( $year < 2020 || $year > 2100 || $center < 0 ) { WP_CLI::error( 'Uso: wp eval-file <archivo> 2026 [centro_id]' ); }
try {
	WP_CLI::log( wp_json_encode( gnf_audit_evidence_review( $year, $center ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
} catch ( Throwable $error ) { WP_CLI::error( $error->getMessage() ); }
