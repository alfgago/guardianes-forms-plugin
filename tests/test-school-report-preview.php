<?php
// Isolated WordPress boundaries; exercise the real signed cookie and PDF service.
define( 'ABSPATH', __DIR__ . '/../' );
class WP_User {
	public $ID;
	public $roles;
	function __construct( $id, $roles ) { $this->ID = $id; $this->roles = $roles; }
}
class WP_Error {
	function __construct( $code, $message ) { $this->message = $message; }
	public $message;
	function get_error_message() { return $this->message; }
}
class SchoolReportStop extends RuntimeException {}
function add_action() {}
function absint( $n ) { return abs( (int) $n ); }
function sanitize_text_field( $s ) { return trim( strip_tags( $s ) ); }
function wp_unslash( $s ) { return stripslashes( $s ); }
function wp_hash( $s ) { return hash_hmac( 'sha256', $s, 'test-only-secret' ); }
function get_current_user_id() { return $GLOBALS['current_id']; }
function get_userdata( $id ) { return $GLOBALS['users'][ $id ] ?? false; }
function wp_get_current_user() { return get_userdata( get_current_user_id() ); }
function user_can( $id, $cap ) { return 'manage_options' === $cap && in_array( 'administrator', get_userdata( $id )->roles ?? array(), true ); }
function current_user_can( $cap ) { return user_can( get_current_user_id(), $cap ); }
function gnf_user_has_role( $user, $role ) { return in_array( $role, $user->roles ?? array(), true ); }
function gnf_get_docente_estado( $id ) { return $GLOBALS['teacher_state']; }
function gnf_user_receives_only_rejections( $id ) { return gnf_user_has_role( get_userdata( $id ), 'docente' ) && ! user_can( $id, 'manage_options' ); }
function gnf_user_can_access_centro( $id, $centro ) { return 10 === $centro; }
function gnf_feature_is_enabled_for_center( $feature, $centro, $default ) { return $GLOBALS['reports_enabled']; }
function gnf_normalize_year( $year ) { return $year ?: 2026; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function gnf_get_centro_matricula_estado( $centro, $year ) { return $GLOBALS['annual_state']; }
function gnf_get_centro_retos_seleccionados( $centro, $year ) { return array( 1 ); }
function gnf_summarize_docente_entries( $entries, $selected ) { return array( 'allComplete' => $GLOBALS['complete'] ); }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . $path; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function wp_nonce_url( $url, $action ) { return htmlspecialchars( $url . '&_wpnonce=' . rawurlencode( $action ), ENT_QUOTES, 'UTF-8' ); }
function check_admin_referer( $action ) {
	$GLOBALS['nonce_checks']++;
	if ( ( $_GET['_wpnonce'] ?? '' ) !== $action ) { throw new SchoolReportStop( 'nonce' ); }
}
function wp_die( $message, $title = '', $args = array() ) { throw new SchoolReportStop( (string) ( $args['response'] ?? 500 ) ); }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
function esc_html( $v ) { return htmlspecialchars( $v ); }
function get_post( $id ) { return (object) array( 'ID' => $id, 'post_type' => 'centro_educativo', 'post_title' => 'Escuela de prueba' ); }
function gnf_build_centros_export_batch_maps( $ids, $year ) { return array(); }
function gnf_build_centro_export_record( $id, $year, $batch ) { return array( 'nombre' => 'Escuela de prueba' ); }
function gnf_get_reto_max_points( $id, $year ) { return 10; }
function gnf_get_assigned_center_award( $id, $year ) { return $GLOBALS['assigned']; }
function gnf_get_center_award_result( $id, $year, $mode ) { return array( 'stars' => 5, 'score' => 25 ); }
function wp_tempnam( $name ) { $GLOBALS['pdf_temp'] = tempnam( sys_get_temp_dir(), 'gnf-school-' ); return $GLOBALS['pdf_temp']; }
function sanitize_title( $v ) { return 'escuela-de-prueba'; }
function gnf_prepare_file_download_response() { throw new SchoolReportStop( 'ready' ); }
function gnf_log_audit_event( $key, $args ) { $GLOBALS['audit'][] = array( $key, $args ); }
class SchoolReportDatabase {
	public $prefix = 'wp_';
	public $report_queries = 0;
	function prepare( $sql, ...$args ) { return $sql; }
	function get_results( $sql ) { $this->report_queries++; return array(); }
}
$wpdb = new SchoolReportDatabase();
$users = array( 1 => new WP_User( 1, array( 'docente' ) ), 2 => new WP_User( 2, array( 'administrator' ) ), 3 => new WP_User( 3, array( 'supervisor' ) ) );
$current_id = 1; $teacher_state = 'activo'; $annual_state = 'aprobada'; $reports_enabled = true;
$complete = false; $assigned = array(); $nonce_checks = 0; $audit = array();
$options = array();
require ABSPATH . 'includes/impersonate.php';
require ABSPATH . 'includes/report-pdf.php';
$tests = 0; $fails = 0;
function check_school_preview( $ok, $message ) {
	global $tests, $fails;
	$tests++; $fails += $ok ? 0 : 1;
	echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $message . "\n";
}
function school_cookie( $id ) { $_COOKIE[ GNF_IMPERSONATE_COOKIE ] = $id . '|' . wp_hash( 'gnf_impersonate_' . $id ); }
function school_download_stop() {
	try { gnf_handle_download_center_report_pdf(); } catch ( SchoolReportStop $e ) { return $e->getMessage(); }
	return '';
}
check_school_preview( ! gnf_user_can_download_center_report( 10, 2026 ), 'Ordinary teacher cannot download an incomplete report' );
$complete = true;
check_school_preview( ! gnf_user_can_download_center_report( 10, 2026 ), 'Ordinary teacher cannot download even a completed report' );
check_school_preview( '' === gnf_get_center_report_download_url( 10, 2026 ), 'No signed URL is exposed to an ordinary teacher' );
$_GET = array( 'centro_id' => 10, 'year' => 2026 );
check_school_preview( '403' === school_download_stop() && 0 === $nonce_checks, 'Direct teacher download is denied before generation and nonce processing' );
$complete = false;
school_cookie( 2 );
check_school_preview( gnf_user_can_download_center_report( 10, 2026 ), 'Signed administrator impersonation can download before completion' );
check_school_preview( false !== strpos( gnf_get_center_report_download_url( 10, 2026 ), 'gnf_download_center_report_10_2026' ), 'Preview URL keeps the center and year nonce' );
$download_url = gnf_get_center_report_download_url( 10, 2026 );
parse_str( parse_url( $download_url, PHP_URL_QUERY ), $download_args );
check_school_preview( false === strpos( $download_url, '&amp;' ) && '10' === ( $download_args['centro_id'] ?? '' ) && '2026' === ( $download_args['year'] ?? '' ) && 'gnf_download_center_report_10_2026' === ( $download_args['_wpnonce'] ?? '' ), 'React navigation URL exposes actual center, year and nonce query parameters, not HTML entities' );
$_GET = $download_args;
$nonce_checks_before = $nonce_checks;
$previous = error_reporting(); error_reporting( $previous & ~E_DEPRECATED & ~E_USER_DEPRECATED );
$download_result = school_download_stop();
error_reporting( $previous );
check_school_preview( 'ready' === $download_result && $nonce_checks_before + 1 === $nonce_checks, 'Navigating the generated preview URL reaches PDF generation with its verified nonce' );
if ( isset( $pdf_temp ) && file_exists( $pdf_temp ) ) { unlink( $pdf_temp ); }
$_GET = array( 'centro_id' => 10, 'year' => 2026 );
check_school_preview( ! gnf_user_can_download_center_report( 20, 2026 ), 'Impersonation does not grant another school access' );
$reports_enabled = false;
check_school_preview( gnf_user_can_download_center_report( 10, 2026 ), 'Administrative preview is available without releasing reports to teachers' );
$reports_enabled = true;
$complete = true;
$_COOKIE[ GNF_IMPERSONATE_COOKIE ] = '2|forged';
check_school_preview( ! gnf_user_can_download_center_report( 10, 2026 ), 'Forged administrative cookie grants no download permission' );
school_cookie( 3 );
check_school_preview( ! gnf_user_can_download_center_report( 10, 2026 ), 'Signed cookie from a nonadministrator grants no permission' );
school_cookie( 99 );
check_school_preview( ! gnf_user_can_download_center_report( 10, 2026 ), 'Deleted original administrator grants no permission' );
school_cookie( 2 ); $teacher_state = 'pendiente';
check_school_preview( ! gnf_user_can_download_center_report( 10, 2026 ), 'Inactive teacher impersonation remains blocked' );
$teacher_state = 'activo'; $current_id = 0;
check_school_preview( ! gnf_user_can_download_center_report( 10, 2026 ), 'Anonymous request cannot use a signed impersonation cookie' );
$current_id = 2;
check_school_preview( gnf_user_can_download_center_report( 20, 2026 ), 'Existing direct administrator downloads are preserved' );
$current_id = 3;
check_school_preview( gnf_user_can_download_center_report( 10, 2026 ), 'Existing scoped reviewer download is preserved' );
$reports_enabled = false;
check_school_preview( ! gnf_user_can_download_center_report( 10, 2026 ), 'Reviewer reports still obey their rollout gate' );
$reports_enabled = true; $current_id = 1; school_cookie( 2 );
$complete = false;
check_school_preview( 'nonce' === school_download_stop(), 'Authorized preview still requires the download nonce' );

$available = function_exists( 'gnf_is_admin_docente_report_preview' ) && function_exists( 'gnf_get_docente_school_report_payload' );
check_school_preview( $available, 'Shared server helpers expose signed preview visibility and year-specific payload' );
if ( $available ) {
	check_school_preview( gnf_is_admin_docente_report_preview(), 'Only a signed administrator currently acting as an active teacher has preview visibility' );
	$payload = gnf_get_docente_school_report_payload( 10, 2026 );
	check_school_preview( $payload['canDownloadSchoolReport'] && $payload['reportPdfProvisional'] && 'draft' === $payload['reportPdfStatus'], 'Incomplete open year exposes an enabled provisional report' );
	$complete = true;
	$payload = gnf_get_docente_school_report_payload( 10, 2026 );
	check_school_preview( $payload['reportPdfProvisional'] && 'final' === $payload['reportPdfStatus'], 'Evidence completion alone does not finalize an open year' );
	$annual_state = 'cerrada';
	check_school_preview( gnf_get_docente_school_report_payload( 10, 2026 )['reportPdfProvisional'], 'Center enrollment closed but annual program open stays provisional' );
	$annual_state = 'aprobada';
	$options['gnf_program_year_closed_2026'] = true;
	check_school_preview( ! gnf_get_docente_school_report_payload( 10, 2026 )['reportPdfProvisional'], 'Explicitly closed program year with completed review is no longer provisional, regardless of enrollment status' );
	$complete = false;
	check_school_preview( gnf_get_docente_school_report_payload( 10, 2026 )['reportPdfProvisional'], 'Explicitly closed program year with incomplete review remains provisional' );
	$complete = true;
	check_school_preview( gnf_get_docente_school_report_payload( 10, 2025 )['reportPdfProvisional'], 'Closing one program year does not finalize another year' );
	foreach ( array( false, 0, '0', 'false', 'yes', 2 ) as $open_value ) {
		$options['gnf_program_year_closed_2026'] = $open_value;
		check_school_preview( gnf_get_docente_school_report_payload( 10, 2026 )['reportPdfProvisional'], 'Nonexplicit closure value ' . var_export( $open_value, true ) . ' remains provisional' );
	}
	foreach ( array( true, 1, '1' ) as $closed_value ) {
		$options['gnf_program_year_closed_2026'] = $closed_value;
		check_school_preview( ! gnf_get_docente_school_report_payload( 10, 2026 )['reportPdfProvisional'], 'Explicit closure value ' . var_export( $closed_value, true ) . ' with completed review finalizes the report' );
	}
	$complete = false;
	$queries_before = $wpdb->report_queries;
	$provided_final = gnf_get_docente_school_report_payload( 10, 2026, 'final' );
	check_school_preview( 'final' === $provided_final['reportPdfStatus'] && ! $provided_final['reportPdfProvisional'] && $queries_before === $wpdb->report_queries, 'Provided final review status is reused without querying entries' );
	$complete = true;
	$provided_draft = gnf_get_docente_school_report_payload( 10, 2026, 'draft' );
	check_school_preview( 'draft' === $provided_draft['reportPdfStatus'] && $provided_draft['reportPdfProvisional'] && $queries_before === $wpdb->report_queries, 'Provided draft review status remains provisional without querying entries' );
	$calculated = gnf_get_docente_school_report_payload( 10, 2026, null );
	check_school_preview( 'final' === $calculated['reportPdfStatus'] && $queries_before + 1 === $wpdb->report_queries, 'Null review status preserves the existing database-backed fallback' );
	$options = array();
	unset( $_COOKIE[ GNF_IMPERSONATE_COOKIE ] );
	$hidden = gnf_get_docente_school_report_payload( 10, 2026 );
	check_school_preview( ! $hidden['canDownloadSchoolReport'] && '' === $hidden['reportPdfUrl'], 'Ordinary teachers receive no report action or URL' );
	$current_id = 2;
	check_school_preview( ! gnf_is_admin_docente_report_preview(), 'Direct admin session is not teacher impersonation' );
	$current_id = 3; school_cookie( 2 );
	check_school_preview( ! gnf_is_admin_docente_report_preview(), 'Supervisor impersonation is not teacher impersonation' );
	$current_id = 1;
}

$annual_state = 'aprobada'; $complete = true; school_cookie( 2 );
$report = gnf_build_center_report_data( 10, 2026 );
$html = gnf_render_center_report_html( $report );
check_school_preview( false !== stripos( $html, 'provisional' ), 'PDF identifies a completed but open-year report as provisional' );
check_school_preview( false !== strpos( $html, 'Pendiente de asignación' ), 'Preview does not publish calculated stars' );
$assigned = array( 'result' => array( 'stars' => 2, 'awards' => array() ) );
$published = gnf_render_center_report_html( gnf_build_center_report_data( 10, 2026 ) );
check_school_preview( false !== strpos( $published, '<div>2</div>' ) && false === strpos( $published, 'Pendiente de asignación' ), 'Only assigned stars are visible in the preview' );
$_GET['_wpnonce'] = 'gnf_download_center_report_10_2026';
$previous = error_reporting(); error_reporting( $previous & ~E_DEPRECATED & ~E_USER_DEPRECATED );
$stop = school_download_stop();
error_reporting( $previous );
$signature = isset( $pdf_temp ) ? file_get_contents( $pdf_temp, false, null, 0, 4 ) : '';
check_school_preview( 'ready' === $stop && '%PDF' === $signature, 'Authorized handler generates a real PDF with Dompdf' );
$event = end( $audit );
check_school_preview( $event && 'admin_download_school_report_preview' === $event[0] && 2 === $event[1]['actor_user_id'] && 1 === $event[1]['target_user_id'] && 10 === $event[1]['centro_id'] && 2026 === $event[1]['anio'] && true === $event[1]['meta']['provisional'], 'Preview download audit identifies original admin, teacher, school, year and provisional state' );
if ( isset( $pdf_temp ) ) { unlink( $pdf_temp ); }

// Render the actual TSX in memory: no browser, bundle, or generated dist files.
$ui_test = <<<'JS'
const fs = require('node:fs');
const vm = require('node:vm');
const root = process.argv[1];
const req = require('node:module').createRequire(root + '/app/package.json');
const ts = req('typescript');
const React = req('react');
const { renderToStaticMarkup } = req('react-dom/server');
let dashboard;
const placeholder = () => null;
function load(relative, dependencies) {
  const code = ts.transpileModule(fs.readFileSync(root + '/app/src/' + relative, 'utf8'), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, jsx: ts.JsxEmit.ReactJSX, target: ts.ScriptTarget.ES2020 }
  }).outputText;
  const exports = {};
  vm.runInNewContext(code, { exports, require: name => dependencies[name] ?? req(name) });
  return exports;
}
const hero = load('panels/docente/components/ProgressHero.tsx', {
  '@/components/ui/ProgressBar': { ProgressBar: placeholder }
});
const page = load('panels/docente/pages/ResumenPage.tsx', {
  '@tanstack/react-query': { useQuery: options => ({ data: options.queryKey[0] === 'docente-dashboard' ? dashboard : [], isLoading: false }) },
  '@/api/retos': { retosApi: {} },
  '@/stores/useYearStore': { useYearStore: () => 2026 },
  '@/components/ui/Spinner': { Spinner: placeholder },
  '@/components/ui/Alert': { Alert: placeholder },
  '@/components/domain/CentroCard': { CentroCard: placeholder },
  '../components/RetoGrid': { RetoGrid: placeholder },
  '../components/ProgressHero': hero
});
const base = { centro: { id: 10, nombre: 'Escuela' }, docenteEstado: 'activo', retosCount: 1, puntajeTotal: 25,
  evidenceCounts: { pending: 1, approved: 0, rejected: 0, total: 1 }, reportPdfUrl: '/signed-report', reportPdfStatus: 'draft' };
let failures = 0;
function check(ok, name) { console.log((ok ? 'ok: ' : 'FAIL: ') + name); if (!ok) failures++; }
function render(extra) { dashboard = { ...base, ...extra }; return renderToStaticMarkup(React.createElement(page.ResumenPage, {})); }
function hasReport(html) { return /Reporte final|Descargar Reporte/.test(html); }
check(!hasReport(render({})), 'UI hides reports when preview permission is absent, even with a stale signed URL');
check(!hasReport(render({ canDownloadSchoolReport: false, reportPdfStatus: 'final' })), 'UI hides completed report from an ordinary teacher');
const preview = render({ canDownloadSchoolReport: true, reportPdfProvisional: true });
check(preview.includes('Descargar Reporte') && !/disabled=""[^>]*title="[^"]*reporte/i.test(preview), 'UI enables Descargar Reporte before completion');
check(preview.includes('Reporte provisional'), 'UI shows provisional state separately from the download label');
check(!hasReport(render({ canDownloadSchoolReport: true, reportPdfUrl: '' })), 'UI exposes no dead download action without a signed URL');
check(!render({ canDownloadSchoolReport: true, reportPdfProvisional: false, reportPdfStatus: 'final' }).includes('Reporte provisional'), 'UI removes provisional indicator when year and review are finalized');
process.exitCode = failures ? 1 : 0;
JS;
$process = proc_open( array( 'node', '-e', $ui_test, realpath( ABSPATH ) ), array( 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
if ( is_resource( $process ) ) {
	echo stream_get_contents( $pipes[1] );
	$errors = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] ); fclose( $pipes[2] );
	$ui_exit = proc_close( $process );
	if ( $errors ) { echo $errors; }
	check_school_preview( 0 === $ui_exit, 'Teacher TSX rendered behavior checks pass' );
} else {
	check_school_preview( false, 'Node UI test could start' );
}
echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
