<?php
define( 'ABSPATH', __DIR__ . '/../' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'ARRAY_A', 'ARRAY_A' );
class ScopeDB {
	public $posts = 'posts', $postmeta = 'meta', $term_relationships = 'relations', $term_taxonomy = 'taxonomies', $terms = 'terms', $last_error = '';
	function get_results( $sql, $format ) {
		if ( strpos( $sql, 'SELECT tr.object_id' ) === 0 ) { return array(); }
		$rows = array();
		foreach ( $GLOBALS['live_centers'] as $id => $data ) {
			foreach ( $data as $key => $value ) { $rows[] = array( 'ID' => $id, 'post_status' => 'publish', 'meta_key' => $key, 'meta_value' => $value ); }
		}
		return $rows;
	}
}
$wpdb = new ScopeDB();
$live_centers = array( 1 => array( 'region' => 1, 'circuito' => '01' ), 2 => array( 'region' => 2, 'circuito' => '01' ), 3 => array( 'region' => 2, 'circuito' => '02' ) );
function add_action() {}
function add_filter() {}
function absint( $n ) { return abs( (int) $n ); }
function gnf_normalize_year( $n ) { return $n ?: 2026; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][ $key ] = $value; }
function wp_next_scheduled( $hook, $args = array() ) { return $GLOBALS['scheduled'][ $hook . json_encode( $args ) ] ?? false; }
function wp_schedule_single_event( $time, $hook, $args ) { $GLOBALS['scheduled'][ $hook . json_encode( $args ) ] = $time; }
function gnf_rest_is_admin() { return $GLOBALS['is_admin']; }
function gnf_rest_is_supervisor() { return $GLOBALS['reviewer']; }
function get_current_user_id() { return 7; }
function gnf_get_user_regions( $id ) { return array( 2 ); }
function gnf_get_user_circuito( $id ) { return $GLOBALS['circuit']; }
function gnf_normalize_circuito( $v ) { return '' === (string) $v ? '' : str_pad( (string) (int) $v, 2, '0', STR_PAD_LEFT ); }
function wp_nonce_url( $url, $action ) { return $url . '&_wpnonce=test'; }
function admin_url( $url ) { return '/wp-admin/' . $url; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
class WP_Error { public $code; function __construct( $code, $message, $data = array() ) { $this->code = $code; } }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
require ABSPATH . 'includes/impact-metrics.php';
if ( file_exists( ABSPATH . 'includes/report-snapshots.php' ) ) { require ABSPATH . 'includes/report-snapshots.php'; }
$tests = 0; $fails = 0; $options = array(); $scheduled = array(); $is_admin = false; $reviewer = true; $circuit = '';
function check_snapshot( $ok, $message ) { global $tests, $fails; $tests++; $fails += $ok ? 0 : 1; echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $message . "\n"; }
check_snapshot( function_exists( 'gnf_get_report_snapshot' ), 'Durable report snapshots exist' );
if ( function_exists( 'gnf_get_report_snapshot' ) ) {
	$empty = gnf_get_report_snapshot( 2026 );
	check_snapshot( empty( $empty['ready'] ) && 1 === count( $scheduled ), 'Cold reads enqueue generation instead of calculating synchronously' );
	gnf_get_report_snapshot( 2026 );
	check_snapshot( 1 === count( $scheduled ), 'Cold concurrent reads reuse the queued event' );
	$options['gnf_report_snapshot_2026_v1'] = array( 'ready' => true, 'generatedTimestamp' => time() - 3 * HOUR_IN_SECONDS, 'centros' => array() );
	check_snapshot( gnf_get_report_snapshot( 2026 )['stale'], 'A three-hour snapshot is now stale under the two-hour policy' );
	$options['gnf_report_snapshot_2026_v1']['generatedTimestamp'] = time() - HOUR_IN_SECONDS;
	check_snapshot( ! gnf_get_report_snapshot( 2026 )['stale'], 'An hour-old snapshot is still immediately usable' );
	$options['gnf_report_snapshot_2026_v1'] = array( 'ready' => true, 'generatedTimestamp' => time() - 5 * HOUR_IN_SECONDS, 'centros' => array(), 'impact' => array() );
	$old = gnf_get_report_snapshot( 2026 );
	check_snapshot( $old['ready'] && $old['stale'], 'Expired snapshots remain available during refresh' );
	$catalog = array( array( 'key' => 'trees', 'title' => 'Arboles', 'unit' => 'arboles', 'available' => true ) );
	$snapshot = array( 'ready' => true, 'generatedTimestamp' => time(), 'generatedAt' => '2026-10-08 10:00:00', 'year' => 2026,
		'impact' => array( 'active' => array( 'catalog' => $catalog ), 'approved' => array( 'catalog' => $catalog ) ),
		'centros' => array(
			array( 'profile' => array( 'centro_id' => 1, 'nombre' => 'Norte', 'region_id' => 1, 'region_name' => 'Norte', 'circuito' => '01' ), 'values' => array( 'active' => array( 'trees' => 50 ), 'approved' => array( 'trees' => 40 ) ), 'stats' => array( 'puntaje' => 5, 'aprobados' => 1, 'estrellas' => 1 ) ),
			array( 'profile' => array( 'centro_id' => 2, 'nombre' => 'Heredia 01', 'region_id' => 2, 'region_name' => 'Heredia', 'circuito' => '01' ), 'values' => array( 'active' => array( 'trees' => 10 ), 'approved' => array( 'trees' => 7 ) ), 'stats' => array( 'puntaje' => 15, 'aprobados' => 2, 'estrellas' => 2 ) ),
			array( 'profile' => array( 'centro_id' => 3, 'nombre' => 'Heredia 02', 'region_id' => 2, 'region_name' => 'Heredia', 'circuito' => '02' ), 'values' => array( 'active' => array( 'trees' => 20 ), 'approved' => array( 'trees' => 12 ) ), 'stats' => array( 'puntaje' => 25, 'aprobados' => 3, 'estrellas' => 3 ) ),
		) );
	$options['gnf_report_snapshot_2026_v1'] = $snapshot;
	$r = gnf_scope_report_snapshot( $snapshot, 0, '', 'active' );
	check_snapshot( 30 === $r['impact']['total']['values']['trees'] && 2 === $r['summary']['totalCentros'] && 1 === count( $r['impact']['regions'] ), 'DRE totals and territories include only assigned regions' );
	check_snapshot( is_wp_error( gnf_scope_report_snapshot( $snapshot, 1 ) ), 'Forged regional filter is forbidden' );
	$circuit = '01'; $r = gnf_scope_report_snapshot( $snapshot, 0, '', 'approved' );
	check_snapshot( 7 === $r['impact']['total']['values']['trees'] && 1 === count( $r['centros'] ), 'Supervisor circuit restriction applies to all totals and downloads' );
	check_snapshot( is_wp_error( gnf_scope_report_snapshot( $snapshot, 2, '02' ) ), 'Forged circuit is forbidden' );
	$circuit = ''; $is_admin = true;
	$r = gnf_scope_report_snapshot( $snapshot, 2 );
	check_snapshot( 30 === $r['impact']['total']['values']['trees'] && 20.0 === $r['summary']['promedioPuntaje'], 'Admin regional filter recalculates totals and averages' );
	$all = gnf_scope_report_snapshot( $snapshot );
	check_snapshot( 80 === $all['impact']['total']['values']['trees'], 'Admin unfiltered total includes all centers' );
	check_snapshot( is_wp_error( gnf_scope_report_snapshot( $snapshot, 0, 'abc' ) ), 'Malformed circuit does not silently widen the scope' );
	check_snapshot( is_wp_error( gnf_scope_report_snapshot( $snapshot, 'abc' ) ) && is_wp_error( gnf_scope_report_snapshot( $snapshot, -1 ) ), 'Malformed region cannot silently widen the scope' );
	check_snapshot( is_wp_error( gnf_scope_report_snapshot( $snapshot, 0, '', array() ) ) && is_wp_error( gnf_scope_report_snapshot( $snapshot, 0, '', 'invalid' ) ), 'Invalid criteria return an error instead of a different mode' );
	$is_admin = false; $live_centers[2]['region'] = 1;
	check_snapshot( 1 === gnf_scope_report_snapshot( $snapshot )['summary']['totalCentros'], 'Moved centers immediately stop exposing private data to their old DRE' );
	$live_centers[2]['region'] = 2; $live_centers[2]['estado_centro'] = 'inactivo';
	check_snapshot( 1 === gnf_scope_report_snapshot( $snapshot )['summary']['totalCentros'], 'Inactive centers immediately disappear without waiting for cron' );
	unset( $live_centers[2]['estado_centro'] ); $is_admin = true;
	check_snapshot( null === gnf_report_numeric_response( 'abc' ) && null === gnf_report_numeric_response( '' ), 'Invalid numeric answers do not become recorded zeroes' );
	check_snapshot( 0.0 === gnf_report_numeric_response( '0' ) && 1234.5 === gnf_report_numeric_response( '1.234,5' ), 'Zero and formatted numeric answers remain valid' );
	check_snapshot( 1234.5 === gnf_report_numeric_response( '$1,234.50' ), 'Currency-formatted amounts remain valid' );
	check_snapshot( null === gnf_report_yes_response( 'unknown' ) && 0.0 === gnf_report_yes_response( 'No' ) && 1.0 === gnf_report_yes_response( 'Sí' ), 'Missing yes/no answers are distinct from an explicit No' );
	check_snapshot( is_wp_error( gnf_scope_report_snapshot( $snapshot, 0, '', 'active', array( array( 'nested' ) ) ) ), 'Malformed source arrays return a controlled error' );
	$source_snapshot = $snapshot;
	foreach ( array( 'active', 'approved' ) as $mode ) {
		$source_snapshot['impact'][$mode]['catalog'] = array(
			array( 'key' => 'trees', 'title' => 'Arboles', 'unit' => 'arboles', 'source' => 'reto-arboles', 'sourceLabel' => 'Arboles', 'available' => true ),
			array( 'key' => 'water', 'title' => 'Agua', 'unit' => 'centros', 'source' => 'reto-agua', 'sourceLabel' => 'Agua', 'available' => true )
		);
	}
	$filtered_sources = gnf_scope_report_snapshot( $source_snapshot, 2, '', 'active', array( 'reto-arboles' ) );
	check_snapshot( 1 === count( $filtered_sources['impact']['catalog'] ) && 2 === count( $filtered_sources['availableSources'] ), 'Source filtering retains all choices while selecting metric columns' );
	check_snapshot( is_wp_error( gnf_scope_report_snapshot( $source_snapshot, 0, '', 'active', 'unknown' ) ), 'Unknown sources cannot return an unfiltered export' );
	check_snapshot( 30 === $all['impact']['regions'][2]['values']['trees'], 'Regional totals do not inherit the previous region total' );
	check_snapshot( 20 === $all['impact']['circuits']['2|02']['values']['trees'], 'Circuit totals do not inherit previous circuits' );
	$missing = $snapshot;
	$missing['centros'][0]['values']['active']['trees'] = null;
	$missing['centros'][1]['values']['active']['trees'] = null;
	$missing['centros'][2]['values']['active']['trees'] = null;
	check_snapshot( null === gnf_scope_report_snapshot( $missing )['impact']['total']['values']['trees'], 'Missing responses stay unavailable instead of a false zero' );
	$missing['centros'][1]['values']['active']['trees'] = 0;
	check_snapshot( 0 === gnf_scope_report_snapshot( $missing )['impact']['total']['values']['trees'], 'A recorded zero remains a valid numeric result' );
	$reviewer = false; $is_admin = false;
	check_snapshot( is_wp_error( gnf_scope_report_snapshot( $snapshot ) ), 'Teachers and unauthenticated users cannot read private snapshots' );
	$rows = iterator_to_array( gnf_report_indicator_export_rows( $all ), false );
	check_snapshot( count( $rows ) > 1 && false !== array_search( 'Arboles (arboles)', $rows[0], true ), 'XLSX has all metric columns including units' );
	check_snapshot( in_array( 80, $rows[1], true ), 'XLSX includes the filtered total as numeric cells' );
}
echo "\n{$tests} checks, {$fails} failures\n"; exit( $fails ? 1 : 0 );
