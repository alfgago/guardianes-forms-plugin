<?php
// Catalogo y agregacion pura de indicadores de impacto.

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

$module = __DIR__ . '/../includes/impact-metrics.php';
if ( file_exists( $module ) ) {
	require_once $module;
}

$tests = 0;
$fails = 0;

function check_impact_metric( $condition, $message ) {
	global $tests, $fails;
	$tests++;
	if ( $condition ) {
		echo "  ok: {$message}\n";
	} else {
		$fails++;
		echo "  FAIL: {$message}\n";
	}
}

check_impact_metric( function_exists( 'gnf_get_impact_metric_catalog' ), 'existe catalogo de indicadores' );
check_impact_metric( function_exists( 'gnf_aggregate_impact_records' ), 'existe agregador puro' );

if ( function_exists( 'gnf_get_impact_metric_catalog' ) && function_exists( 'gnf_aggregate_impact_records' ) ) {
	$catalog = gnf_get_impact_metric_catalog( 2026 );
	check_impact_metric( 41 === count( $catalog ), 'el catalogo 2026 contiene los 41 indicadores solicitados' );
	foreach ( array( 'inscripciones', 'centros_con_evidencias', 'residuos_kg_valorizables', 'eco_giras_estudiantes', 'arboles_plantados' ) as $key ) {
		check_impact_metric( isset( $catalog[ $key ] ), "incluye {$key}" );
	}

	$records = array(
		array(
			'id'          => 10,
			'regionId'    => 1,
			'regionName'  => 'DRE Norte',
			'circuito'    => '01',
			'tipoCentro'  => 'unidocente',
			'tipologia'   => 'tipo_v',
			'migrantes'   => 2,
			'entries'     => array(
				'agua' => array(
					'active'    => true,
					'approved'  => true,
					'responses' => array(
						'agua_captacion' => 'si',
						'agua_riego' => 'si',
					),
				),
				'residuos' => array(
					'active'    => true,
					'approved'  => true,
					'responses' => array( 'residuos_kg_valorizables' => '125.5' ),
				),
				'eco-gira' => array(
					'active'    => true,
					'approved'  => true,
					'responses' => array( 'eco_giras_total' => 2, 'eco_giras_estudiantes' => 35 ),
				),
			),
		),
		array(
			'id'          => 11,
			'regionId'    => 1,
			'regionName'  => 'DRE Norte',
			'circuito'    => '02',
			'tipoCentro'  => 'direccion_iii',
			'tipologia'   => 'tipo_ii',
			'migrantes'   => 3,
			'entries'     => array(
				'agua' => array(
					'active'    => true,
					'approved'  => false,
					'responses' => array( 'agua_captacion' => 'si' ),
				),
				'residuos' => array(
					'active'    => true,
					'approved'  => false,
					'responses' => array( 'residuos_kg_valorizables' => '74,5' ),
				),
			),
		),
		array(
			'id'          => 12,
			'regionId'    => 2,
			'regionName'  => 'DRE Sur',
			'circuito'    => '01',
			'tipoCentro'  => 'direccion_ii',
			'tipologia'   => 'tipo_iii',
			'migrantes'   => 0,
			'entries'     => array(),
		),
	);

	$active = gnf_aggregate_impact_records( $records, $catalog, 'active' );
	check_impact_metric( 3 === $active['total']['values']['inscripciones'], 'totaliza inscripciones una vez por centro' );
	check_impact_metric( 1 === $active['total']['values']['centros_pequenos'], 'cuenta unidocentes y Direccion I' );
	$label_record = $records[2];
	$label_record['id'] = 13;
	$label_record['tipoCentro'] = 'Dirección I';
	$label_record['regionId'] = 2;
	$label_record['circuito'] = '02';
	$with_label = gnf_aggregate_impact_records( array( $label_record ), $catalog, 'active' );
	check_impact_metric( 1 === $with_label['total']['values']['centros_pequenos'], 'centros pequenos acepta etiqueta humana' );
	check_impact_metric( 5 === $active['total']['values']['estudiantes_migrantes'], 'suma estudiantes migrantes' );
	check_impact_metric( 2 === $active['total']['values']['centros_con_evidencias'], 'cuenta centros con cualquier evidencia activa sin duplicarlos' );
	check_impact_metric( 2 === $active['total']['values']['agua_centros'], 'incluye evidencia pendiente en vista operativa' );
	check_impact_metric( 2 === $active['total']['values']['agua_captacion'], 'cuenta respuesta afirmativa en centros con evidencia' );
	check_impact_metric( 200.0 === $active['total']['values']['residuos_kg_valorizables'], 'normaliza coma decimal y suma cantidades' );
	check_impact_metric( 2 === count( $active['regions'] ), 'agrupa en dos DRE' );
	check_impact_metric( 2 === $active['regions']['1']['values']['inscripciones'], 'DRE Norte contiene dos centros' );
	check_impact_metric( 3 === count( $active['circuits'] ), 'separa circuitos dentro de cada DRE' );

	$approved = gnf_aggregate_impact_records( $records, $catalog, 'approved' );
	check_impact_metric( 1 === $approved['total']['values']['agua_centros'], 'vista publica excluye evidencia pendiente' );
	check_impact_metric( 1 === $approved['total']['values']['agua_captacion'], 'respuesta pendiente no se publica' );
	check_impact_metric( 125.5 === $approved['total']['values']['residuos_kg_valorizables'], 'sumas publicas usan solo evidencia aprobada' );
	check_impact_metric( 35.0 === $approved['total']['values']['eco_giras_estudiantes'], 'suma estudiantes de eco gira' );

	check_impact_metric( function_exists( 'gnf_filter_impact_records_for_rollout' ), 'agregacion admite alcance piloto' );
	if ( function_exists( 'gnf_filter_impact_records_for_rollout' ) ) {
		$pilot = gnf_filter_impact_records_for_rollout( $records, 'pilot', array( 11 ) );
		check_impact_metric( 1 === count( $pilot ) && 11 === $pilot[0]['id'], 'alcance piloto conserva solo centros seleccionados' );
		check_impact_metric( 3 === count( gnf_filter_impact_records_for_rollout( $records, 'all', array( 11 ) ) ), 'alcance general conserva todos los centros' );
	}
}

$module = file_get_contents( __DIR__ . '/../includes/impact-metrics.php' );
check_impact_metric( false !== strpos( $module, "gnf_get_feature_rollout_mode( 'impact' )" ), 'reporte de impacto consulta el modo de lanzamiento' );
check_impact_metric( false !== strpos( $module, "if ( 'all' !== gnf_get_feature_rollout_mode( 'impact' ) )" ), 'salida publica exige lanzamiento general' );
check_impact_metric( false === strpos( $module, "if ( ! empty( \$metric['public'] ) )" ), 'sin seleccion no publica indicadores predeterminados' );

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
