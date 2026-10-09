<?php
/** Read-only operational summary. Never print payloads, users or private file paths. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit; }

$year = (string) ( $args[0] ?? gmdate( 'Y' ) );
if ( count( $args ) > 1 || ! preg_match( '/^[0-9]{4}$/D', $year ) || (int) $year < 2000 || (int) $year > 2100 ) {
	WP_CLI::error( 'Uso: wp eval-file tools/diagnose-impact-exports.php 2026' );
}
global $wpdb;
$wpdb->last_error = '';
$rows = $wpdb->get_results( $wpdb->prepare(
	"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_id DESC LIMIT 100",
	$wpdb->esc_like( 'gnf_impact_pdf_job_' ) . '%'
) );
if ( $wpdb->last_error ) { WP_CLI::error( 'No se pudo consultar la cola de PDF.' ); }
$snapshot = get_option( 'gnf_report_snapshot_' . (int) $year . '_v1', array() );
$output = array(
	'year' => (int) $year,
	'wp_cron_automatico' => ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ),
	'cron_alternativo' => defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON,
	'corte_disponible' => ! empty( $snapshot['ready'] ),
	'fecha_corte' => $snapshot['generatedAt'] ?? null,
	'proxima_actualizacion_utc' => ( $next = wp_next_scheduled( 'gnf_refresh_report_snapshot', array( (int) $year ) ) ) ? gmdate( 'c', $next ) : null,
	'jobs' => array(),
	'limite_consulta' => 100,
);
foreach ( (array) $rows as $row ) {
	$job = maybe_unserialize( $row->option_value );
	if ( ! is_array( $job ) || ! preg_match( '/^gnf_impact_pdf_job_[a-f0-9]{64}$/D', $row->option_name ) ) { continue; }
	$job_year = $job['year'] ?? ( $job['payload']['year'] ?? null );
	if ( null !== $job_year && (int) $job_year !== (int) $year ) { continue; }
	$lock = get_option( str_replace( '_job_', '_lock_', $row->option_name ), array() );
	$next = wp_next_scheduled( 'gnf_run_impact_pdf_job', array( $row->option_name ) );
	$output['jobs'][] = array(
		'id' => substr( $row->option_name, -12 ),
		'estado' => $job['status'] ?? 'desconocido',
		'year' => $job_year,
		'detalle' => $job['detail'] ?? null,
		'edad_segundos' => max( 0, time() - (int) ( $job['created'] ?? time() ) ),
		'iniciado_utc' => ! empty( $job['started'] ) ? gmdate( 'c', $job['started'] ) : null,
		'duracion_segundos' => isset( $job['finished'], $job['started'] ) ? $job['finished'] - $job['started'] : null,
		'worker_programado_utc' => $next ? gmdate( 'c', $next ) : null,
		'lock_activo' => (int) ( $lock['expires'] ?? 0 ) > time(),
		'archivo_presente' => is_string( $job['path'] ?? null ) && is_file( $job['path'] ),
	);
}
WP_CLI::log( wp_json_encode( $output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
