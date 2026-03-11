<?php
/**
 * Roles y capabilities.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registrar roles personalizados.
 */
function gnf_register_roles() {

	add_role(
		'docente',
		'Docente',
		array(
			'read'                   => true,
			'upload_files'           => true,
			'gnf_front_access'       => true,
			'gnf_view_docente_panel' => true,
		)
	);

	add_role(
		'supervisor',
		'Supervisor',
		array(
			'read'                      => true,
			'upload_files'              => true,
			'gnf_view_supervisor_panel' => true,
		)
	);

	/**
	 * Decisión BAE: Comité Bandera Azul es autoridad superior.
	 * - Revisa y valida (segunda instancia después del supervisor)
	 * - Ve todos los centros (no filtrado por región)
	 * - NO asigna puntos manualmente
	 */
	add_role(
		'comite_bae',
		'Comité Bandera Azul',
		array(
			'read'                      => true,
			'gnf_view_supervisor_panel' => true,
			'gnf_view_all_regions'      => true,
			'gnf_validate_entries'      => true,
		)
	);

}
add_action( 'init', 'gnf_register_roles' );

/**
 * Agregar capacidades personalizadas a roles.
 */
function gnf_add_custom_caps() {

	$caps_admin = array(
		'manage_guardianes',
		'manage_guardianes_centros',
		'manage_guardianes_retos',
		'approve_guardianes_docentes',
		'approve_guardianes_centros',
		'view_guardianes_supervisor',
		'view_guardianes_docente',
		'submit_guardianes_retos',
		'edit_guardianes_centro_info',
		'view_guardianes_notificaciones',
	);

	$caps_supervisor = array(
		'view_guardianes_supervisor',
		'manage_guardianes_centros',
		'approve_guardianes_docentes',
		'view_guardianes_notificaciones',
	);

	$caps_docente = array(
		'view_guardianes_docente',
		'submit_guardianes_retos',
		'edit_guardianes_centro_info',
		'view_guardianes_notificaciones',
	);

	// Comité BAE: puede ver todo pero NO asignar puntos.
	$caps_comite = array(
		'view_guardianes_supervisor',
		'gnf_view_all_regions',
		'gnf_validate_entries',
		'view_guardianes_notificaciones',
	);

	$roles_caps = array(
		'administrator' => $caps_admin,
		'supervisor'    => $caps_supervisor,
		'docente'       => $caps_docente,
		'comite_bae'    => $caps_comite,
	);

	foreach ( $roles_caps as $role_name => $caps ) {
		if ( $role = get_role( $role_name ) ) {
			foreach ( $caps as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}
}

// Cargar caps después de registrar roles.
// Asegura caps tanto en front como en admin.
add_action( 'init', 'gnf_add_custom_caps', 11 );
add_action( 'admin_init', 'gnf_add_custom_caps', 11 );

/**
 * Bloquear acceso al admin de WordPress para usuarios no-admin.
 * Docentes, supervisores y comité_bae no deben entrar al wp-admin.
 */
function gnf_block_admin_for_frontend_roles() {
	if ( ! is_admin() || wp_doing_ajax() ) {
		return;
	}

	// Allow admin-post.php actions through (impersonate stop, etc.).
	global $pagenow;
	if ( 'admin-post.php' === $pagenow ) {
		return;
	}

	$user  = wp_get_current_user();
	$roles = (array) $user->roles;
	$blocked = array( 'docente', 'supervisor', 'comite_bae' );
	foreach ( $blocked as $role ) {
		if ( in_array( $role, $roles, true ) ) {
			wp_safe_redirect( home_url() );
			exit;
		}
	}
}
add_action( 'init', 'gnf_block_admin_for_frontend_roles' );

/**
 * Protección de paneles frontend por rol.
 *
 * Redirige a los usuarios que intentan acceder a un panel
 * que no les corresponde hacia su propio panel.
 * El administrador puede acceder a todos.
 */
function gnf_protect_frontend_panels() {
	if ( ! is_singular() && ! is_page() ) {
		return;
	}

	// Mapa panel → roles permitidos (slug de página, sin trailing slash).
	$panel_rules = array(
		'panel-admin'      => array( 'administrator' ),
		'panel-comite'     => array( 'administrator', 'comite_bae' ),
		'panel-supervisor' => array( 'administrator', 'supervisor', 'comite_bae' ),
		'panel-docente'    => array( 'administrator', 'docente' ),
	);

	global $post;
	$slug = $post->post_name ?? '';

	if ( ! isset( $panel_rules[ $slug ] ) ) {
		return;
	}

	// No redirigir si no hay usuario logueado (los paneles muestran el bloque de login).
	if ( ! is_user_logged_in() ) {
		return;
	}

	$user           = wp_get_current_user();
	$roles          = (array) $user->roles;
	$allowed_roles  = $panel_rules[ $slug ];

	foreach ( $allowed_roles as $ar ) {
		if ( in_array( $ar, $roles, true ) ) {
			return; // Tiene permiso.
		}
	}

	// No tiene permiso → redirigir al panel correcto.
	if ( function_exists( 'gnf_get_default_panel_url' ) ) {
		wp_safe_redirect( gnf_get_default_panel_url( $user ) );
	} else {
		wp_safe_redirect( home_url() );
	}
	exit;
}
add_action( 'template_redirect', 'gnf_protect_frontend_panels' );

/**
 * Ocultar toolbar para roles de frontend.
 */
function gnf_hide_toolbar_for_frontend_roles( $show ) {
	$user  = wp_get_current_user();
	$roles = (array) $user->roles;
	foreach ( array( 'docente', 'supervisor', 'comite_bae' ) as $role ) {
		if ( in_array( $role, $roles, true ) ) {
			return false;
		}
	}
	return $show;
}
add_filter( 'show_admin_bar', 'gnf_hide_toolbar_for_frontend_roles' );
