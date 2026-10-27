<?php
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
$routes = $meta = $transients = $ttls = array();
$panel_versions = array( 'center_1' => 0, 'config' => 0 );
$permission = 'read';
$current_user = 1; $roles = array( 'docente' ); $context_reads = $backfills = $date_reads = 0;
function add_action() {}
function register_rest_route( $ns, $route, $args ) { $GLOBALS['routes'][ $route ] = $args; }
function get_current_user_id() { return $GLOBALS['current_user']; }
function is_user_logged_in() { return get_current_user_id() > 0; }
function get_userdata( $id ) { return (object) array( 'roles' => $GLOBALS['roles'] ); }
function get_user_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ''; }
function update_user_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; }
function get_transient( $key ) { return $GLOBALS['transients'][ $key ] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][ $key ] = $value; $GLOBALS['ttls'][ $key ] = $ttl; }
function delete_transient( $key ) { unset( $GLOBALS['transients'][ $key ] ); }
function gnf_panel_cache_version( $scope ) { return (string) ( $GLOBALS['panel_versions'][ $scope ] ?? 0 ); }
function gnf_panel_cache_context( $teacher ) {
	return array( 'user' => get_current_user_id(), 'roles' => $GLOBALS['roles'], 'caps' => $GLOBALS['permission'], 'center' => $teacher ? 1 : 0, 'config' => gnf_panel_cache_version( 'config' ), 'version' => $teacher ? gnf_panel_cache_version( 'center_1' ) : 'reviewer' );
}
function absint( $n ) { return abs( (int) $n ); }
function gnf_backfill_docente_pause_notifications( $id ) {
	$GLOBALS['backfills']++;
	update_user_meta( $id, '_gnf_pause_notifications_backfilled_v1', 1 );
}
function gnf_apply_evidence_original_date( $evidence, $year ) { $GLOBALS['date_reads']++; return $evidence; }
function gnf_build_notification_context( $item, $id ) {
	$GLOBALS['context_reads']++;
	$evidences = json_decode( $GLOBALS['wpdb']->entries[ $item->relacion_id ]['evidencias'] ?? '[]', true );
	$evidences = gnf_filter_notification_evidences( $item->tipo, $item->relacion_tipo, $item->mensaje, $evidences );
	foreach ( $evidences as &$ev ) {
		if ( ! isset( $ev['estado'] ) && gnf_evidence_has_verifiable_date_issue( $ev ) ) { $ev['estado'] = 'rechazada'; }
	}
	return array( 'evidenceItems' => array_values( $evidences ), 'actionTarget' => array( 'page' => 'formularios' ) );
}
class WP_REST_Request {
	private $params;
	function __construct( $params ) { $this->params = $params; }
	function get_param( $key ) { return $this->params[ $key ] ?? null; }
}
class WP_Error {
	public $code;
	function __construct( $code, $message, $data ) { $this->code = $code; }
}
class NotificationSummaryDatabase {
	public $prefix = 'wp_'; public $notifications = array(); public $entries = array(); public $queries = array(); public $fail = false; public $signatures = 0;
	function __construct() { $GLOBALS['panel_versions']['center_1']++; }
	function prepare( $sql, ...$args ) { return array( $sql, $args ); }
	function get_row( $query ) {
		$this->signatures++;
		if ( $this->fail ) { return null; }
		$items = array_filter( $this->notifications, function( $n ) use ( $query ) { return $n->user_id === $query[1][0]; } );
		$row = array( 'total' => count( $items ), 'latest_id' => 0, 'latest_at' => '', 'read_count' => 0, 'read_ids' => 0 );
		foreach ( $items as $n ) {
			$row['latest_id'] = max( $row['latest_id'], $n->id );
			$row['latest_at'] = max( $row['latest_at'], $n->created_at );
			$row['read_count'] += $n->leido ? 1 : 0;
			$row['read_ids'] += $n->leido ? $n->id : 0;
		}
		return (object) $row;
	}
	function get_results( $query ) {
		$this->queries[] = $query;
		if ( $this->fail ) { return null; }
		list( $sql, $args ) = $query;
		$teacher = false !== strpos( $sql, "'evidencia_en_pausa'" );
		$items = array_values( array_filter( $this->notifications, function( $n ) use ( $args, $teacher ) {
			return $n->user_id === $args[0] && ( ! $teacher || in_array( $n->tipo, array( 'evidencia_rechazada', 'evidencia_en_pausa', 'invalid_photo_date', 'correccion' ), true ) );
		} ) );
		usort( $items, function( $a, $b ) { return strcmp( $b->created_at, $a->created_at ) ?: $b->id <=> $a->id; } );
		$items = array_slice( $items, $args[1], 50 );
		return array_map( function( $n ) use ( $teacher ) {
			$n = clone $n;
			$raw = $this->entries[ $n->relacion_id ]['evidencias'] ?? null;
			if ( $teacher ) { $n->entry_evidencias = $raw; }
			$n->entry_version = null !== $raw ? md5( $raw ) : null;
			$n->entry_anio = $this->entries[ $n->relacion_id ]['anio'] ?? null;
			$n->entry_centro = $this->entries[ $n->relacion_id ]['centro_id'] ?? null;
			return $n;
		}, $items );
	}
	function update( $table, $values, $where, ...$formats ) {
		$changed = 0;
		foreach ( $this->notifications as $n ) {
			foreach ( $where as $key => $value ) { if ( $n->$key != $value ) { continue 2; } }
			foreach ( $values as $key => $value ) { $n->$key = $value; }
			$changed++;
		}
		return $changed;
	}
}
require ABSPATH . 'includes/rest-api.php';
// Load the real policy without replacing the backfill boundary used above.
$policy = file_get_contents( ABSPATH . 'includes/evidence-notifications.php' );
$start = strpos( $policy, 'function gnf_backfill_docente_pause_notifications(' );
$end = strpos( $policy, "\n}", $start ) + 2;
eval( '?>' . substr_replace( $policy, '', $start, $end - $start ) );
require ABSPATH . 'includes/evidence-review.php';
$tests = $fails = 0;
function check_notification_summary( $ok, $message ) {
	global $tests, $fails; $tests++; $fails += $ok ? 0 : 1;
	echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $message . "\n";
}
function notification_fixture( $id, $evidence, $type = 'evidencia_rechazada', $read = 0, $user = 1, $entry = null ) {
	global $wpdb;
	$entry = $entry ?? $id;
	$wpdb->entries[ $entry ] = array( 'evidencias' => json_encode( array( $evidence ) ), 'anio' => 2026, 'centro_id' => 1 );
	$n = (object) array( 'id' => $id, 'user_id' => $user, 'tipo' => $type, 'mensaje' => 'Foto: ' . ( $evidence['nombre'] ?? 'foto.jpg' ), 'relacion_tipo' => gnf_get_evidence_notification_relation_type( $evidence ), 'relacion_id' => $entry, 'leido' => $read, 'created_at' => '2026-10-01 12:00:00' );
	$wpdb->notifications[] = $n;
	return $n;
}
gnf_register_rest_routes();
check_notification_summary( isset( $routes['/notifications/summary'] ) && 'is_user_logged_in' === $routes['/notifications/summary']['permission_callback'], 'Summary is an authenticated route' );
check_notification_summary( function_exists( 'gnf_rest_notifications_summary' ), 'Lightweight notification summary exists' );
if ( $fails ) { exit( 1 ); }
$wpdb = new NotificationSummaryDatabase();
$rejected = array( 'field_id' => 1, 'nombre' => 'foto.jpg', 'ruta' => '/private/foto.jpg', 'estado' => 'rechazada' );
for ( $id = 1; $id <= 55; $id++ ) { notification_fixture( $id, $rejected, 'evidencia_rechazada', $id % 2 ); }
for ( $id = 56; $id <= 110; $id++ ) { notification_fixture( $id, array_merge( $rejected, array( 'estado' => 'aprobada' ) ) ); }
notification_fixture( 111, array_merge( $rejected, array( 'estado' => 'en_pausa' ) ), 'evidencia_en_pausa', 0 );
notification_fixture( 112, $rejected, 'evidencia_aprobada' );
notification_fixture( 113, $rejected, 'evidencia_rechazada', 0, 2 );
$summary = gnf_rest_notifications_summary();
check_notification_summary( 25 === $summary['unreadCount'] && 50 === $summary['total'], 'Badge counts the newest 50 actionable rows, not all unread database rows' );
check_notification_summary( 0 === $context_reads && 1 === $backfills, 'Heartbeat never builds evidence details; legacy pause recovery runs once' );
check_notification_summary( array( 'total', 'unreadCount', 'version' ) === ( function( $s ) { $keys = array_keys( $s ); sort( $keys ); return $keys; } )( $summary ), 'Heartbeat contains counts/version only, not private evidence or messages' );
$reads = $date_reads;
$queries = count( $wpdb->queries );
$same = gnf_rest_notifications_summary();
check_notification_summary( $summary === $same && 1 === $backfills && $reads === $date_reads, 'Unchanged heartbeat avoids repeated backfill and metadata enrichment' );
check_notification_summary( $queries === count( $wpdb->queries ) && $wpdb->signatures >= 2, 'Cached heartbeat reads only a per-user change signature, without evidence joins or hashing' );
$list = gnf_rest_notifications_list();
check_notification_summary( 50 === count( $list ) && 25 === count( array_filter( $list, function( $n ) { return ! $n['leido']; } ) ), 'Detail list agrees with the heartbeat window' );
gnf_rest_notification_mark_read( new WP_REST_Request( array( 'id' => 111 ) ) );
$read = gnf_rest_notifications_summary();
check_notification_summary( 24 === $read['unreadCount'] && $summary['version'] !== $read['version'], 'Mark read immediately changes summary count and version' );
gnf_rest_notification_mark_read( new WP_REST_Request( array( 'id' => 113 ) ) );
check_notification_summary( 0 === end( $wpdb->notifications )->leido, 'A user cannot mark someone else\'s notification read' );
gnf_rest_notifications_mark_all_read();
check_notification_summary( 0 === gnf_rest_notifications_summary()['unreadCount'] && 0 === end( $wpdb->notifications )->leido, 'Mark all is private and does not resolve actionable evidence' );
$current_user = 2;
check_notification_summary( 1 === gnf_rest_notifications_summary()['unreadCount'], 'Same-process user switch cannot reuse another user\'s summary' );
$current_user = 1;
$version = gnf_rest_notifications_summary()['version'];
$wpdb->entries[111]['evidencias'] = json_encode( array( array_merge( $rejected, array( 'estado' => 'aprobada' ) ) ) );
$panel_versions['center_1']++;
check_notification_summary( 50 === gnf_rest_notifications_summary()['total'] && $version !== gnf_rest_notifications_summary()['version'], 'Resolved evidence immediately removes the row and reveals older actionable history' );
$wpdb = new NotificationSummaryDatabase();
notification_fixture( 2, $rejected, 'evidencia_rechazada', 1, 1, 1 );
notification_fixture( 1, $rejected, 'evidencia_rechazada', 0, 1, 1 );
check_notification_summary( 0 === gnf_rest_notifications_summary()['unreadCount'] && 1 === gnf_rest_notifications_summary()['total'], 'Newest stable evidence identity wins, including its read state' );
$version = gnf_rest_notifications_summary()['version'];
$wpdb->notifications[0]->mensaje = 'Updated reviewer note';
if ( function_exists( 'gnf_invalidate_notification_summary' ) ) { gnf_invalidate_notification_summary( 1 ); }
check_notification_summary( $version !== gnf_rest_notifications_summary()['version'], 'Same-count notification refresh changes the version' );
$wpdb->entries[1]['evidencias'] = json_encode( array( array_merge( $rejected, array( 'replaced' => true ) ) ) );
$panel_versions['center_1']++;
check_notification_summary( 0 === gnf_rest_notifications_summary()['total'], 'Replacing an evidence invalidates eligibility immediately' );
$wpdb->entries[1]['evidencias'] = '[]';
$panel_versions['center_1']++;
check_notification_summary( 0 === gnf_rest_notifications_summary()['total'], 'Removing evidence removes its old notification' );
$roles = array( 'docente', 'administrator' );
for ( $id = 3; $id <= 60; $id++ ) { notification_fixture( $id, $rejected, 'general', $id % 2 ); }
$summary = gnf_rest_notifications_summary();
check_notification_summary( 50 === $summary['total'] && 25 === $summary['unreadCount'], 'Multi-role administrators keep the newest 50 operational notifications' );
$version = $summary['version'];
$wpdb->entries[60]['evidencias'] = json_encode( array( array_merge( $rejected, array( 'supervisor_comment' => 'Edited note' ) ) ) );
$panel_versions['center_1']++;
check_notification_summary( $version !== gnf_rest_notifications_summary()['version'], 'Reviewer detail changes invalidate the version even without a new notification' );
$roles = array( 'docente' );
$wpdb = new NotificationSummaryDatabase();
$legacy = array( 'field_id' => 3, 'nombre' => 'old.jpg', 'ruta' => '/old.jpg', 'tipo' => 'imagen', 'requires_year_validation' => true, 'photo_date' => '2025-01-01' );
notification_fixture( 1, $legacy, 'invalid_photo_date' );
$summary = gnf_rest_notifications_summary();
check_notification_summary( 1 === $summary['unreadCount'], 'Legacy date-derived rejection is actionable without persisted review state' );
$reads = $date_reads;
gnf_rest_notifications_summary();
check_notification_summary( $reads === $date_reads, 'Unchanged legacy heartbeat reuses eligibility instead of reading file metadata again' );
$legacy['estado'] = null;
$wpdb->entries[1]['evidencias'] = json_encode( array( $legacy ) );
$panel_versions['center_1']++;
$summary = gnf_rest_notifications_summary();
check_notification_summary( 1 === $summary['unreadCount'] && 1 === count( gnf_rest_notifications_list() ), 'A null legacy state uses the same date fallback as notification detail' );
$legacy['requires_year_validation'] = false;
$wpdb->entries[1]['evidencias'] = json_encode( array( $legacy ) );
$panel_versions['center_1']++;
check_notification_summary( 0 === gnf_rest_notifications_summary()['total'], 'A changed legacy evidence source cannot reuse obsolete cached eligibility' );
$queries = count( $wpdb->queries );
$permission = 'changed';
gnf_rest_notifications_summary();
check_notification_summary( count( $wpdb->queries ) > $queries, 'Changed permissions cannot reuse a populated summary' );
$queries = count( $wpdb->queries );
$panel_versions['config']++;
gnf_rest_notifications_summary();
check_notification_summary( count( $wpdb->queries ) > $queries, 'Config changes invalidate the cached computed summary' );
$wpdb->fail = true;
$failed = gnf_rest_notifications_summary();
check_notification_summary( $failed instanceof WP_Error && 'notifications_unavailable' === $failed->code, 'A database failure cannot silently clear the unread badge' );
echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
