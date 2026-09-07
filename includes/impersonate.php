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
define( 'GNF_IMPERSONATE_RETURN_COOKIE', 'gnf_impersonate_return' );

/**
 * Obtiene el primer docente activo de cada centro solicitado.
 *
 * @param int[] $centro_ids IDs de centros.
 * @return array<int,int> Centro ID => usuario ID.
 */
function gnf_get_primary_docentes_for_centros( $centro_ids ) {
	global $wpdb;

	$centro_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $centro_ids ) ) ) );
	$centro_ids = array_values(
		array_filter(
			$centro_ids,
			static function ( $centro_id ) {
				return 'centro_educativo' === get_post_type( $centro_id );
			}
		)
	);
	if ( ! $centro_ids ) {
		return array();
	}

	update_meta_cache( 'post', $centro_ids );
	$by_centro      = array();
	$id_placeholders = implode( ',', array_fill( 0, count( $centro_ids ), '%d' ) );
	$meta_keys       = array( 'centro_educativo_id', 'centro_solicitado', 'gnf_centro_id' );
	$key_placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
	$user_rows       = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key IN ({$key_placeholders}) AND CAST(meta_value AS UNSIGNED) IN ({$id_placeholders})",
			array_merge( $meta_keys, $centro_ids )
		),
		ARRAY_A
	);
	foreach ( (array) $user_rows as $row ) {
		$centro_id = absint( $row['meta_value'] ?? 0 );
		$user_id   = absint( $row['user_id'] ?? 0 );
		if ( $centro_id && $user_id ) {
			$by_centro[ $centro_id ][] = $user_id;
		}
	}

	foreach ( $centro_ids as $centro_id ) {
		$associated = get_post_meta( $centro_id, 'docentes_asociados', true );
		$associated = maybe_unserialize( $associated );
		if ( ! is_array( $associated ) ) {
			$associated = '' !== trim( (string) $associated ) ? array( $associated ) : array();
		}
		$by_centro[ $centro_id ] = array_values(
			array_unique(
				array_filter(
					array_map(
						'absint',
						array_merge( $by_centro[ $centro_id ] ?? array(), $associated )
					)
				)
			)
		);
		sort( $by_centro[ $centro_id ], SORT_NUMERIC );
	}

	$all_user_ids = array();
	foreach ( $by_centro as $user_ids ) {
		$all_user_ids = array_merge( $all_user_ids, $user_ids );
	}
	$all_user_ids = array_values( array_unique( array_filter( array_map( 'absint', $all_user_ids ) ) ) );
	if ( $all_user_ids && function_exists( 'cache_users' ) ) {
		cache_users( $all_user_ids );
		update_meta_cache( 'user', $all_user_ids );
	}

	$primary = array();
	foreach ( $centro_ids as $centro_id ) {
		foreach ( $by_centro[ $centro_id ] as $user_id ) {
			$user = get_userdata( $user_id );
			if ( ! $user instanceof WP_User || ! in_array( 'docente', (array) $user->roles, true ) ) {
				continue;
			}
			if ( function_exists( 'gnf_get_docente_estado' ) && 'activo' !== gnf_get_docente_estado( $user_id ) ) {
				continue;
			}

			$primary[ $centro_id ] = $user_id;
			break;
		}
	}

	return $primary;
}

/**
 * Obtiene el primer docente activo asociado a un centro.
 *
 * @param int $centro_id ID del centro.
 * @return int
 */
function gnf_get_primary_docente_for_centro( $centro_id ) {
	$centro_id = absint( $centro_id );
	$primary   = gnf_get_primary_docentes_for_centros( array( $centro_id ) );
	return absint( $primary[ $centro_id ] ?? 0 );
}

/**
 * Construye una URL administrativa firmada para impersonar un usuario.
 *
 * @param int    $target_user_id Usuario destino.
 * @param string $return_url     URL administrativa de retorno.
 * @return string
 */
function gnf_build_impersonate_url( $target_user_id, $return_url = '' ) {
	$target_user_id = absint( $target_user_id );
	if ( ! current_user_can( 'manage_options' ) || ! $target_user_id || get_current_user_id() === $target_user_id ) {
		return '';
	}
	if ( ! get_userdata( $target_user_id ) || user_can( $target_user_id, 'manage_options' ) ) {
		return '';
	}

	$args = array(
		'action'  => 'gnf_impersonate',
		'user_id' => $target_user_id,
	);
	if ( $return_url ) {
		$args['return_to'] = wp_validate_redirect( $return_url, admin_url() );
	}

	return wp_nonce_url(
		add_query_arg( $args, admin_url( 'admin-post.php' ) ),
		'gnf_impersonate'
	);
}

/**
 * Construye la URL para entrar al panel docente de un centro.
 *
 * @param int    $centro_id  ID del centro.
 * @param string $return_url URL administrativa de retorno.
 * @return string
 */
function gnf_build_centro_docente_impersonate_url( $centro_id, $return_url = '' ) {
	$user_id = gnf_get_primary_docente_for_centro( $centro_id );
	return $user_id ? gnf_build_impersonate_url( $user_id, $return_url ) : '';
}

/**
 * Reconstruye la URL administrativa actual conservando sus filtros.
 *
 * @param string $fallback URL alternativa.
 * @return string
 */
function gnf_get_current_admin_return_url( $fallback = '' ) {
	$fallback    = $fallback ? wp_validate_redirect( $fallback, admin_url() ) : admin_url();
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$parts       = $request_uri ? wp_parse_url( $request_uri ) : array();
	$filename    = isset( $parts['path'] ) ? basename( (string) $parts['path'] ) : '';
	if ( ! $filename || ! preg_match( '/^[a-z0-9-]+\.php$/i', $filename ) ) {
		return $fallback;
	}

	$url = admin_url( $filename );
	if ( ! empty( $parts['query'] ) ) {
		$url .= '?' . (string) $parts['query'];
	}
	return wp_validate_redirect( esc_url_raw( $url ), $fallback );
}

/**
 * Guarda una URL de retorno firmada para la sesión de impersonación.
 *
 * @param int    $original_user_id Administrador original.
 * @param string $return_url       URL de retorno.
 * @return void
 */
function gnf_set_impersonate_return_cookie( $original_user_id, $return_url ) {
	$payload = base64_encode(
		wp_json_encode(
			array(
				'user_id' => absint( $original_user_id ),
				'url'     => wp_validate_redirect( $return_url, admin_url() ),
			)
		)
	);
	$value = $payload . '|' . wp_hash( 'gnf_impersonate_return_' . $payload );
	setcookie( GNF_IMPERSONATE_RETURN_COOKIE, $value, 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
	$_COOKIE[ GNF_IMPERSONATE_RETURN_COOKIE ] = $value;
}

/**
 * Lee y valida la URL de retorno de la sesión de impersonación.
 *
 * @param int $original_user_id Administrador original esperado.
 * @return string
 */
function gnf_get_impersonate_return_url( $original_user_id = 0 ) {
	if ( empty( $_COOKIE[ GNF_IMPERSONATE_RETURN_COOKIE ] ) ) {
		return admin_url();
	}

	$parts = explode( '|', sanitize_text_field( wp_unslash( $_COOKIE[ GNF_IMPERSONATE_RETURN_COOKIE ] ) ), 2 );
	if ( 2 !== count( $parts ) || ! hash_equals( wp_hash( 'gnf_impersonate_return_' . $parts[0] ), $parts[1] ) ) {
		return admin_url();
	}

	$decoded = base64_decode( $parts[0], true );
	$data    = $decoded ? json_decode( $decoded, true ) : null;
	if ( ! is_array( $data ) || absint( $data['user_id'] ?? 0 ) !== absint( $original_user_id ) ) {
		return admin_url();
	}

	return wp_validate_redirect( (string) ( $data['url'] ?? '' ), admin_url() );
}

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
	gnf_log_audit_event(
		'admin_start_impersonation',
		array(
			'actor_user_id'  => $original_id,
			'target_user_id' => $target_id,
			'message'        => 'Admin inicio impersonacion de usuario.',
		)
	);

	// Guardar cookie firmada con ID original.
	$signature = wp_hash( 'gnf_impersonate_' . $original_id );
	$cookie_value = $original_id . '|' . $signature;
	setcookie( GNF_IMPERSONATE_COOKIE, $cookie_value, 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
	$return_url = isset( $_GET['return_to'] )
		? wp_validate_redirect( esc_url_raw( wp_unslash( $_GET['return_to'] ) ), admin_url() )
		: admin_url();
	gnf_set_impersonate_return_cookie( $original_id, $return_url );

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

	check_admin_referer( 'gnf_impersonate' );

	$original_id = gnf_get_impersonate_original_user();
	if ( ! $original_id || ! get_userdata( $original_id ) ) {
		// Limpiar cookie corrupta y redirigir.
		gnf_clear_impersonate_cookie();
		wp_safe_redirect( home_url() );
		exit;
	}
	$return_url = gnf_get_impersonate_return_url( $original_id );

	// Restaurar sesión original.
	gnf_clear_impersonate_cookie();
	wp_clear_auth_cookie();
	wp_set_current_user( $original_id );
	wp_set_auth_cookie( $original_id, false );
	gnf_log_audit_event(
		'admin_stop_impersonation',
		array(
			'actor_user_id' => $original_id,
			'message'       => 'Admin volvio a su cuenta original.',
		)
	);

	// Redirigir al panel admin.
	wp_safe_redirect( $return_url );
	exit;
}
add_action( 'admin_post_gnf_impersonate_stop',        'gnf_handle_impersonate_stop' );
add_action( 'admin_post_nopriv_gnf_impersonate_stop', 'gnf_handle_impersonate_stop' );

/**
 * Elimina la cookie de impersonation.
 */
function gnf_clear_impersonate_cookie() {
	setcookie( GNF_IMPERSONATE_COOKIE, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
	setcookie( GNF_IMPERSONATE_RETURN_COOKIE, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
	unset( $_COOKIE[ GNF_IMPERSONATE_COOKIE ] );
	unset( $_COOKIE[ GNF_IMPERSONATE_RETURN_COOKIE ] );
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
		right: 16px;
		bottom: 16px;
		z-index: 99999;
		max-width: min(420px, calc(100vw - 32px));
		background: rgba(35, 51, 84, 0.94);
		color: #fff;
		font-family: 'League Spartan', -apple-system, BlinkMacSystemFont, sans-serif;
		font-size: 13px;
		padding: 12px 14px;
		display: flex;
		align-items: flex-end;
		gap: 12px;
		justify-content: space-between;
		border-radius: 14px;
		box-shadow: 0 12px 34px rgba(15,23,42,0.28);
		backdrop-filter: blur(10px);
	">
		<span style="line-height: 1.4;">
			<strong style="display:block; margin-bottom:2px;">Visto como: <?php echo esc_html( $current_user->display_name ); ?></strong>
			<span style="opacity: 0.78; font-size: 12px;">Rol actual: <?php echo esc_html( $roles_label ); ?></span>
		</span>
		<a href="<?php echo esc_url( $stop_url ); ?>" style="
			background: rgba(255,255,255,0.14);
			color: #fff;
			padding: 8px 12px;
			border-radius: 999px;
			text-decoration: none;
			font-weight: 600;
			font-size: 12px;
			white-space: nowrap;
		">Volver a mi cuenta</a>
	</div>
	<?php
}
add_action( 'wp_footer', 'gnf_render_impersonate_bar' );
