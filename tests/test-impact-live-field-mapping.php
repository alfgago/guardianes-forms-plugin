<?php
define( 'ABSPATH', dirname( __DIR__ ) . '/' );

function gnf_get_reto_form_id_for_year( $reto_id ) { return $GLOBALS['form_ids'][ $reto_id ]; }
function gnf_get_wpforms_form_definition( $form_id ) { return array( 'fields' => $GLOBALS['forms'][ $form_id ] ); }
function get_the_title( $id ) { return $GLOBALS['slugs'][ $id ]; }
function gnf_get_reto_canonical_slug( $title ) { return $title; }
function gnf_get_wpforms_display_value( $field, $value ) { return 'file-upload' === $field['type'] ? '' : (string) $value; }
function add_filter() {}
function add_action() {}

require ABSPATH . 'includes/impact-metrics.php';
require ABSPATH . 'includes/report-snapshots.php';

$cases = array(
	array( 'agua', 30, 'radio', '¿CUENTAN CON EL REPORTE DE CONSUMO DEL AGUA, EMITIDO POR LA ENTIDAD, DE ENERO A NOVIEMBRE DEL AÑO PASADO?', 'agua_registro_consumo', 'Sí', 1.0 ),
	array( 'electricidad', 22, 'radio', '¿CUENTAN CON EL REPORTE DE CONSUMO, GENERADO POR EL PROVEEDOR DEL SERVICIO, DEL AÑO PASADO DESDE ENERO A NOVIEMBRE?', 'energia_registro_consumo', 'No', 0.0 ),
	array( 'residuos', 15, 'radio', '¿CUENTAN CON RECIPIENTES DE SEPARACIÓN DE RESIDUOS EN PASILLOS?', 'residuos_estaciones', 'Sí', 1.0 ),
	array( 'residuos', 33, 'number', 'Total de kilos de residuos valorizables recolectados y entregados hasta octubre', 'residuos_kg_valorizables', '25,5', 25.5 ),
	array( 'gestion-de-organicos', 10, 'radio', '¿DAN APROVECHAMIENTO A LOS RESIDUOS ORGÁNICOS?', 'organicos_compostaje', 'Sí', 1.0 ),
	array( 'limpiezas', 13, 'number', '¿Cuántas bolsas de residuos recogieron en total durante las limpiezas realizadas?', 'limpiezas_bolsas', '3', 3.0 ),
	array( 'jardines-y-polinizadores', 18, 'number', '¿Cuántas áreas de jardín distintas que atraen polinizadores tiene el centro educativo?', 'jardines_areas', '2', 2.0 ),
	array( 'jardines-y-polinizadores', 19, 'number', '¿Cuántas casitas para abejas (meliponarios) u hoteles de insectos tienen?', 'jardines_refugios', '4', 4.0 ),
	array( 'huerta', 6, 'radio', '¿REALIZARON ACTIVIDADES DE HUERTA (CUALQUIER TIPO) CON ESTUDIANTES?', 'huerta_actividades', 'Sí', 1.0 ),
	array( 'eco-gira', 13, 'number', 'Cantidad total de estudiantes que fueron a la eco gira o las eco giras', 'eco_giras_estudiantes', '16', 16.0 ),
	array( 'eco-emprendimiento', 5, 'text', 'Indique el monto total recaudado a la fecha.', 'emprendimiento_ingresos', '₡1.234,50', 1234.5 ),
);

$failures = 0;
foreach ( $cases as $index => list( $slug, $field_id, $type, $label, $key, $answer, $expected ) ) {
	$reto_id = $index + 1;
	$GLOBALS['slugs'][ $reto_id ] = $slug;
	$metric = gnf_get_impact_metric_catalog( 2026 )[ $key ];
	$form_id = $metric['proof']['formId'] ?? $reto_id;
	$proof_field = $metric['proof']['fields'][0] ?? 99;
	$GLOBALS['form_ids'][ $reto_id ] = $form_id;
	$GLOBALS['forms'][ $form_id ] = array( array( 'id' => $field_id, 'type' => $type, 'label' => $label ) );
	if ( 'agua' === $slug ) {
		$GLOBALS['forms'][ $form_id ][] = array( 'id' => 31, 'type' => 'file-upload', 'label' => 'Consumo de agua de enero a noviembre', 'gnf_metric_key' => $key );
	} else {
		$GLOBALS['forms'][ $form_id ][] = array( 'id' => $proof_field, 'type' => 'file-upload' );
	}
	$entry = gnf_build_impact_entry_record( (object) array(
		'reto_id' => $reto_id,
		'data' => json_encode( array( '__raw_values__' => array( $field_id => $answer ) ) ),
		'evidencias' => json_encode( array( array( 'field_id' => $proof_field, 'estado' => 'aprobada', 'puntos' => 5 ) ) ),
	), 2026 );
	$record = array( 'entries' => array( $slug => $entry ), 'profile' => array(), 'stats' => array() );
	$metric['available'] = gnf_impact_metric_is_available( $metric, 2026, array( $slug => $reto_id ) );
	$result = gnf_report_center_snapshot( $record, 2026, array( $metric ) );
	$ok = $expected === $result['values']['active'][ $key ] && $expected === $result['values']['approved'][ $key ];
	$failures += $ok ? 0 : 1;
	echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $key . "\n";
}

exit( $failures ? 1 : 0 );
