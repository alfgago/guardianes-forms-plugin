<?php
/**
 * Lanzamiento controlado de funcionalidades por centro educativo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normaliza un modo de lanzamiento.
 *
 * @param mixed $mode Valor recibido.
 * @return string
 */
function gnf_normalize_feature_rollout_mode( $mode ) {
	$mode = strtolower( trim( (string) $mode ) );
	return in_array( $mode, array( 'off', 'pilot', 'all' ), true ) ? $mode : 'off';
}

/**
 * Evalua un modo sin depender de WordPress.
 *
 * @param string $mode       Modo.
 * @param int    $centro_id  Centro.
 * @param int[]  $pilot_ids  Centros piloto.
 * @param bool   $is_preview Vista previa administrativa.
 * @return bool
 */
function gnf_feature_enabled_for_values( $mode, $centro_id, $pilot_ids, $is_preview = false ) {
	if ( $is_preview ) {
		return true;
	}

	$mode      = gnf_normalize_feature_rollout_mode( $mode );
	$centro_id = abs( (int) $centro_id );
	$pilot_ids = array_values(
		array_unique(
			array_filter(
				array_map(
					static function ( $value ) {
						return abs( (int) $value );
					},
					(array) $pilot_ids
				)
			)
		)
	);

	if ( 'all' === $mode ) {
		return true;
	}

	return 'pilot' === $mode && $centro_id > 0 && in_array( $centro_id, $pilot_ids, true );
}

/**
 * Nombre de opcion ACF para una funcionalidad.
 *
 * @param string $feature Funcionalidad.
 * @return string
 */
function gnf_get_feature_rollout_option_name( $feature ) {
	$map = array(
		'awards'  => 'rollout_awards_mode',
		'reports' => 'rollout_reports_mode',
		'impact'  => 'rollout_impact_mode',
	);

	return $map[ sanitize_key( (string) $feature ) ] ?? '';
}

/**
 * Lee una opcion de la pagina ACF, con respaldo al nombre nativo.
 *
 * @param string $name Nombre de campo.
 * @param mixed  $default Valor predeterminado.
 * @return mixed
 */
function gnf_get_rollout_option( $name, $default = '' ) {
	$value = function_exists( 'get_field' ) ? get_field( $name, 'option' ) : null;
	if ( false === $value || null === $value || '' === $value ) {
		$value = function_exists( 'get_option' ) ? get_option( 'options_' . $name, $default ) : $default;
	}
	return $value;
}

/**
 * Obtiene el modo vigente de una funcionalidad.
 *
 * @param string $feature Funcionalidad.
 * @return string
 */
function gnf_get_feature_rollout_mode( $feature ) {
	$option = gnf_get_feature_rollout_option_name( $feature );
	return $option ? gnf_normalize_feature_rollout_mode( gnf_get_rollout_option( $option, 'off' ) ) : 'off';
}

/**
 * IDs seleccionados para el piloto.
 *
 * @return int[]
 */
function gnf_get_pilot_center_ids() {
	$value = gnf_get_rollout_option( 'pilot_centers', array() );
	$ids   = array();
	foreach ( (array) $value as $item ) {
		$ids[] = is_object( $item ) && isset( $item->ID ) ? (int) $item->ID : (int) $item;
	}
	return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
}

/**
 * Indica si el usuario actual puede previsualizar funcionalidades.
 *
 * @return bool
 */
function gnf_current_user_can_preview_features() {
	return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' );
}

/**
 * Comprueba rollout para un centro concreto.
 *
 * @param string $feature             Funcionalidad.
 * @param int    $centro_id           Centro.
 * @param bool   $allow_admin_preview Permitir vista previa administrativa.
 * @return bool
 */
function gnf_feature_is_enabled_for_center( $feature, $centro_id, $allow_admin_preview = true ) {
	$is_preview = $allow_admin_preview && gnf_current_user_can_preview_features();
	return gnf_feature_enabled_for_values(
		gnf_get_feature_rollout_mode( $feature ),
		$centro_id,
		gnf_get_pilot_center_ids(),
		$is_preview
	);
}

/**
 * Datos compactos para explicar el alcance en las interfaces.
 *
 * @param string $feature Funcionalidad.
 * @return array
 */
function gnf_get_feature_rollout_summary( $feature ) {
	$mode  = gnf_get_feature_rollout_mode( $feature );
	$count = count( gnf_get_pilot_center_ids() );
	$labels = array(
		'off'   => 'Vista previa administrativa',
		'pilot' => sprintf( 'Piloto con %d centros', $count ),
		'all'   => 'Disponible para todos los centros',
	);
	return array(
		'mode'             => $mode,
		'label'            => $labels[ $mode ],
		'pilotCenterCount' => $count,
	);
}
