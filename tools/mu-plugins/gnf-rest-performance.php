<?php
/**
 * Plugin Name: Guardianes REST Performance
 * Description: Opt-in plugin isolation for Guardianes operational REST requests only.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function gnf_rest_profile_plugins( $plugins ) {
	if ( ! defined( 'GNF_REST_PLUGIN_PROFILE' ) || ! GNF_REST_PLUGIN_PROFILE
		|| ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'DOING_CRON' ) && DOING_CRON )
		|| ( defined( 'WP_ADMIN' ) && WP_ADMIN ) ) {
		return $plugins;
	}
	$path = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	$route = is_string( $path ) && 0 === strpos( $path, '/wp-json/' ) ? substr( $path, 8 )
		: ( in_array( $path, array( '/', '/index.php' ), true ) ? ( $_GET['rest_route'] ?? '' ) : '' );
	if ( ! is_string( $route ) || ! preg_match( '#^/gnf/v1/(docente|supervisor|comite|notifications|reports|events|panel)(/|$)#D', $route ) ) {
		return $plugins;
	}
	// ACF, all WPForms addons, security, mail, Redis and theme dependencies remain loaded.
	$unrelated = array( 'woocommerce', 'facebook-for-woocommerce', 'mailchimp-for-woocommerce',
		'woo-correos-de-costa-rica-shipping', 'wc-provincia-canton-distrito', 'woocommerce-multilingual',
		'sitepress-multilingual-cms', 'wpml-string-translation', 'contact-form-7',
		'contact-form-7-honeypot', 'contact-form-cfdb7', 'ultimate-addons-for-contact-form-7', 'the-grid' );
	return array_values( array_filter( (array) $plugins, static function ( $plugin ) use ( $unrelated ) {
		return ! in_array( strtok( $plugin, '/' ), $unrelated, true );
	} ) );
}

add_filter( 'option_active_plugins', 'gnf_rest_profile_plugins', 1 );
