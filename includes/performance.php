<?php
/** Avoid unrelated form requests on dedicated Guardianes panels. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function gnf_panel_cf7_assets( $load ) {
	return gnf_is_panel_request_path() ? false : $load;
}

function gnf_trim_panel_assets() {
	if ( ! gnf_is_panel_request_path() ) { return; }
	wp_dequeue_script( 'contact-form-7' );
	wp_dequeue_script( 'swv' );
	wp_dequeue_style( 'contact-form-7' );
}

add_filter( 'wpcf7_load_js', 'gnf_panel_cf7_assets' );
add_filter( 'wpcf7_load_css', 'gnf_panel_cf7_assets' );
add_action( 'wp_enqueue_scripts', 'gnf_trim_panel_assets', 100 );
