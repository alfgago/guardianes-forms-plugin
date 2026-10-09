<?php
define( 'ABSPATH', dirname( __DIR__ ) . '/' ); define( 'WP_CLI', true );
class WP_CLI { static function log( $value ) { $GLOBALS['cli_output'] = $value; } static function error( $value ) { throw new RuntimeException( $value ); } }
class BlockedPreparation extends RuntimeException { public $status; function __construct( $status ) { $this->status = $status; parent::__construct( 'Blocked' ); } }
function add_action( ...$args ) {}
function wpforms() { return true; }
function gnf_get_available_retos_for_year( $year ) { return $GLOBALS['retos']; }
function gnf_get_reto_canonical_slug( $title ) { return $title; }
function gnf_get_reto_form_id_for_year( $id, $year ) { return $GLOBALS['links'][$id] ?? $id + 100; }
function get_post( $id ) { return isset( $GLOBALS['forms'][$id] ) ? (object) array( 'ID' => $id, 'post_type' => 'wpforms', 'post_content' => $GLOBALS['forms'][$id] ) : null; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function add_option( $key, $value, $unused = '', $autoload = null ) { if ( $GLOBALS['backup_failure'] || ( $GLOBALS['backup_failure_key'] && strpos( $key, $GLOBALS['backup_failure_key'] ) !== false ) || isset( $GLOBALS['options'][$key] ) ) { return false; } $GLOBALS['options'][$key] = $value; return true; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][$key] = $value; return true; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
function wp_slash( $value ) { return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value ); }
function is_wp_error( $value ) { return false; }
function wp_update_post( $data, $error = false ) { $GLOBALS['writes']++; $GLOBALS['forms'][$data['ID']] = stripslashes( $data['post_content'] ); return $data['ID']; }
function current_user_can( $cap ) { return $GLOBALS['admin']; }
function wp_die( $message, $title, $args ) { throw new BlockedPreparation( $args['response'] ); }
function check_admin_referer( ...$args ) {}
function admin_url( $url ) { return '/wp-admin/' . $url; }
function add_query_arg( $args, $url ) { return $url; }
function wp_safe_redirect( $url ) { throw new RuntimeException( 'Unexpected preparation redirect' ); }
function sanitize_key( $value ) { return $value; }
function esc_html( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $value ) { return $value; }
function wp_nonce_field( ...$args ) { echo '<input name="nonce">'; }
function submit_button( ...$args ) { echo '<button>Preparar campos</button>'; }
require ABSPATH . 'includes/impact-metrics.php';
$tests = 0; $fails = 0;
function check_preparation( $ok, $label ) { global $tests, $fails; $tests++; $fails += $ok ? 0 : 1; echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $label . "\n"; }
function reset_preparation() {
	$GLOBALS['retos'] = array( (object) array( 'ID' => 1, 'post_title' => 'limpiezas' ) );
	$GLOBALS['forms'] = array( 101 => json_encode( array( 'fields' => array( 5 => array( 'id' => 5, 'type' => 'file-upload', 'label' => 'Foto "terminada"' ) ), 'field_id' => 20, 'settings' => array( 'private' => 'DO NOT EXPORT SETTINGS' ) ) ) );
	$GLOBALS['links'] = array(); $GLOBALS['options'] = array( 'gnf_impact_fields_schema_2026' => 1 );
	$GLOBALS['writes'] = 0; $GLOBALS['backup_failure'] = false; $GLOBALS['backup_failure_key'] = ''; $GLOBALS['admin'] = true;
	$_GET = array( 'page' => 'guardianes-config', 'gnf_impact_fields_result' => 'success' );
}
reset_preparation(); ob_start(); gnf_render_impact_fields_admin_notice(); $notice = ob_get_clean();
check_preparation( false === strpos( $notice, '<form' ) && false === strpos( $notice, '<button' ) && false === strpos( $notice, 'gnf_prepare_impact_fields' ) && 0 === $writes, 'Config status is read-only and contains no preparation action' );
reset_preparation(); $forms[101] = '{invalid'; ob_start(); gnf_render_impact_fields_admin_notice(); $notice = ob_get_clean();
check_preparation( false !== strpos( $notice, 'requiere revisi') && false === strpos( $notice, 'quedaron preparados') && 0 === $writes, 'Live invalid definitions override stale prepared flags and old success query parameters' );
foreach ( array( true => 410, false => 403 ) as $allowed => $expected ) {
	reset_preparation(); $admin = (bool) $allowed; $status = null;
	try { gnf_handle_prepare_impact_fields(); } catch ( BlockedPreparation $error ) { $status = $error->status; } catch ( RuntimeException $error ) {}
	check_preparation( $expected === $status && 0 === $writes, 'Legacy preparation endpoint cannot mutate forms (status ' . $expected . ')' );
}
reset_preparation();
if ( ! function_exists( 'gnf_plan_impact_fields_for_year' ) ) { check_preparation( false, 'Read-only migration planner exists' ); echo "{$tests} checks, {$fails} failures\n"; exit( 1 ); }
$before = $forms; $options_before = $options; $plan = gnf_plan_impact_fields_for_year( 2026 );
check_preparation( $before === $forms && $options_before === $options && 0 === $writes && empty( $plan['errors'] ), 'Planning never changes forms or options' );
check_preparation( 64 === strlen( $plan['signature'] ) && $plan['signature'] === gnf_plan_impact_fields_for_year( 2026 )['signature'], 'Plan signature is stable for unchanged inputs' );
$tool = ABSPATH . 'tools/prepare-impact-fields.php';
if ( ! file_exists( $tool ) ) { check_preparation( false, 'CLI-only controlled preparation tool exists' ); echo "{$tests} checks, {$fails} failures\n"; exit( 1 ); }
$args = array( '2026' ); require $tool;
$result = json_decode( $cli_output, true );
check_preparation( 'simulacion' === $result['resultado'] && 0 === $writes && $options_before === $options, 'CLI defaults to preview with no writes or backups' );
check_preparation( 2 === count( $result['formularios'][0]['campos_nuevos'] ) && false === strpos( $cli_output, 'DO NOT EXPORT SETTINGS' ) && false === strpos( $cli_output, 'post_content' ), 'Preview lists added questions but excludes full definitions and private settings' );
$blocked = false;
try { gnf_prepare_impact_fields_cli( array( '2026', 'aplicar' ) ); } catch ( RuntimeException $error ) { $blocked = true; }
check_preparation( $blocked && 0 === $writes, 'Application without the reviewed signature is rejected' );
$signature = $plan['signature']; $data = json_decode( $forms[101], true ); $data['fields'][5]['label'] = 'Changed meanwhile'; $forms[101] = json_encode( $data ); $options_before = $options;
$blocked = false;
try { gnf_prepare_impact_fields_cli( array( '2026', 'aplicar', 'firma=' . $signature ) ); } catch ( RuntimeException $error ) { $blocked = true; }
check_preparation( $blocked && 0 === $writes && $options_before === $options, 'Stale reviewed signature cannot apply edits or change options' );
reset_preparation(); $retos[] = (object) array( 'ID' => 2, 'post_title' => 'huerta' ); $forms[102] = '{invalid'; $before = $forms;
check_preparation( ! gnf_ensure_impact_fields_for_year( 2026 ) && 0 === $writes && $before === $forms, 'Global preflight prevents edits to healthy forms if a later definition is corrupt' );
$preview = gnf_prepare_impact_fields_cli( array( '2026' ) );
check_preparation( 'simulacion_bloqueada' === $preview['resultado'] && 1 === count( $preview['errores'] ), 'Preview explains invalid definitions without reconstructing them' );
reset_preparation(); $retos[] = (object) array( 'ID' => 2, 'post_title' => 'huerta' ); $links[2] = 101;
$plan = gnf_plan_impact_fields_for_year( 2026 );
check_preparation( ! empty( $plan['errors'] ) && 0 === $writes, 'Ambiguous shared form mappings block a migration rather than overwriting each other' );
reset_preparation(); $data = json_decode( $forms[101], true ); $data['fields'][5]['id'] = 99; $forms[101] = json_encode( $data );
$plan = gnf_plan_impact_fields_for_year( 2026 );
check_preparation( ! empty( $plan['errors'] ) && 0 === $writes, 'Valid JSON with inconsistent question IDs cannot be prepared' );
reset_preparation(); $preview = gnf_prepare_impact_fields_cli( array( '2026' ) );
$result = gnf_prepare_impact_fields_cli( array( '2026', 'aplicar', 'firma=' . $preview['firma'] ) );
check_preparation( 'aplicado' === $result['resultado'] && 1 === $writes && is_array( json_decode( $forms[101], true ) ), 'Explicit application of the reviewed plan keeps stored JSON valid' );
check_preparation( count( array_filter( array_keys( $options ), static function ( $key ) { return strpos( $key, 'gnf_impact_form_backup_2026_101_' ) === 0; } ) ) === 1, 'Each migration keeps a verified snapshot of the actual pre-write definition' );
$before = $forms[101]; $preview = gnf_prepare_impact_fields_cli( array( '2026' ) );
gnf_prepare_impact_fields_cli( array( '2026', 'aplicar', 'firma=' . $preview['firma'] ) );
check_preparation( $before === $forms[101] && 1 === $writes, 'Repeating preparation does not rewrite or duplicate questions' );
reset_preparation(); $backup_failure = true; $preview = gnf_prepare_impact_fields_cli( array( '2026' ) ); $blocked = false;
try { gnf_prepare_impact_fields_cli( array( '2026', 'aplicar', 'firma=' . $preview['firma'] ) ); } catch ( RuntimeException $error ) { $blocked = true; }
check_preparation( $blocked && 0 === $writes, 'A missing verified backup prevents every form write' );
reset_preparation(); $retos[] = (object) array( 'ID' => 2, 'post_title' => 'limpiezas' ); $forms[102] = $forms[101]; $before = $forms; $backup_failure_key = '_102_';
$preview = gnf_prepare_impact_fields_cli( array( '2026' ) ); $blocked = false;
try { gnf_prepare_impact_fields_cli( array( '2026', 'aplicar', 'firma=' . $preview['firma'] ) ); } catch ( RuntimeException $error ) { $blocked = true; }
check_preparation( $blocked && 0 === $writes && $before === $forms, 'Failure to back up a later form prevents mutation of the first form' );
reset_preparation(); $snapshot = 'gnf_impact_form_backup_2026_101_' . md5( $forms[101] ); $options[$snapshot] = array( 'post_content' => '{unrelated' );
$preview = gnf_prepare_impact_fields_cli( array( '2026' ) ); $blocked = false;
try { gnf_prepare_impact_fields_cli( array( '2026', 'aplicar', 'firma=' . $preview['firma'] ) ); } catch ( RuntimeException $error ) { $blocked = true; }
check_preparation( $blocked && 0 === $writes, 'An existing snapshot must match the exact current definition' );
echo "{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
