<?php
class WP_REST_Request {
	private $params;
	function __construct( $params ) { $this->params = $params; }
	function get_param( $key ) { return $this->params[$key] ?? null; }
}
class WP_Error { public $code; function __construct( $code, ...$args ) { $this->code = $code; } }
function gnf_rest_get_active_panel_year() { return 2026; }
function get_current_user_id() { return 1; }
function gnf_get_centro_for_docente( $id ) { return 10; }
function get_post( $id ) { return (object) array( 'ID' => $id, 'post_type' => 'reto' ); }
function gnf_get_reto_form_id_for_year( ...$args ) { return 101; }
function gnf_get_wpforms_form_definition( $id ) { return $GLOBALS['definition']; }
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return $value; }
function sanitize_text_field( $value ) { return $value; }
function gnf_normalize_fields( $fields ) { return $fields; }
function gnf_store_reto_entry( ...$args ) { $GLOBALS['writes']++; }
function gnf_rest_get_docente_entry_row( ...$args ) { return (object) array( 'id' => 1 ); }
function gnf_rest_build_saved_form_state( ...$args ) { return array( 'entry' => array( 'id' => 1 ), 'savedAt' => '2026-10-08' ); }
function gnf_log_audit_event( ...$args ) {}
$source = file_get_contents( dirname( __DIR__ ) . '/includes/rest-api.php' );
$start = strpos( $source, 'function gnf_rest_docente_autosave_reto(' );
eval( substr( $source, $start, strpos( $source, "\n}", $start ) + 2 - $start ) );
$definition = array(); $writes = 0; $tests = 0; $fails = 0;
$params = array( 'reto_id' => 1, 'formId' => 101, 'fields' => array( 3 => array( 'type' => 'text', 'value' => 'Respuesta' ) ) );
function check_autosave_guard( $ok, $label ) { global $tests, $fails; $tests++; $fails += $ok ? 0 : 1; echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $label . "\n"; }
$result = gnf_rest_docente_autosave_reto( new WP_REST_Request( $params ) );
check_autosave_guard( $result instanceof WP_Error && 'form_unavailable' === $result->code && 0 === $writes, 'A corrupt form cannot overwrite stored progress even from an old browser tab' );
$definition = array( 'fields' => array( 3 => array( 'id' => 3, 'type' => 'text' ) ) ); $writes = 0;
$result = gnf_rest_docente_autosave_reto( new WP_REST_Request( array_replace( $params, array( 'formId' => 999 ) ) ) );
check_autosave_guard( $result instanceof WP_Error && 'form_changed' === $result->code && 0 === $writes, 'A browser with a different annual form must reload before saving' );
$writes = 0; $result = gnf_rest_docente_autosave_reto( new WP_REST_Request( $params ) );
check_autosave_guard( is_array( $result ) && ! empty( $result['success'] ) && 1 === $writes, 'A restored matching form saves normally' );
echo "{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
