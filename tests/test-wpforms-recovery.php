<?php
define( 'WP_CLI', true );
class WP_CLI { static function log( $message ) {} static function error( $message ) { throw new RuntimeException( $message ); } }
function gnf_get_available_retos_for_year( $year ) { return array( (object) array( 'ID' => 1 ) ); }
function gnf_get_reto_form_id_for_year( $id, $year ) { return 101; }
function get_post( $id ) { return isset( $GLOBALS['posts'][ $id ] ) ? clone $GLOBALS['posts'][ $id ] : null; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function add_option( $key, $value, $unused = '', $autoload = null ) { if ( $GLOBALS['backup_failure'] ) { return false; } if ( isset( $GLOBALS['options'][ $key ] ) ) { return false; } $GLOBALS['options'][ $key ] = $value; return true; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_slash( $value ) { return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value ); }
class WP_Error {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_update_post( $data, $return_error = false ) {
	$GLOBALS['writes']++;
	if ( $GLOBALS['save_failure'] ) { return new WP_Error(); }
	if ( $data['ID'] !== 101 || array_diff( array_keys( $data ), array( 'ID', 'post_content' ) ) ) { throw new RuntimeException( 'Unexpected mutation' ); }
	$GLOBALS['posts'][101]->post_content = $GLOBALS['saved_corruption'] ? '{corrupt' : stripslashes( $data['post_content'] );
	return 101;
}
class RecoveryDatabase {
	public $prefix = 'wp_'; public $last_error = '';
	function prepare( $sql, ...$args ) { return vsprintf( $sql, $args ); }
	function get_results( $sql ) {
		if ( strpos( $sql, 'SELECT ' ) !== 0 ) { throw new RuntimeException( 'Unexpected database write' ); }
		if ( $GLOBALS['db_failure'] ) { $this->last_error = 'failed'; return null; }
		preg_match( '/id > (\d+)/', $sql, $match );
		$cursor = (int) ( $match[1] ?? 0 );
		return array_slice( array_values( array_filter( $GLOBALS['entries'], static function ( $row ) use ( $cursor ) { return $row->id > $cursor; } ) ), 0, 200 );
	}
}
$wpdb = new RecoveryDatabase(); $tests = 0; $fails = 0;
function check_recovery( $ok, $label ) { global $tests, $fails; $tests++; $fails += $ok ? 0 : 1; echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $label . "\n"; }
function reset_recovery() {
	$GLOBALS['posts'] = array(
		101 => (object) array( 'ID' => 101, 'post_type' => 'wpforms', 'post_status' => 'publish', 'post_content' => '{broken' ),
		201 => (object) array( 'ID' => 201, 'post_parent' => 101, 'post_type' => 'revision', 'post_content' => json_encode( array( 'fields' => array( 6 => array( 'id' => 6, 'type' => 'file-upload', 'label' => 'Foto "terminada"', 'description' => '<a href="https://example.test">Guia</a>' ) ), 'field_id' => 100 ) ) ),
	);
	$GLOBALS['entries'] = array( (object) array( 'id' => 1, 'data' => '{"__raw_values__":{"6":""}}', 'evidencias' => '[{"field_id":6,"replaced":false},{"field_id":150,"replaced":true}]' ) );
	$GLOBALS['options'] = array(); $GLOBALS['writes'] = 0;
	foreach ( array( 'db_failure', 'backup_failure', 'save_failure', 'saved_corruption' ) as $key ) { $GLOBALS[$key] = false; }
	$GLOBALS['wpdb']->last_error = '';
}
reset_recovery(); $args = array( '2026', '101', '201' );
$tool = dirname( __DIR__ ) . '/tools/recover-wpforms-form.php';
if ( ! file_exists( $tool ) ) { check_recovery( false, 'Recovery tool exists' ); exit( 1 ); }
require $tool;
$preview = gnf_recover_wpforms_form( 2026, 101, 201, false );
check_recovery( 'simulacion' === $preview['resultado'] && 0 === $writes && empty( $options ), 'Default simulation performs no writes or backups' );
$original = $posts[101]->post_content; $entry_json = json_encode( $entries );
$result = gnf_recover_wpforms_form( 2026, 101, 201, true );
$restored = json_decode( $posts[101]->post_content, true );
check_recovery( 'restaurado' === $result['resultado'] && 1 === $writes, 'Explicit application restores only the requested definition' );
check_recovery( 'Foto "terminada"' === $restored['fields'][6]['label'] && '<a href="https://example.test">Guia</a>' === $restored['fields'][6]['description'], 'Restoration preserves JSON quotes and HTML' );
check_recovery( 151 === $restored['field_id'], 'Restoration avoids reusing historical evidence IDs' );
check_recovery( $original === reset( $options )['post_content'], 'Original corrupt definition is backed up before restoring' );
check_recovery( json_encode( $entries ) === $entry_json, 'Evidence and response records are never changed' );
$second = gnf_recover_wpforms_form( 2026, 101, 201, true );
check_recovery( 'ya_valido' === $second['resultado'] && 1 === $writes, 'A valid live form cannot be overwritten by rerunning recovery' );
foreach ( array( 'wrong_parent', 'bad_revision', 'missing_field', 'wrong_type', 'corrupt_entries', 'db_failure', 'backup_failure', 'save_failure', 'saved_corruption' ) as $failure ) {
	reset_recovery();
	if ( 'wrong_parent' === $failure ) { $posts[201]->post_parent = 999; }
	elseif ( 'bad_revision' === $failure ) { $posts[201]->post_content = '{bad'; }
	elseif ( 'missing_field' === $failure ) { $entries[0]->evidencias = '[{"field_id":99}]'; }
	elseif ( 'wrong_type' === $failure ) { $data = json_decode( $posts[201]->post_content, true ); $data['fields'][6]['type'] = 'radio'; $posts[201]->post_content = json_encode( $data ); }
	elseif ( 'corrupt_entries' === $failure ) { $entries[0]->evidencias = '{bad'; }
	else { $GLOBALS[$failure] = true; }
	$blocked = false;
	try { gnf_recover_wpforms_form( 2026, 101, 201, true ); } catch ( RuntimeException $error ) { $blocked = true; }
	check_recovery( $blocked, $failure . ': unsafe or failed recovery is reported' );
	if ( ! in_array( $failure, array( 'save_failure', 'saved_corruption' ), true ) ) { check_recovery( 0 === $writes, $failure . ': no live form mutation' ); }
}
reset_recovery();
$entries[0]->data = '{"__raw_values__":{"99":"Si"}}';
$blocked = false;
try { gnf_recover_wpforms_form( 2026, 101, 201, true ); } catch ( RuntimeException $error ) { $blocked = true; }
check_recovery( $blocked && 0 === $writes, 'Saved answers also block restoring a revision with missing IDs' );
reset_recovery(); $template = clone $entries[0]; $entries = array();
for ( $i = 1; $i <= 201; $i++ ) { $row = clone $template; $row->id = $i; $entries[] = $row; }
$preview = gnf_recover_wpforms_form( 2026, 101, 201, false );
check_recovery( 201 === $preview['entradas_verificadas'] && 0 === $writes, 'Simulation checks all centers across multiple batches' );
$entries[200]->evidencias = '[{"field_id":99}]'; $blocked = false;
try { gnf_recover_wpforms_form( 2026, 101, 201, true ); } catch ( RuntimeException $error ) { $blocked = true; }
check_recovery( $blocked && 0 === $writes, 'An incompatibility in a later batch prevents restoration' );
echo "{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
