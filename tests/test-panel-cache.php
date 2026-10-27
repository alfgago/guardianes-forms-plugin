<?php
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
$options = $transients = $ttls = $filters = array();
$user = (object) array( 'ID' => 10, 'roles' => array( 'docente' ), 'allcaps' => array( 'read' => true ) );
$center = 100; $regions = array( 2 ); $circuit = '01'; $allowed = true; $calls = 0; $nonce = 'fresh';
$year = 2026; $state = 'activo'; $original = 0;
function add_filter( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['filters'][$name] = array( $callback, $args ); }
function add_action() {}
function get_option( $key, $default = false ) { return $GLOBALS['options'][$key] ?? $default; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][$key] = $value; }
function get_transient( $key ) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][$key] = $value; $GLOBALS['ttls'][] = $ttl; }
function wp_cache_delete( $key, $group ) {}
function maybe_serialize( $value ) { return serialize( $value ); }
function maybe_unserialize( $value ) { return unserialize( $value ); }
function wp_get_current_user() { return $GLOBALS['user']; }
function get_current_user_id() { return $GLOBALS['user']->ID; }
function current_user_can( $cap ) { return ! empty( $GLOBALS['user']->allcaps[$cap] ); }
function gnf_get_centro_for_docente() { return $GLOBALS['center']; }
function gnf_get_active_year() { return $GLOBALS['year']; }
function gnf_get_user_regions() { return $GLOBALS['regions']; }
function gnf_get_user_circuito() { return $GLOBALS['circuit']; }
function gnf_get_docente_estado() { return $GLOBALS['state']; }
function gnf_get_supervisor_estado() { return $GLOBALS['state']; }
function gnf_get_impersonate_original_user() { return $GLOBALS['original']; }
function gnf_get_docente_school_report_payload( $id, $year, $status ) { return array( 'reportPdfUrl' => '/pdf?_wpnonce=' . $GLOBALS['nonce'], 'canDownloadSchoolReport' => true, 'reportPdfStatus' => $status, 'reportPdfProvisional' => true ); }
function gnf_get_center_report_download_url( $id, $year ) { return '/pdf?_wpnonce=' . $GLOBALS['nonce']; }
function gnf_build_centro_docente_impersonate_url() { return ''; }
function get_post_type() { return 'centro_educativo'; }
function absint( $value ) { return abs( (int) $value ); }
function wp_json_encode( $value ) { return json_encode( $value ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
class WP_Error {
    private $code; private $data;
    function __construct( $code = '', $message = '', $data = array() ) { $this->code = $code; $this->data = $data; }
    function get_error_code() { return $this->code; }
    function get_error_data() { return $this->data; }
}
class WP_REST_Response {
    private $data; private $status; public $headers = array();
    function __construct( $data, $status = 200 ) { $this->data = $data; $this->status = $status; }
    function get_data() { return $this->data; }
    function get_status() { return $this->status; }
    function header( $name, $value ) { $this->headers[$name] = $value; }
}
function rest_ensure_response( $value ) { return $value instanceof WP_REST_Response ? $value : new WP_REST_Response( $value ); }
class CacheRequest {
    private $route; private $method; private $params;
    function __construct( $route, $params = array(), $method = 'GET' ) { $this->route = '/gnf/v1' . $route; $this->method = $method; $this->params = $params; }
    function get_route() { return $this->route; }
    function get_method() { return $this->method; }
    function get_params() { return $this->params; }
    function get_param( $key ) { return $this->params[$key] ?? null; }
}
$tests = $fails = 0;
function check_cache( $ok, $message ) { global $tests, $fails; $tests++; if ( ! $ok ) { $fails++; } echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $message . "\n"; }
check_cache( file_exists( ABSPATH . 'includes/panel-cache.php' ), 'Server panel cache module exists' );
if ( $fails ) { exit( 1 ); }
require ABSPATH . 'includes/panel-cache.php';
require __DIR__ . '/work-lock-fixture.php';
$wpdb = new Work_Lock_DB();
$handler = array( 'permission_callback' => function() { return $GLOBALS['allowed']; }, 'callback' => function() { $GLOBALS['calls']++; return array( 'centro' => array( 'id' => $GLOBALS['center'] ), 'allRetosComplete' => false, 'puntajeTotal' => $GLOBALS['calls'], 'reportPdfUrl' => '/old?_wpnonce=old', 'canDownloadSchoolReport' => true ); } );
$request = new CacheRequest( '/docente/dashboard' );
$one = rest_ensure_response( gnf_panel_cache_dispatch( null, $request, $request->get_route(), $handler ) );
$nonce = 'new';
$two = rest_ensure_response( gnf_panel_cache_dispatch( null, $request, $request->get_route(), $handler ) );
check_cache( 1 === $calls && $one->get_data()['puntajeTotal'] === $two->get_data()['puntajeTotal'], 'Repeated GET skips expensive callback' );
check_cache( array( 7500 ) === $ttls && reset( $transients )['fresh_until'] - time() <= 7200, 'Two-hour freshness retains only five minutes of same-context stale fallback' );
check_cache( '/pdf?_wpnonce=new' === $two->get_data()['reportPdfUrl'], 'Signed URLs are regenerated on cache hits' );
check_cache( false === strpos( json_encode( $transients ), '_wpnonce' ), 'Transients do not persist nonces or signed URLs' );
$allowed = false;
check_cache( null === gnf_panel_cache_dispatch( null, $request, $request->get_route(), $handler ) && 1 === $calls, 'Denied permissions cannot use populated cache' );
$allowed = true; $center = 101;
gnf_panel_cache_dispatch( null, $request, $request->get_route(), $handler );
check_cache( 2 === $calls, 'Another center has a separate cache' );
gnf_invalidate_docente_panel_cache( 100 );
gnf_panel_cache_dispatch( null, $request, $request->get_route(), $handler );
check_cache( 2 === $calls, 'Changing center 100 does not invalidate center 101' );
$center = 100;
gnf_panel_cache_dispatch( null, $request, $request->get_route(), $handler );
check_cache( 3 === $calls, 'Changing a center invalidates its teacher data immediately' );
$user->ID = 11;
gnf_panel_cache_dispatch( null, $request, $request->get_route(), $handler );
check_cache( 4 === $calls, 'Teachers at the same center do not share response caches' );
$form = new CacheRequest( '/docente/retos/25/form-html' );
check_cache( null === gnf_panel_cache_dispatch( null, $form, $form->get_route(), $handler ), 'Forms with answers are never response-cached' );
$detail = new CacheRequest( '/supervisor/centros/100' );
check_cache( null === gnf_panel_cache_dispatch( null, $detail, $detail->get_route(), $handler ), 'Evidence review details stay live' );
$user->roles = array( 'supervisor' );
$list = new CacheRequest( '/supervisor/centros', array( 'region' => 2 ) );
gnf_panel_cache_dispatch( null, $list, $list->get_route(), $handler );
$count = $calls;
gnf_invalidate_docente_panel_cache( 100 );
gnf_panel_cache_dispatch( null, $list, $list->get_route(), $handler );
check_cache( $calls === $count, 'Teacher updates do not discard all supervisor summaries' );
$regions = array( 3 );
gnf_panel_cache_dispatch( null, $list, $list->get_route(), $handler );
check_cache( $calls === $count + 1, 'Changed regional assignment cannot reuse old results' );
$circuit = '02';
gnf_panel_cache_dispatch( null, $list, $list->get_route(), $handler );
check_cache( $calls === $count + 2, 'Changed circuit assignment cannot reuse old results' );
gnf_invalidate_supervisor_panel_cache();
gnf_panel_cache_dispatch( null, $list, $list->get_route(), $handler );
check_cache( $calls === $count + 3, 'Admin refresh invalidates supervisor snapshots' );
$error_request = new CacheRequest( '/supervisor/dashboard' );
$bad = $handler; $bad['callback'] = function() { $GLOBALS['calls']++; return new WP_Error(); };
gnf_panel_cache_dispatch( null, $error_request, $error_request->get_route(), $bad );
gnf_panel_cache_dispatch( null, $error_request, $error_request->get_route(), $bad );
check_cache( $calls === $count + 5, 'Errors are not cached' );
check_cache( isset( $filters['rest_dispatch_request'] ) && ! isset( $filters['rest_pre_dispatch'] ), 'Cache dispatch runs after WordPress permission checks' );
$notification_request = new CacheRequest( '/notifications' );
$signal = gnf_panel_cache_after_request( array(), array(), $notification_request );
check_cache( 'private, no-store' === $signal->headers['Cache-Control'] && 'supervisor' === $signal->headers['X-GNF-Panel-Kind'], 'Notifications carry a private lightweight revision signal' );
$before = $signal->headers['X-GNF-Panel-Version'];
gnf_invalidate_supervisor_panel_cache();
$signal = gnf_panel_cache_after_request( array(), array(), $notification_request );
check_cache( $before !== $signal->headers['X-GNF-Panel-Version'], 'Admin refresh reaches open supervisor sessions through revision polling' );
$before = $calls;
gnf_panel_cache_dispatch( null, $list, $list->get_route(), $handler );
gnf_panel_cache_post_meta_changed( 1, 100, 'circuito' );
gnf_panel_cache_dispatch( null, $list, $list->get_route(), $handler );
check_cache( $calls === $before + 2, 'Moving a center to another circuit invalidates cached territorial lists immediately' );
$user->roles = array( 'docente' );
$before = gnf_panel_cache_after_request( array(), array(), $notification_request )->headers['X-GNF-Panel-Version'];
gnf_panel_cache_after_request( array( 'success' => true ), array(), new CacheRequest( '/docente/retos/25/reopen', array(), 'POST' ) );
$after = gnf_panel_cache_after_request( array(), array(), $notification_request )->headers['X-GNF-Panel-Version'];
check_cache( $before !== $after, 'Teacher mutations invalidate even when they write SQL directly' );
$race_handler = $handler;
$race_handler['callback'] = function() { $GLOBALS['calls']++; gnf_invalidate_docente_panel_cache( $GLOBALS['center'] ); return array( 'puntaje' => 1 ); };
$race_request = new CacheRequest( '/docente/retos' );
$writes = count( $ttls );
gnf_panel_cache_dispatch( null, $race_request, $race_request->get_route(), $race_handler );
check_cache( $writes === count( $ttls ), 'A concurrent center change prevents storing an obsolete result' );
$nonces_handler = $handler;
$nonces_handler['callback'] = function() { return array( 'otherUrl' => '/unknown?_wpnonce=secret' ); };
$writes = count( $ttls );
gnf_panel_cache_dispatch( null, $race_request, $race_request->get_route(), $nonces_handler );
check_cache( $writes === count( $ttls ), 'Unknown signed fields fail safe by bypassing storage' );
$user->roles = array( 'supervisor' );
$list_handler = $handler;
$list_handler['callback'] = function() { return array( array( 'id' => 100, 'annual' => array( 'centroId' => 100, 'reportPdfUrl' => '/old?_wpnonce=old' ), 'docenteImpersonateUrl' => '/old?_wpnonce=old', 'canImpersonateDocente' => true ) ); };
$specific_list = new CacheRequest( '/supervisor/centros', array( 'region' => 3, 'circuito' => '02' ) );
gnf_panel_cache_dispatch( null, $specific_list, $specific_list->get_route(), $list_handler );
$nonce = 'supervisor-new';
$fresh_list = gnf_panel_cache_dispatch( null, $specific_list, $specific_list->get_route(), $list_handler )->get_data();
check_cache( '/pdf?_wpnonce=supervisor-new' === $fresh_list[0]['annual']['reportPdfUrl'], 'Nested supervisor report URLs are rebuilt on every cache hit' );
check_cache( '' === $fresh_list[0]['docenteImpersonateUrl'] && ! $fresh_list[0]['canImpersonateDocente'], 'A cached action cannot grant impersonation to a supervisor' );
$signal = gnf_panel_cache_after_request( array( 'unreadCount' => 1 ), array(), new CacheRequest( '/notifications/summary' ) );
check_cache( isset( $signal->headers['X-GNF-Panel-Version'] ) && 'private, no-store' === ( $signal->headers['Cache-Control'] ?? '' ), 'Notification summary also carries revision and private-cache headers' );

$user->roles = array( 'docente' );
$cold = new CacheRequest( '/docente/dashboard', array( 'case' => 'cold-concurrent' ) );
$nested = null; $generations = 0;
$concurrent = array( 'permission_callback' => $handler['permission_callback'] );
$concurrent['callback'] = function() use ( &$concurrent, $cold, &$nested, &$generations ) {
    $generations++;
    if ( 1 === $generations ) { $nested = gnf_panel_cache_dispatch( null, $cold, $cold->get_route(), $concurrent ); }
    return array( 'puntajeTotal' => 123 );
};
$start = microtime( true );
gnf_panel_cache_dispatch( null, $cold, $cold->get_route(), $concurrent );
check_cache( 1 === $generations && $nested instanceof WP_REST_Response && 503 === $nested->get_status() && '2' === ( $nested->headers['Retry-After'] ?? '' ) && microtime( true ) - $start < 1, 'Cold concurrent caller gets an explicit bounded retry without running the callback' );
check_cache( ! array_filter( array_keys( $options ), function( $key ) { return 0 === strpos( $key, 'gnf_panel_lock_' ); } ), 'Completed regeneration releases its lease' );
$retry_response = gnf_panel_cache_dispatch( null, $cold, $cold->get_route(), $concurrent );
check_cache( 1 === $generations && 123 === $retry_response->get_data()['puntajeTotal'], 'Cold retry reuses the completed result rather than the busy response' );

$stale_request = new CacheRequest( '/docente/dashboard', array( 'case' => 'stale-concurrent' ) );
gnf_panel_cache_dispatch( null, $stale_request, $stale_request->get_route(), $handler );
$stale_key = array_key_last( $transients ); $old_score = $transients[$stale_key]['data']['puntajeTotal'];
$transients[$stale_key]['fresh_until'] = time() - 1;
$stale_nested = null; $stale_generations = 0;
$stale_handler = array( 'permission_callback' => $handler['permission_callback'] );
$stale_handler['callback'] = function() use ( &$stale_handler, $stale_request, &$stale_nested, &$stale_generations ) {
    $stale_generations++;
    if ( 1 === $stale_generations ) { $stale_nested = gnf_panel_cache_dispatch( null, $stale_request, $stale_request->get_route(), $stale_handler ); }
    return array( 'puntajeTotal' => 999 );
};
$nonce = 'stale-fresh';
gnf_panel_cache_dispatch( null, $stale_request, $stale_request->get_route(), $stale_handler );
check_cache( 1 === $stale_generations && $stale_nested instanceof WP_REST_Response && $old_score === ( $stale_nested->get_data()['puntajeTotal'] ?? null ) && 'stale' === ( $stale_nested->headers['X-GNF-Panel-Cache'] ?? '' ), 'Only one worker refreshes an expired result while a contender serves same-context stale data' );
check_cache( $stale_nested instanceof WP_REST_Response && '/pdf?_wpnonce=stale-fresh' === ( $stale_nested->get_data()['reportPdfUrl'] ?? '' ) && false === strpos( json_encode( $transients ), 'nonce' ), 'Stale fallback rebuilds signed actions and never persists credentials' );

$transients[$stale_key]['fresh_until'] = time() - 1;
$denied = null; $invalidated = null;
$security_handler = $handler;
$security_handler['callback'] = function() use ( $stale_request, $handler, &$denied, &$invalidated ) {
    $GLOBALS['allowed'] = false;
    $denied = gnf_panel_cache_dispatch( null, $stale_request, $stale_request->get_route(), $handler );
    $GLOBALS['allowed'] = true;
    gnf_invalidate_docente_panel_cache( $GLOBALS['center'] );
    $error_handler = $handler; $error_handler['callback'] = function() { return new WP_Error(); };
    $invalidated = gnf_panel_cache_dispatch( null, $stale_request, $stale_request->get_route(), $error_handler );
    return array( 'puntajeTotal' => 555 );
};
$writes = count( $ttls );
gnf_panel_cache_dispatch( null, $stale_request, $stale_request->get_route(), $security_handler );
check_cache( null === $denied, 'Revoked permissions cannot read stale data during regeneration' );
check_cache( $invalidated instanceof WP_Error && $writes === count( $ttls ), 'Invalidation neither serves the old stale context nor stores an obsolete in-flight result or an error' );

$throw_request = new CacheRequest( '/docente/retos', array( 'case' => 'throw' ) );
$throw_handler = $handler; $throw_handler['callback'] = function() { throw new RuntimeException( 'fixture' ); };
try { gnf_panel_cache_dispatch( null, $throw_request, $throw_request->get_route(), $throw_handler ); } catch ( RuntimeException $error ) {}
check_cache( ! array_filter( array_keys( $options ), function( $key ) { return 0 === strpos( $key, 'gnf_panel_lock_' ); } ), 'Thrown callbacks also release panel leases' );

foreach ( array( '/asset?token=secret', '/asset?signature=secret', '/asset?X-Amz-Signature=secret', '/asset?NONCE=secret' ) as $url ) {
    $signed = new CacheRequest( '/docente/retos', array( 'case' => $url ) );
    $signed_handler = $handler; $signed_handler['callback'] = function() use ( $url ) { return array( 'otherUrl' => $url ); };
    $writes = count( $ttls );
    gnf_panel_cache_dispatch( null, $signed, $signed->get_route(), $signed_handler );
    check_cache( $writes === count( $ttls ), 'Unknown signed URL is not persisted: ' . $url );
}

$before_calls = $calls;
$work_read_failure = true;
$unavailable = new CacheRequest( '/docente/retos', array( 'case' => 'database-unavailable' ) );
$busy = gnf_panel_cache_dispatch( null, $unavailable, $unavailable->get_route(), $handler );
$work_read_failure = false;
check_cache( $busy instanceof WP_REST_Response && 503 === $busy->get_status() && $calls === $before_calls, 'Database lock failure fails closed with a retry instead of unguarded generation' );

// Interleave an old regeneration with each security/data-context change.
$changes = array(
    'user' => function() { $GLOBALS['user']->ID++; },
    'roles' => function() { $GLOBALS['user']->roles[] = 'extra'; },
    'caps' => function() { $GLOBALS['user']->allcaps['extra'] = true; },
    'year' => function() { $GLOBALS['year']++; },
    'center' => function() { $GLOBALS['center']++; },
    'state' => function() { $GLOBALS['state'] = 'pendiente'; },
    'impersonator' => function() { $GLOBALS['original'] = 99; },
    'config' => function() { gnf_bump_panel_cache_version( 'config' ); },
    'profile' => function() { gnf_bump_panel_cache_version( 'user_' . get_current_user_id() ); },
    'data' => function() { gnf_invalidate_docente_panel_cache( $GLOBALS['center'] ); },
);
foreach ( $changes as $name => $change ) {
    $context_request = new CacheRequest( '/docente/retos', array( 'case' => 'context-' . $name ) );
    gnf_panel_cache_dispatch( null, $context_request, $context_request->get_route(), $handler );
    $context_key = array_key_last( $transients ); $transients[$context_key]['fresh_until'] = time() - 1;
    $other = null; $context_handler = $handler;
    $context_handler['callback'] = function() use ( $context_request, $handler, $change, &$other ) {
        $change();
        $next = $handler; $next['callback'] = function() { return new WP_Error( 'live-error' ); };
        $other = gnf_panel_cache_dispatch( null, $context_request, $context_request->get_route(), $next );
        return array( 'obsolete' => true );
    };
    $writes = count( $ttls );
    gnf_panel_cache_dispatch( null, $context_request, $context_request->get_route(), $context_handler );
    check_cache( $other instanceof WP_Error && $writes === count( $ttls ), 'Stale data and in-flight publication cannot cross changed context: ' . $name );
}

$lease_request = new CacheRequest( '/docente/retos', array( 'case' => 'lease-takeover' ) );
$successor = array( 'token' => 'successor', 'expires' => time() + 300 ); $lease_key = '';
$takeover = $handler;
$takeover['callback'] = function() use ( &$lease_key, $successor ) {
    foreach ( array_keys( $GLOBALS['options'] ) as $key ) {
        if ( 0 === strpos( $key, 'gnf_panel_lock_' ) ) { $lease_key = $key; $GLOBALS['options'][$key] = $successor; }
    }
    return array( 'obsolete' => true );
};
$writes = count( $ttls );
gnf_panel_cache_dispatch( null, $lease_request, $lease_request->get_route(), $takeover );
check_cache( $writes === count( $ttls ) && $successor === ( $options[$lease_key] ?? null ), 'Lease takeover fences obsolete panel publication and late release' );
$options[$lease_key]['expires'] = time();
$recovered = gnf_panel_cache_dispatch( null, $lease_request, $lease_request->get_route(), $handler );
check_cache( 200 === $recovered->get_status() && ! isset( $options[$lease_key] ), 'A later request recovers an expired panel lease' );

$http_error_request = new CacheRequest( '/docente/retos', array( 'case' => 'http-error' ) );
$http_error = $handler; $http_error['callback'] = function() { return new WP_REST_Response( array( 'error' => true ), 403 ); };
$writes = count( $ttls );
gnf_panel_cache_dispatch( null, $http_error_request, $http_error_request->get_route(), $http_error );
check_cache( $writes === count( $ttls ), 'Non-200 REST responses are never cached' );

$late_db_request = new CacheRequest( '/docente/retos', array( 'case' => 'late-database-failure' ) );
$late_db_handler = $handler;
$late_db_handler['callback'] = function() { $GLOBALS['work_read_failure'] = true; return array( 'live' => 1 ); };
$writes = count( $ttls ); $live_response = null;
try { $live_response = gnf_panel_cache_dispatch( null, $late_db_request, $late_db_request->get_route(), $late_db_handler ); } catch ( RuntimeException $error ) {}
$work_read_failure = false;
check_cache( $live_response instanceof WP_REST_Response && array( 'live' => 1 ) === $live_response->get_data() && $writes === count( $ttls ), 'A late ownership-read failure returns the successful live contract without caching it' );
echo "\n{$tests} checks, {$fails} failures\n"; exit( $fails ? 1 : 0 );
