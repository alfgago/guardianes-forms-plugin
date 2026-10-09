<?php
// Standalone scoped fixtures: php tests/test-impact-exports.php [--review-dir=PATH].
define( 'ABSPATH', __DIR__ . '/../' );
if ( in_array( '--disabled-cron', $argv, true ) ) { define( 'DISABLE_WP_CRON', true ); }
if ( in_array( '--alternate-cron', $argv, true ) ) { define( 'ALTERNATE_WP_CRON', true ); }
class WP_Error {
	private $code; private $message; private $data;
	public function __construct( $code, $message, $data = array() ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function is_user_logged_in() { return $GLOBALS['logged']; }
function gnf_rest_is_supervisor() { return $GLOBALS['supervisor']; }
function gnf_rest_is_admin() { return $GLOBALS['admin']; }
function current_user_can( $cap ) { return $GLOBALS['admin']; }
function wp_verify_nonce( $nonce, $action ) { return 'fresh' === $nonce && 'gnf_export_impact' === $action; }
function wp_nonce_url( $url, $action ) { $GLOBALS['nonce_actions'][] = $action; return htmlspecialchars( $url . '&_wpnonce=fresh', ENT_QUOTES, 'UTF-8' ); }
function admin_url( $path ) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function wp_unslash( $value ) { return $value; }
function add_action( $hook, $callback ) { $GLOBALS['hooks'][ $hook ] = $callback; }
function get_current_user_id() { return $GLOBALS['user_id'] ?? 7; }
function gnf_get_user_regions( $id ) { return $GLOBALS['allowed_regions'] ?? array( 2 ); }
function gnf_get_user_circuito( $id ) { return $GLOBALS['assigned_circuit'] ?? '01'; }
function get_option( $key, $default = false ) { return $GLOBALS['job_options'][ $key ] ?? $default; }
function add_option( $key, $value, $deprecated = '', $autoload = false ) { $GLOBALS['job_options'][ $key ] = $value; return true; }
function update_option( $key, $value, $autoload = false ) { $GLOBALS['job_options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['job_options'][ $key ] ); return true; }
function wp_schedule_single_event( $time, $hook, $args ) { $GLOBALS['job_events'][ $hook . json_encode( $args ) ] = $time; return true; }
function wp_next_scheduled( $hook, $args ) { return $GLOBALS['job_events'][ $hook . json_encode( $args ) ] ?? false; }
function spawn_cron() { $GLOBALS['cron_spawns'] = ( $GLOBALS['cron_spawns'] ?? 0 ) + 1; return true; }
function wp_cache_delete( $key, $group ) {}
function maybe_serialize( $value ) { return serialize( $value ); }
function maybe_unserialize( $value ) { return unserialize( $value ); }
function home_url( $path ) { return 'https://example.test' . $path; }
class Impact_Job_DB {
	public $options = 'options'; public $posts = 'posts'; public $postmeta = 'postmeta'; public $term_relationships = 'term_relationships'; public $term_taxonomy = 'term_taxonomy'; public $terms = 'terms'; public $last_error = ''; private $args;
	public function prepare( $sql, ...$args ) { $this->args = $args; return $sql; }
	public function query( $sql ) {
		$this->last_error = '';
		if ( ! empty( $GLOBALS['before_sql'] ) ) { $hook = $GLOBALS['before_sql']; unset( $GLOBALS['before_sql'] ); $hook( $sql, $this->args ); }
		if ( ! empty( $GLOBALS['sql_failure'] ) && false !== stripos( $sql, $GLOBALS['sql_failure'] ) ) { unset( $GLOBALS['sql_failure'] ); $this->last_error = 'Fixture SQL failure'; return false; }
		if ( 0 === strpos( $sql, 'INSERT IGNORE' ) ) {
			list( $key, $serialized ) = $this->args;
			if ( isset( $GLOBALS['job_options'][ $key ] ) ) { return 0; }
			$GLOBALS['job_options'][ $key ] = unserialize( $serialized ); return 1;
		}
		if ( 0 === strpos( $sql, 'UPDATE' ) ) {
			list( $lock_key, $lock_value, $new_value, $key, $expected ) = $this->args;
			if ( serialize( get_option( $lock_key ) ) !== $lock_value || serialize( get_option( $key ) ) !== $expected ) { return 0; }
			$GLOBALS['job_options'][ $key ] = unserialize( $new_value ); return 1;
		}
		if ( 0 === strpos( $sql, 'DELETE job' ) ) {
			list( $lock_key, $key, $expected, $lock_value ) = $this->args;
			$actual_lock = get_option( $lock_key, null );
			if ( serialize( get_option( $key ) ) !== $expected || ( null === $actual_lock ? '' : serialize( $actual_lock ) ) !== $lock_value ) { return 0; }
			delete_option( $key ); return 1;
		}
		list( $key, $serialized ) = $this->args;
		if ( isset( $GLOBALS['job_options'][ $key ] ) && serialize( $GLOBALS['job_options'][ $key ] ) === $serialized ) { delete_option( $key ); return 1; } return 0;
	}
	public function get_var( $sql ) { $this->last_error = ''; return isset( $GLOBALS['job_options'][ $this->args[0] ] ) ? serialize( $GLOBALS['job_options'][ $this->args[0] ] ) : null; }
	public function get_results( $sql, $format ) {
		$this->last_error = ''; $rows = array();
		foreach ( $GLOBALS['live_profiles'] ?? array() as $profile ) {
			if ( false !== strpos( $sql, 'term_relationships' ) ) { $rows[] = array( 'object_id' => $profile['centro_id'], 'term_id' => $profile['region_id'], 'name' => $profile['region_name'] ); }
			else { foreach ( array( 'region' => $profile['region_id'], 'circuito' => $profile['circuito'], 'estado_centro' => 'activo' ) as $key => $value ) { $rows[] = array( 'ID' => $profile['centro_id'], 'post_status' => 'publish', 'meta_key' => $key, 'meta_value' => $value ); } }
		}
		return $rows;
	}
}
$wpdb = new Impact_Job_DB(); $job_options = array(); $job_events = array(); $cron_spawns = 0;
if ( ! in_array( '--real-scope', $argv, true ) ) {
function gnf_get_report_snapshot( $year ) { $GLOBALS['requested_year'] = $year; return $GLOBALS['snapshot']; }
function gnf_scope_report_snapshot( $snapshot, $region = 0, $circuit = '', $mode = 'active', $sources = array() ) {
	$GLOBALS['scope_calls'][] = array( $region, $circuit, $mode, $sources );
	return $GLOBALS['scope_result'];
}
// The real snapshot module owns this helper; this fixture exercises its iterable contract.
function gnf_report_indicator_export_rows( $scoped ) {
	$GLOBALS['indicator_calls']++;
	$headers = array( 'Grupo', 'Territorio' );
	foreach ( $scoped['impact']['catalog'] as $metric ) { $headers[] = $metric['title'] . ' (' . $metric['unit'] . ')'; }
	yield $headers;
	foreach ( array( 'total', 'regions', 'circuits' ) as $group ) {
		$items = 'total' === $group ? array( $scoped['impact']['total'] ) : $scoped['impact'][ $group ];
		foreach ( $items as $item ) {
			$row = array( $group, $item['label'] );
			foreach ( $scoped['impact']['catalog'] as $metric ) { $row[] = $item['values'][ $metric['key'] ] ?? 0; }
			yield $row;
		}
	}
}
}
$tests = 0; $fails = 0; $logged = true; $supervisor = true; $admin = false;
$scope_calls = array(); $nonce_actions = array(); $indicator_calls = 0;
function check_impact_export( $ok, $message ) {
	global $tests, $fails; $tests++; $fails += $ok ? 0 : 1;
	echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $message . "\n";
}
$module = ABSPATH . 'includes/impact-exports.php';
check_impact_export( file_exists( $module ), 'Independent impact export module exists' );
if ( ! file_exists( $module ) ) { echo "\n{$tests} checks, {$fails} failures\n"; exit( 1 ); }
require $module;
if ( in_array( '--real-scope', $argv, true ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'ARRAY_A', 'ARRAY_A' );
	function add_filter() {}
	function absint( $value ) { return abs( (int) $value ); }
	function gnf_normalize_year( $value ) { return $value ?: 2026; }
	function gnf_normalize_circuito( $value ) { return '' === (string) $value ? '' : str_pad( (string) (int) $value, 2, '0', STR_PAD_LEFT ); }
	require ABSPATH . 'includes/report-snapshots.php';
}
$scoped = array(
	'ready' => true, 'year' => 2026, 'generatedAt' => '2026-10-08 10:30:00',
	'filters' => array( 'region' => 2, 'circuit' => '01', 'mode' => 'approved', 'sources' => array( 'reto-siembra-de-arboles', 'inscripcion' ) ),
	'summary' => array( 'totalCentros' => 1, 'totalAprobados' => 2, 'promedioPuntaje' => 15.5, 'promedioEstrellas' => 2, 'centrosConEvidencias' => 1 ),
	'impact' => array(
		'catalog' => array(
			array( 'key' => 'trees', 'title' => 'Arboles', 'unit' => 'arboles', 'source' => 'reto-siembra-de-arboles', 'sourceLabel' => 'Respuestas <ambientales>', 'available' => true ),
			array( 'key' => 'migrants', 'title' => 'Estudiantes migrantes', 'unit' => 'estudiantes', 'source' => 'inscripcion', 'sourceLabel' => 'Centro & matricula', 'available' => true ),
			array( 'key' => 'missing', 'title' => 'Sin medicion', 'unit' => 'kg', 'source' => 'reto-siembra-de-arboles', 'sourceLabel' => 'Respuestas <ambientales>', 'available' => false ),
		),
		'total' => array( 'label' => 'Total autorizado', 'values' => array( 'trees' => 7, 'migrants' => 3, 'missing' => 0 ) ),
		'regions' => array( 2 => array( 'label' => 'Heredia <script>alert(1)</script>', 'values' => array( 'trees' => 7, 'migrants' => 3, 'missing' => 0 ) ) ),
		'circuits' => array( '2|01' => array( 'label' => 'Circuito 01', 'regionName' => 'Heredia', 'circuito' => '01', 'values' => array( 'trees' => 7, 'migrants' => 3, 'missing' => 0 ) ) ),
	),
	'centros' => array( array(
		'profile' => array( 'centro_id' => 12, 'nombre' => '=Escuela <Norte>', 'region_id' => 2, 'region_name' => 'Heredia', 'circuito' => '01', 'codigo_mep' => '0012', 'total_estudiantes' => 24,
			'matricula_id' => 120, 'matricula_estado' => 'activa', 'coordinador_nombre' => 'PERSONAL_PRIVATE', 'docente_names' => array( 'DOCENTE_PRIVATE' ), 'docente_emails' => array( 'private@example.test' ), 'user_pass' => 'SECRET_FORBIDDEN', 'diagnostico' => 'Completo' ),
		'stats' => array( 'puntaje' => 15.5, 'aprobados' => 2, 'estrellas' => 2, 'evidencias' => 3 ),
		'values' => array( 'active' => array( 'trees' => 19, 'migrants' => 3 ), 'approved' => array( 'trees' => 7, 'migrants' => 3 ) ),
		'retos' => array(
			array( 'id' => 9, 'title' => 'Siembra <img src="https://evil.test/x">', 'source' => 'reto-siembra-de-arboles', 'state' => 'aprobado', 'points' => 15.5, 'evidenceCounts' => array( 'total' => 3, 'approved' => 2, 'pending' => 1 ) ),
			array( 'id' => 10, 'title' => 'EXCLUDED_CHALLENGE', 'source' => 'reto-agua', 'state' => 'pendiente', 'points' => 0, 'evidenceCounts' => array( 'excludedCount' => 99 ) ),
		),
	) ),
);
$snapshot = $scoped; $scope_result = $scoped;
if ( in_array( '--real-scope', $argv, true ) ) {
	$outside = $scoped['centros'][0]; $outside['profile']['centro_id'] = 99; $outside['profile']['nombre'] = 'OUTSIDE_DRE'; $outside['profile']['region_id'] = 9; $outside['profile']['region_name'] = 'OUTSIDE_REGION';
	$outside['values']['approved']['trees'] = 900;
	$other_circuit = $scoped['centros'][0]; $other_circuit['profile']['centro_id'] = 98; $other_circuit['profile']['nombre'] = 'OUTSIDE_CIRCUIT'; $other_circuit['profile']['circuito'] = '02';
	$other_circuit['values']['approved']['trees'] = 800;
	$raw = array( 'ready' => true, 'year' => 2026, 'generatedAt' => $scoped['generatedAt'], 'generatedTimestamp' => time(), 'centros' => array( $scoped['centros'][0], $outside, $other_circuit ), 'impact' => array( 'active' => array( 'catalog' => $scoped['impact']['catalog'] ), 'approved' => array( 'catalog' => $scoped['impact']['catalog'] ) ) );
	$live_profiles = array_column( $raw['centros'], 'profile' );
	update_option( gnf_report_snapshot_key( 2026 ), $raw );
	$r = gnf_scope_report_snapshot( $raw, 0, '', 'approved', array( 'reto-siembra-de-arboles' ) );
	check_impact_export( 1 === count( $r['centros'] ) && 7 === $r['impact']['total']['values']['trees'], 'Real scope enforces assigned DRE/circuit on collective totals' );
	check_impact_export( 2 === count( $r['impact']['catalog'] ), 'Real scope selects full source group including unavailable indicator' );
	check_impact_export( is_wp_error( gnf_scope_report_snapshot( $raw, 9 ) ) && is_wp_error( gnf_scope_report_snapshot( $raw, 2, '02' ) ), 'Real scope rejects forged DRE and circuit' );
	$request = array( '_wpnonce' => 'fresh', 'year' => 2026, 'mode' => 'approved', 'sources' => array( 'reto-siembra-de-arboles' ), 'format' => 'pdf', 'detail' => 'full' );
	$p = gnf_prepare_impact_export_request( $request );
	check_impact_export( ! is_wp_error( $p ) && 7 === $p['scoped']['impact']['total']['values']['trees'], 'Actual snapshot request is reauthorized and scoped' );
	foreach ( array( 'indicators', 'centros', 'retos' ) as $dataset ) {
		$path = tempnam( sys_get_temp_dir(), 'gnf-real-' );
		$result = gnf_write_impact_xlsx( $r, $path, $dataset );
		check_impact_export( ! is_wp_error( $result ), $dataset . ' real snapshot helper works without conflict' );
		$zip = new ZipArchive(); $zip->open( $path ); $xml = '';
		for ( $i = 0; $i < $zip->numFiles; $i++ ) { if ( strpos( $zip->getNameIndex( $i ), 'worksheets/' ) !== false ) { $xml .= $zip->getFromIndex( $i ); } }
		check_impact_export( false === strpos( $xml, 'OUTSIDE_' ) && false === strpos( $xml, 'EXCLUDED_CHALLENGE' ) && false === strpos( $xml, 'Estudiantes migrantes (' ), $dataset . ' real scoped XLSX never leaks unauthorized centers or sources' );
		check_impact_export( false !== strpos( $xml, 'Arboles (arboles)' ) && preg_match( '~t="n"[^>]*><v>7</v>~', $xml ), $dataset . ' real numeric filtered total' );
		$zip->close(); @unlink( $path );
	}
	foreach ( array( 'summary', 'full' ) as $detail ) {
		$html = gnf_render_impact_pdf_html( $r, $detail );
		check_impact_export( false === strpos( $html, 'OUTSIDE_' ) && false === strpos( $html, 'PERSONAL_PRIVATE' ) && false === strpos( $html, 'Estudiantes migrantes' ), $detail . ' real scope PDF has no PII or unselected territory/source' );
	}
	$assigned_circuit = '02'; $p = gnf_prepare_impact_export_request( $request );
	check_impact_export( ! is_wp_error( $p ) && 800 === $p['scoped']['impact']['total']['values']['trees'], 'Changed current assignment scopes a previously signed request again' );
	$admin = true; $assigned_circuit = '';
	$all = gnf_scope_report_snapshot( $raw, 0, '', 'approved' );
	check_impact_export( 1707 === $all['impact']['total']['values']['trees'], 'Real admin total spans all authorized regions/circuits' );
	$filtered = gnf_scope_report_snapshot( $raw, 2, '01', 'active' );
	check_impact_export( 19 === $filtered['impact']['total']['values']['trees'], 'Real admin filter preserves active vector independently of approved' );
	echo "\n{$tests} checks, {$fails} failures\n"; exit( $fails ? 1 : 0 );
}
foreach ( array( 'gnf_write_impact_xlsx', 'gnf_render_impact_pdf_html', 'gnf_get_impact_export_urls', 'gnf_prepare_impact_export_request', 'gnf_generate_impact_pdf' ) as $name ) {
	check_impact_export( function_exists( $name ), $name . ' callable' );
}
$urls = gnf_get_impact_export_urls( 2026, $scoped['filters'] + array( 'signedURL' => 'never-export', 'token' => 'secret' ) );
check_impact_export( array( 'indicators', 'centros', 'retos', 'pdfSummary', 'pdfFull' ) === array_keys( $urls ), 'All five download variants' );
foreach ( $urls as $key => $url ) {
	parse_str( parse_url( $url, PHP_URL_QUERY ), $query );
	check_impact_export( 'gnf_export_impact' === $query['action'] && 'fresh' === $query['_wpnonce'] && '2026' === $query['year'], $key . ' fresh nonce and year' );
	check_impact_export( '01' === $query['circuit'] && $query['sources'] === array( 'reto-siembra-de-arboles', 'inscripcion' ) && ! isset( $query['token'] ), $key . ' allowlisted filters' );
}
check_impact_export( 5 === count( $nonce_actions ) && array( 'gnf_export_impact' ) === array_values( array_unique( $nonce_actions ) ), 'Nonces generated outside snapshot with correct action' );
$request = array( 'year' => '2026', '_wpnonce' => 'fresh', 'region' => '2', 'circuit' => '01', 'mode' => 'approved', 'sources' => array( 'reto-siembra-de-arboles' ), 'dataset' => 'retos', 'format' => 'xlsx', 'detail' => 'full', 'signedURL' => 'yes' );
$prepared = gnf_prepare_impact_export_request( $request );
check_impact_export( ! is_wp_error( $prepared ) && $prepared['scoped'] === $scoped && 2026 === $requested_year, 'Endpoint uses scoped snapshot' );
check_impact_export( end( $scope_calls ) === array( 2, '01', 'approved', array( 'reto-siembra-de-arboles' ) ), 'All filters delegated to current authorization scope' );
$scope_result = new WP_Error( 'report_forbidden', 'Region no autorizada.', array( 'status' => 403 ) );
check_impact_export( is_wp_error( gnf_prepare_impact_export_request( $request ) ), 'Signed URL cannot bypass revoked scope' );
$scope_result = $scoped;
$logged = false; check_impact_export( is_wp_error( gnf_prepare_impact_export_request( $request ) ), 'Anonymous denied' ); $logged = true;
$supervisor = false; check_impact_export( is_wp_error( gnf_prepare_impact_export_request( $request ) ), 'Teacher denied' );
$admin = true; check_impact_export( ! is_wp_error( gnf_prepare_impact_export_request( $request ) ), 'Admin allowed with current scope' ); $admin = false; $supervisor = true;
check_impact_export( is_wp_error( gnf_prepare_impact_export_request( array_replace( $request, array( '_wpnonce' => 'expired' ) ) ) ), 'Expired nonce denied even signed URL' );
foreach ( array( 'dataset' => 'unknown', 'format' => 'csv', 'detail' => 'raw', 'mode' => 'all', 'year' => array( 2026 ), 'region' => '-2', 'circuit' => array( '01' ), 'sources' => array( array( 'response' ) ), '_wpnonce' => array( 'fresh' ) ) as $key => $bad ) {
	check_impact_export( is_wp_error( gnf_prepare_impact_export_request( array_replace( $request, array( $key => $bad ) ) ) ), 'Malformed ' . $key . ' rejected' );
}
$snapshot = array( 'ready' => false ); $scope_result = $snapshot;
$cold = gnf_prepare_impact_export_request( $request );
check_impact_export( is_wp_error( $cold ) && 503 === $cold->get_error_data()['status'], 'Cold snapshot recoverable 503' );
$snapshot = $scoped; $scope_result = $scoped;
$bad_year = gnf_prepare_impact_export_request( array_replace( $request, array( 'year' => '9999' ) ) );
check_impact_export( is_wp_error( $bad_year ) && 400 === $bad_year->get_error_data()['status'], 'Year 9999 rejected before snapshot normalization' );
$html = gnf_render_impact_pdf_html( $scoped, 'summary' );
$full = gnf_render_impact_pdf_html( $scoped, 'full' );
check_impact_export( is_string( $html ) && false !== strpos( $html, 'Panel de Impacto' ), 'Executive PDF brand heading' );
check_impact_export( false !== strpos( $html, '2026-10-08 10:30:00' ) && false !== strpos( $html, 'Validados' ) && false === strpos( $html, '(approved)' ), 'PDF cutoff and Spanish criterion without internal mode key' );
check_impact_export( false !== strpos( $html, 'Año' ) && false !== strpos( $html, 'Direcciones Regionales' ) && false !== strpos( $html, 'Región' ), 'PDF metadata has Spanish accents' );
check_impact_export( false !== strpos( $html, 'Respuestas &lt;ambientales&gt;' ) && false !== strpos( $html, 'Centro &amp; matricula' ), 'Source groups escaped' );
check_impact_export( false !== strpos( $html, 'Sin medicion' ) && false !== strpos( $html, 'No disponible' ), 'Unavailable indicators are not reported as measured zero' );
check_impact_export( false !== strpos( $html, 'bar-fill' ), 'PDF uses CSS charts' );
check_impact_export( false !== strpos( $full, 'Heredia &lt;script&gt;' ) && false === strpos( $full, '<script>' ), 'Territory names escaped' );
check_impact_export( strlen( $full ) > strlen( $html ) && false !== strpos( $full, 'Circuito 01' ), 'Full PDF adds territorial tables' );
foreach ( array( $html, $full ) as $document ) {
	check_impact_export( false === strpos( $document, 'PERSONAL_PRIVATE' ) && false === strpos( $document, 'DOCENTE_PRIVATE' ) && false === strpos( $document, 'private@example.test' ) && false === strpos( $document, 'SECRET_FORBIDDEN' ), 'Collective PDF excludes personal data and secrets' );
	check_impact_export( false === strpos( $document, '<img' ) && false === strpos( $document, 'https://evil' ), 'No remote images or raw challenge markup' );
	check_impact_export( false !== strpos( $document, 'table-header-group' ) && false !== strpos( $document, 'position: fixed' ), 'Repeated table and page headers' );
}
check_impact_export( is_wp_error( gnf_render_impact_pdf_html( array( 'ready' => false ), 'full' ) ), 'Pure renderer rejects cold data' );
check_impact_export( is_wp_error( gnf_render_impact_pdf_html( $scoped, 'raw' ) ), 'Pure renderer rejects unknown detail' );
$null_fixture = $scoped;
$null_fixture['impact']['catalog'][0]['title'] = 'NULL_MEASUREMENT';
$null_fixture['impact']['total']['values']['trees'] = null;
$null_fixture['impact']['regions'][2]['values']['trees'] = null;
$null_fixture['centros'][0]['values']['approved']['trees'] = null;
check_impact_export( false !== strpos( gnf_render_impact_pdf_html( $null_fixture ), 'Sin datos' ), 'Available null PDF measurement stays unknown, not zero' );
$many = $scoped;
for ( $i = 0; $i < 12; $i++ ) {
	$metric = $scoped['impact']['catalog'][0]; $metric['key'] = 'metric' . $i; $metric['title'] = 'SELECTED_TOTAL_' . $i;
	$many['impact']['catalog'][] = $metric; $many['impact']['total']['values'][ $metric['key'] ] = $i + 1;
	$many['impact']['regions'][2]['values'][ $metric['key'] ] = $i + 1;
}
$many_html = gnf_render_impact_pdf_html( $many );
check_impact_export( substr_count( $many_html, '<div class="chart">' ) <= 6 && substr_count( $many_html, '<div class="chart">' ) > 0, 'Executive report has at most six charts' );
for ( $i = 0; $i < 12; $i++ ) { check_impact_export( false !== strpos( $many_html, 'SELECTED_TOTAL_' . $i . '</td>' ), 'Executive retains selected total ' . $i ); }
$large = $many; $large['impact']['regions'] = array(); $large['impact']['circuits'] = array();
for ( $i = 0; $i < 100; $i++ ) { $large['impact']['circuits'][ '2|' . $i ] = array( 'label' => 'Circuito ' . $i, 'regionName' => 'Heredia', 'values' => $many['impact']['total']['values'] ); }
for ( $i = 0; $i < 30; $i++ ) { $large['impact']['regions'][ $i ] = array( 'label' => 'Region ' . $i, 'values' => $many['impact']['total']['values'] ); }
$large_html = gnf_render_impact_pdf_html( $large, 'full' );
check_impact_export( substr_count( $large_html, '<tr>' ) < 800, 'Full report groups metric columns instead of multiplying a row for every territory/metric' );
$territorial_html = substr( $large_html, strpos( $large_html, '<div class="territorial">' ) );
preg_match_all( '/<table class="data">(.*?)<\/table>/s', $territorial_html, $territorial_tables );
check_impact_export( ! array_filter( $territorial_tables[1], static function ( $table ) { return substr_count( $table, '<tr>' ) > 26; } ), 'Territorial tables bound layout to 25 data rows without omitting circuits' );
$empty = $scoped; $empty['centros'] = array(); $empty['impact']['catalog'] = array(); $empty['impact']['regions'] = array(); $empty['impact']['circuits'] = array();
check_impact_export( false !== strpos( gnf_render_impact_pdf_html( $empty, 'full' ), 'Sin indicadores' ), 'Empty catalog has explicit PDF state' );
require_once ABSPATH . 'includes/xlsx-writer.php';
$temps_before = glob( sys_get_temp_dir() . '/gnf-xlsx-*' );
foreach ( array( 'indicators', 'centros', 'retos' ) as $dataset ) {
	$path = tempnam( sys_get_temp_dir(), 'gnf-impact-test-' );
	try {
		$result = gnf_write_impact_xlsx( $scoped, $path, $dataset );
		check_impact_export( ! is_wp_error( $result ) && filesize( $path ) > 0, $dataset . ' actual XLSX generated' );
		$zip = new ZipArchive(); $zip->open( $path );
		$workbook = $zip->getFromName( 'xl/workbook.xml' ); $xml = ''; $sheets = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = $zip->getNameIndex( $i );
			if ( preg_match( '~^xl/worksheets/sheet[0-9]+\.xml$~', $name ) ) { $sheets[] = $zip->getFromIndex( $i ); }
			if ( preg_match( '~\.(xml|rels)$~', $name ) ) { check_impact_export( false !== simplexml_load_string( $zip->getFromIndex( $i ) ), $dataset . ' valid ' . $name ); }
		}
		$xml = implode( '', $sheets );
		check_impact_export( false !== strpos( $workbook, 'Resumen' ) && false !== strpos( $workbook, 'Indicadores' ) && false !== strpos( $workbook, 'Centros' ) && count( $sheets ) >= 4, $dataset . ' multisheet package' );
		check_impact_export( false !== strpos( $xml, 'Arboles (arboles)' ) && preg_match( '~t="n"[^>]*><v>7</v>~', $xml ), $dataset . ' canonical indicator header and numeric total' );
		check_impact_export( false !== strpos( $xml, '2026-10-08 10:30:00' ) && false !== strpos( $xml, 'Validados' ) && false !== strpos( $xml, 'Respuestas &lt;ambientales&gt;' ), $dataset . ' cutoff mode and source labels' );
		check_impact_export( false === strpos( $xml, 'SECRET_FORBIDDEN' ) && false === strpos( $xml, '<f>' ), $dataset . ' no secrets or executable formulas' );
		check_impact_export( false !== strpos( $xml, 'PERSONAL_PRIVATE' ) && false !== strpos( $xml, 'DOCENTE_PRIVATE' ) && false !== strpos( $xml, 'Matr' ) && false !== strpos( $xml, '0012' ), $dataset . ' canonical operational enrollment and teacher columns' );
		if ( 'retos' === $dataset ) {
			check_impact_export( false !== strpos( $workbook, 'Retos' ) && false !== strpos( $xml, 'Siembra &lt;img' ) && false !== strpos( $xml, 'aprobado' ), 'Challenge rows carry escaped titles states points and evidence counts' );
			check_impact_export( false === strpos( $xml, 'EXCLUDED_CHALLENGE' ) && false === strpos( $xml, 'excludedCount' ), 'Challenge rows and evidence columns respect selected sources' );
		}
		$zip->close();
	} finally { @unlink( $path ); }
}
check_impact_export( 3 === $indicator_calls, 'Uses snapshot-owned indicator row helper without redefining' );
$path = tempnam( sys_get_temp_dir(), 'gnf-impact-fail-' );
check_impact_export( is_wp_error( gnf_write_impact_xlsx( $scoped, $path, 'unknown' ) ), 'Unknown dataset rejected' );
check_impact_export( is_wp_error( gnf_write_impact_xlsx( array( 'ready' => false ), $path, 'centros' ) ), 'XLSX rejects cold snapshot' );
@unlink( $path );
$bad_path = sys_get_temp_dir() . '/gnf-missing-' . uniqid() . '/file.xlsx';
check_impact_export( is_wp_error( gnf_write_impact_xlsx( $scoped, $bad_path, 'centros' ) ), 'XLSX write failure informative' );
check_impact_export( $temps_before === glob( sys_get_temp_dir() . '/gnf-xlsx-*' ), 'Writer temporaries cleaned after success and failure' );
$legacy = tempnam( sys_get_temp_dir(), 'gnf-legacy-' );
$writer = new GNF_XLSX_Writer( $legacy, 'Legacy' ); $writer->add_row( array( 'ID' ), true ); $writer->add_row( array( 2 ) ); $writer->close();
$zip = new ZipArchive(); $zip->open( $legacy );
check_impact_export( 1 === substr_count( $zip->getFromName( 'xl/workbook.xml' ), '<sheet ' ), 'Legacy single-sheet API unchanged' ); $zip->close(); @unlink( $legacy );
check_impact_export( isset( $GLOBALS['hooks']['admin_post_gnf_export_impact'] ) && ! isset( $GLOBALS['hooks']['admin_post_nopriv_gnf_export_impact'] ), 'Only authenticated admin-post hook registered' );
check_impact_export( function_exists( 'gnf_get_impact_pdf_job' ), 'Background PDF job service exists' );
check_impact_export( function_exists( 'gnf_impact_pdf_insert' ) && function_exists( 'gnf_impact_pdf_owned_update' ), 'Atomic insert and fenced job update helpers exist' );
if ( function_exists( 'gnf_impact_pdf_owned_update' ) ) {
	$atomic_key = 'gnf_impact_pdf_job_' . str_repeat( 'a', 64 ); $lease_key = str_replace( '_job_', '_lock_', $atomic_key );
	$owner = array( 'token' => 'owner', 'expires' => time() + 600 ); $successor = array( 'token' => 'successor', 'expires' => time() + 600 );
	check_impact_export( 1 === gnf_impact_pdf_insert( $lease_key, $owner ) && 0 === gnf_impact_pdf_insert( $lease_key, $successor ) && get_option( $lease_key ) === $owner, 'INSERT IGNORE admits exactly one lease owner without upsert' );
	$first = array( 'generation' => 'first', 'status' => 'queued', 'expires' => time() + 3600 );
	check_impact_export( 1 === gnf_impact_pdf_insert( $atomic_key, $first ) && 0 === gnf_impact_pdf_insert( $atomic_key, array( 'generation' => 'duplicate' ) ) && get_option( $atomic_key ) === $first, 'Job dedupe never overwrites an existing generation' );
	$before_sql = static function () use ( $lease_key, $successor ) { update_option( $lease_key, $successor ); };
	check_impact_export( 0 === gnf_impact_pdf_owned_update( $atomic_key, $first, array( 'status' => 'ready' ), $lease_key, $owner ) && get_option( $atomic_key ) === $first, 'Lease takeover immediately before SQL blocks stale worker publication' );
	check_impact_export( ! gnf_impact_pdf_compare_delete( $lease_key, $owner ) && get_option( $lease_key ) === $successor, 'Stale release cannot delete successor lease' );
	$before_sql = static function () use ( $lease_key, $owner ) { update_option( $lease_key, $owner ); };
	check_impact_export( false === gnf_cleanup_impact_pdf_job( $atomic_key, true, $first ) && get_option( $atomic_key ) === $first && get_option( $lease_key ) === $owner, 'Cleanup fenced against a lease takeover cannot delete successor state' );
	$sql_failure = 'UPDATE';
	check_impact_export( false === gnf_impact_pdf_owned_update( $atomic_key, $first, array( 'status' => 'ready' ), $lease_key, $owner ) && get_option( $atomic_key ) === $first, 'SQL checkpoint failure retains last good state and reports failure' );
	$expired_owner = $owner; $expired_owner['expires'] = time() - 1; update_option( $lease_key, $expired_owner );
	check_impact_export( 0 === gnf_impact_pdf_owned_update( $atomic_key, $first, array( 'status' => 'failed' ), $lease_key, $expired_owner ) && get_option( $atomic_key ) === $first, 'Expired owner cannot persist failure over last good state' );
	gnf_cleanup_impact_pdf_job( $atomic_key, true, $first );
	$sql_failure = 'INSERT IGNORE';
	check_impact_export( is_wp_error( gnf_get_impact_pdf_job( $scoped, 'full' ) ), 'SQL job insertion failure never reports queued success' );
}
if ( function_exists( 'gnf_get_impact_pdf_job' ) ) {
	$job = gnf_get_impact_pdf_job( $scoped, 'full' );
	check_impact_export( 'queued' === $job['status'] && empty( $job['path'] ), 'Download preparation queues PDF without rendering' );
	$again = gnf_get_impact_pdf_job( $scoped, 'full' );
	check_impact_export( $job['key'] === $again['key'] && 2 === count( $job_events ), 'Atomic dedupe schedules one worker and one TTL cleanup' );
	$record = get_option( $job['key'] ); $serialized = serialize( $record );
	$automatic_dispatch = ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) && ! ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON );
	check_impact_export( wp_next_scheduled( 'gnf_run_impact_pdf_job', array( $job['key'] ) ) <= time() && ( $automatic_dispatch ? $cron_spawns > 0 : 0 === $cron_spawns ), 'PDF worker is immediately due; nonblocking dispatch respects disabled or alternate cron configuration' );
	check_impact_export( $record['expires'] - $record['created'] === 7200, 'Private PDF cache lasts two hours instead of rebuilding after each download' );
	check_impact_export( ! isset( $record['payload']['centros'] ) && false === strpos( $serialized, 'PERSONAL_PRIVATE' ) && false === strpos( $serialized, 'private@example.test' ) && false === strpos( $serialized, 'nonce' ) && false === strpos( $serialized, 'https://' ), 'Queued PDF payload excludes PII URLs and nonces' );
	$allowed_regions = array( 2, 9 ); $changed = gnf_get_impact_pdf_job( $scoped, 'full' );
	check_impact_export( $changed['key'] !== $job['key'], 'Changed region assignment cannot reuse cached PDF' ); $allowed_regions = array( 2 );
	$assigned_circuit = '02'; $changed = gnf_get_impact_pdf_job( $scoped, 'full' );
	check_impact_export( $changed['key'] !== $job['key'], 'Changed assigned circuit cannot reuse cached PDF' ); $assigned_circuit = '01';
	$user_id = 8; $changed = gnf_get_impact_pdf_job( $scoped, 'full' );
	check_impact_export( $changed['key'] !== $job['key'], 'Different user cannot reuse cached PDF' ); $user_id = 7;
	$new_cut = $scoped; $new_cut['generatedAt'] = '2026-10-08 11:30:00'; $changed = gnf_get_impact_pdf_job( $new_cut, 'full' );
	check_impact_export( $changed['key'] !== $job['key'], 'Different snapshot cutoff cannot reuse cached PDF' );
	$new_filter = $scoped; $new_filter['filters']['sources'] = array( 'inscripcion' ); $changed = gnf_get_impact_pdf_job( $new_filter, 'full' );
	check_impact_export( $changed['key'] !== $job['key'], 'Different selected sources cannot reuse cached PDF' );
	$movement = $scoped; $second = $scoped['centros'][0]; $second['profile']['centro_id'] = 14; $second['profile']['circuito'] = '02';
	$movement['centros'][] = $second; $movement['impact']['circuits']['2|02'] = $movement['impact']['circuits']['2|01'];
	$movement_key = gnf_impact_pdf_job_key( $movement, 'full' );
	$movement['centros'][0]['profile']['circuito'] = '02'; $movement['centros'][1]['profile']['circuito'] = '01';
	check_impact_export( $movement_key !== gnf_impact_pdf_job_key( $movement, 'full' ), 'Center circuit swap invalidates signature with identical IDs territory keyset and cutoff' );
	$movement_key = gnf_impact_pdf_job_key( $movement, 'full' );
	$movement['centros'][0]['profile']['region_id'] = 3; $movement['centros'][1]['profile']['region_id'] = 4;
	check_impact_export( $movement_key !== gnf_impact_pdf_job_key( $movement, 'full' ), 'Per-center live region movement invalidates signature independently of territory keyset' );
	$waiting = gnf_render_impact_export_waiting_html( $scoped );
	check_impact_export( false !== strpos( $waiting, 'content="5"' ) && false !== strpos( $waiting, 'Volver al panel' ) && false === strpos( $waiting, 'PERSONAL_PRIVATE' ), 'Waiting page refreshes every five seconds and links back without PII' );
	$delayed = gnf_render_impact_export_waiting_html( $scoped, array( 'status' => 'queued', 'created' => time() - 310 ) );
	check_impact_export( false !== strpos( $delayed, 'En cola' ) && false !== strpos( $delayed, 'no ha iniciado' ) && false !== strpos( $delayed, 'Descargar Excel' ), 'Long queued wait exposes actual state and offers a scoped XLSX alternative' );
	$working = gnf_render_impact_export_waiting_html( $scoped, array( 'status' => 'working', 'created' => time() - 30 ) );
	check_impact_export( false !== strpos( $working, 'Generando PDF' ) && false === strpos( $working, 'no ha iniciado' ), 'Waiting page distinguishes active rendering from a stalled queue' );
	preg_match( '/href="([^"]+)"/', $waiting, $backlink );
	parse_str( parse_url( html_entity_decode( $backlink[1], ENT_QUOTES, 'UTF-8' ), PHP_URL_QUERY ), $back_filters );
	check_impact_export( '2026' === $back_filters['year'] && '2' === ( $back_filters['region'] ?? '' ) && '01' === ( $back_filters['circuit'] ?? '' ) && 'approved' === ( $back_filters['mode'] ?? '' ) && implode( ',', $scoped['filters']['sources'] ) === ( $back_filters['sources'] ?? '' ), 'Waiting backlink preserves filters in the panels comma-separated source format' );
	$lock_key = str_replace( 'gnf_impact_pdf_job_', 'gnf_impact_pdf_lock_', $job['key'] );
	add_option( $lock_key, array( 'token' => 'other-worker', 'expires' => time() + 600 ) );
	gnf_run_impact_pdf_job( $job['key'] );
	check_impact_export( 'queued' === get_option( $job['key'] )['status'], 'Concurrent worker cannot render a locked job' );
	update_option( $lock_key, array( 'token' => 'stale-worker', 'expires' => time() - 1 ) );
	gnf_run_impact_pdf_job( $job['key'] );
	$ready_job = gnf_get_impact_pdf_job( $scoped, 'full' );
	check_impact_export( 'ready' === $ready_job['status'] && is_file( $ready_job['path'] ) && '%PDF-' === substr( file_get_contents( $ready_job['path'] ), 0, 5 ), 'Cron recovers stale lock and prepares private PDF' );
	check_impact_export( strpos( realpath( $ready_job['path'] ), realpath( sys_get_temp_dir() ) ) === 0 && strpos( $ready_job['path'], 'uploads' ) === false, 'Prepared PDF is outside public uploads' );
	check_impact_export( ! get_option( $lock_key ), 'Worker releases its own lock' );
	$events_before = $job_events; $spawns_before = $cron_spawns;
	$cached = gnf_get_impact_pdf_job( $scoped, 'full' );
	check_impact_export( 'ready' === $cached['status'] && $cached['path'] === $ready_job['path'] && $job_events === $events_before && $cron_spawns === $spawns_before, 'Repeated authorized requests reuse the prepared artifact without scheduling another render' );
	$handler = substr( file_get_contents( $module ), strpos( file_get_contents( $module ), 'function gnf_handle_export_impact()' ) );
	check_impact_export( false === strpos( $handler, 'gnf_cleanup_impact_pdf_job(' ), 'Download handler leaves cached private PDF for TTL cleanup instead of deleting it on delivery' );
	$sql_failure = 'DELETE job'; $last_good = get_option( $job['key'] );
	check_impact_export( false === gnf_cleanup_impact_pdf_job( $job['key'], true, $last_good ) && is_file( $ready_job['path'] ) && get_option( $job['key'] ) === $last_good, 'SQL cleanup failure retains ready artifact and persistent state' );
	$scope_result = new WP_Error( 'forbidden', 'Sin alcance.', array( 'status' => 403 ) );
	check_impact_export( is_wp_error( gnf_prepare_impact_export_request( $request ) ), 'Ready artifact still requires current scope authorization' ); $scope_result = $scoped;
	check_impact_export( is_wp_error( gnf_prepare_impact_export_request( array_replace( $request, array( '_wpnonce' => 'expired' ) ) ) ), 'Ready artifact still requires fresh nonce' );
	gnf_cleanup_impact_pdf_job( $job['key'], true );
	check_impact_export( ! is_file( $ready_job['path'] ) && ! get_option( $job['key'] ), 'Delivered artifact and job removed' );
	$unsafe = gnf_get_impact_pdf_job( $scoped, 'full' ); $record = get_option( $unsafe['key'] );
	$record['status'] = 'ready'; $record['path'] = ABSPATH . 'composer.json'; update_option( $unsafe['key'], $record );
	check_impact_export( is_wp_error( gnf_get_impact_pdf_job( $scoped, 'full' ) ) && is_file( ABSPATH . 'composer.json' ), 'Job path tampering cannot serve or delete files outside private storage' );
	$ttl_job = gnf_get_impact_pdf_job( $scoped, 'summary' ); gnf_run_impact_pdf_job( $ttl_job['key'] );
	$record = get_option( $ttl_job['key'] ); $ttl_path = $record['path'];
	gnf_cleanup_impact_pdf_job( $ttl_job['key'] );
	check_impact_export( is_file( $ttl_path ), 'Early cleanup cannot delete an unexpired artifact' );
	$record['expires'] = time() - 1; update_option( $ttl_job['key'], $record ); gnf_cleanup_impact_pdf_job( $ttl_job['key'] );
	check_impact_export( ! is_file( $ttl_path ) && ! get_option( $ttl_job['key'] ), 'TTL cron deletes abandoned ready artifacts and records' );
	$expired = gnf_get_impact_pdf_job( $scoped, 'full' ); $record = get_option( $expired['key'] ); $record['expires'] = time() - 1; update_option( $expired['key'], $record );
	check_impact_export( is_wp_error( gnf_get_impact_pdf_job( $scoped, 'full' ) ), 'Expired preparation ends polling with recoverable error' );
	$failed = gnf_get_impact_pdf_job( $scoped, 'full' ); $record = get_option( $failed['key'] ); $record['payload']['ready'] = false; update_option( $failed['key'], $record );
	gnf_run_impact_pdf_job( $failed['key'] );
	check_impact_export( is_wp_error( gnf_get_impact_pdf_job( $scoped, 'full' ) ), 'Worker failure is recoverable and ends polling' );
	$retry = gnf_get_impact_pdf_job( $scoped, 'full' );
	check_impact_export( 'queued' === $retry['status'], 'Explicit retry can prepare a new job after failure' );
	foreach ( array_keys( $job_options ) as $key ) { if ( 0 === strpos( $key, 'gnf_impact_pdf_job_' ) ) { gnf_cleanup_impact_pdf_job( $key, true ); } }
}
$review_dir = '';
foreach ( $argv as $arg ) { if ( 0 === strpos( $arg, '--review-dir=' ) ) { $review_dir = substr( $arg, 13 ); } }
if ( file_exists( ABSPATH . 'vendor/autoload.php' ) ) {
		$pdf_scoped = $scoped;
		if ( $review_dir ) {
			require_once ABSPATH . 'includes/impact-metrics.php';
			$pdf_scoped = gnf_impact_pdf_payload( $scoped );
			$pdf_scoped['filters'] = array( 'region' => 0, 'circuit' => '', 'mode' => 'approved', 'sources' => array() );
			$pdf_scoped['summary'] = array( 'totalCentros' => 20, 'totalAprobados' => 58, 'promedioPuntaje' => 64.5, 'promedioEstrellas' => 3.2, 'centrosConEvidencias' => 18 );
			$catalog = array_values( gnf_get_impact_metric_catalog( 2026 ) );
			foreach ( $catalog as &$metric ) {
				$metric['source'] = ! empty( $metric['reto'] ) ? 'reto-' . $metric['reto'] : ( 'center' === $metric['source'] ? 'inscripcion' : 'eco-retos' );
				$metric['sourceLabel'] = ! empty( $metric['reto'] ) ? ucfirst( str_replace( '-', ' ', $metric['reto'] ) ) : ( 'inscripcion' === $metric['source'] ? 'Inscripcion' : 'Eco Retos' );
				$metric['available'] = 'comodin_centros' !== $metric['key'];
			}
			unset( $metric );
			$pdf_scoped['impact'] = array( 'catalog' => $catalog, 'total' => array( 'values' => array() ), 'regions' => array(), 'circuits' => array() );
			$region_count = in_array( '--review-large', $argv, true ) ? 60 : 4;
			foreach ( $catalog as $index => $metric ) {
				$total = 0;
				for ( $rid = 1; $rid <= $region_count; $rid++ ) {
					$region_name = array( 'Heredia', 'Cartago', 'Nicoya', 'San Jose' )[ ( $rid - 1 ) % 4 ] . ( $rid > 4 ? ' ' . $rid : '' );
					$pdf_scoped['impact']['regions'][ $rid ]['label'] = $region_name;
					$regional = 0;
					for ( $c = 1; $c <= 2; $c++ ) {
						$key = $rid . '|' . $c; $value = ( $index % 5 + 1 ) * $c;
						$pdf_scoped['impact']['circuits'][ $key ]['label'] = 'Circuito 0' . $c;
						$pdf_scoped['impact']['circuits'][ $key ]['regionName'] = $region_name;
						$pdf_scoped['impact']['circuits'][ $key ]['values'][ $metric['key'] ] = $value; $regional += $value;
					}
					$pdf_scoped['impact']['regions'][ $rid ]['values'][ $metric['key'] ] = $regional; $total += $regional;
				}
				$pdf_scoped['impact']['total']['values'][ $metric['key'] ] = $total;
			}
		}
		if ( $review_dir && ! is_dir( $review_dir ) ) { mkdir( $review_dir, 0755, true ); }
		foreach ( array( 'summary', 'full' ) as $detail ) {
			$pdf_path = $review_dir ? $review_dir . '/impact-' . $detail . '.pdf' : tempnam( sys_get_temp_dir(), 'gnf-impact-pdf-' );
			$previous_errors = error_reporting(); ob_start();
			$result = gnf_generate_impact_pdf( $pdf_scoped, $pdf_path, $detail );
			$noise = ob_get_clean();
			check_impact_export( '' === $noise && $previous_errors === error_reporting(), $detail . ' Composer deprecations cannot corrupt downloads and error mask restored' );
			check_impact_export( ! is_wp_error( $result ) && '%PDF-' === substr( file_get_contents( $pdf_path ), 0, 5 ), $detail . ' PDF generated with bundled Dompdf' );
			if ( ! $review_dir ) { @unlink( $pdf_path ); }
		}
		check_impact_export( is_wp_error( gnf_generate_impact_pdf( $scoped, sys_get_temp_dir() . '/gnf-missing-' . uniqid() . '/file.pdf', 'summary' ) ), 'PDF write failure informative' );
}
echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
