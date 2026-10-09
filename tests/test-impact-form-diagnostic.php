<?php
define( 'WP_CLI', true );
class WP_CLI {
	public static $output;
	public static function log( $message ) { self::$output = $message; }
	public static function error( $message ) { throw new RuntimeException( $message ); }
}
class DiagnosticDatabase {
	public $posts = 'wp_posts';
	public $prefix = 'wp_';
	public $last_error = '';
	public $queries = array();
	public function esc_like( $value ) { return $value; }
	public function prepare( $query, ...$values ) { return $query; }
	public function get_results( $query ) {
		$this->queries[] = $query;
		if ( ! preg_match( '/^SELECT /', $query ) ) { throw new RuntimeException( 'Non-read query' ); }
		if ( strpos( $query, 'post_title LIKE' ) !== false ) { return array( (object) array( 'ID' => 10, 'post_title' => 'Escuela Salvador Villar', 'post_status' => 'publish' ) ); }
		if ( strpos( $query, 'gn_reto_entries' ) !== false ) {
			return array( (object) array( 'id' => 9, 'centro_id' => 10, 'reto_id' => 2, 'user_id' => 8, 'estado' => 'en_progreso', 'updated_at' => '2026-10-01', 'data' => '{"3":"Si"}', 'evidencias' => json_encode( array(
				array( 'field_id' => 33, 'path_local' => __FILE__ ),
				array( 'field_id' => 99, 'ruta' => 'https://test.test/uploads/not-there.jpg' ),
				array( 'field_id' => 33, 'replaced' => true, 'ruta' => 'https://external.test/old.jpg' ),
			) ) ) );
		}
		return array( (object) array( 'ID' => 102, 'post_modified_gmt' => '2026-09-30', 'post_content' => '{"fields":{"33":{"id":33,"type":"file-upload"}}}' ) );
	}
}
function gnf_get_reto_form_id_for_year( $reto, $year ) { return 101; }
function gnf_get_available_retos_for_year( $year ) { return array( (object) array( 'ID' => 2 ) ); }
function get_post( $id ) { return (object) array( 'ID' => $id, 'post_type' => 'wpforms', 'post_status' => 'publish', 'post_content' => $GLOBALS['form_json'], 'post_modified_gmt' => '2026-10-01' ); }
function get_the_title( $id ) { return 'Reto Huerta'; }
function get_option( $key, $default = false ) { return $default; }
function wp_upload_dir( $time = null, $create = true ) { if ( $create ) { throw new RuntimeException( 'Directory creation requested' ); } return array( 'basedir' => __DIR__, 'baseurl' => 'https://test.test/uploads' ); }
function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
function wp_normalize_path( $path ) { return str_replace( '\\', '/', $path ); }
$wpdb = new DiagnosticDatabase();
$form_json = '{"fields":{"33":{"id":33,"type":"file-upload"}}}';
$args = array( 'Salvador Villar', '2026' );
require dirname( __DIR__ ) . '/tools/diagnose-impact-forms.php';
$report = json_decode( WP_CLI::$output, true );
$checks = array(
	'Reports the requested center' => 10 === $report['centros'][0]['id'],
	'Validates live form JSON' => true === $report['formularios'][0]['json_valido'],
	'Counts stored active and replaced evidence separately' => 2 === $report['entradas'][0]['evidencias_activas'] && 1 === $report['entradas'][0]['evidencias_reemplazadas'],
	'Checks locally present and missing files' => 1 === $report['entradas'][0]['archivos_activos']['encontrados'] && 1 === $report['entradas'][0]['archivos_activos']['no_encontrados'],
	'Identifies evidence pointing to missing form fields' => array( 99 ) === $report['entradas'][0]['campos_archivo_ausentes'],
	'Lists valid revision candidates without restoring' => 102 === $report['formularios'][0]['revisiones_validas'][0]['id'],
	'Contains no saved answers or file paths' => false === strpos( WP_CLI::$output, __FILE__ ) && ! isset( $report['entradas'][0]['data'] ),
);
$form_json = '{invalid';
$report = gnf_diagnose_impact_forms( 'Salvador Villar', 2026 );
$checks['Detects corrupt JSON with parsing error'] = ! $report['formularios'][0]['json_valido'] && ! empty( $report['formularios'][0]['error_json'] );
$checks['Never claims field mismatches when the definition cannot be read'] = null === $report['entradas'][0]['campos_archivo_ausentes'];
$uploads = wp_upload_dir( null, false );
$checks['Does not inspect paths outside uploads'] = 'no_verificables' === gnf_diagnostic_file_status( array( 'path_local' => dirname( __DIR__ ) . '/guardianes-formularios.php' ), $uploads );
$checks['Rejects encoded traversal and null bytes'] = 'no_verificables' === gnf_diagnostic_file_status( array( 'ruta' => 'https://test.test/uploads/%2e%2e/secret' ), $uploads ) && 'no_verificables' === gnf_diagnostic_file_status( array( 'ruta' => 'https://test.test/uploads/bad%00.jpg' ), $uploads );
$checks['Does not request external files'] = 'no_verificables' === gnf_diagnostic_file_status( array( 'ruta' => 'https://external.test/photo.jpg' ), $uploads );
$checks['Compact output identifies the damaged form and candidate revision'] = function_exists( 'gnf_diagnostic_summary' ) && false !== strpos( gnf_diagnostic_summary( $report ), '101 | Reto Huerta | REVISAR | 102' );
$checks['Compact output totals stored files without dumping revisions'] = function_exists( 'gnf_diagnostic_summary' ) && false !== strpos( gnf_diagnostic_summary( $report ), 'Archivos activos: 1 encontrados, 1 no encontrados, 0 no verificables' );
$fails = 0;
foreach ( $checks as $label => $ok ) { echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $label . "\n"; $fails += $ok ? 0 : 1; }
echo count( $checks ) . " checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
