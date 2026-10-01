<?php
define( 'ABSPATH', __DIR__ . '/../' );
function get_userdata( $id ) { return (object) array( 'roles' => array( 'docente' ) ); }
function get_user_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $key ] ?? ''; }
function update_user_meta( $id, $key, $value ) { $GLOBALS['meta'][ $key ] = $value; }
function absint( $n ) { return abs( (int) $n ); }
function current_time( $type ) { return '2026-10-01 12:00:00'; }
function get_the_title( $id ) { return 'Eco Mural'; }
require ABSPATH . 'includes/evidence-notifications.php';
require ABSPATH . 'includes/evidence-review.php';
$source = file_get_contents( ABSPATH . 'includes/helpers.php' );
foreach ( array( 'gnf_insert_notification', 'gnf_insert_or_refresh_notification' ) as $name ) {
	$start = strpos( $source, 'function ' . $name . '(' );
	if ( false === $start ) { $start = strpos( $source, 'function ' . $name . ' (' ); }
	$end = strpos( $source, "\n}", $start ) + 2;
	eval( substr( $source, $start, $end - $start ) );
}
class PauseNotificationDatabase {
	public $prefix = 'wp_'; public $entries = array(); public $notifications = array(); public $insert_id = 0; public $fail = false; public $reads = 0;
	function prepare( $sql, ...$args ) { return array( $sql, $args ); }
	function get_results( $query ) {
		$this->reads++;
		return array_slice( array_values( array_filter( $this->entries, function ( $entry ) use ( $query ) { return $entry->id > $query[1][1]; } ) ), 0, 200 );
	}
	function get_var( $query ) {
		foreach ( $this->notifications as $id => $n ) {
			if ( array( $n['user_id'], $n['tipo'], $n['relacion_tipo'], $n['relacion_id'] ) === $query[1] ) { return $id; }
		}
		return null;
	}
	function get_row( $query ) { return null; }
	function insert( $table, $data, $formats ) {
		if ( $this->fail ) { return false; }
		$this->notifications[ ++$this->insert_id ] = $data;
		return 1;
	}
}
$tests = 0; $fails = 0; $meta = array(); $wpdb = new PauseNotificationDatabase();
function check_pause_notification( $ok, $message ) {
	global $tests, $fails; $tests++; $fails += $ok ? 0 : 1;
	echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $message . "\n";
}
gnf_insert_notification( 1, 'evidencia_en_pausa', 'Pausa', 'reto_entry', 4 );
check_pause_notification( 1 === count( $wpdb->notifications ), 'Pause inserts are no longer discarded for teachers' );
gnf_insert_notification( 1, 'evidencia_aprobada', 'Aprobada', 'reto_entry', 4 );
check_pause_notification( 1 === count( $wpdb->notifications ), 'Approvals remain suppressed for teachers' );
check_pause_notification( 2 === gnf_insert_or_refresh_notification( 1, 'evidencia_en_pausa', 'Pausa', 'reto_entry', 5 ), 'Refresh helper accepts pause notifications too' );
$wpdb = new PauseNotificationDatabase();
$paused = array( 'field_id' => 3, 'nombre' => 'mural.jpg', 'ruta' => '/mural.jpg', 'estado' => 'en_pausa', 'review_reason' => 'reto_inconcluso', 'supervisor_comment' => 'Falta el mural terminado', 'reviewed_at' => '' );
$wpdb->entries = array( (object) array( 'id' => 4, 'reto_id' => 2, 'evidencias' => json_encode( array( $paused, array_merge( $paused, array( 'replaced' => true ) ), array_merge( $paused, array( 'estado' => 'aprobada' ) ) ) ) ) );
gnf_backfill_docente_pause_notifications( 1 );
$n = reset( $wpdb->notifications );
check_pause_notification( 1 === count( $wpdb->notifications ), 'Backfill recovers only current active paused evidence' );
check_pause_notification( 'evidencia_en_pausa' === $n['tipo'] && 0 === $n['leido'] && '2026-10-01 12:00:00' === $n['created_at'], 'Recovered pause is unread with a valid timestamp' );
check_pause_notification( false !== strpos( $n['mensaje'], 'Reto inconcluso' ) && false !== strpos( $n['mensaje'], 'Falta el mural terminado' ), 'Recovered notification preserves the cause and reviewer note' );
check_pause_notification( gnf_get_evidence_notification_relation_type( $paused ) === $n['relacion_tipo'], 'Backfill uses stable identity rather than array index' );
gnf_backfill_docente_pause_notifications( 1 );
check_pause_notification( 1 === count( $wpdb->notifications ) && 1 === $wpdb->reads, 'Completed backfill is idempotent' );
$meta = array(); gnf_backfill_docente_pause_notifications( 1 );
check_pause_notification( 1 === count( $wpdb->notifications ), 'Existing read or unread notifications are not recreated' );
$meta = array(); $wpdb->notifications = array(); $wpdb->fail = true;
gnf_backfill_docente_pause_notifications( 1 );
check_pause_notification( empty( $meta ), 'Failed inserts do not advance or complete migration' );
$wpdb->fail = false; gnf_backfill_docente_pause_notifications( 1 );
check_pause_notification( 1 === count( $wpdb->notifications ) && ! empty( $meta['_gnf_pause_notifications_backfilled_v1'] ), 'Failed migrations retry without losing evidence' );
$meta = array(); $wpdb = new PauseNotificationDatabase();
for ( $i = 1; $i <= 201; $i++ ) { $wpdb->entries[] = (object) array( 'id' => $i, 'reto_id' => 2, 'evidencias' => '[]' ); }
gnf_backfill_docente_pause_notifications( 1 );
check_pause_notification( 200 === $meta['_gnf_pause_notifications_cursor_v1'] && empty( $meta['_gnf_pause_notifications_backfilled_v1'] ), 'Backfill processes at most 200 entries per request' );
gnf_backfill_docente_pause_notifications( 1 );
check_pause_notification( 201 === $meta['_gnf_pause_notifications_cursor_v1'] && ! empty( $meta['_gnf_pause_notifications_backfilled_v1'] ), 'The next request resumes at the saved cursor' );
echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
