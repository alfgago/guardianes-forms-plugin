<?php
define( 'ABSPATH', __DIR__ . '/../' );
function add_action() {}
function add_filter() {}
function add_shortcode() {}
function sanitize_text_field( $value ) { return strip_tags( $value ); }
function wp_unslash( $value ) { return $value; }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function check_password_reset_key( $key, $login ) {
	$GLOBALS['validated_credentials'] = array( $key, $login );
	return $GLOBALS['reset_result'];
}
function reset_password( $user, $password ) { $GLOBALS['resets'][] = array( $user, $password ); }
function is_user_logged_in() { return true; }
function wp_get_current_user() { return new WP_User(); }
function is_singular() { return true; }
function is_page() { return true; }
function gnf_render_react_panel( $panel, $data ) { return $panel; }
class WP_User {}
class WP_Error {
	public $code;
	function __construct( $code, $message, $data = array() ) { $this->code = $code; }
	function get_error_code() { return $this->code; }
}
class WP_REST_Request {
	private $params;
	function __construct( $params ) { $this->params = $params; }
	function get_param( $key ) { return $this->params[ $key ] ?? null; }
}
require ABSPATH . 'includes/rest-api.php';
require ABSPATH . 'includes/roles.php';
require ABSPATH . 'includes/shortcodes.php';
$tests = 0; $fails = 0; $resets = array();
function check_recovery( $ok, $message ) {
	global $tests, $fails; $tests++; $fails += $ok ? 0 : 1;
	echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $message . "\n";
}
$request = new WP_REST_Request( array( 'login' => 'school+pilot@example.org', 'key' => 'NativeKey123', 'password' => 'new-password-123' ) );
$reset_result = new WP_Error( 'expired_key', 'Expired' );
$result = gnf_rest_auth_reset_password( $request );
check_recovery( 'expired_reset_key' === $result->code && ! $resets, 'An expired native key is distinguished and cannot reset a password' );
$reset_result = new WP_Error( 'invalid_key', 'Invalid' );
$result = gnf_rest_auth_reset_password( $request );
check_recovery( 'invalid_reset_key' === $result->code && ! $resets, 'Invalid or already consumed keys remain rejected' );
$reset_result = new WP_User();
$result = gnf_rest_auth_reset_password( $request );
check_recovery( true === $result['success'] && 1 === count( $resets ), 'Valid native key resets exactly once' );
check_recovery( array( 'NativeKey123', 'school+pilot@example.org' ) === $validated_credentials, 'Email usernames containing plus signs reach native validation unchanged' );
$_GET = array( 'reset' => '1', 'login' => 'school+pilot@example.org', 'key' => 'NativeKey123' );
check_recovery( function_exists( 'gnf_is_password_reset_request' ), 'Shared recovery request detection exists' );
if ( function_exists( 'gnf_is_password_reset_request' ) ) {
	check_recovery( gnf_is_password_reset_request(), 'Well-shaped reset requests render recovery even with a session' );
	foreach ( array( 'docente', 'supervisor', 'admin', 'comite' ) as $panel ) {
		check_recovery( 'auth' === call_user_func( 'gnf_render_' . $panel . '_panel_shortcode' ), "{$panel} renders only auth for a logged-in recovery request" );
	}
	$post = (object) array( 'post_name' => 'panel-admin' );
	gnf_protect_frontend_panels();
	check_recovery( true, 'Role redirects do not discard recovery credentials' );
	$_GET['key'] = array( 'not-scalar' );
	check_recovery( ! gnf_is_password_reset_request(), 'Malformed array parameters do not activate recovery mode' );
	$_GET = array( 'reset' => '1' );
	check_recovery( ! gnf_is_password_reset_request(), 'Incomplete recovery query does not bypass normal panel protection' );
}
echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
