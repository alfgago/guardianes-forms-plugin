<?php
define( 'WP_CLI', true );
class WP_CLI { static function log( $value ) { $GLOBALS['output'] = $value; } static function error( $message ) { throw new RuntimeException( $message ); } }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function gnf_get_available_retos_for_year( $year ) { return array( (object) array( 'ID' => 1, 'post_title' => 'Agua' ), (object) array( 'ID' => 2, 'post_title' => 'Residuos' ), (object) array( 'ID' => 3, 'post_title' => 'Reto sin formulario' ), (object) array( 'ID' => 4, 'post_title' => 'Reto con enlace incorrecto' ) ); }
function gnf_get_reto_form_id_for_year( $id, $year ) { return array( 1 => 101, 2 => 102, 3 => 0, 4 => 999 )[$id]; }
class AuditDatabase {
	public $posts = 'wp_posts'; public $prefix = 'wp_'; public $last_error = '';
	function prepare( $sql, ...$args ) { return vsprintf( $sql, $args ); }
	function get_results( $sql ) {
		if ( 0 !== strpos( $sql, 'SELECT ' ) ) { throw new RuntimeException( 'Audit attempted a write' ); }
		if ( $GLOBALS['fail_db'] ) { $this->last_error = 'failed'; return null; }
		if ( false !== strpos( $sql, 'gn_reto_entries' ) ) { return array( (object) array( 'reto_id' => 1, 'entradas' => 438, 'centros' => 400 ), (object) array( 'reto_id' => 2, 'entradas' => 329, 'centros' => 320 ) ); }
		preg_match( '/ID > (\d+)/', $sql, $matches ); $cursor = (int) $matches[1];
		return array_slice( array_values( array_filter( $GLOBALS['forms'], static function ( $form ) use ( $cursor ) { return $form->ID > $cursor; } ) ), 0, 200 );
	}
}
$wpdb = new AuditDatabase(); $fail_db = false; $tests = 0; $fails = 0;
$forms = array(
	(object) array( 'ID' => 101, 'post_title' => 'Agua', 'post_status' => 'publish', 'post_content' => '{"fields":{"6":{"id":6,"type":"file-upload"}}}', 'post_modified_gmt' => '2026-10-08' ),
	(object) array( 'ID' => 102, 'post_title' => 'Residuos', 'post_status' => 'publish', 'post_content' => '{broken', 'post_modified_gmt' => '2026-10-08' ),
	(object) array( 'ID' => 103, 'post_title' => 'Encuesta no asociada a reto', 'post_status' => 'publish', 'post_content' => '{"fields":{"1":{"id":1,"type":"text"}}}' ),
	(object) array( 'ID' => 104, 'post_title' => 'Formulario sin preguntas', 'post_status' => 'draft', 'post_content' => '{"fields":[]}' ),
);
$args = array( '2026', 'json' ); $tool = dirname( __DIR__ ) . '/tools/audit-wpforms.php';
function check_audit( $ok, $label ) { global $tests, $fails; $tests++; $fails += $ok ? 0 : 1; echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $label . "\n"; }
if ( ! file_exists( $tool ) ) { check_audit( false, 'Global audit tool exists' ); exit( 1 ); }
require $tool;
$report = json_decode( $output, true );
check_audit( 4 === $report['total_formularios'] && 2 === $report['formularios_ilegibles_o_vacios'], 'Audit checks every nontrashed form including those outside reto mappings' );
check_audit( 'OK' === $report['formularios'][0]['estado_definicion'], 'A removed historical question does not make the current definition invalid' );
check_audit( 'JSON_INVALIDO' === $report['formularios'][1]['estado_definicion'] && 329 === $report['formularios'][1]['retos'][0]['entradas'], 'Audit reports broken shared forms and aggregate entry counts without inspecting individual answers' );
check_audit( 'SIN_PREGUNTAS' === $report['formularios'][3]['estado_definicion'], 'Audit distinguishes missing questions from malformed JSON' );
check_audit( count( $report['retos_sin_formulario'] ) === 2, 'Audit detects missing and incorrect annual form mappings' );
check_audit( false === strpos( $output, 'post_content' ) && false === strpos( $output, 'user_id' ), 'Audit output excludes definitions and user data' );
$summary = gnf_wpforms_audit_summary( $report );
check_audit( false !== strpos( $summary, '101 | Agua | publish | OK | 1' ) && false !== strpos( $summary, '102 | Residuos | publish | JSON_INVALIDO' ), 'Compact global output identifies good and damaged forms without long JSON' );
$base = $forms[0]; $forms = array();
for ( $i = 1; $i <= 201; $i++ ) { $form = clone $base; $form->ID = $i; $forms[] = $form; }
$report = gnf_audit_wpforms( 2026 );
check_audit( 201 === $report['total_formularios'], 'Audit processes all shared forms across multiple batches' );
$fail_db = true; $blocked = false;
try { gnf_audit_wpforms( 2026 ); } catch ( RuntimeException $error ) { $blocked = true; }
check_audit( $blocked, 'Database failures cannot be reported as a healthy empty catalog' );
echo "{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
