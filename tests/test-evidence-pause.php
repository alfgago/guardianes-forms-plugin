<?php
define( 'ABSPATH', __DIR__ . '/../' );
function absint( $value ) { return abs( (int) $value ); }
function gnf_get_reto_field_points( $id, $year ) { return array( 1 => array( 'puntos' => 10, 'tipo' => 'file-upload' ) ); }
require_once ABSPATH . 'includes/evidence-review.php';
require_once ABSPATH . 'includes/puntajes.php';
require_once ABSPATH . 'includes/award-rules.php';
require_once ABSPATH . 'includes/docente-summary.php';
$tests = 0; $fails = 0;
function check_pause( $ok, $message ) {
	global $tests, $fails; $tests++; $fails += $ok ? 0 : 1;
	echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $message . "\n";
}
check_pause( function_exists( 'gnf_get_evidence_pause_reasons' ), 'existe taxonomia de pausa' );
if ( function_exists( 'gnf_get_evidence_pause_reasons' ) ) {
	check_pause( array( 'reto_inconcluso' => 'Reto inconcluso', 'subir_requisito' => 'Subir requisito', 'no_concluyente' => 'No es concluyente' ) === gnf_get_evidence_pause_reasons(), 'tres causas exactas' );
	check_pause( gnf_is_valid_evidence_pause_reason( 'subir_requisito' ), 'acepta causa de pausa' );
	check_pause( ! gnf_is_valid_evidence_pause_reason( 'no_corresponde' ), 'rechazo no es causa de pausa' );
	check_pause( ! gnf_is_valid_evidence_rejection_reason( 'subir_requisito' ), 'pausa no es causa de rechazo' );
}
$entry = (object) array( 'reto_id' => 1, 'anio' => 2026, 'estado' => 'enviado', 'data' => '{}' );
foreach ( array( 'pendiente' => 10, 'en_pausa' => 0, 'aprobada' => 10, 'rechazada' => 0 ) as $state => $expected ) {
	$entry->evidencias = json_encode( array( array( 'field_id' => 1, 'estado' => $state, 'puntos' => 10 ) ) );
	check_pause( $expected === gnf_calcular_puntaje_por_campos( $entry ), "puntaje {$state}" );
}
$paused = array( 'field_id' => 1, 'estado' => 'en_pausa', 'puntos' => 10 );
$entry->evidencias = json_encode( array( $paused ) );
check_pause( ! gnf_award_evidence_qualifies( $paused, 'projected' ), 'pausa no cumple requisito proyectado' );
check_pause( ! gnf_award_evidence_qualifies( $paused, 'validated' ), 'pausa no cumple requisito validado' );
$summary = gnf_summarize_docente_entries( array( $entry ), array( 1 ) );
check_pause( ! $summary['allComplete'], 'pausa impide reporte final' );
check_pause( 1 === ( $summary['evidenceCounts']['paused'] ?? 0 ) && 0 === $summary['evidenceCounts']['pending'], 'pausa se cuenta aparte de pendientes sin revisar' );
$entry->evidencias = json_encode( array( $paused, array( 'field_id' => 1, 'estado' => 'aprobada', 'puntos' => 10 ) ) );
check_pause( 10 === gnf_calcular_puntaje_por_campos( $entry ), 'otra evidencia aprobada del campo conserva sus puntos sin duplicarlos' );
echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
