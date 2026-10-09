<?php
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
$admin = true;
function add_action() {}
function absint( $value ) { return abs( (int) $value ); }
function current_user_can() { return $GLOBALS['admin']; }
function get_current_user_id() { return 1; }
function get_userdata( $id ) { return in_array( $id, array( 1, 600, 700 ), true ) ? (object) array( 'ID' => $id ) : false; }
function user_can( $id ) { return in_array( $id, array( 1, 700 ), true ); }
function admin_url( $path = '' ) { return 'https://example.org/wp-admin/' . $path; }
function wp_validate_redirect( $url, $fallback ) { return 0 === strpos( $url, 'https://example.org/' ) ? $url : $fallback; }
function add_query_arg( $args, $url ) {
    $parts = parse_url( $url );
    $query = array(); parse_str( $parts['query'] ?? '', $query );
    $query = array_merge( $query, $args );
    return $parts['scheme'] . '://' . $parts['host'] . $parts['path'] . '?' . http_build_query( $query );
}
// Match WordPress: nonce URLs are returned escaped for HTML, not JSON navigation.
function wp_nonce_url( $url, $action ) {
    $GLOBALS['nonce_action'] = $action;
    return htmlspecialchars( add_query_arg( array( '_wpnonce' => 'valid-test-nonce' ), str_replace( '&amp;', '&', $url ) ), ENT_QUOTES, 'UTF-8' );
}
require ABSPATH . 'includes/impersonate.php';
$return = 'https://example.org/panel-admin/?p=centros&year=2026&region=2&search=Escuela%20Prueba';
$url = gnf_build_impersonate_url( 600, $return );
if ( in_array( '--url-only', $argv, true ) ) { echo json_encode( array( 'url' => $url, 'returnUrl' => $return ) ); exit; }
$tests = $fails = 0;
function check_impersonation_url( $ok, $message ) { global $tests, $fails; $tests++; if ( ! $ok ) { $fails++; } echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $message . "\n"; }
$params = array(); parse_str( parse_url( $url, PHP_URL_QUERY ), $params );
check_impersonation_url( '600' === ( $params['user_id'] ?? null ), 'Raw navigation URL preserves the target user parameter' );
check_impersonation_url( 'valid-test-nonce' === ( $params['_wpnonce'] ?? null ), 'WordPress receives the nonce under its actual parameter name' );
check_impersonation_url( $return === ( $params['return_to'] ?? null ), 'Return URL preserves every administrative filter' );
check_impersonation_url( false === strpos( $url, '&amp;' ), 'REST URL contains no HTML entities' );
check_impersonation_url( 'gnf_impersonate' === $nonce_action, 'The original nonce security action is unchanged' );
$html_url = htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
$html_params = array(); parse_str( parse_url( html_entity_decode( $html_url, ENT_QUOTES, 'UTF-8' ), PHP_URL_QUERY ), $html_params );
check_impersonation_url( $params === $html_params, 'Escaping at the wp-admin HTML boundary still preserves the link' );
check_impersonation_url( '' === gnf_build_impersonate_url( 1 ), 'An administrator cannot impersonate their own account' );
check_impersonation_url( '' === gnf_build_impersonate_url( 700 ), 'Other administrators remain excluded' );
check_impersonation_url( '' === gnf_build_impersonate_url( 999 ), 'Missing target users remain excluded' );
$admin = false;
check_impersonation_url( '' === gnf_build_impersonate_url( 600 ), 'Nonadministrators cannot generate an impersonation link' );
echo "\n{$tests} checks, {$fails} failures\n"; exit( $fails ? 1 : 0 );
