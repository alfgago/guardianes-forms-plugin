<?php
// Reglas puras de galardones 2026.

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

$module = __DIR__ . '/../includes/award-rules.php';
if ( file_exists( $module ) ) {
	require_once $module;
}

$tests = 0;
$fails = 0;

function check_award_rule( $condition, $message ) {
	global $tests, $fails;
	$tests++;
	if ( $condition ) {
		echo "  ok: {$message}\n";
	} else {
		$fails++;
		echo "  FAIL: {$message}\n";
	}
}

check_award_rule( function_exists( 'gnf_evaluate_award' ), 'existe el evaluador puro' );
check_award_rule( function_exists( 'gnf_award_rubric_key' ), 'existe selector de rubrica' );
check_award_rule( 'small' === gnf_award_rubric_key( 'Tipo IV', 'Dirección II' ), 'selector acepta etiquetas humanas de tipologia' );
check_award_rule( 'small' === gnf_award_rubric_key( '', 'Dirección I' ), 'selector acepta etiquetas humanas de tipo de centro' );

if ( function_exists( 'gnf_evaluate_award' ) ) {
	$required = array( 'agua' => true, 'electricidad' => true, 'residuos' => true );
	$small    = gnf_evaluate_award(
		array(
			'score'       => 145,
			'tipologia'   => 'tipo_iv',
			'tipo_centro' => 'direccion_ii',
			'required'    => $required,
			'criteria'    => array(),
		)
	);
	check_award_rule( 'small' === $small['rubricKey'], 'Tipo IV usa rubrica de centros pequenos' );
	check_award_rule( 4 === $small['stars'], '145 puntos entrega cuatro estrellas en rubrica pequena' );
	check_award_rule( array( 60, 90, 120, 145, 180 ) === array_values( $small['thresholds'] ), 'umbrales pequenos coinciden con rubrica' );

	$large = gnf_evaluate_award(
		array(
			'score'       => 240,
			'tipologia'   => 'tipo_ii',
			'tipo_centro' => 'direccion_ii',
			'required'    => $required,
			'criteria'    => array(),
		)
	);
	check_award_rule( 'general' === $large['rubricKey'], 'Tipo II usa rubrica general' );
	check_award_rule( 4 === $large['stars'], '240 puntos entrega cuatro estrellas en rubrica general' );
	check_award_rule( array( 105, 120, 180, 240, 275 ) === array_values( $large['thresholds'] ), 'umbrales generales coinciden con rubrica' );

	$without_base = gnf_evaluate_award(
		array(
			'score'       => 300,
			'tipologia'   => 'tipo_i',
			'tipo_centro' => 'direccion_iii',
			'required'    => array( 'agua' => true, 'electricidad' => false, 'residuos' => true ),
			'criteria'    => array(
				'huerta_consumo'                    => true,
				'organicos'                         => true,
				'eco_lonchera'                      => true,
				'residuos_taller'                   => true,
				'residuos_estacion'                 => true,
				'residuos_registro_valorizables'   => true,
				'residuos_registro_no_valorizables'=> true,
				'bienestar_capacitacion'            => true,
				'bienestar_protocolo'               => true,
				'bienestar_implementacion'          => true,
			),
		)
	);
	check_award_rule( 5 === $without_base['scoreStars'], 'conserva estrellas potenciales por puntaje' );
	check_award_rule( 0 === $without_base['stars'], 'sin los tres retos base no asigna estrellas' );
	check_award_rule( in_array( 'electricidad', $without_base['missingRequired'], true ), 'explica el reto base faltante' );
	check_award_rule( empty( $without_base['awards']['dorada_turquesa']['achieved'] ), 'sin requisitos base no asigna reconocimientos especiales' );

	$small_fallback = gnf_evaluate_award(
		array(
			'score'       => 180,
			'tipologia'   => '',
			'tipo_centro' => 'unidocente',
			'required'    => $required,
			'criteria'    => array(
				'eco_gira'                       => true,
				'eco_emprendimiento'             => true,
				'huerta_consumo'                 => true,
				'organicos'                      => true,
				'residuos_taller'                => true,
				'residuos_estacion'              => true,
				'bienestar_capacitacion'         => true,
				'bienestar_protocolo'            => true,
				'bienestar_implementacion'       => true,
			)
		)
	);
	check_award_rule( 'small' === $small_fallback['rubricKey'], 'unidocente sirve de respaldo si falta tipologia' );
	check_award_rule( ! empty( $small_fallback['awards']['excelencia_general']['achieved'] ), 'excelencia de centro pequeno requiere cinco estrellas y dos retos' );
	check_award_rule( ! empty( $small_fallback['awards']['dorada_turquesa']['achieved'] ), 'premio dorado o turquesa aplica requisitos de huerta y organicos' );
	check_award_rule( ! empty( $small_fallback['awards']['plata_residuos']['achieved'] ), 'estrella plata aplica requisitos de residuos para centro pequeno' );
	check_award_rule( ! empty( $small_fallback['awards']['naranja_bienestar']['achieved'] ), 'estrella naranja exige las tres acciones' );

	$large_excellence = gnf_evaluate_award(
		array(
			'score'       => 345,
			'tipologia'   => 'tipo_i',
			'tipo_centro' => 'direccion_iii',
			'required'    => $required,
			'criteria'    => array(
				'huerta_consumo'                    => true,
				'organicos'                         => true,
				'eco_lonchera'                      => true,
				'residuos_taller'                   => true,
				'residuos_estacion'                 => true,
				'residuos_registro_valorizables'   => true,
				'residuos_registro_no_valorizables'=> true,
			)
		)
	);
	check_award_rule( ! empty( $large_excellence['awards']['excelencia_general']['achieved'] ), 'centro general alcanza excelencia con cinco estrellas mas 70 puntos' );
	check_award_rule( ! empty( $large_excellence['awards']['dorada_turquesa']['achieved'] ), 'centro general agrega eco lonchera al premio dorado o turquesa' );
	check_award_rule( ! empty( $large_excellence['awards']['plata_residuos']['achieved'] ), 'centro general agrega ambos registros al premio plata' );

	$rejected_score = gnf_evaluate_award(
		array(
			'score'       => 119,
			'tipologia'   => 'tipo_iv',
			'tipo_centro' => 'direccion_i',
			'required'    => $required,
			'criteria'    => array(),
		)
	);
	check_award_rule( 2 === $rejected_score['stars'], 'al bajar de 120 a 119 puntos baja de tres a dos estrellas' );
}

check_award_rule( function_exists( 'gnf_get_award_field_definitions' ), 'existen claves tecnicas para criterios de galardon' );
check_award_rule( function_exists( 'gnf_prepare_award_form_fields' ), 'los formularios pueden anotarse sin cambiar IDs' );
if ( function_exists( 'gnf_get_award_field_definitions' ) && function_exists( 'gnf_prepare_award_form_fields' ) ) {
	$definitions = array_values(
		array_filter(
			gnf_get_award_field_definitions(),
			static function ( $definition ) {
				return 'residuos' === $definition['reto'];
			}
		)
	);
	$fields = array(
		8 => array( 'id' => 8, 'label' => '¿Realizaron un taller teórico-práctico sobre residuos?' ),
		9 => array( 'id' => 9, 'label' => 'Pregunta no relacionada' ),
	);
	$prepared = gnf_prepare_award_form_fields( $fields, $definitions );
	check_award_rule( 'residuos_taller' === $prepared['fields'][8]['gnf_award_key'], 'la etiqueta historica recibe una clave estable' );
	check_award_rule( ! isset( $prepared['fields'][9]['gnf_award_key'] ), 'no anota preguntas no relacionadas' );
	check_award_rule( 8 === $prepared['fields'][8]['id'], 'la anotacion conserva el ID del campo' );
	$renamed = $prepared['fields'];
	$renamed[8]['label'] = 'Nuevo texto definido por Guardianes';
	$second = gnf_prepare_award_form_fields( $renamed, $definitions );
	check_award_rule( 'residuos_taller' === $second['fields'][8]['gnf_award_key'] && false === $second['changed'], 'la clave sobrevive cambios posteriores de etiqueta' );
}

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
