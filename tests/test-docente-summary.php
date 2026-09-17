<?php
define( 'ABSPATH', __DIR__ . '/../' );
function add_action() {}
function register_rest_route( $ns, $route, $args ) { $GLOBALS['routes'][ $route ] = $args; }
function get_current_user_id() { return 1; }
function get_userdata( $id ) { return (object) array( 'roles' => $GLOBALS['notification_roles'] ?? array( 'docente' ) ); }
function gnf_build_notification_context( $item, $id ) { return array( 'evidenceItems' => array( array( 'estado' => $item->current_status ) ) ); }
require_once __DIR__ . '/../includes/rest-api.php';
require_once __DIR__ . '/../includes/evidence-notifications.php';
require_once __DIR__ . '/../includes/award-rules.php';
if ( file_exists( __DIR__ . '/../includes/docente-summary.php' ) ) {
	require_once __DIR__ . '/../includes/docente-summary.php';
}
$tests = 0; $fails = 0;
function verify_summary( $ok, $message ) {
	global $tests, $fails;
	$tests++; $fails += $ok ? 0 : 1;
	echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $message . "\n";
}
gnf_register_rest_routes();
verify_summary( isset( $GLOBALS['routes']['/admin/impact'], $GLOBALS['routes']['/impact'] ), 'Impact routes registered during REST initialization' );
verify_summary( function_exists( 'gnf_summarize_docente_entries' ), 'Evidence summary available' );
if ( function_exists( 'gnf_summarize_docente_entries' ) ) {
	$entries = array(
		(object) array( 'reto_id' => 1, 'estado' => 'aprobado', 'evidencias' => json_encode( array(
			array( 'ruta' => '/a', 'estado' => 'aprobada', 'puntos' => 0 ),
			array( 'ruta' => '/b', 'estado' => 'rechazada' ),
			array( 'ruta' => '/c' ),
			array( 'ruta' => '/old', 'estado' => 'rechazada', 'replaced' => true ),
		) ) ),
		(object) array( 'reto_id' => 2, 'estado' => 'en_progreso', 'evidencias' => '[]' ),
		(object) array( 'reto_id' => 3, 'estado' => 'aprobado', 'evidencias' => '[{"ruta":"/excluded","estado":"aprobada"}]' ),
	);
	$result = gnf_summarize_docente_entries( $entries, array( 1, 2 ) );
	verify_summary( array( 'pending' => 1, 'approved' => 1, 'rejected' => 1, 'total' => 3 ) === $result['evidenceCounts'], 'Counts individual active evidence, including zero points; excludes replaced and unselected retos' );
	verify_summary( 1 === $result['aprobados'] && 1 === $result['en_progreso'], 'Counts singular entry states once' );
	verify_summary( ! $result['allComplete'], 'Partial selection is not complete' );
	verify_summary( ! gnf_summarize_docente_entries( array(), array() )['allComplete'], 'Empty selection is not final' );
	$entries[0]->estado = 'en_progreso';
	$entries[0]->evidencias = '[{"ruta":"/a","estado":"aprobada"}]';
	verify_summary( gnf_summarize_docente_entries( $entries, array( 1 ) )['allComplete'], 'Individual evidence approval completes review even if legacy entry state remains in progress' );
}
verify_summary( function_exists( 'gnf_required_evidence_field_ids' ), 'Requirement selector available' );
if ( function_exists( 'gnf_required_evidence_field_ids' ) ) {
	$fields = array( array( 'id' => 0, 'type' => 'file-upload', 'label' => 'REQUISITO - Foto' ), array( 'id' => 8, 'type' => 'radio', 'label' => 'Esta accion es requisito' ), array( 'id' => 9, 'type' => 'file-upload', 'label' => 'Foto opcional' ) );
	verify_summary( array( 0 ) === gnf_required_evidence_field_ids( 'agua', $fields ), 'Highlights only requirement upload fields, including ID zero' );
	verify_summary( array() === gnf_required_evidence_field_ids( 'huerta', $fields ), 'Requirement styling limited to three base retos' );
}
verify_summary( function_exists( 'gnf_docente_notification_is_actionable' ), 'Docente notification policy available' );
if ( function_exists( 'gnf_docente_notification_is_actionable' ) ) {
	verify_summary( gnf_docente_notification_is_actionable( 'evidencia_rechazada', array( array( 'estado' => 'rechazada' ) ) ), 'Current rejection is shown' );
	verify_summary( ! gnf_docente_notification_is_actionable( 'evidencia_aprobada', array( array( 'estado' => 'aprobada' ) ) ), 'Approval does not notify docente' );
	verify_summary( ! gnf_docente_notification_is_actionable( 'evidencia_rechazada', array( array( 'estado' => 'aprobada' ) ) ), 'Reversed rejection is hidden' );
	verify_summary( ! gnf_docente_notification_is_actionable( 'evidencia_rechazada', array() ), 'Removed rejection is hidden' );
}
verify_summary( function_exists( 'gnf_award_result_fingerprint' ), 'Assignment change detection available' );
if ( function_exists( 'gnf_award_required_fields_met' ) ) {
	$fields = array(
		array( 'id' => 0, 'type' => 'file-upload', 'label' => 'REQUISITO - actividad' ),
		array( 'id' => 1, 'type' => 'file-upload', 'label' => 'REQUISITO - recurso educativo' ),
		array( 'id' => 2, 'type' => 'file-upload', 'label' => 'REQUISITO - planeamiento (si lo tienen)' ),
	);
	$evidences = array( array( 'field_id' => 0, 'estado' => 'aprobada' ), array( 'field_id' => 1, 'estado' => 'pendiente' ) );
	verify_summary( ! gnf_award_required_fields_met( $fields, $evidences, 'validated' ), 'Pending required evidence cannot earn a validated award' );
	verify_summary( gnf_award_required_fields_met( $fields, $evidences, 'projected' ), 'Pending evidence counts for projection; optional planning does not block' );
	$evidences[1]['estado'] = 'aprobada';
	verify_summary( gnf_award_required_fields_met( $fields, $evidences, 'validated' ), 'All mandatory fields approved qualifies' );
	$evidences[1]['replaced'] = true;
	verify_summary( ! gnf_award_required_fields_met( $fields, $evidences, 'validated' ), 'Replaced mandatory evidence cannot qualify' );
	verify_summary( ! gnf_award_required_fields_met( array(), $evidences, 'validated' ), 'Unknown requirements do not silently qualify' );
}
if ( function_exists( 'gnf_award_result_fingerprint' ) ) {
	$a = array( 'stars' => 3, 'score' => 120, 'generatedAt' => 'old' );
	$b = array_merge( $a, array( 'generatedAt' => 'new' ) );
	verify_summary( gnf_award_result_fingerprint( $a ) === gnf_award_result_fingerprint( $b ), 'Recalculation timestamp does not revoke assignment' );
	$b['stars'] = 2;
	verify_summary( gnf_award_result_fingerprint( $a ) !== gnf_award_result_fingerprint( $b ), 'Changed result invalidates assignment' );
}
class SummaryNotificationDatabase {
	public $prefix = 'wp_'; public $queries = array();
	function prepare( $sql, ...$args ) { return array( $sql, $args ); }
	function get_results( $query ) {
		$this->queries[] = $query;
		$offset = $query[1][1];
		$items = array();
		for ( $i = 1; $i <= ( $offset === 0 ? 50 : 2 ); $i++ ) {
			$items[] = (object) array( 'id' => $offset + $i, 'user_id' => 1, 'tipo' => 'evidencia_rechazada', 'mensaje' => 'Rechazada', 'relacion_tipo' => 'reto_entry_evidence:' . str_repeat( 'a', 64 ), 'relacion_id' => 1, 'leido' => 0, 'created_at' => '2026-09-16', 'current_status' => $offset === 0 ? 'aprobada' : 'rechazada' );
		}
		return $items;
	}
}
$wpdb = new SummaryNotificationDatabase();
$notifications = gnf_rest_notifications_list();
verify_summary( count( $notifications ) === 1 && $notifications[0]['id'] === 51, 'Resolved recent notifications do not hide older actionable rejections; duplicates collapsed' );
verify_summary( count( $wpdb->queries ) === 2, 'Notifications fetched in bounded batches' );
$notification_roles = array( 'supervisor' );
$notifications = gnf_rest_notifications_list();
verify_summary( count( $notifications ) === 50, 'Reviewer notification history remains available' );
$notification_roles = array( 'docente', 'administrator' );
verify_summary( ! gnf_user_receives_only_rejections( 1 ), 'Admin with multiple roles retains operational notifications' );
echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
