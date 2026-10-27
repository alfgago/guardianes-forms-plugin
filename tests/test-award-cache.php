<?php
/** Offline behavioral coverage for award calculation reuse, not REST authorization. */
define( 'ABSPATH', __DIR__ . '/../' );
define( 'HOUR_IN_SECONDS', 3600 );
$without_revisions = in_array( '--without-revisions', $argv, true );
$tests = 0;
$fails = 0;
$meta = array();
$options = array();
$transients = array();
$clock = 100000;
$calculations = 0;
$meta_writes = 0;
$point_reads = 0;
$profile = array( 'tipologia' => 'tipo_iv', 'tipo_centro_educativo' => 'direccion_i' );
$points = 60;
$required_field = 1;
$admin = true;
$audit = array();
$during_calculation = null;

function check_award_cache( $ok, $message ) {
	global $tests, $fails;
	$tests++;
	$fails += $ok ? 0 : 1;
	echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $message . "\n";
}
function absint( $value ) { return abs( (int) $value ); }
function sanitize_key( $value ) { return $value; }
function gnf_normalize_year( $year ) { return (int) $year; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; }
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function get_transient( $key ) {
	$cached = $GLOBALS['transients'][ $key ] ?? null;
	return $cached && $cached['expires'] > $GLOBALS['clock'] ? $cached['value'] : false;
}
function set_transient( $key, $value, $ttl ) {
	$GLOBALS['transients'][ $key ] = array( 'value' => $value, 'expires' => $GLOBALS['clock'] + $ttl );
	return true;
}
function get_field( $key, $id ) { return $GLOBALS['profile'][ $key ] ?? ''; }
function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function update_post_meta( $id, $key, $value ) {
	$GLOBALS['meta_writes']++;
	$GLOBALS['meta'][ $id ][ $key ] = $value;
	return true;
}
function delete_post_meta( $id, $key ) { unset( $GLOBALS['meta'][ $id ][ $key ] ); return true; }
function current_time( $format ) {
	$GLOBALS['calculations']++;
	if ( $GLOBALS['during_calculation'] ) {
		$callback = $GLOBALS['during_calculation'];
		$GLOBALS['during_calculation'] = null;
		$callback();
	}
	return gmdate( 'Y-m-d H:i:s', $GLOBALS['clock'] );
}
function get_the_title( $id ) { return array( 11 => 'agua', 12 => 'electricidad', 13 => 'residuos' )[ $id ] ?? ''; }
function gnf_get_reto_canonical_slug( $title ) { return $title; }
function gnf_get_reto_form_id_for_year( $id, $year ) { return $id; }
function gnf_get_wpforms_form_definition( $id ) {
	return array( 'fields' => array( array( 'id' => $GLOBALS['required_field'], 'type' => 'file-upload', 'label' => 'REQUISITO - actividad' ) ) );
}
function gnf_get_reto_field_points( $id, $year ) {
	$GLOBALS['point_reads']++;
	return array( 1 => array( 'puntos' => $GLOBALS['points'], 'tipo' => 'file-upload' ) );
}
function gnf_enrich_evidencias( $evidences, $id, $year ) { return $evidences; }
function current_user_can( $cap ) { return $GLOBALS['admin']; }
function get_current_user_id() { return 7; }
function get_post_type( $id ) { return 'centro_educativo'; }
function gnf_log_audit_event( $event, $data ) { $GLOBALS['audit'][] = $event; }
class WP_REST_Request {
	private $params;
	function __construct( $params ) { $this->params = $params; }
	function get_param( $key ) { return $this->params[ $key ] ?? null; }
}
class WP_Error {
	public $code;
	function __construct( $code, $message, $data ) { $this->code = $code; }
}
class AwardCacheDatabase {
	public $prefix = 'wp_';
	public $entries = array();
	public $matricula = '';
	public $last_error = '';
	public $fail_entries = false;
	function prepare( $sql, ...$args ) { return vsprintf( str_replace( '%d', '%d', $sql ), $args ); }
	function get_results( $sql ) {
		$this->last_error = $this->fail_entries ? 'read failed' : '';
		if ( $this->fail_entries ) { return null; }
		preg_match( '/centro_id = (\d+) AND anio = (\d+)/', $sql, $match );
		return array_values( array_map( static function ( $entry ) { return clone $entry; }, array_filter(
			$this->entries,
			static function ( $entry ) use ( $match ) { return (int) $match[1] === $entry->centro_id && (int) $match[2] === $entry->anio; }
		) ) );
	}
	function get_var( $sql ) { return $this->matricula; }
}
$wpdb = new AwardCacheDatabase();
foreach ( array( 1, 2 ) as $center ) {
	foreach ( array( 2026, 2027 ) as $year ) {
		foreach ( array( 11, 12, 13 ) as $id ) {
			$wpdb->entries[] = (object) array(
				'id' => count( $wpdb->entries ) + 1, 'centro_id' => $center, 'reto_id' => $id,
				'anio' => $year, 'puntaje' => 60, 'data' => '{}',
				'evidencias' => '[{"field_id":1,"estado":"aprobada"}]', 'updated_at' => '2026-09-16 12:00:00',
			);
		}
	}
}
if ( ! $without_revisions ) { require_once ABSPATH . 'includes/panel-cache.php'; }
require_once ABSPATH . 'includes/award-rules.php';

$first = gnf_get_center_award_result( 1, 2026, 'validated' );
check_award_cache( 180 === $first['score'] && 5 === $first['stars'], 'Validated score comes from approved evidence and the small-center rubric' );
$before = $calculations;
$reads_before = $point_reads;
$writes_before = $meta_writes;
gnf_get_center_award_result( '1', '2026', 'validated' );
if ( $without_revisions ) {
	check_award_cache( $calculations > $before && ! $transients, 'No revision provider means no persistent or request-local result reuse' );
	$wpdb->entries[0]->evidencias = '[{"field_id":1,"estado":"rechazada"}]';
	$changed = gnf_get_center_award_result( 1, 2026, 'validated' );
	check_award_cache( 120 === $changed['score'] && 0 === $changed['stars'], 'Unversioned edits stay live without caching infrastructure' );
	echo "\n{$tests} checks, {$fails} failures\n";
	exit( $fails ? 1 : 0 );
}
check_award_cache( $before === $calculations && $reads_before === $point_reads && $writes_before === $meta_writes, 'An unchanged repeated calculation reuses the result without rescoring or metadata writes' );
check_award_cache( count( $transients ) > 0, 'Calculation reuse is stored server-side, not only in a request static' );
$clock += 7199;
$before = $calculations;
gnf_get_center_award_result( 1, 2026, 'validated' );
check_award_cache( $before === $calculations, 'The server cache remains reusable before two hours' );
$clock++;
gnf_get_center_award_result( 1, 2026, 'validated' );
check_award_cache( $before + 1 === $calculations, 'The server cache expires at two hours without sliding on reads' );

$before = $calculations;
gnf_get_center_award_result( 2, 2026, 'validated' );
gnf_get_center_award_result( 1, 2027, 'validated' );
$projected = gnf_get_center_award_result( 1, 2026, 'anything-else' );
check_award_cache( $before + 3 === $calculations && 'projected' === $projected['mode'], 'Center, year and normalized mode have separate cache entries' );
$before = $calculations;
gnf_get_center_award_result( 1, 2026, 'projected' );
check_award_cache( $before === $calculations, 'All non-validated modes share the normalized projected key' );

$before = $calculations;
gnf_bump_panel_cache_version( 'center_2' );
gnf_get_center_award_result( 1, 2026, 'validated' );
check_award_cache( $before === $calculations, 'An unrelated center edit leaves this calculation reusable' );
gnf_invalidate_docente_panel_cache( 1 );
gnf_get_center_award_result( 1, 2026, 'validated' );
check_award_cache( $before + 1 === $calculations, 'The shared center revision invalidates calculation reuse' );
$before = $calculations;
gnf_bump_panel_cache_version( 'config' );
gnf_get_center_award_result( 1, 2026, 'validated' );
check_award_cache( $before + 1 === $calculations, 'The shared configuration revision invalidates calculation reuse' );

// Custom-table writes can precede the shared revision bump, even in one second.
foreach ( array( 'pendiente', 'en_pausa', 'rechazada', 'aprobada' ) as $state ) {
	$wpdb->entries[0]->evidencias = json_encode( array( array( 'field_id' => 1, 'estado' => $state ) ) );
	$validated = gnf_get_center_award_result( 1, 2026, 'validated' );
	$projected = gnf_get_center_award_result( 1, 2026, 'projected' );
	check_award_cache( ( 'aprobada' === $state ? 180 : 120 ) === $validated['score'], "Same-second {$state} evidence changes validated scoring before invalidation hooks" );
	check_award_cache( ( in_array( $state, array( 'pendiente', 'aprobada' ), true ) ? 5 : 0 ) === $projected['stars'], "Same-second {$state} evidence changes projected eligibility" );
}
$wpdb->entries[0]->evidencias = '[{"field_id":1,"estado":"aprobada","replaced":true}]';
$replaced = gnf_get_center_award_result( 1, 2026, 'validated' );
check_award_cache( 120 === $replaced['score'] && ! $replaced['baseEligible'], 'Replacing approved evidence invalidates cached scoring and base requirements' );
$wpdb->entries[0]->evidencias = '[{"field_id":1,"estado":"aprobada"}]';
$wpdb->entries[0]->puntaje = 30;
check_award_cache( 150 === gnf_get_center_award_result( 1, 2026 )['score'], 'Persisted projected point changes do not reuse an old total' );
$wpdb->entries[0]->puntaje = 60;

$profile = array( 'tipologia' => 'tipo_i', 'tipo_centro_educativo' => 'direccion_iii' );
$general = gnf_get_center_award_result( 1, 2026, 'validated' );
check_award_cache( 'general' === $general['rubricKey'] && 3 === $general['stars'], 'Profile rubric edits invalidate reuse without depending on delayed meta hooks' );
$profile = array();
$wpdb->matricula = '{"centro-tipologia":"tipo_iv","centro-tipo-centro-educativo":"direccion_i"}';
check_award_cache( 5 === gnf_get_center_award_result( 1, 2026, 'validated' )['stars'], 'Missing profile fields use the latest matricula fallback' );
$wpdb->matricula = '{"centro-tipologia":"tipo_i","centro-tipo-centro-educativo":"direccion_iii"}';
check_award_cache( 3 === gnf_get_center_award_result( 1, 2026, 'validated' )['stars'], 'A same-second matricula edit changes the cached rubric immediately' );
$profile = array( 'tipologia' => 'tipo_iv', 'tipo_centro_educativo' => 'direccion_i' );
$points = 30;
gnf_bump_panel_cache_version( 'config' );
check_award_cache( 90 === gnf_get_center_award_result( 1, 2026, 'validated' )['score'], 'Rubric point edits recalculate validated totals using live configuration' );
$required_field = 2;
gnf_bump_panel_cache_version( 'config' );
check_award_cache( ! gnf_get_center_award_result( 1, 2026, 'validated' )['baseEligible'], 'Form requirement edits do not reuse the previous eligible result' );
$required_field = 1;
$points = 60;
gnf_bump_panel_cache_version( 'config' );

// An old cache schema must not supply a computed award, even at a current key.
gnf_get_center_award_result( 1, 2026, 'validated' );
foreach ( $transients as &$cached ) {
	if ( isset( $cached['value']['schema'] ) ) { $cached['value']['schema'] = -1; }
}
unset( $cached );
$before = $calculations;
gnf_get_center_award_result( 1, 2026, 'validated' );
check_award_cache( $before + 1 === $calculations, 'A cached envelope from an unknown schema is ignored' );

$before_count = count( $transients );
gnf_bump_panel_cache_version( 'config' );
$during_calculation = static function () { gnf_bump_panel_cache_version( 'config' ); };
gnf_get_center_award_result( 1, 2026, 'validated' );
check_award_cache( $before_count === count( $transients ), 'A configuration edit during calculation cannot publish a cache entry' );

$request = new WP_REST_Request( array( 'id' => 1, 'year' => 2026, 'action' => 'assign' ) );
check_award_cache( ! gnf_get_assigned_center_award( 1, 2026 ), 'A warm computed cache alone never publishes an assigned award' );
$assigned = gnf_rest_admin_assign_award( $request );
check_award_cache( is_array( $assigned ) && 5 === $assigned['assignedAward']['result']['stars'], 'Explicit admin assignment still uses validated evidence' );
$before = $calculations;
$bundle = gnf_get_center_award_bundle( 1, 2026 );
check_award_cache( ! empty( $bundle['assigned'] ) && $calculations <= $before + 1, 'Bundle and assignment verification reuse the same validated calculation' );
$wpdb->entries[0]->evidencias = '[{"field_id":1,"estado":"rechazada"}]';
check_award_cache( ! gnf_get_assigned_center_award( 1, 2026 ) && ! isset( $meta[1]['_gnf_award_assigned_2026'] ), 'Evidence rejection invalidates an assignment despite a previously warm cache' );
check_award_cache( in_array( 'award_assignment_invalidated', $audit, true ), 'Assignment fingerprint invalidation is audited' );
$wpdb->entries[0]->evidencias = '[{"field_id":1,"estado":"aprobada"}]';
gnf_rest_admin_assign_award( $request );
$profile['tipologia'] = 'tipo_i';
check_award_cache( ! gnf_get_assigned_center_award( 1, 2026 ), 'Profile changes invalidate an assigned fingerprint rather than publishing a lower award' );
$profile['tipologia'] = 'tipo_iv';
gnf_rest_admin_assign_award( $request );
$admin = false;
$denied = gnf_rest_admin_assign_award( $request );
check_award_cache( $denied instanceof WP_Error && 'forbidden' === $denied->code, 'A warm calculation never bypasses admin assignment authorization' );
$admin = true;
gnf_rest_admin_assign_award( new WP_REST_Request( array( 'id' => 1, 'year' => 2026, 'action' => 'revoke' ) ) );
check_award_cache( ! gnf_get_assigned_center_award( 1, 2026 ), 'Revocation stays live even when the calculation cache remains warm' );

gnf_bump_panel_cache_version( 'config' );
$wpdb->fail_entries = true;
$before_count = count( $transients );
gnf_get_center_award_result( 1, 2026, 'validated' );
check_award_cache( $before_count === count( $transients ), 'An entry read failure cannot be cached as an empty center' );
$wpdb->fail_entries = false;
check_award_cache( 180 === gnf_get_center_award_result( 1, 2026, 'validated' )['score'], 'A recovered read calculates the real score immediately' );
echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
