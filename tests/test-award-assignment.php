<?php
define( 'ABSPATH', __DIR__ . '/../' );
require_once __DIR__ . '/../includes/award-rules.php';
$meta = array(); $admin = true; $audit = array();
class WP_REST_Request {
	private $params;
	function __construct( $params ) { $this->params = $params; }
	function get_param( $key ) { return $this->params[ $key ] ?? null; }
}
class WP_Error {
	public $code;
	function __construct( $code, $message, $data ) { $this->code = $code; }
}
function absint( $n ) { return abs( (int) $n ); }
function sanitize_key( $key ) { return $key; }
function current_user_can( $cap ) { return $GLOBALS['admin']; }
function get_current_user_id() { return 7; }
function gnf_normalize_year( $year ) { return (int) $year; }
function get_post_type( $id ) { return $id === 1 ? 'centro_educativo' : 'post'; }
function current_time( $format ) { return '2026-09-16 12:00:00'; }
function get_field( $key, $id ) { return $key === 'tipologia' ? 'tipo_iv' : 'direccion_i'; }
function get_post_meta( $id, $key, $single ) { return $GLOBALS['meta'][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $key ] = $value; return true; }
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $key ] ); return true; }
function gnf_log_audit_event( $key, $data ) { $GLOBALS['audit'][] = $key; }
function get_the_title( $id ) { return array( 1 => 'agua', 2 => 'electricidad', 3 => 'residuos' )[ $id ]; }
function gnf_get_reto_canonical_slug( $title ) { return $title; }
function gnf_get_reto_form_id_for_year( $id, $year ) { return $id; }
function gnf_get_wpforms_form_definition( $id ) { return array( 'fields' => array( array( 'id' => 1, 'type' => 'file-upload', 'label' => 'REQUISITO - evidencia de actividad' ) ) ); }
function gnf_get_reto_field_points( $id, $year ) { return array( 1 => array( 'puntos' => 60, 'tipo' => 'file-upload' ) ); }
function gnf_enrich_evidencias( $evidences, $id, $year ) { return $evidences; }
class AwardTestDatabase {
	public $prefix = 'wp_'; public $entries = array();
	function prepare( $sql, ...$args ) { return $sql; }
	function get_results( $sql ) { return $this->entries; }
}
$wpdb = new AwardTestDatabase();
for ( $id = 1; $id <= 3; $id++ ) {
	$wpdb->entries[] = (object) array( 'reto_id' => $id, 'anio' => 2026, 'puntaje' => 60, 'data' => '{}', 'evidencias' => '[{"field_id":1,"estado":"aprobada"}]' );
}
$tests = 0; $fails = 0;
function check_assignment( $ok, $message ) {
	global $tests, $fails; $tests++; $fails += $ok ? 0 : 1;
	echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $message . "\n";
}
$request = new WP_REST_Request( array( 'id' => 1, 'year' => 2026, 'action' => 'assign' ) );
$admin = false;
$result = gnf_rest_admin_assign_award( $request );
check_assignment( $result instanceof WP_Error && 'forbidden' === $result->code && ! $meta, 'Non-admin cannot assign or write metadata' );
$admin = true;
$result = gnf_rest_admin_assign_award( new WP_REST_Request( array( 'id' => 1, 'year' => 2027, 'action' => 'assign' ) ) );
check_assignment( $result instanceof WP_Error && 'unsupported_rubric' === $result->code, 'Unknown annual rubric cannot be assigned' );
check_assignment( ! gnf_get_assigned_center_award( 1, 2026 ), 'Calculated award alone is not published' );
$result = gnf_rest_admin_assign_award( $request );
check_assignment( is_array( $result ) && $result['assignedAward']['result']['stars'] === 5, 'Assignment uses validated small-center rubric' );
check_assignment( isset( $meta['_gnf_award_assigned_2026'] ) && in_array( 'admin_assign_award', $audit, true ), 'Assignment persisted per year with audit' );
check_assignment( gnf_get_assigned_center_award( 1, 2026 )['result']['stars'] === 5, 'Published result can be retrieved' );
$wpdb->entries[0]->evidencias = '[{"field_id":1,"estado":"rechazada"}]';
check_assignment( ! gnf_get_assigned_center_award( 1, 2026 ) && ! isset( $meta['_gnf_award_assigned_2026'] ), 'Rejection invalidates previous award instead of silently publishing a lower one' );
check_assignment( in_array( 'award_assignment_invalidated', $audit, true ), 'Invalidation audited' );
$result = gnf_rest_admin_assign_award( $request );
check_assignment( $result instanceof WP_Error && 'award_not_eligible' === $result->code, 'Missing approved base requirement blocks assignment' );
$wpdb->entries[0]->evidencias = '[{"field_id":1,"estado":"aprobada"}]';
gnf_rest_admin_assign_award( $request );
gnf_rest_admin_assign_award( new WP_REST_Request( array( 'id' => 1, 'year' => 2026, 'action' => 'revoke' ) ) );
check_assignment( ! gnf_get_assigned_center_award( 1, 2026 ) && in_array( 'admin_revoke_award', $audit, true ), 'Admin can revoke publication without changing points' );
echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
