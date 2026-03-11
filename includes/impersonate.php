<?php
/**
 * Impersonation de usuarios para administradores.
 *
 * Permite a un administrador entrar al panel de cualquier usuario
 * manteniendo una cookie firmada para restaurar la sesión original.
 *
 * @package Guardianes_Formularios
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GNF_IMPERSONATE_COOKIE', 'gnf_impersonate_from' );

/**
 * Retorna true si hay una sesión de impersonation activa.
 */
function gnf_is_impersonating() {
	if ( empty( $_COOKIE[ GNF_IMPERSONATE_COOKIE ] ) ) {
		return false;
	}

	$parts = explode( '|', sanitize_text_field( wp_unslash( $_COOKIE[ GNF_IMPERSONATE_COOKIE ] ) ), 2 );
	if ( count( $parts ) !== 2 ) {
		return false;
	}

	list( $user_id, $signature ) = $parts;
	$user_id = absint( $user_id );

	return $user_id > 0 && wp_hash( 'gnf_impersonate_' . $user_id ) === $signature;
}

/**
 * Retorna el ID del administrador original si hay impersonation activa.
 *
 * @return int ID de usuario o 0.
 */
function gnf_get_impersonate_original_user() {
	if ( ! gnf_is_impersonating() ) {
		return 0;
	}

	$parts   = explode( '|', sanitize_text_field( wp_unslash( $_COOKIE[ GNF_IMPERSONATE_COOKIE ] ) ), 2 );
	return absint( $parts[0] );
}

/**
 * Inicia impersonation: el admin pasa a tener la sesión del usuario target.
 * Guarda cookie firmada con el ID original.
 *
 * Handler: admin-post.php?action=gnf_impersonate
 */
function gnf_handle_impersonate_start() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permisos.', 403 );
	}

	check_admin_referer( 'gnf_impersonate' );

	$target_id = absint( $_GET['user_id'] ?? 0 );
	if ( ! $target_id ) {
		wp_die( 'Usuario inválido.' );
	}

	$target_user = get_userdata( $target_id );
	if ( ! $target_user ) {
		wp_die( 'Usuario no encontrado.' );
	}

	// No permitir impersonar a otro administrador.
	if ( user_can( $target_id, 'manage_options' ) ) {
		wp_die( 'No se puede impersonar a un administrador.' );
	}

	$original_id = get_current_user_id();

	// Guardar cookie firmada con ID original.
	$signature = wp_hash( 'gnf_impersonate_' . $original_id );
	$cookie_value = $original_id . '|' . $signature;
	setcookie( GNF_IMPERSONATE_COOKIE, $cookie_value, 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

	// Cambiar sesión al usuario target.
	wp_clear_auth_cookie();
	wp_set_current_user( $target_id );
	wp_set_auth_cookie( $target_id, false );

	// Redirigir al panel del usuario target según su rol.
	$redirect_url = function_exists( 'gnf_get_default_panel_url' )
		? gnf_get_default_panel_url( $target_user )
		: home_url();
	wp_safe_redirect( $redirect_url );
	exit;
}
add_action( 'admin_post_gnf_impersonate', 'gnf_handle_impersonate_start' );

/**
 * Finaliza impersonation: restaura la sesión del administrador original.
 *
 * Handler: admin-post.php?action=gnf_impersonate_stop
 */
function gnf_handle_impersonate_stop() {
	if ( ! gnf_is_impersonating() ) {
		wp_safe_redirect( home_url() );
		exit;
	}

	$original_id = gnf_get_impersonate_original_user();
	if ( ! $original_id || ! get_userdata( $original_id ) ) {
		// Limpiar cookie corrupta y redirigir.
		gnf_clear_impersonate_cookie();
		wp_safe_redirect( home_url() );
		exit;
	}

	// Restaurar sesión original.
	gnf_clear_impersonate_cookie();
	wp_clear_auth_cookie();
	wp_set_current_user( $original_id );
	wp_set_auth_cookie( $original_id, false );

	// Redirigir al panel admin.
	wp_safe_redirect( home_url( '/panel-admin/' ) );
	exit;
}
add_action( 'admin_post_gnf_impersonate_stop',        'gnf_handle_impersonate_stop' );
add_action( 'admin_post_nopriv_gnf_impersonate_stop', 'gnf_handle_impersonate_stop' );

/**
 * Elimina la cookie de impersonation.
 */
function gnf_clear_impersonate_cookie() {
	setcookie( GNF_IMPERSONATE_COOKIE, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
	unset( $_COOKIE[ GNF_IMPERSONATE_COOKIE ] );
}

/**
 * Muestra barra flotante de impersonation en el frontend cuando está activa.
 */
function gnf_render_impersonate_bar() {
	if ( ! gnf_is_impersonating() ) {
		return;
	}

	$current_user = wp_get_current_user();
	$roles_label  = implode( ', ', (array) $current_user->roles );

	$stop_url = wp_nonce_url(
		admin_url( 'admin-post.php?action=gnf_impersonate_stop' ),
		'gnf_impersonate'
	);

	?>
	<div id="gnf-impersonate-bar" style="
		position: fixed;
		top: 0;
		left: 0;
		right: 0;
		z-index: 99999;
		background: #e67e22;
		color: #fff;
		font-family: 'League Spartan', -apple-system, BlinkMacSystemFont, sans-serif;
		font-size: 13px;
		padding: 8px 16px;
		display: flex;
		align-items: center;
		justify-content: space-between;
		box-shadow: 0 2px 6px rgba(0,0,0,0.25);
	">
		<span>
			<strong>Vista como:</strong>
			<?php echo esc_html( $current_user->display_name ); ?>
			<span style="opacity: 0.8;">(<?php echo esc_html( $roles_label ); ?>)</span>
		</span>
		<a href="<?php echo esc_url( $stop_url ); ?>" style="
			background: #fff;
			color: #e67e22;
			padding: 4px 12px;
			border-radius: 4px;
			text-decoration: none;
			font-weight: 600;
			font-size: 12px;
		">Volver a mi cuenta</a>
	</div>
	<style>body { margin-top: 36px !important; }</style>
	<?php
}
add_action( 'wp_footer', 'gnf_render_impersonate_bar' );
