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
	public $prefix = 'wp_'; public $posts = 'wp_posts'; public $last_error = '';
	function prepare( $sql, ...$args ) { return vsprintf( $sql, $args ); }
	function get_results( $sql ) {
		if ( strpos( $sql, 'SELECT ' ) !== 0 ) { throw new RuntimeException( 'Unexpected database write' ); }
		if ( $GLOBALS['db_failure'] ) { $this->last_error = 'failed'; return null; }
		if ( false !== strpos( $sql, 'FROM wp_posts' ) ) {
			if ( $GLOBALS['revision_db_failure'] ) { $this->last_error = 'failed'; return null; }
			preg_match( '/ID < (\d+)/', $sql, $match ); $before = (int) ( $match[1] ?? PHP_INT_MAX );
			$rows = array_filter( $GLOBALS['posts'], static function ( $post ) use ( $before ) { return 101 === ( $post->post_parent ?? null ) && in_array( $post->post_type, array( 'revision', 'wpforms_revision' ), true ) && $post->ID < $before; } );
			usort( $rows, static function ( $a, $b ) { return $b->ID <=> $a->ID; } );
			return array_slice( $rows, 0, 200 );
		}
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
	foreach ( array( 'db_failure', 'backup_failure', 'save_failure', 'saved_corruption', 'revision_db_failure' ) as $key ) { $GLOBALS[$key] = false; }
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

reset_recovery();
$entries[0]->data = '{"__raw_values__":{"6":"","7":"","8":"CONFIDENTIAL RESPONSE","9":0,"10":false,"11":[]}}';
$entries[0]->evidencias = '[{"field_id":7,"replaced":false,"ruta":"https://private.test/secret.jpg"},{"field_id":150,"replaced":true}]';
$historical = json_decode( $posts[201]->post_content, true );
foreach ( array( 7 => 'file-upload', 8 => 'text', 9 => 'number', 10 => 'checkbox', 11 => 'text' ) as $id => $type ) { $historical['fields'][$id] = array( 'id' => $id, 'type' => $type, 'label' => '<b>Original question ' . $id . '</b>' ); }
$posts[200] = (object) array( 'ID' => 200, 'post_parent' => 101, 'post_type' => 'wpforms_revision', 'post_modified_gmt' => '2026-09-01', 'post_content' => json_encode( $historical ) );
$report = null;
try { $report = gnf_recover_wpforms_form( 2026, 101, 201, false, true ); } catch ( RuntimeException $error ) {}
check_recovery( is_array( $report ) && 'diagnostico' === $report['resultado'] && 0 === $writes && empty( $options ), 'Diagnostic mode reports incompatibility without writing forms or backups' );
check_recovery( isset( $report['uso_campos'][7] ) && 1 === $report['uso_campos'][7]['respuestas_vacias'] && 1 === $report['uso_campos'][7]['evidencias_activas'], 'Diagnostic distinguishes empty answers from active evidence using the same field' );
check_recovery( isset( $report['uso_campos'][8] ) && 1 === $report['uso_campos'][8]['respuestas_con_valor'] && 1 === $report['uso_campos'][9]['respuestas_con_valor'] && 1 === $report['uso_campos'][10]['respuestas_con_valor'] && 1 === $report['uso_campos'][11]['respuestas_vacias'], 'Diagnostic counts nonempty answers including zero and false without disclosing values' );
check_recovery( isset( $report['historial']['revisiones_compatibles'][0] ) && 200 === $report['historial']['revisiones_compatibles'][0]['id'], 'Diagnostic finds an older compatible revision rather than merging fields or choosing it automatically' );
check_recovery( isset( $report['historial']['campos_en_revisiones'][7][0] ) && 'file-upload' === $report['historial']['campos_en_revisiones'][7][0]['tipo'] && 'Original question 7' === $report['historial']['campos_en_revisiones'][7][0]['etiqueta'], 'Diagnostic reports original field labels and types from valid revisions' );
$json = json_encode( $report );
check_recovery( false === strpos( $json, 'CONFIDENTIAL RESPONSE' ) && false === strpos( $json, 'secret.jpg' ) && false === strpos( $json, 'post_content' ), 'Diagnostic output excludes response values, file names and full definitions' );
$blocked = false;
try { gnf_recover_wpforms_form( 2026, 101, 201, true, true ); } catch ( RuntimeException $error ) { $blocked = true; }
check_recovery( $blocked && 0 === $writes, 'Diagnostic mode cannot be combined with application' );
$posts[200]->post_content = '{bad';
$report = null;
try { $report = gnf_recover_wpforms_form( 2026, 101, 201, false, true ); } catch ( RuntimeException $error ) {}
check_recovery( isset( $report['historial'] ) && empty( $report['historial']['revisiones_compatibles'] ) && 1 === $report['historial']['revisiones_ilegibles'], 'Corrupt historical revisions are reported but never proposed as compatible' );
$revision_db_failure = true; $blocked = false;
try { gnf_recover_wpforms_form( 2026, 101, 201, false, true ); } catch ( RuntimeException $error ) { $blocked = true; }
check_recovery( $blocked && 0 === $writes, 'Revision lookup failures are explicit and cannot change data' );
reset_recovery(); $entries[0]->data = '{"__raw_values__":{"7":"Si"}}';
$base = json_decode( $posts[201]->post_content, true ); $base['fields'][7] = array( 'id' => 7, 'type' => 'text', 'label' => 'Older question' );
$posts[200] = (object) array( 'ID' => 200, 'post_parent' => 101, 'post_type' => 'revision', 'post_content' => json_encode( $base ) );
for ( $i = 300; $i < 500; $i++ ) { $posts[$i] = (object) array( 'ID' => $i, 'post_parent' => 101, 'post_type' => 'revision', 'post_content' => '{bad' ); }
$report = null;
try { $report = gnf_recover_wpforms_form( 2026, 101, 201, false, true ); } catch ( RuntimeException $error ) {}
check_recovery( isset( $report['historial']['revisiones_compatibles'][0] ) && 200 === $report['historial']['revisiones_compatibles'][0]['id'] && 202 === $report['historial']['revisiones_revisadas'], 'Historical lookup examines revisions beyond the first batch' );

function historical_recovery_fixture() {
	reset_recovery();
	$old = json_decode( $GLOBALS['posts'][201]->post_content, true );
	$old['fields'][7] = array( 'id' => 7, 'type' => 'text', 'label' => 'Retired quantity' );
	$old['fields'][8] = array( 'id' => 8, 'type' => 'file-upload', 'label' => 'Retired evidence' );
	$GLOBALS['posts'][200] = (object) array( 'ID' => 200, 'post_parent' => 101, 'post_type' => 'revision', 'post_content' => json_encode( $old ) );
	$GLOBALS['entries'][0]->data = '{"__raw_values__":{"6":"","7":"25","8":"private.jpg"}}';
	$GLOBALS['entries'][0]->evidencias = '[{"field_id":6,"replaced":false},{"field_id":8,"replaced":false,"estado":"en_pausa","puntos":5},{"field_id":150,"replaced":true}]';
}
historical_recovery_fixture(); $refs = array( 7 => 200, 8 => 200 ); $preview = null;
try { $preview = gnf_recover_wpforms_form( 2026, 101, 201, false, false, $refs ); } catch ( RuntimeException $error ) {}
check_recovery( isset( $preview['campos_historicos'][7], $preview['campos_historicos'][8] ) && 'simulacion' === $preview['resultado'] && 0 === $writes && empty( $options ), 'Explicit historical references permit a read-only preview without inventing fields' );
$before_entries = json_encode( $entries ); $result = null;
try { $result = gnf_recover_wpforms_form( 2026, 101, 201, true, false, $refs ); } catch ( RuntimeException $error ) {}
$saved = json_decode( $posts[101]->post_content, true );
check_recovery( isset( $result['resultado'] ) && 'restaurado' === $result['resultado'] && array( 6 ) === array_keys( $saved['fields'] ) && 151 === $saved['field_id'], 'Historical recovery restores current questions without republishing retired fields or reusing their IDs' );
check_recovery( $before_entries === json_encode( $entries ), 'Historical recovery preserves every response, evidence, review state and stored evidence points byte for byte' );
$backup = reset( $options );
check_recovery( isset( $backup['campos_historicos'][8] ) && 200 === $backup['campos_historicos'][8]['revision_id'] && 'file-upload' === $backup['campos_historicos'][8]['tipo'], 'Recovery backup records the explicit original revisions for historical references' );
foreach ( array( 'incomplete_refs', 'wrong_parent', 'corrupt_source', 'field_not_in_source', 'file_wrong_type', 'not_older', 'current_field', 'unused_field' ) as $failure ) {
	historical_recovery_fixture(); $refs = array( 7 => 200, 8 => 200 );
	if ( 'incomplete_refs' === $failure ) { unset( $refs[8] ); }
	elseif ( 'wrong_parent' === $failure ) { $posts[200]->post_parent = 999; }
	elseif ( 'corrupt_source' === $failure ) { $posts[200]->post_content = '{bad'; }
	elseif ( 'not_older' === $failure ) { $posts[202] = clone $posts[200]; $posts[202]->ID = 202; $refs[8] = 202; }
	elseif ( 'current_field' === $failure ) { $refs[6] = 200; }
	elseif ( 'unused_field' === $failure ) { $refs[99] = 200; }
	else { $old = json_decode( $posts[200]->post_content, true ); if ( 'field_not_in_source' === $failure ) { unset( $old['fields'][8] ); } else { $old['fields'][8]['type'] = 'text'; } $posts[200]->post_content = json_encode( $old ); }
	$blocked = false;
	try { gnf_recover_wpforms_form( 2026, 101, 201, true, false, $refs ); } catch ( RuntimeException $error ) { $blocked = true; }
	check_recovery( $blocked && 0 === $writes && empty( $options ), $failure . ': historical references cannot bypass unrelated validation or mutate data' );
}
$parsed = null;
if ( function_exists( 'gnf_recovery_parse_historical_refs' ) ) { $parsed = gnf_recovery_parse_historical_refs( 'historicos=7:200,8:199' ); }
check_recovery( array( 7 => 200, 8 => 199 ) === $parsed, 'CLI parses exact field-to-revision historical references' );
foreach ( array( '7,8', 'historicos=', 'historicos=7:200,7:199', 'historicos=0:200', 'historicos=7:bad', 'historicos=7:200,' ) as $bad_arg ) {
	$blocked = false;
	if ( function_exists( 'gnf_recovery_parse_historical_refs' ) ) { try { gnf_recovery_parse_historical_refs( $bad_arg ); } catch ( RuntimeException $error ) { $blocked = true; } }
	check_recovery( $blocked, 'Malformed historical option rejected: ' . $bad_arg );
}

// Exercise the existing merge functions: subsequent saves must retain retired data.
function current_time( $type ) { return '2026-10-08 15:00:00'; }
$hooks = file_get_contents( dirname( __DIR__ ) . '/includes/wpforms-hooks.php' );
foreach ( array( 'gnf_merge_reto_entry_data', 'gnf_merge_reto_evidencias' ) as $name ) {
	$start = strpos( $hooks, 'function ' . $name . '(' );
	eval( substr( $hooks, $start, strpos( $hooks, "\n}", $start ) + 2 - $start ) );
}
$old_values = array( '__raw_values__' => array( 6 => 'Old current answer', 7 => '25', 8 => 'private.jpg' ), '__fields__' => array( 7 => '25', 8 => 'private.jpg' ) );
$merged = gnf_merge_reto_entry_data( $old_values, array( 'anio' => 2026 ), array( 6 => 'Updated' ), array( 6 => 'Updated' ) );
check_recovery( '25' === $merged['__raw_values__'][7] && 'private.jpg' === $merged['__raw_values__'][8] && 'Updated' === $merged['__raw_values__'][6] && $old_values['__fields__'][8] === $merged['__fields__'][8], 'Normal autosaves retain answers and summaries belonging to retired fields' );
$old_evidence = array( array( 'field_id' => 8, 'ruta' => 'legacy.jpg', 'estado' => 'en_pausa', 'review_reason' => 'No es concluyente', 'puntos' => 5 ) );
$merged = gnf_merge_reto_evidencias( $old_evidence, array( array( 'field_id' => 6, 'ruta' => 'current.jpg', 'estado' => 'pendiente' ) ) );
check_recovery( 2 === count( $merged ) && $old_evidence[0] === $merged[0], 'Uploads to current questions retain retired evidence and its review metadata unchanged' );
echo "{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
