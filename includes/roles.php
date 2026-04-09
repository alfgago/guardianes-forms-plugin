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
 * Verifica acceso a un panel frontend usando roles y capabilities.
 *
 * @param WP_User $user Usuario.
 * @param string  $panel_slug Slug del panel, por ejemplo panel-docente.
 * @return bool
 */
function gnf_user_can_access_panel( $user, $panel_slug ) {
	if ( ! $user instanceof WP_User || ! $user->exists() ) {
		return false;
	}

	if ( function_exists( 'gnf_maybe_restore_frontend_role' ) ) {
		$restored = gnf_maybe_restore_frontend_role( $user, 'user_can_access_panel:' . $panel_slug );
		if ( $restored instanceof WP_User ) {
			$user = $restored;
		}
	}

	$roles = (array) $user->roles;
	$is_admin_panel_user = in_array( 'administrator', $roles, true )
		|| user_can( $user, 'manage_options' )
		|| user_can( $user, 'manage_network_options' )
		|| user_can( $user, 'manage_guardianes' )
		|| user_can( $user, 'manage_translations' );

	switch ( $panel_slug ) {
		case 'panel-admin':
			return $is_admin_panel_user;

		case 'panel-comite':
			return $is_admin_panel_user
				|| in_array( 'comite_bae', $roles, true )
				|| user_can( $user, 'gnf_view_all_regions' )
				|| user_can( $user, 'view_guardianes_supervisor' );

		case 'panel-supervisor':
			return $is_admin_panel_user
				|| in_array( 'supervisor', $roles, true )
				|| in_array( 'comite_bae', $roles, true )
				|| user_can( $user, 'view_guardianes_supervisor' );

		case 'panel-docente':
			return $is_admin_panel_user
				|| in_array( 'docente', $roles, true )
				|| user_can( $user, 'view_guardianes_docente' );
	}

	return false;
}

/**
 * Devuelve el panel principal disponible para un usuario.
 *
 * @param WP_User $user Usuario.
 * @return string
 */
function gnf_get_primary_panel_slug( $user ) {
	if ( function_exists( 'gnf_maybe_restore_frontend_role' ) ) {
		$restored = gnf_maybe_restore_frontend_role( $user, 'get_primary_panel_slug' );
		if ( $restored instanceof WP_User ) {
			$user = $restored;
		}
	}

	if ( gnf_user_can_access_panel( $user, 'panel-admin' ) ) {
		return 'panel-admin';
	}

	if ( gnf_user_can_access_panel( $user, 'panel-supervisor' ) ) {
		return 'panel-supervisor';
	}

	if ( gnf_user_can_access_panel( $user, 'panel-docente' ) ) {
		return 'panel-docente';
	}

	return '';
}

/**
 * Registra contexto de acceso en el error log para depurar permisos.
 *
 * @param string  $context Contexto.
 * @param WP_User $user    Usuario.
 * @param array   $extra   Datos extra.
 * @return void
 */
function gnf_log_panel_access_context( $context, $user, $extra = array() ) {
	if ( ! $user instanceof WP_User ) {
		return;
	}

	$payload = array_merge(
		array(
			'context'      => (string) $context,
			'user_id'      => (int) $user->ID,
			'user_login'   => (string) $user->user_login,
			'roles'        => array_values( (array) $user->roles ),
			'primary'      => gnf_get_primary_panel_slug( $user ),
			'panel_admin'  => gnf_user_can_access_panel( $user, 'panel-admin' ),
			'panel_sup'    => gnf_user_can_access_panel( $user, 'panel-supervisor' ),
			'panel_doc'    => gnf_user_can_access_panel( $user, 'panel-docente' ),
		),
		(array) $extra
	);

	error_log( '[GNF access] ' . wp_json_encode( $payload ) );
}

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
	if ( function_exists( 'gnf_maybe_restore_frontend_role' ) ) {
		$restored = gnf_maybe_restore_frontend_role( $user, 'block_admin_for_frontend_roles' );
		if ( $restored instanceof WP_User ) {
			$user = $restored;
		}
	}
	$roles = (array) $user->roles;
	$blocked = array( 'docente', 'supervisor', 'comite_bae' );
	foreach ( $blocked as $role ) {
		if ( in_array( $role, $roles, true ) ) {
			$redirect = function_exists( 'gnf_get_default_panel_url' )
				? gnf_get_default_panel_url( $user )
				: home_url();
			if ( function_exists( 'gnf_log_panel_access_context' ) ) {
				gnf_log_panel_access_context(
					'wp_admin_redirect',
					$user,
					array(
						'matched_role' => $role,
						'redirect'     => $redirect,
					)
				);
			}
			wp_safe_redirect( $redirect );
			exit;
		}
	}
}
add_action( 'init', 'gnf_block_admin_for_frontend_roles' );

/**
 * Redirige a los usuarios al panel correcto tras login en wp-login.php.
 *
 * Sin este filtro WordPress redirige a wp-admin, donde
 * gnf_block_admin_for_frontend_roles los envía al home.
 */
function gnf_login_redirect( $redirect_to, $requested_redirect_to, $user ) {
	if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
		return $redirect_to;
	}

	if ( '' === gnf_get_primary_panel_slug( $user ) ) {
		return $redirect_to;
	}

	$home_path = wp_parse_url( home_url(), PHP_URL_PATH ) ?: '';
	$req_path  = wp_parse_url( $requested_redirect_to, PHP_URL_PATH ) ?: '';
	$rel_path  = trim( str_replace( $home_path, '', $req_path ), '/' );
	$primary_panel = gnf_get_primary_panel_slug( $user );

	foreach ( array( 'panel-docente', 'panel-supervisor', 'panel-admin', 'panel-comite' ) as $panel_slug ) {
		if ( 0 !== strpos( trailingslashit( '/' . $rel_path ), '/' . $panel_slug . '/' ) ) {
			continue;
		}

		$allowed_panel_redirects = array( $primary_panel );
		if ( 'panel-supervisor' === $primary_panel ) {
			$allowed_panel_redirects[] = 'panel-comite';
		}

		return in_array( $panel_slug, $allowed_panel_redirects, true )
			? $requested_redirect_to
			: ( function_exists( 'gnf_get_default_panel_url' ) ? gnf_get_default_panel_url( $user ) : home_url() );
	}

	return function_exists( 'gnf_get_default_panel_url' )
		? gnf_get_default_panel_url( $user )
		: $redirect_to;
}
add_filter( 'login_redirect', 'gnf_login_redirect', 10, 3 );

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
	$panel_slugs = array(
		'panel-admin',
		'panel-comite',
		'panel-supervisor',
		'panel-docente',
	);

	global $post;
	$slug = $post->post_name ?? '';

	if ( 'panel-comite' === $slug ) {
		$query = array();
		foreach ( (array) wp_unslash( $_GET ) as $key => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$query[ sanitize_key( (string) $key ) ] = sanitize_text_field( (string) $value );
		}
		$target = add_query_arg( $query, home_url( '/panel-supervisor/' ) );
		wp_safe_redirect( $target );
		exit;
	}

	if ( ! in_array( $slug, $panel_slugs, true ) ) {
		return;
	}

	// Sin sesión, solo los paneles frontend muestran auth propio.
	if ( ! is_user_logged_in() ) {
		if ( 'panel-admin' === $slug ) {
			wp_safe_redirect( wp_login_url( home_url( '/panel-admin/' ) ) );
			exit;
		}

		return;
	}

	$user = wp_get_current_user();
	if ( gnf_user_can_access_panel( $user, $slug ) ) {
		return;
	}

	// No tiene permiso → redirigir al panel correcto.
	if ( function_exists( 'gnf_log_panel_access_context' ) ) {
		gnf_log_panel_access_context(
			'template_redirect_denied',
			$user,
			array(
				'requested_panel' => $slug,
				'request_uri'     => isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '',
			)
		);
	}

	if ( function_exists( 'gnf_get_default_panel_url' ) ) {
		$target = gnf_get_default_panel_url( $user );

		// Si el usuario no tiene ningún panel válido (devuelve home_url()),
		// cerrar sesión para que vea el formulario de login en esta misma página.
		if ( untrailingslashit( $target ) === untrailingslashit( home_url() ) ) {
			wp_logout();
			return; // El shortcode mostrará el panel de autenticación.
		}

		wp_safe_redirect( $target );
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
