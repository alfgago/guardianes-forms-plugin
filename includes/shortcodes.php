<?php
/**
 * Registro de shortcodes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gnf_render_docente_panel_shortcode() {
	if ( ! is_user_logged_in() ) {
		return gnf_render_react_panel( 'auth', array( 'redirectTo' => 'docente' ) );
	}
	$user            = wp_get_current_user();
	$centro_id       = function_exists( 'gnf_get_centro_for_docente' ) ? gnf_get_centro_for_docente( $user->ID ) : 0;
	$available_years = function_exists( 'gnf_get_available_years' ) ? gnf_get_available_years() : array( (int) gmdate( 'Y' ) );
	$selected_year   = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : ( function_exists( 'gnf_get_active_year' ) ? gnf_get_active_year() : (int) gmdate( 'Y' ) );
	if ( ! in_array( $selected_year, $available_years, true ) ) {
		$selected_year = function_exists( 'gnf_get_active_year' ) ? gnf_get_active_year() : $available_years[0];
	}
	return gnf_render_react_panel(
		'docente',
		array(
			'centroId'       => $centro_id,
			'availableYears' => $available_years,
			'selectedYear'   => $selected_year,
		)
	);
}

add_shortcode( 'gn_docente_panel', 'gnf_render_docente_panel_shortcode' );
add_shortcode( 'gn_escuela_panel', 'gnf_render_docente_panel_shortcode' );

add_shortcode(
	'gn_supervisor_panel',
	function () {
		if ( ! is_user_logged_in() ) {
			return gnf_render_react_panel( 'auth', array( 'redirectTo' => 'supervisor' ) );
		}
		$user            = wp_get_current_user();
		$region_id       = function_exists( 'gnf_get_user_region' ) ? gnf_get_user_region( $user->ID ) : 0;
		$available_years = function_exists( 'gnf_get_available_years' ) ? gnf_get_available_years() : array( (int) gmdate( 'Y' ) );
		$selected_year   = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : ( function_exists( 'gnf_get_active_year' ) ? gnf_get_active_year() : (int) gmdate( 'Y' ) );
		if ( ! in_array( $selected_year, $available_years, true ) ) {
			$selected_year = function_exists( 'gnf_get_active_year' ) ? gnf_get_active_year() : $available_years[0];
		}
		return gnf_render_react_panel(
			'supervisor',
			array(
				'regionId'       => $region_id,
				'availableYears' => $available_years,
				'selectedYear'   => $selected_year,
			)
		);
	}
);

/**
 * Panel de Administrador Frontend.
 * Acceso completo a gestión de usuarios, centros, retos, reportes y configuración.
 */
add_shortcode(
	'gn_admin_panel',
	function () {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return gnf_render_react_panel( 'auth', array( 'redirectTo' => 'admin' ) );
		}
		$available_years = function_exists( 'gnf_get_available_years' ) ? gnf_get_available_years() : array( (int) gmdate( 'Y' ) );
		$selected_year   = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : ( function_exists( 'gnf_get_active_year' ) ? gnf_get_active_year() : (int) gmdate( 'Y' ) );
		if ( ! in_array( $selected_year, $available_years, true ) ) {
			$selected_year = function_exists( 'gnf_get_active_year' ) ? gnf_get_active_year() : $available_years[0];
		}
		return gnf_render_react_panel(
			'admin',
			array(
				'availableYears' => $available_years,
				'selectedYear'   => $selected_year,
			)
		);
	}
);

/**
 * Panel del Comité Bandera Azul Ecológica.
 * Vista de todos los centros con capacidad de validación final.
 */
add_shortcode(
	'gn_comite_panel',
	function () {
		if ( ! is_user_logged_in() ) {
			return gnf_render_react_panel( 'auth', array( 'redirectTo' => 'comite' ) );
		}
		$available_years = function_exists( 'gnf_get_available_years' ) ? gnf_get_available_years() : array( (int) gmdate( 'Y' ) );
		$selected_year   = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : ( function_exists( 'gnf_get_active_year' ) ? gnf_get_active_year() : (int) gmdate( 'Y' ) );
		if ( ! in_array( $selected_year, $available_years, true ) ) {
			$selected_year = function_exists( 'gnf_get_active_year' ) ? gnf_get_active_year() : $available_years[0];
		}
		return gnf_render_react_panel(
			'comite',
			array(
				'availableYears' => $available_years,
				'selectedYear'   => $selected_year,
			)
		);
	}
);

add_shortcode(
	'gn_notificaciones',
	function () {
		if ( ! is_user_logged_in() ) {
			return '<p>Debes iniciar sesión.</p>';
		}
		global $wpdb;
		$user_id = get_current_user_id();
		$table   = $wpdb->prefix . 'gn_notificaciones';
		$items   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND leido = 0 ORDER BY created_at DESC",
				$user_id
			)
		);
		ob_start();
		if ( empty( $items ) ) {
			echo '<p>Sin notificaciones pendientes.</p>';
		} else {
			echo '<ul class="gnf-notificaciones">';
			foreach ( $items as $item ) {
				echo '<li><strong>' . esc_html( $item->tipo ) . ':</strong> ' . esc_html( $item->mensaje ) . ' <small>' . esc_html( $item->created_at ) . '</small></li>';
				$wpdb->update( $table, array( 'leido' => 1 ), array( 'id' => $item->id ), array( '%d' ), array( '%d' ) );
			}
			echo '</ul>';
		}
		return ob_get_clean();
	}
);

/**
 * Shortcode del Wizard Maestro.
 * Decisión BAE: Los docentes llenan un solo formulario tipo wizard.
 * Nota: El wizard ahora está integrado como pestaña en el panel docente.
 */
add_shortcode(
	'gn_wizard',
	function () {
		// Si está como shortcode independiente, mostrar mensaje con link al panel.
		if ( is_user_logged_in() ) {
			$panel_url = add_query_arg( 'tab', 'formularios', home_url( '/panel-docente/' ) );
			return '<div class="gnf-wizard-redirect" style="padding: 24px; text-align: center; background: #f0f9ff; border-radius: 12px;">
				<p style="margin: 0 0 16px;">El wizard de formularios ahora está integrado en el Panel Docente.</p>
				<a href="' . esc_url( $panel_url ) . '" class="gnf-btn" style="display: inline-block; padding: 12px 24px; background: #369484; color: #fff; text-decoration: none; border-radius: 8px;">
					Ir al Panel Docente → Formularios
				</a>
			</div>';
		}
		// Si no está logueado, mostrar el wizard completo.
		return gnf_render_wizard();
	}
);

/**
 * Shortcode para registro de supervisores.
 * Los supervisores se registran y quedan pendientes de aprobación.
 */
add_shortcode(
	'gn_registro_supervisor',
	function () {
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( in_array( 'supervisor', (array) $user->roles, true ) || in_array( 'comite_bae', (array) $user->roles, true ) ) {
				return '<p>Ya tienes una cuenta de supervisor. <a href="' . esc_url( home_url( '/panel-supervisor/' ) ) . '">Ir al panel</a></p>';
			}
		}
		return gnf_render_react_panel( 'auth', array( 'defaultTab' => 'register-supervisor' ) );
	}
);

);
