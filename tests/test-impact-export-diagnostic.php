<?php
define( 'WP_CLI', true );
class WP_CLI {
	static function error( $message ) { throw new RuntimeException( $message ); }
	static function log( $value ) { $GLOBALS['diagnostic_output'] = $value; }
}
class ImpactDiagnosticDB {
	public $options = 'wp_options';
	public $last_error = '';
	function esc_like( $value ) { return addcslashes( $value, '_%' ); }
	function prepare( $query, ...$args ) { return $query; }
	function get_results( $query ) { return $GLOBALS['diagnostic_rows']; }
}
function maybe_unserialize( $value ) { return unserialize( $value ); }
function get_option( $key, $default = false ) { return $GLOBALS['diagnostic_options'][$key] ?? $default; }
function wp_next_scheduled( $hook, $args ) { return time() - 300; }
function wp_json_encode( $value, $flags ) { return json_encode( $value, $flags ); }
$wpdb = new ImpactDiagnosticDB();
$args = array( 2026 );
$key = 'gnf_impact_pdf_job_' . str_repeat( 'a', 64 );
$job = array( 'year' => 2026, 'userId' => 888, 'status' => 'queued', 'detail' => 'full', 'created' => time() - 310, 'payload' => array( 'private' => 'SECRET_RESPONSE' ), 'path' => '/private/SECRET_PATH' );
$diagnostic_rows = array( (object) array( 'option_name' => $key, 'option_value' => serialize( $job ) ), (object) array( 'option_name' => 'gnf_impact_pdf_job_' . str_repeat( 'b', 64 ), 'option_value' => serialize( array_replace( $job, array( 'year' => 2025 ) ) ) ) );
$diagnostic_options = array( 'gnf_report_snapshot_2026_v1' => array( 'ready' => true, 'generatedAt' => '2026-10-09 10:00:00' ) );
require __DIR__ . '/../tools/diagnose-impact-exports.php';
$result = json_decode( $diagnostic_output, true );
$tests = array(
	1 === count( $result['jobs'] ),
	'queued' === $result['jobs'][0]['estado'] && $result['jobs'][0]['edad_segundos'] >= 310,
	true === $result['corte_disponible'],
	false === strpos( $diagnostic_output, 'SECRET' ) && false === strpos( $diagnostic_output, '888' ),
);
$fails = count( array_filter( $tests, static function ( $ok ) { return ! $ok; } ) );
echo count( $tests ) . ' checks, ' . $fails . " failures\n";
exit( $fails ? 1 : 0 );
