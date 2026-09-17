<?php
// Disponibilidad general, incluso con opciones antiguas del piloto.

$root      = __DIR__ . '/..';
$module    = @file_get_contents( $root . '/includes/feature-rollout.php' ) ?: '';
$bootstrap = file_get_contents( $root . '/guardianes-formularios.php' );
$acf       = file_get_contents( $root . '/includes/acf-fields.php' );

$tests = 0;
$fails = 0;

function check_feature_rollout( $condition, $message ) {
	global $tests, $fails;
	$tests++;
	if ( $condition ) {
		echo "  ok: {$message}\n";
	} else {
		$fails++;
		echo "  FAIL: {$message}\n";
	}
}

check_feature_rollout( '' !== $module, 'existe el servicio de lanzamiento controlado' );
check_feature_rollout( false !== strpos( $bootstrap, "require_once 'includes/feature-rollout.php';" ), 'bootstrap carga rollout antes de las funcionalidades' );
foreach ( array( 'rollout_awards_mode', 'rollout_reports_mode', 'rollout_impact_mode', 'pilot_centers' ) as $field ) {
	check_feature_rollout( false === strpos( $acf, "'name'          => '{$field}'" ), "configuracion ya no ofrece {$field}" );
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
}
function absint( $value ) {
	return abs( (int) $value );
}
function get_field( $name, $context ) {
	return $GLOBALS['rollout_acf'][ $name ] ?? false;
}
function get_option( $name, $default = false ) {
	return $GLOBALS['rollout_options'][ $name ] ?? $default;
}
function current_user_can( $capability ) {
	return ! empty( $GLOBALS['rollout_admin'] );
}

if ( file_exists( $root . '/includes/feature-rollout.php' ) ) {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', $root . '/' );
	}
	require_once $root . '/includes/feature-rollout.php';
	check_feature_rollout( 'off' === gnf_normalize_feature_rollout_mode( 'invalid' ), 'modo desconocido queda desactivado' );
	check_feature_rollout( 'pilot' === gnf_normalize_feature_rollout_mode( 'pilot' ), 'acepta modo piloto' );
	check_feature_rollout( false === gnf_feature_enabled_for_values( 'off', 10, array( 10 ), false ), 'modo desactivado no expone el centro' );
	check_feature_rollout( true === gnf_feature_enabled_for_values( 'pilot', 10, array( 10, 11 ), false ), 'piloto permite un centro seleccionado' );
	check_feature_rollout( false === gnf_feature_enabled_for_values( 'pilot', 12, array( 10, 11 ), false ), 'piloto excluye centros no seleccionados' );
	check_feature_rollout( true === gnf_feature_enabled_for_values( 'all', 12, array(), false ), 'modo general permite cualquier centro' );
	check_feature_rollout( true === gnf_feature_enabled_for_values( 'off', 12, array(), true ), 'vista administrativa puede previsualizar' );

	foreach ( array( 'off', 'pilot', 'all', 'invalid', '' ) as $stored_mode ) {
		foreach ( array( 'acf', 'options' ) as $storage ) {
			$GLOBALS['rollout_acf'] = array();
			$GLOBALS['rollout_options'] = array();
			$values = array( 'pilot_centers' => array( 10 ) );
			foreach ( array( 'awards', 'reports', 'impact' ) as $feature ) {
				$values[ 'rollout_' . $feature . '_mode' ] = $stored_mode;
			}
			foreach ( $values as $name => $value ) {
				if ( 'acf' === $storage ) {
					$GLOBALS['rollout_acf'][ $name ] = $value;
				} else {
					$GLOBALS['rollout_options'][ 'options_' . $name ] = $value;
				}
			}
			foreach ( array( 'awards', 'reports', 'impact' ) as $feature ) {
				$context = "{$feature}, {$storage}, modo guardado '{$stored_mode}'";
				check_feature_rollout( 'all' === gnf_get_feature_rollout_mode( $feature ), "siempre disponible: {$context}" );
				check_feature_rollout( gnf_feature_is_enabled_for_center( $feature, 12, false ), "centro fuera del antiguo piloto tiene acceso: {$context}" );
				$summary = gnf_get_feature_rollout_summary( $feature );
				check_feature_rollout( 'all' === $summary['mode'] && 0 === $summary['pilotCenterCount'], "resumen sin restriccion piloto: {$context}" );
			}
			check_feature_rollout( array() === gnf_get_pilot_center_ids(), 'lista antigua no limita agregaciones ni cache' );
		}
	}
	check_feature_rollout( 'off' === gnf_get_feature_rollout_mode( 'unknown' ), 'funcionalidad desconocida no se activa' );
	$GLOBALS['rollout_admin'] = true;
	check_feature_rollout( ! gnf_feature_is_enabled_for_center( 'unknown', 12 ), 'vista administrativa no habilita funcionalidades desconocidas' );
}

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );

