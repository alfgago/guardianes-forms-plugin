<?php
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'GNF_REST_PLUGIN_PROFILE', ! in_array( '--disabled', $argv, true ) );
function add_filter() {}
$file = ABSPATH . 'tools/mu-plugins/gnf-rest-performance.php';
if ( ! is_file( $file ) ) { fwrite( STDERR, "FAIL: isolated REST plugin profile is missing\n" ); exit( 1 ); }
require $file;
function check_profile( $ok, $message ) { if ( ! $ok ) { throw new Exception( $message ); } }
$plugins = array( 'guardianes-formularios/guardianes-formularios.php', 'advanced-custom-fields-pro/acf.php', 'wpforms/wpforms.php', 'wpforms-surveys-polls/wpforms-surveys-polls.php', 'woocommerce/woocommerce.php', 'facebook-for-woocommerce/facebook-for-woocommerce.php', 'sitepress-multilingual-cms/sitepress.php', 'contact-form-7/wp-contact-form-7.php', 'elementor/elementor.php', 'irmawp/irmawp.php', 'wp-security-audit-log/wp-security-audit-log.php', 'redis-cache/redis-cache.php' );
foreach ( array( '/', '/tienda/', '/wp-admin/admin-ajax.php', '/wp-json/wc/v3/orders', '/wp-json/contact-form-7/v1/contact-forms/1/feedback', '/wp-json/wp/v2/pages', '/wp-json/gnf/v1/auth/login', '/wp-json/gnf/v1/admin/seeders/retos', '/wp-json/gnf/v10/docente/dashboard', '/panel-docente/' ) as $uri ) {
    $_SERVER['REQUEST_URI'] = $uri;
    check_profile( $plugins === gnf_rest_profile_plugins( $plugins ), 'Other pages, login, public APIs and admin tools preserve every plugin: ' . $uri );
}
$_SERVER['REQUEST_URI'] = '/wp-json/gnf/v1/docente/dashboard?year=2026';
$selected = gnf_rest_profile_plugins( $plugins );
$_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php'; $_GET['rest_route'] = '/gnf/v1/docente/dashboard';
check_profile( $plugins === gnf_rest_profile_plugins( $plugins ), 'An unrelated admin script cannot opt into isolation with a query parameter' );
unset( $_GET['rest_route'] );
if ( ! GNF_REST_PLUGIN_PROFILE ) {
    check_profile( $selected === $plugins, 'Emergency switch restores all plugins' );
} else {
    check_profile( ! in_array( 'woocommerce/woocommerce.php', $selected, true ) && ! in_array( 'sitepress-multilingual-cms/sitepress.php', $selected, true ), 'Only isolated API avoids store and translation bootstrap' );
    foreach ( array( 'guardianes-formularios/guardianes-formularios.php', 'advanced-custom-fields-pro/acf.php', 'wpforms/wpforms.php', 'wpforms-surveys-polls/wpforms-surveys-polls.php', 'elementor/elementor.php', 'irmawp/irmawp.php', 'wp-security-audit-log/wp-security-audit-log.php', 'redis-cache/redis-cache.php' ) as $plugin ) {
        check_profile( in_array( $plugin, $selected, true ), 'Keep forms, theme dependencies, security, audit and object cache: ' . $plugin );
    }
    $_SERVER['REQUEST_URI'] = '/'; $_GET['rest_route'] = '/gnf/v1/supervisor/centros/12';
    check_profile( $selected === gnf_rest_profile_plugins( $plugins ), 'Plain REST permalinks share exact profile' );
    unset( $_GET['rest_route'] );
}
echo "REST plugin isolation checks passed\n";
