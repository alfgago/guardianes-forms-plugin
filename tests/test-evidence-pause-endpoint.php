<?php
define( 'ABSPATH', __DIR__ . '/../' );
require_once ABSPATH . 'includes/evidence-review.php';
$scoring = file_get_contents( ABSPATH . 'includes/puntajes.php' );
foreach ( array( 'gnf_calcular_puntaje_por_campos', 'gnf_recalcular_puntaje_reto', 'gnf_refresh_reto_entry_score' ) as $function ) {
	$start = strpos( $scoring, 'function ' . $function . '(' );
	$end = strpos( $scoring, "\n}", $start ) + 2;
	eval( substr( $scoring, $start, $end - $start ) );
}
$source = file_get_contents( ABSPATH . 'includes/rest-api.php' );
$start = strpos( $source, 'function gnf_rest_supervisor_review_evidence(' );
$end = strpos( $source, "\n}", $start ) + 2;
eval( substr( $source, $start, $end - $start ) );
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
function sanitize_text_field( $s ) { return strip_tags( $s ); }
function sanitize_textarea_field( $s ) { return strip_tags( $s ); }
function sanitize_key( $s ) { return $s; }
function current_time( $format ) { return '2026-09-22 12:00:00'; }
function get_current_user_id() { return 7; }
function current_user_can( $cap ) { return false; }
function gnf_user_can_access_centro( $user, $center ) { return $GLOBALS['access']; }
function get_userdata( $id ) { return (object) array( 'display_name' => 'Revisor' ); }
function gnf_enrich_evidencias( $items, $reto, $year ) { return $items; }
function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
function gnf_get_reto_field_points( $reto, $year ) { return array( 1 => array( 'tipo' => 'file-upload', 'puntos' => 10 ) ); }
function gnf_recalcular_puntaje_centro( $center, $year ) {}
function gnf_clear_supervisor_cache() {}
function get_post( $id ) { return (object) array( 'post_title' => 'Eco Mural' ); }
function gnf_insert_notification( ...$args ) { $GLOBALS['notifications'][] = $args; }
function gnf_log_audit_event( $event, $data ) { $GLOBALS['audit'][] = $event; }
function gnf_get_reto_entry_computed_status( $entry ) { return array(); }
class PauseDatabase {
	public $prefix = 'wp_'; public $row; public $writes = 0;
	function prepare( $sql, ...$args ) { return $sql; }
	function get_row( $sql ) { return clone $this->row; }
	function update( $table, $values, $where, ...$formats ) {
		$this->writes++;
		foreach ( $values as $key => $value ) { $this->row->$key = $value; }
		return 1;
	}
}
$wpdb = new PauseDatabase();
$wpdb->row = (object) array( 'id' => 1, 'reto_id' => 1, 'anio' => 2026, 'centro_id' => 1, 'user_id' => 2, 'data' => '{}', 'puntaje' => 10, 'evidencias' => '[{"field_id":1,"nombre":"mural.jpg","estado":"pendiente","puntos":10}]' );
$access = true; $notifications = array(); $audit = array(); $tests = 0; $fails = 0;
function check_pause_endpoint( $ok, $message ) {
	global $tests, $fails; $tests++; $fails += $ok ? 0 : 1;
	echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $message . "\n";
}
function review_pause( $action, $reason = '', $comment = '' ) {
	return gnf_rest_supervisor_review_evidence( new WP_REST_Request( array( 'entry_id' => 1, 'evidence_index' => 0, 'action' => $action, 'reviewReason' => $reason, 'comment' => $comment ) ) );
}
foreach ( array( '', 'no_corresponde', 'arbitrary' ) as $reason ) {
	$result = review_pause( 'pausar', $reason );
	check_pause_endpoint( $result instanceof WP_Error && 'missing_review_reason' === $result->code && 0 === $wpdb->writes, 'causa invalida no escribe' );
}
$access = false;
$result = review_pause( 'pausar', 'reto_inconcluso' );
check_pause_endpoint( $result instanceof WP_Error && 'forbidden' === $result->code && 0 === $wpdb->writes, 'no permite pausar centros sin acceso' );
$access = true;
foreach ( array( 'pendiente', 'aprobada', 'rechazada', 'en_pausa' ) as $previous ) {
	$wpdb->row->evidencias = json_encode( array( array( 'field_id' => 1, 'puntos' => 10, 'nombre' => 'mural.jpg', 'estado' => $previous ) ) );
	$result = review_pause( 'pausar', 'reto_inconcluso', 'Falta el mural completo' );
	check_pause_endpoint( is_array( $result ) && 'en_pausa' === $result['evidence']['estado'] && 0 === $result['entry_puntaje'], "{$previous} pasa a pausa sin puntos" );
}
check_pause_endpoint( 'Falta el mural completo' === $result['evidence']['supervisor_comment'] && 7 === $result['evidence']['reviewed_by'] && in_array( 'supervisor_pause_evidence', $audit, true ), 'conserva nota, revisor y auditoria' );
check_pause_endpoint( 'evidencia_en_pausa' === end( $notifications )[1], 'no genera notificacion de rechazo al pausar' );
$result = review_pause( 'pausar', 'no_concluyente', 'Nota editada' );
check_pause_endpoint( 'Nota editada' === $result['evidence']['supervisor_comment'] && 'en_pausa' === $result['evidence']['estado'], 'editar nota no aprueba accidentalmente' );
$result = review_pause( 'aprobar', '', 'Ahora completo' );
check_pause_endpoint( 'aprobada' === $result['evidence']['estado'] && 10 === $result['entry_puntaje'] && null === $result['evidence']['review_reason'], 'aprobar restaura puntos y limpia motivo' );
review_pause( 'pausar', 'subir_requisito' );
$result = review_pause( 'rechazar', 'no_corresponde' );
check_pause_endpoint( 'rechazada' === $result['evidence']['estado'] && 0 === $result['entry_puntaje'], 'pausa puede rechazarse' );
echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
