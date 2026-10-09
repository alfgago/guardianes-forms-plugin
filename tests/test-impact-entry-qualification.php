<?php
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
function get_the_title() { return 'Siembra de Arboles'; }
function gnf_get_reto_canonical_slug() { return 'siembra-de-arboles'; }
function gnf_get_reto_form_id_for_year() { return 9; }
function gnf_get_wpforms_form_definition() { return array( 'fields' => array( array( 'id' => 1, 'gnf_metric_key' => 'arboles_plantados' ) ) ); }
require ABSPATH . 'includes/impact-metrics.php';
$tests = 0; $fails = 0;
function check_qualification( $ok, $message ) { global $tests, $fails; $tests++; $fails += $ok ? 0 : 1; echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $message . "\n"; }
function impact_entry_fixture( $evidence, $state = 'enviado' ) {
	return gnf_build_impact_entry_record( (object) array( 'reto_id' => 2, 'estado' => $state, 'data' => '{"__raw_values__":{"1":12}}', 'evidencias' => json_encode( $evidence ) ), 2026 );
}
check_qualification( impact_entry_fixture( array( array( 'estado' => 'aprobada', 'puntos' => 5 ) ) )['approved'], 'Per-evidence approval validates impact even when legacy entry state remains enviado' );
check_qualification( ! impact_entry_fixture( array(), 'aprobado' )['approved'], 'A stale approved entry without evidence is not validated impact' );
check_qualification( ! impact_entry_fixture( array( array( 'estado' => 'aprobada', 'puntos' => 5 ), array( 'estado' => 'en_pausa', 'puntos' => 5 ) ), 'aprobado' )['approved'], 'Paused evidence prevents validated quantities' );
check_qualification( ! impact_entry_fixture( array( array( 'estado' => 'rechazada', 'puntos' => 5 ) ), 'aprobado' )['active'], 'Rejected evidence does not qualify as active impact' );
check_qualification( impact_entry_fixture( array( array( 'estado' => 'aprobada', 'puntos' => 5 ), array( 'estado' => 'pendiente', 'puntos' => null ) ) )['approved'], 'Informational uploads do not block approval of reviewable evidence' );
check_qualification( ! impact_entry_fixture( array( array( 'estado' => 'pendiente', 'puntos' => null ) ) )['approved'], 'Informational uploads alone are not proof of validated impact' );
check_qualification( impact_entry_fixture( array( array( 'estado' => 'aprobada', 'puntos' => 5 ), array( 'estado' => 'rechazada', 'puntos' => 5, 'replaced' => true ) ) )['approved'], 'Replaced evidence does not affect current validation' );
echo "\n{$tests} checks, {$fails} failures\n"; exit( $fails ? 1 : 0 );
