<?php
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'HOUR_IN_SECONDS', 3600 );
function absint( $value ) { return abs( (int) $value ); }
function maybe_unserialize( $value ) {
	$decoded = is_string( $value ) ? @unserialize( $value ) : false;
	return false !== $decoded ? $decoded : $value;
}
function maybe_serialize( $value ) { return serialize( $value ); }
function add_action() {}
function add_filter() {}
function wp_cache_delete() {}
function gnf_normalize_year( $value ) { return $value ?: 2026; }
function gnf_normalize_circuito( $value ) { return (string) $value; }
function current_time() { return '2026-10-08 12:00:00'; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); }
function wp_next_scheduled() { return false; }
function wp_schedule_single_event() { return true; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Error {}
class ReportBulkReadDB {
	public $prefix = 'wp_';
	public $usermeta = 'wp_usermeta';
	public $options = 'wp_options';
	public $last_error = '';
	public $failure = '';
	public $null_result = false;
	public $taxonomy_error = '';
	public $cached_reads = array();
	public $export_posts = array();
	public $reads = array();
	function prepare( $sql, ...$args ) { return strpos( $sql, 'wp_options' ) !== false ? array( $sql, $args ) : $sql; }
	function get_var( $query ) { return isset( $GLOBALS['options'][ $query[1][0] ] ) ? serialize( $GLOBALS['options'][ $query[1][0] ] ) : null; }
	function query( $query ) {
		if ( is_string( $query ) ) {
			return in_array( $query, array( 'START TRANSACTION', 'COMMIT', 'ROLLBACK' ), true ) ? 0 : false;
		}
		list( $sql, $args ) = $query;
		$options = &$GLOBALS['options'];
		if ( strpos( $sql, 'INSERT IGNORE' ) === 0 ) {
			if ( isset( $options[ $args[0] ] ) ) { return 0; }
			$options[ $args[0] ] = unserialize( $args[1] ); return 1;
		}
		if ( strpos( $sql, 'INSERT INTO' ) === 0 ) {
			if ( serialize( $options[ $args[2] ] ?? null ) !== $args[3] ) { return 0; }
			$options[ $args[0] ] = unserialize( $args[1] ); return 1;
		}
		if ( strpos( $sql, 'DELETE target' ) === 0 ) {
			if ( serialize( $options[ $args[0] ] ?? null ) !== $args[1] ) { return 0; }
			unset( $options[ $args[2] ] ); return 1;
		}
		if ( serialize( $options[ $args[0] ] ?? null ) === $args[1] ) { unset( $options[ $args[0] ] ); return 1; }
		return 0;
	}
	function read( $stage, $result ) {
		if ( in_array( $stage, $this->cached_reads, true ) ) { return $result; }
		$this->reads[] = $stage;
		$this->last_error = $stage === $this->failure ? 'Simulated SQL failure: ' . $stage : '';
		if ( $stage === $this->taxonomy_error ) { return new WP_Error(); }
		return $this->last_error ? ( $this->null_result ? null : array() ) : $result;
	}
	function get_results( $sql, $output = null ) {
		if ( strpos( $sql, 'SUM(puntaje)' ) !== false ) { return $this->read( 'scores', array() ); }
		if ( strpos( $sql, 'wp_usermeta' ) !== false ) { return $this->read( 'users', array( array( 'user_id' => 7, 'meta_value' => 1 ) ) ); }
		return $this->read( strpos( $sql, 'gn_matriculas' ) !== false ? 'matriculas' : 'entries', array() );
	}
	function get_col( $sql ) { return $this->read( 'participants', array( 1 ) ); }
}
function gnf_get_centros_with_matricula( $year ) { return array_map( 'absint', (array) $GLOBALS['wpdb']->get_col( 'SELECT DISTINCT centro_id FROM wp_gn_matriculas' ) ); }
function get_posts( $args ) {
	$wpdb = $GLOBALS['wpdb'];
	$posts = $wpdb->read( 'posts', array( (object) array( 'ID' => 1 ) ) );
	if ( $posts && ( $args['update_post_meta_cache'] ?? true ) ) {
		update_meta_cache( 'post', $args['post__in'] );
		if ( $wpdb->last_error ) { $wpdb->cached_reads[] = 'post_meta'; }
	}
	if ( $posts && ( $args['update_post_term_cache'] ?? true ) ) { $wpdb->read( 'term_cache', array() ); }
	return $posts;
}
function wp_list_pluck( $rows, $key ) { return array_column( $rows, $key ); }
function update_meta_cache( $type, $ids ) { return $GLOBALS['wpdb']->read( $type . '_meta', array() ); }
function cache_users( $ids ) { $GLOBALS['wpdb']->read( 'user_cache', array() ); }
function get_post_meta( $id, $key, $single ) { return 'region' === $key ? 2 : ( 'docentes_asociados' === $key ? array() : '' ); }
function wp_get_object_terms( $ids, $taxonomy, $args ) { return $GLOBALS['wpdb']->read( 'regions', array() ); }
function get_terms( $args ) { return $GLOBALS['wpdb']->read( 'legacy_regions', array() ); }
function get_post( $id ) { return null; }
function get_userdata( $id ) { return null; }
class WP_Query {
	public $posts;
	function __construct( $args ) {
		$wpdb = $GLOBALS['wpdb'];
		$this->posts = $wpdb->read( 'export_posts', $wpdb->export_posts );
		if ( $this->posts && ( $args['update_post_meta_cache'] ?? true ) ) {
			update_meta_cache( 'post', $args['post__in'] );
			if ( $wpdb->last_error ) { $wpdb->cached_reads[] = 'post_meta'; }
		}
		if ( $this->posts && ( $args['update_post_term_cache'] ?? true ) ) { $wpdb->read( 'term_cache', array() ); }
	}
}
require ABSPATH . 'includes/centros-export.php';
require ABSPATH . 'includes/impact-metrics.php';
$tests = 0; $fails = 0;
function check_bulk_read( $condition, $message ) {
	global $tests, $fails;
	$tests++; $fails += $condition ? 0 : 1;
	echo ( $condition ? 'ok: ' : 'FAIL: ' ) . $message . "\n";
}
$cases = array(
	'participants' => static function() { gnf_get_impact_center_records( 2026 ); },
	'posts' => static function() { gnf_get_impact_center_records( 2026, array( 1 ) ); },
	'entries' => static function() { gnf_get_impact_center_records( 2026, array( 1 ) ); },
);
foreach ( array( 'post_meta', 'matriculas', 'scores', 'users', 'user_cache', 'user_meta', 'regions', 'legacy_regions' ) as $stage ) {
	$cases[ $stage ] = static function() { gnf_build_centros_export_batch_maps( array( 1 ), 2026 ); };
}
$cases['export_participants'] = static function() { iterator_to_array( gnf_iter_centros_export_records( 2026 ) ); };
$cases['export_posts'] = $cases['export_participants'];
foreach ( $cases as $stage => $read ) {
	$variants = in_array( $stage, array( 'participants', 'export_participants', 'entries', 'matriculas', 'scores', 'users' ), true ) ? array( false, true ) : array( false );
	foreach ( $variants as $null_result ) {
		$wpdb = new ReportBulkReadDB();
		$wpdb->failure = 'export_participants' === $stage ? 'participants' : $stage;
		$wpdb->null_result = $null_result;
		$thrown = false;
		try { $read(); } catch ( RuntimeException $error ) { $thrown = true; }
		check_bulk_read( $thrown, $stage . ' SQL failure throws even with ' . ( $null_result ? 'null' : 'empty' ) . ' results' );
		check_bulk_read( end( $wpdb->reads ) === $wpdb->failure, $stage . ' failure stops before a later read can clear last_error' );
	}
}
$wpdb = new ReportBulkReadDB();
$records = gnf_get_impact_center_records( 2026, array( 1 ) );
check_bulk_read( 1 === count( $records ) && array() === $records[0]['entries'], 'Successful empty evidence SELECT preserves a center with no evidence' );
check_bulk_read( array() === iterator_to_array( gnf_iter_centros_export_records( 2026 ) ), 'Successful empty center SELECT remains an empty export' );
$wpdb->last_error = 'Unrelated earlier query failure';
check_bulk_read( array() === gnf_build_centros_export_batch_maps( array(), 2026 )['matriculas'], 'An empty batch does not treat an unrelated error as its own failure' );
foreach ( array( 'regions', 'legacy_regions' ) as $stage ) {
	$wpdb = new ReportBulkReadDB();
	$wpdb->taxonomy_error = $stage;
	$thrown = false;
	try { gnf_build_centros_export_batch_maps( array( 1 ), 2026 ); } catch ( RuntimeException $error ) { $thrown = true; }
	check_bulk_read( $thrown, $stage . ' WP_Error stops the batch even without last_error' );
}
$wpdb = new ReportBulkReadDB();
$wpdb->cached_reads = array( 'participants', 'posts', 'post_meta', 'user_cache', 'user_meta', 'regions', 'legacy_regions', 'export_posts' );
$wpdb->last_error = 'Unrelated earlier query failure';
check_bulk_read( 1 === count( gnf_get_impact_center_records( 2026 ) ), 'Cache hits do not inherit an unrelated earlier SQL failure' );
$wpdb->last_error = 'Unrelated earlier query failure';
check_bulk_read( array() === iterator_to_array( gnf_iter_centros_export_records( 2026 ) ), 'Cached export reads do not inherit an unrelated SQL failure' );
$wpdb = new ReportBulkReadDB();
$wpdb->failure = 'post_meta';
$wpdb->export_posts = array( (object) array( 'ID' => 1 ) );
$thrown = false;
try { iterator_to_array( gnf_iter_centros_export_records( 2026 ) ); } catch ( RuntimeException $error ) { $thrown = true; }
check_bulk_read( $thrown, 'Export post query cannot hide a metadata SQL failure behind term cache warming' );
require ABSPATH . 'includes/report-snapshots.php';
$old = array( 'ready' => true, 'generatedTimestamp' => 1, 'centros' => array( array( 'previous' => true ) ) );
$checkpoint = array( 'started' => time(), 'cursor' => 1, 'ids' => array( 1, 2 ), 'catalog' => array(), 'centros' => array() );
foreach ( array( 'entries', 'matriculas', 'scores', 'users', 'posts', 'post_meta', 'user_cache', 'user_meta', 'regions', 'legacy_regions' ) as $stage ) {
	$wpdb = new ReportBulkReadDB();
	$wpdb->failure = $stage;
	$options = array( 'gnf_report_snapshot_2026_v1' => $old, 'gnf_report_job_2026' => $checkpoint );
	gnf_refresh_report_snapshot( 2026 );
	check_bulk_read( $old === $options['gnf_report_snapshot_2026_v1'], $stage . ' SQL failure keeps the last good snapshot' );
	check_bulk_read( $checkpoint === ( $options['gnf_report_job_2026'] ?? null ), $stage . ' SQL failure keeps the saved cursor' );
	check_bulk_read( isset( $options['gnf_report_error_2026'] ) && ! isset( $options['gnf_report_lock_2026'] ), $stage . ' SQL failure records recovery and releases the lease' );
}
echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
