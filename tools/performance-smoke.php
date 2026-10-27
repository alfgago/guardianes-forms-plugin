<?php
/** CLI-only read smoke test: php performance-smoke.php WP_ROOT ROUTE USER_ID [profile]. */
if ( PHP_SAPI !== 'cli' || ! empty( $_SERVER['REMOTE_ADDR'] ) ) { http_response_code( 403 ); exit; }
$root = realpath( $argv[1] ?? '' );
$url = $argv[2] ?? '';
$gnf_smoke_user_id = (int) ( $argv[3] ?? 0 );
$gnf_smoke_use_cache = in_array( 'cache', $argv, true );
$gnf_smoke_route = parse_url( $url, PHP_URL_PATH );
if ( ! $root || ! is_file( $root . '/wp-load.php' ) || ! $gnf_smoke_user_id || ! is_string( $gnf_smoke_route )
	|| ! preg_match( '#^/gnf/v1/(docente/(dashboard|wizard|retos(/\d+/form-html)?)|notifications(/summary)?|supervisor/(dashboard|centros(/\d+)?)|reports/overview)$#D', $gnf_smoke_route ) ) {
	fwrite( STDERR, "Invalid root, read-only route or account ID.\n" ); exit( 1 );
}
$gnf_smoke_start = microtime( true );
if ( 'configured' !== ( $argv[4] ?? '' ) ) { define( 'GNF_REST_PLUGIN_PROFILE', 'profile' === ( $argv[4] ?? '' ) ); }
define( 'REST_REQUEST', true );
define( 'WP_USE_THEMES', false );
$_SERVER['REQUEST_URI'] = '/wp-json' . $url;
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'movimientoguardianes.org';
$_SERVER['HTTPS'] = 'on';
chdir( $root );
require $root . '/wp-load.php';
if ( ! defined( 'GNF_REST_PLUGIN_PROFILE' ) ) { define( 'GNF_REST_PLUGIN_PROFILE', false ); }
wp_set_current_user( $gnf_smoke_user_id );
// Compare the actual handlers, not a response populated by the other profile.
if ( ! $gnf_smoke_use_cache ) { remove_filter( 'rest_dispatch_request', 'gnf_panel_cache_dispatch', 20 ); }
$request = new WP_REST_Request( 'GET', $gnf_smoke_route );
parse_str( parse_url( $url, PHP_URL_QUERY ) ?: '', $params );
$request->set_query_params( $params );
$response = rest_do_request( $request );
$data = $response->get_data();
$normalize = static function ( $value ) use ( &$normalize ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $key => $item ) {
			if ( in_array( $key, array( 'generatedAt', 'generatedTimestamp' ), true ) ) { unset( $value[ $key ] ); }
			else { $value[ $key ] = $normalize( $item ); }
		}
	} elseif ( is_string( $value ) && false !== strpos( $value, '_wpnonce=' ) ) {
		$value = preg_replace( '/([?&])_wpnonce=[^&]*/', '$1_wpnonce=redacted', $value );
	}
	return $value;
};
echo wp_json_encode( array( 'route' => $gnf_smoke_route, 'status' => $response->get_status(),
	'elapsedMs' => (int) round( ( microtime( true ) - $gnf_smoke_start ) * 1000 ), 'peakMB' => round( memory_get_peak_usage( true ) / 1048576, 1 ),
	'queries' => $GLOBALS['wpdb']->num_queries, 'activePlugins' => count( get_option( 'active_plugins', array() ) ),
	'panelCache' => array_change_key_case( $response->get_headers() )['x-gnf-panel-cache'] ?? null,
	'profile' => GNF_REST_PLUGIN_PROFILE, 'dataHash' => hash( 'sha256', wp_json_encode( $normalize( $data ) ) ),
	'notificationCount' => '/gnf/v1/notifications' === $gnf_smoke_route && is_array( $data ) ? count( $data ) : ( $data['total'] ?? null ),
	'unreadCount' => '/gnf/v1/notifications' === $gnf_smoke_route && is_array( $data ) ? count( array_filter( $data, static function ( $item ) { return empty( $item['leido'] ); } ) ) : ( $data['unreadCount'] ?? null ),
	'formHtmlBytes' => isset( $data['html'] ) && is_string( $data['html'] ) ? strlen( $data['html'] ) : null,
	'errorCode' => $response->get_status() >= 400 ? ( $data['code'] ?? 'unknown' ) : null,
), JSON_PRETTY_PRINT ) . "\n";
exit( $response->get_status() >= 500 ? 1 : 0 );
