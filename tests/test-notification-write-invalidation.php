<?php
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
$invalidated = array();
function gnf_invalidate_notification_summary( $id ) { $GLOBALS['invalidated'][] = $id; }
function gnf_user_receives_only_rejections() { return true; }
function gnf_get_docente_action_notification_types() { return array( 'evidencia_rechazada' ); }
function current_time() { return '2026-10-27 12:00:00'; }
class NotificationWriteDB {
    public $prefix = 'wp_'; public $insert_id = 12; public $fail = false; public $existing = false;
    function insert() { return $this->fail ? false : 1; }
    function update() { return $this->fail ? false : 1; }
    function prepare( $sql, ...$args ) { return $sql; }
    function get_row() { return $this->existing ? (object) array( 'id' => 12 ) : null; }
}
$wpdb = new NotificationWriteDB();
foreach ( array( 'gnf_insert_notification', 'gnf_insert_or_refresh_notification' ) as $name ) {
    $source = file_get_contents( ABSPATH . 'includes/helpers.php' ); $start = strpos( $source, 'function ' . $name . '(' );
    eval( substr( $source, $start, strpos( $source, "\n}", $start ) + 2 - $start ) );
}
gnf_insert_notification( 5, 'evidencia_rechazada', 'a' );
if ( array( 5 ) !== $invalidated ) { throw new Exception( 'Successful notification inserts invalidate only their recipient' ); }
$wpdb->existing = true;
gnf_insert_or_refresh_notification( 5, 'evidencia_rechazada', 'b' );
if ( array( 5, 5 ) !== $invalidated ) { throw new Exception( 'Same-second unread notification refreshes invalidate cached signatures' ); }
$wpdb->fail = true;
gnf_insert_notification( 5, 'evidencia_rechazada', 'c' );
gnf_insert_or_refresh_notification( 5, 'evidencia_rechazada', 'd' );
gnf_insert_notification( 5, 'evidencia_aprobada', 'ignored' );
if ( array( 5, 5 ) !== $invalidated ) { throw new Exception( 'Failed or suppressed notifications must not discard valid summaries' ); }
echo "Notification write invalidation checks passed\n";
