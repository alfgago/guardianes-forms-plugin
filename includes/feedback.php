<?php
/**
 * Formulario de retroalimentacion compartido por paneles.
 *
 * Se muestra como iframe (pagina same-domain) dentro del modal de los
 * paneles docente y supervisor, con override de URL desde opciones ACF.
 *
 * @package Guardianes_Formularios
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GNF_FEEDBACK_DEFAULT_URL', 'https://movimientoguardianes.org/califica-el-pilotaje-de-innovaciones-pbae/' );

/**
 * Obtiene la URL de la pagina de retroalimentacion.
 *
 * @return string
 */
function gnf_get_feedback_page_url() {
	$value = function_exists( 'get_field' )
		? get_field( 'feedback_page_url', 'option' )
		: get_option( 'options_feedback_page_url', '' );

	$url = esc_url_raw( trim( (string) $value ) );

	return $url ? $url : GNF_FEEDBACK_DEFAULT_URL;
}

/**
 * Determina si un usuario puede usar el formulario.
 *
 * @param WP_User $user Usuario actual.
 * @return bool
 */
function gnf_feedback_user_is_allowed( $user ) {
	if ( ! $user instanceof WP_User || ! $user->exists() ) {
		return false;
	}

	$roles = (array) $user->roles;
	if ( in_array( 'comite_bae', $roles, true ) || user_can( $user, 'manage_options' ) ) {
		return false;
	}

	return in_array( 'docente', $roles, true )
		|| in_array( 'supervisor', $roles, true );
}
