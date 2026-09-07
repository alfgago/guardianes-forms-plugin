<?php
// Contrato del lanzamiento controlado por funcionalidad y centro.

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
check_feature_rollout( false !== strpos( $acf, "'name'          => 'rollout_awards_mode'" ), 'configuracion expone modo de galardones' );
check_feature_rollout( false !== strpos( $acf, "'name'          => 'rollout_reports_mode'" ), 'configuracion expone modo de reportes' );
check_feature_rollout( false !== strpos( $acf, "'name'          => 'rollout_impact_mode'" ), 'configuracion expone modo de indicadores' );
check_feature_rollout( false !== strpos( $acf, "'name'          => 'pilot_centers'" ) && false !== strpos( $acf, "'post_type'     => array( 'centro_educativo' )" ), 'configuracion permite seleccionar centros piloto' );

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
}

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );

