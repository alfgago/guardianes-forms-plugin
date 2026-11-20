<?php
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
function add_action() {}
function add_filter() {}
function get_the_title() { return 'residuos'; }
function gnf_get_reto_canonical_slug( $title ) { return $title; }
function gnf_get_reto_form_id_for_year( $reto ) { return 1 === $reto ? 77468 : 99999; }
function gnf_get_wpforms_form_definition() {
	return array( 'fields' => array(
		array( 'id' => 17, 'type' => 'file-upload' ),
		array( 'id' => 20, 'type' => 'file-upload' ),
		array( 'id' => 22, 'type' => 'file-upload' ),
		array( 'id' => 21, 'type' => 'radio', 'label' => 'Cuantifican los residuos no valorizables' ),
		array( 'id' => 33, 'type' => 'number', 'label' => 'Total de kilos de residuos valorizables' ),
		array( 'id' => 36, 'type' => 'number', 'label' => 'Total de bolsas de residuos no valorizables' ),
	) );
}
require ABSPATH . 'includes/evidence-review.php';
require ABSPATH . 'includes/impact-metrics.php';
require ABSPATH . 'includes/report-snapshots.php';

function snapshot_for( $files, $raw = array( 36 => '23', 33 => '30', 21 => 'Sí' ), $reto = 1 ) {
	$catalog = gnf_get_impact_metric_catalog( 2026 );
	foreach ( $catalog as &$metric ) {
		$metric['available'] = gnf_impact_metric_is_available( $metric, 2026, array( 'residuos' => $reto ) );
	}
	unset( $metric );
	$entry = gnf_build_impact_entry_record( (object) array(
		'reto_id' => $reto,
		'data' => json_encode( array( '__raw_values__' => $raw ) ),
		'evidencias' => json_encode( $files ),
	), 2026 );
	return gnf_report_center_snapshot( array( 'profile' => array(), 'stats' => array(), 'entries' => array( 'residuos' => $entry ) ), 2026, $catalog )['values'];
}

$centers = 'residuos_registro_no_valorizables';
$bags = 'residuos_bolsas_no_valorizables';
$kg = 'residuos_kg_valorizables';
$stations = 'residuos_estaciones';
$unrelated = array( 'field_id' => 5, 'estado' => 'aprobada' );
$register = array( 'field_id' => 22, 'estado' => 'aprobada' );
$pending = array( 'field_id' => 22, 'estado' => 'pendiente' );
$other = snapshot_for( array( $unrelated ) );
$mixed = snapshot_for( array( $register, array( 'field_id' => 5, 'estado' => 'pendiente' ), array( 'field_id' => 17, 'estado' => 'aprobada' ) ), array( 36 => '23', 33 => '30', 21 => 'No' ) );
$not_approved = snapshot_for( array( $unrelated, $pending ) );
$replaced = snapshot_for( array( $register + array( 'replaced' => true ) ) );
$zero = snapshot_for( array( $register ), array( 36 => '0' ) );
$missing = snapshot_for( array( $register ), array() );
$invalid = snapshot_for( array( $register ), array( 36 => '-2' ) );
$same_field_other_form = snapshot_for( array( $register ), array( 36 => '23' ), 2 );
$checks = array(
	'Yes and unrelated approval do not count as the required register' => 0.0 === $other['approved'][ $centers ] && null === $other['approved'][ $bags ],
	'Reported bags are a separate total from the count of centers' => 1.0 === $not_approved['active'][ $centers ] && 23.0 === $not_approved['active'][ $bags ],
	'A declared bag total can be reported without claiming validation' => 0.0 === $other['active'][ $centers ] && 23.0 === $other['active'][ $bags ],
	'Approved activity evidence counts despite unrelated pending files' => 1.0 === $mixed['approved'][ $centers ] && 1.0 === $mixed['approved'][ $stations ] && 23.0 === $mixed['approved'][ $bags ],
	'The type of waste and unit must not share the same supporting evidence' => null === $mixed['approved'][ $kg ],
	'Pending and replaced registers do not validate bags' => null === $not_approved['approved'][ $bags ] && 0.0 === $replaced['active'][ $centers ] && 0.0 === $replaced['approved'][ $centers ],
	'Zero is a valid record while missing or negative counts are not' => 0.0 === $zero['approved'][ $bags ] && null === $missing['approved'][ $bags ] && null === $invalid['approved'][ $bags ],
	'Reused field IDs in another form cannot validate the indicator' => null === $same_field_other_form['approved'][ $bags ] && null === $same_field_other_form['approved'][ $centers ],
);
foreach ( $checks as $message => $ok ) { echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $message . "\n"; }
exit( in_array( false, $checks, true ) ? 1 : 0 );
