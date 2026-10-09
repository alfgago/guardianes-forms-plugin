<?php
// Integration boundary: WordPress unslashes post_content before persistence.
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
function add_action() {}
function wpforms() { return true; }
function gnf_get_available_retos_for_year( $year ) { return $GLOBALS['retos']; }
function gnf_get_reto_canonical_slug( $title ) { return $title; }
function gnf_get_reto_form_id_for_year( $id, $year ) { return $id + 100; }
function get_post( $id ) { return (object) array( 'ID' => $id, 'post_type' => 'wpforms', 'post_content' => $GLOBALS['forms'][ $id ] ); }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function add_option( $key, $value, $deprecated = '', $autoload = null ) { if ( ! empty( $GLOBALS['backup_failure'] ) || isset( $GLOBALS['options'][ $key ] ) ) { return false; } $GLOBALS['options'][ $key ] = $value; return true; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_slash( $value ) { return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value ); }
class WP_Error {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_update_post( $data, $error = false ) {
	$GLOBALS['writes']++;
	if ( ! empty( $GLOBALS['save_failure'] ) ) { return new WP_Error(); }
	$GLOBALS['forms'][ $data['ID'] ] = ! empty( $GLOBALS['saved_corruption'] ) ? '{invalid' : stripslashes( $data['post_content'] );
	return $data['ID'];
}
require ABSPATH . 'includes/impact-metrics.php';
$tests = 0; $fails = 0;
function check_integrity( $ok, $name ) { global $tests, $fails; $tests++; $fails += $ok ? 0 : 1; echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $name . "\n"; }
function reset_integrity( $title = 'huerta' ) {
	$GLOBALS['retos'] = array( (object) array( 'ID' => 1, 'post_title' => $title ) );
	$fields = array(
		5 => array( 'id' => 5, 'type' => 'radio', 'label' => 'Realizaron actividades en la huerta con estudiantes', 'choices' => array( 1 => array( 'label' => 'Si' ) ) ),
		33 => array( 'id' => 33, 'type' => 'file-upload', 'label' => 'Foto del trabajo "terminado"', 'description' => '<a href="https://example.test/guia">Guia</a>', 'conditionals' => array( array( array( 'field' => 5, 'value' => '1' ) ) ) ),
	);
	$GLOBALS['forms'] = array( 101 => json_encode( array( 'fields' => $fields, 'field_id' => 200, 'settings' => array( 'notification_message' => "Primera linea\nSegunda linea", 'path' => 'C:\\docs\\ejemplo' ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	$GLOBALS['options'] = array(); $GLOBALS['writes'] = 0;
	$GLOBALS['save_failure'] = false; $GLOBALS['backup_failure'] = false; $GLOBALS['saved_corruption'] = false;
}
reset_integrity();
$original = $forms[101]; $original_data = json_decode( $original, true );
check_integrity( gnf_ensure_impact_fields_for_year( 2026 ), 'Valid Huerta preparation succeeds' );
$saved = json_decode( $forms[101], true );
check_integrity( is_array( $saved ), 'Huerta remains readable JSON after WordPress unslashes it' );
check_integrity( ( $saved['fields'][33] ?? null ) === $original_data['fields'][33], 'Evidence field keeps its ID, type, quoted label, HTML and logic unchanged' );
check_integrity( ( $saved['settings'] ?? null ) === $original_data['settings'], 'Quoted content, line breaks and backslashes survive persistence' );
check_integrity( ( $saved['field_id'] ?? 0 ) === 200, 'Existing builder counter is never reduced or reused' );
check_integrity( isset( $options['gnf_impact_form_backup_2026_101'] ), 'Original form has a durable pre-write backup' );
$backup = $options['gnf_impact_form_backup_2026_101'] ?? null;
$first = $forms[101];
gnf_ensure_impact_fields_for_year( 2026 );
check_integrity( $first === $forms[101] && 1 === $writes, 'Second preparation does not rewrite or duplicate fields' );
check_integrity( $backup === ( $options['gnf_impact_form_backup_2026_101'] ?? null ), 'Second preparation cannot overwrite the recovery baseline' );
reset_integrity( 'limpiezas' );
$original_field = json_decode( $forms[101], true )['fields'][33];
check_integrity( gnf_ensure_impact_fields_for_year( 2026 ), 'Valid Limpiezas preparation succeeds' );
$saved = json_decode( $forms[101], true );
check_integrity( is_array( $saved ) && ( $saved['fields'][33] ?? null ) === $original_field, 'Limpiezas appends measurements without breaking its evidence field' );
$new_ids = array();
foreach ( (array) ( $saved['fields'] ?? array() ) as $field ) { if ( ! empty( $field['gnf_metric_key'] ) ) { $new_ids[] = $field['id']; } }
check_integrity( $new_ids === array( 200, 201 ) && 202 === ( $saved['field_id'] ?? 0 ), 'New measurement IDs respect the historical builder counter' );
reset_integrity(); $forms[101] = '{invalid'; $options['gnf_impact_fields_schema_2026'] = 1;
check_integrity( ! gnf_ensure_impact_fields_for_year( 2026 ) && 0 === $writes, 'Already-corrupt forms are never replaced with an empty reconstruction' );
check_integrity( ! get_option( 'gnf_impact_fields_schema_2026' ), 'A failed preparation cannot keep the misleading prepared status' );
foreach ( array( 'save_failure', 'backup_failure', 'saved_corruption' ) as $failure ) {
	reset_integrity(); $GLOBALS[$failure] = true;
	check_integrity( ! gnf_ensure_impact_fields_for_year( 2026 ), $failure . ' is reported as failure, not prepared' );
	check_integrity( ! get_option( 'gnf_impact_fields_schema_2026' ), $failure . ' never sets successful schema status' );
	if ( 'backup_failure' === $failure ) { check_integrity( 0 === $writes, 'A failed backup prevents any form mutation' ); }
}
echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
