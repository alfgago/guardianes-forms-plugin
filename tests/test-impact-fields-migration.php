<?php
// Migracion incremental de campos cuantificables WPForms.

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

require_once __DIR__ . '/../includes/impact-metrics.php';

$tests = 0;
$fails = 0;

function check_impact_field( $condition, $message ) {
	global $tests, $fails;
	$tests++;
	if ( $condition ) {
		echo "  ok: {$message}\n";
	} else {
		$fails++;
		echo "  FAIL: {$message}\n";
	}
}

check_impact_field( function_exists( 'gnf_get_impact_field_definitions' ), 'existen definiciones estables de campos' );
check_impact_field( function_exists( 'gnf_prepare_impact_form_fields' ), 'existe transformacion incremental pura' );

if ( function_exists( 'gnf_get_impact_field_definitions' ) && function_exists( 'gnf_prepare_impact_form_fields' ) ) {
	$definitions = gnf_get_impact_field_definitions( 2026 );
	check_impact_field( count( $definitions ) >= 19, 'cubre campos existentes y cantidades nuevas' );

	$water_defs = array_values(
		array_filter(
			$definitions,
			static function ( $definition ) {
				return 'agua' === $definition['reto'];
			}
		)
	);
	$water_fields = array(
		5 => array( 'id' => 5, 'type' => 'radio', 'label' => '6. ¿Cuentan con un sistema permanente de captación de agua llovida?' ),
		8 => array( 'id' => 8, 'type' => 'radio', 'label' => 'Otro campo que no se debe modificar' ),
	);
	$prepared = gnf_prepare_impact_form_fields( $water_fields, $water_defs );
	check_impact_field( 'agua_captacion' === $prepared['fields'][5]['gnf_metric_key'], 'anota campo historico por etiqueta' );
	check_impact_field( ! isset( $prepared['fields'][8]['gnf_metric_key'] ), 'no altera campos no relacionados' );
	check_impact_field( 5 === $prepared['fields'][5]['id'] && 8 === $prepared['fields'][8]['id'], 'conserva IDs existentes' );

	$cleanup_defs = array_values(
		array_filter(
			$definitions,
			static function ( $definition ) {
				return 'limpiezas' === $definition['reto'];
			}
		)
	);
	$cleanup_fields = array( 20 => array( 'id' => 20, 'type' => 'radio', 'label' => '4. ¿Realizaron alguna limpieza?' ) );
	$prepared       = gnf_prepare_impact_form_fields( $cleanup_fields, $cleanup_defs );
	$added_keys     = array();
	foreach ( $prepared['fields'] as $field ) {
		if ( ! empty( $field['gnf_metric_key'] ) ) {
			$added_keys[] = $field['gnf_metric_key'];
		}
	}
	check_impact_field( in_array( 'limpiezas_total', $added_keys, true ), 'agrega cantidad total de limpiezas' );
	check_impact_field( in_array( 'limpiezas_bolsas', $added_keys, true ), 'agrega bolsas recolectadas' );
	$new_field_types = array();
	foreach ( $prepared['fields'] as $field ) {
		if ( ! empty( $field['gnf_metric_key'] ) ) {
			$new_field_types[ $field['gnf_metric_key'] ] = $field['type'];
		}
	}
	check_impact_field( 'number' === $new_field_types['limpiezas_total'] && 'number' === $new_field_types['limpiezas_bolsas'], 'cantidades nuevas usan control numerico' );
	check_impact_field( max( array_keys( $prepared['fields'] ) ) > 20, 'nuevos campos usan IDs posteriores' );

	$second_pass = gnf_prepare_impact_form_fields( $prepared['fields'], $cleanup_defs );
	check_impact_field( count( $prepared['fields'] ) === count( $second_pass['fields'] ), 'segunda ejecucion es idempotente' );
	check_impact_field( false === $second_pass['changed'], 'segunda ejecucion no vuelve a guardar' );
}

$seeder = file_get_contents( __DIR__ . '/../seeders/seed-wpforms.php' );
check_impact_field( false !== strpos( $seeder, "'gnf_metric_key'" ), 'seeder conserva la clave estable en instalaciones nuevas' );

$module = file_get_contents( __DIR__ . '/../includes/impact-metrics.php' );
check_impact_field( false === strpos( $module, "add_action( 'admin_init', 'gnf_maybe_ensure_impact_fields'" ), 'la migracion no modifica formularios al abrir wp-admin' );
check_impact_field( false !== strpos( $module, 'admin_post_gnf_prepare_impact_fields' ), 'la preparacion se ejecuta mediante una accion administrativa explicita' );
check_impact_field( false !== strpos( $module, "check_admin_referer( 'gnf_prepare_impact_fields_2026'" ), 'la accion manual exige nonce' );
check_impact_field( false !== strpos( $module, "current_user_can( 'manage_options' )" ), 'la accion manual exige permisos administrativos' );
check_impact_field( function_exists( 'gnf_impact_field_visibility_css' ), 'existe supresion estructurada de campos fuera del rollout' );
if ( function_exists( 'gnf_impact_field_visibility_css' ) ) {
	$css = gnf_impact_field_visibility_css( 45, array( 7, 9 ) );
	check_impact_field( false !== strpos( $css, '#wpforms-45-field_7-container' ) && false !== strpos( $css, '#wpforms-45-field_9-container' ), 'oculta unicamente los campos cuantificables introducidos' );
}

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
