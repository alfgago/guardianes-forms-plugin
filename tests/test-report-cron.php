<?php
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
function add_action() {}
function add_filter() {}
function absint( $v ) { return abs( (int) $v ); }
function gnf_normalize_year( $v ) { return $v ?: 2026; }
function gnf_normalize_circuito( $v ) { return (string) $v; }
function get_option( $k, $default = false ) { return $GLOBALS['options'][$k] ?? $default; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['options'][$k] = $v; }
function add_option( $k, $v, $deprecated = '', $autoload = null ) { if ( isset( $GLOBALS['options'][$k] ) ) { return false; } $GLOBALS['options'][$k] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['options'][$k] ); }
function maybe_serialize( $v ) { return serialize( $v ); }
function maybe_unserialize( $v ) { return unserialize( $v ); }
function wp_cache_delete() {}
function wp_next_scheduled( $hook, $args = array() ) { return $GLOBALS['scheduled'][$hook . json_encode($args)] ?? false; }
function wp_schedule_single_event( $t, $hook, $args ) { $GLOBALS['scheduled'][$hook . json_encode($args)] = $t; }
function current_time() { return '2026-10-08 10:30:00'; }
function gnf_get_active_year() { return 2026; }
function gnf_get_centros_with_matricula() { if ( ! empty( $GLOBALS['fail_ids'] ) ) { $GLOBALS['wpdb']->last_error = 'Simulated enrollment SQL failure'; return array(); } return range( 1, 201 ); }
function gnf_impact_reto_map() { return array(); }
function gnf_get_impact_metric_catalog() { return array( 'inscripciones' => array( 'key' => 'inscripciones', 'source' => 'center', 'unit' => 'centros', 'title' => 'Inscripciones', 'operation' => 'count', 'reto' => '' ) ); }
function gnf_impact_metric_is_available() { return true; }
function gnf_impact_metric_value_for_record() { return 1; }
function gnf_get_impact_center_records( $year, $ids, $with_profiles ) {
	$GLOBALS['batches'][] = count( $ids );
	if ( $GLOBALS['fail_batch'] ) { throw new RuntimeException( 'Simulated failure' ); }
	return array_map( static function( $id ) { return array( 'profile' => array( 'centro_id' => $id, 'region_id' => 2, 'region_name' => 'Heredia', 'circuito' => '01' ), 'stats' => array(), 'retos' => array() ); }, $ids );
}
class ReportDB {
	public $options = 'wp_options', $last_error = '';
	function prepare( $sql, ...$args ) { return array( $sql, $args ); }
	function get_var( $query ) { return isset( $GLOBALS['options'][$query[1][0]] ) ? serialize( $GLOBALS['options'][$query[1][0]] ) : null; }
	function query( $query ) {
		if ( is_string( $query ) ) { $GLOBALS['transaction_commands'][] = $query; return 0; }
		list( $sql, $args ) = $query;
		if ( ! empty( $GLOBALS['fail_write'] ) && false !== strpos( $sql, 'INSERT INTO' ) ) { return false; }
		if ( false !== strpos( $sql, 'INSERT IGNORE' ) ) {
			if ( isset( $GLOBALS['options'][$args[0]] ) ) { return 0; }
			$GLOBALS['options'][$args[0]] = unserialize( $args[1] ); return 1;
		}
		if ( false !== strpos( $sql, 'INSERT INTO' ) ) {
			if ( ! isset( $GLOBALS['options'][$args[2]] ) || serialize( $GLOBALS['options'][$args[2]] ) !== $args[3] ) { return 0; }
			$GLOBALS['options'][$args[0]] = unserialize( $args[1] ); return 1;
		}
		if ( false !== strpos( $sql, 'DELETE target' ) ) {
			if ( ! isset( $GLOBALS['options'][$args[0]] ) || serialize( $GLOBALS['options'][$args[0]] ) !== $args[1] ) { return 0; }
			unset( $GLOBALS['options'][$args[2]] ); return 1;
		}
		if ( isset( $GLOBALS['options'][$args[0]] ) && serialize( $GLOBALS['options'][$args[0]] ) === $args[1] ) { unset( $GLOBALS['options'][$args[0]] ); return 1; }
		return 0;
	}
}
$wpdb = new ReportDB(); $options = array(); $scheduled = array(); $batches = array(); $fail_batch = false;
require ABSPATH . 'includes/report-snapshots.php';
$tests = 0; $fails = 0;
function check_cron( $ok, $message ) { global $tests, $fails; $tests++; if ( ! $ok ) { $fails++; } echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $message . "\n"; }
$old = array( 'ready' => true, 'generatedTimestamp' => 1, 'centros' => array() );
$options['gnf_report_snapshot_2026_v1'] = $old;
gnf_refresh_report_snapshot( 2026 );
check_cron( $batches === array( 200 ), 'One cron event handles only 200 centers' );
check_cron( $options['gnf_report_snapshot_2026_v1'] === $old, 'Readers retain the last good snapshot during a partial build' );
check_cron( 200 === $options['gnf_report_job_2026']['cursor'], 'Cursor is checkpointed after each completed batch' );
gnf_refresh_report_snapshot( 2026 );
$built = $options['gnf_report_snapshot_2026_v1'];
check_cron( $batches === array( 200, 1 ) && count( $built['centros'] ) === 201, 'The second event finishes without omissions or duplicates' );
check_cron( 201 === $built['impact']['active']['total']['values']['inscripciones'], 'Published totals include every completed batch' );
check_cron( ! isset( $options['gnf_report_job_2026'] ) && ! isset( $options['gnf_report_lock_2026'] ), 'Successful jobs clean up their cursor and lock' );
$fail_batch = true;
gnf_refresh_report_snapshot( 2026 );
check_cron( $built === $options['gnf_report_snapshot_2026_v1'], 'A failed cron build never replaces the last good snapshot' );
check_cron( isset( $options['gnf_report_error_2026'] ) && ! isset( $options['gnf_report_lock_2026'] ), 'Failures record a recoverable error and release the lock' );
$one = gnf_acquire_report_lock( 2026 );
check_cron( false === gnf_acquire_report_lock( 2026 ), 'A second worker cannot acquire a held lock' );
$two = array( 'token' => 'new', 'expires' => time() + 600 );
$options['gnf_report_lock_2026'] = $two;
gnf_release_report_lock( 2026, $one );
check_cron( $two === $options['gnf_report_lock_2026'], 'An old worker cannot release a replacement lock' );
try { gnf_report_owned_option( 2026, $one, 'gnf_report_job_2026', array( 'cursor' => 999 ) ); $fenced = false; } catch ( RuntimeException $error ) { $fenced = true; }
check_cron( $fenced && ! isset( $options['gnf_report_job_2026'] ), 'An old worker cannot checkpoint after its lease is replaced' );
$options['gnf_report_job_2026'] = array( 'cursor' => 777 );
try { gnf_report_owned_option( 2026, $one, 'gnf_report_job_2026', null, true ); } catch ( RuntimeException $error ) {}
check_cron( 777 === $options['gnf_report_job_2026']['cursor'], 'An old worker cannot delete its successor checkpoint' );
$fail_write = true;
try { gnf_report_owned_option( 2026, $two, 'gnf_report_snapshot_2026_v1', array( 'ready' => true ) ); $rejected = false; } catch ( RuntimeException $error ) { $rejected = true; }
check_cron( $rejected && $built === $options['gnf_report_snapshot_2026_v1'], 'Failed persistent writes retain the previous snapshot' );
$fail_write = false; $fail_ids = true; $fail_batch = false;
unset( $options['gnf_report_lock_2026'], $options['gnf_report_job_2026'] );
gnf_refresh_report_snapshot( 2026 );
check_cron( $built === $options['gnf_report_snapshot_2026_v1'], 'An enrollment query failure never publishes an empty report' );
check_cron( in_array( 'START TRANSACTION', $transaction_commands, true ) && in_array( 'COMMIT', $transaction_commands, true ) && in_array( 'ROLLBACK', $transaction_commands, true ), 'Owned writes hold their lease until commit and roll back failures' );
echo "\n{$tests} checks, {$fails} failures\n"; exit( $fails ? 1 : 0 );
