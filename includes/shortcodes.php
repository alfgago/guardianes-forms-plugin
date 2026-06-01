<?php
/**
 * Registro de shortcodes React.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gnf_get_shortcode_selected_year() {
	$selected_year = function_exists( 'gnf_get_active_year' ) ? gnf_get_active_year() : (int) gmdate( 'Y' );
	$selected_year = function_exists( 'gnf_normalize_year' ) ? gnf_normalize_year( $selected_year ) : absint( $selected_year );

	return array(
		'availableYears' => array( $selected_year ),
		'selectedYear'   => $selected_year,
	);
}

function gnf_render_docente_panel_shortcode() {
	if ( ! is_user_logged_in() ) {
		return gnf_render_react_panel( 'auth', array( 'redirectTo' => 'docente' ) );
	}

	$user       = wp_get_current_user();
	$centro_id  = function_exists( 'gnf_get_centro_for_docente' ) ? gnf_get_centro_for_docente( $user->ID ) : 0;
	$year_state = gnf_get_shortcode_selected_year();

	return gnf_render_react_panel(
		'docente',
		array(
			'centroId'       => $centro_id,
			'availableYears' => $year_state['availableYears'],
			'selectedYear'   => $year_state['selectedYear'],
		)
	);
}

function gnf_render_supervisor_panel_shortcode() {
	if ( ! is_user_logged_in() ) {
		return gnf_render_react_panel( 'auth', array( 'redirectTo' => 'supervisor' ) );
	}

	$user       = wp_get_current_user();
	$region_id  = function_exists( 'gnf_get_user_region' ) ? gnf_get_user_region( $user->ID ) : 0;
	$year_state = gnf_get_shortcode_selected_year();

	return gnf_render_react_panel(
		'supervisor',
		array(
			'regionId'       => $region_id,
			'availableYears' => $year_state['availableYears'],
			'selectedYear'   => $year_state['selectedYear'],
		)
	);
}

function gnf_render_admin_panel_shortcode() {
	$user = wp_get_current_user();
	if ( ! is_user_logged_in() || ( function_exists( 'gnf_user_can_access_panel' ) ? ! gnf_user_can_access_panel( $user, 'panel-admin' ) : ! current_user_can( 'manage_options' ) ) ) {
		return gnf_render_react_panel( 'auth', array( 'redirectTo' => 'admin' ) );
	}

	$year_state = gnf_get_shortcode_selected_year();

	return gnf_render_react_panel(
		'admin',
		array(
			'availableYears' => $year_state['availableYears'],
			'selectedYear'   => $year_state['selectedYear'],
		)
	);
}

function gnf_render_comite_panel_shortcode() {
	if ( ! is_user_logged_in() ) {
		return gnf_render_react_panel( 'auth', array( 'redirectTo' => 'supervisor' ) );
	}

	return gnf_render_supervisor_panel_shortcode();
}

add_shortcode( 'gn_docente_panel', 'gnf_render_docente_panel_shortcode' );
add_shortcode( 'gn_escuela_panel', 'gnf_render_docente_panel_shortcode' );
add_shortcode( 'gn_supervisor_panel', 'gnf_render_supervisor_panel_shortcode' );
add_shortcode( 'gn_admin_panel', 'gnf_render_admin_panel_shortcode' );
add_shortcode( 'gn_comite_panel', 'gnf_render_comite_panel_shortcode' );
