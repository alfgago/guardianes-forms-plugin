<?php
// Test plano (sin WordPress) para la logica pura de fusion de centros.
require_once __DIR__ . '/../includes/merge-centros-logic.php';

$tests = 0; $fails = 0;
function check( $cond, $msg ) {
	global $tests, $fails;
	$tests++;
	if ( $cond ) { echo "  ok: {$msg}\n"; }
	else { $fails++; echo "  FAIL: {$msg}\n"; }
}

// clave de agrupacion
check( gnf_merge_centro_key( ' 04-AB ', 5, ' 03 ', ' San Francisco ' ) === '04-AB|5|03|san francisco', 'key normaliza trim/lower' );
check( gnf_merge_centro_key( '', 5, '03', 'x' ) === null, 'codigo vacio -> null' );
check( gnf_merge_centro_key( 'A', 1, 'c', 'Ñoño' ) === 'A|1|c|ñoño', 'conserva acentos en minuscula' );

// rangos de estado
check( gnf_merge_entry_estado_rank( 'aprobado' ) > gnf_merge_entry_estado_rank( 'completo' ), 'aprobado>completo' );
check( gnf_merge_entry_estado_rank( 'desconocido' ) === 0, 'estado desconocido = 0' );
check( gnf_merge_matricula_estado_rank( 'enviado' ) > gnf_merge_matricula_estado_rank( 'pendiente' ), 'mat enviado>pendiente' );

// ganador de entry
check( gnf_merge_entry_a_wins( array( 'estado' => 'aprobado' ), array( 'estado' => 'completo' ) ) === true, 'a aprobado gana a completo' );
check( gnf_merge_entry_a_wins( array( 'estado' => 'completo', 'puntaje' => 5 ), array( 'estado' => 'completo', 'puntaje' => 9 ) ) === false, 'mayor puntaje (b) gana' );
check( gnf_merge_entry_a_wins( array( 'estado' => 'completo', 'puntaje' => 5, 'evidencias_count' => 1 ), array( 'estado' => 'completo', 'puntaje' => 5, 'evidencias_count' => 0 ) ) === true, 'desempate por evidencias' );
check( gnf_merge_entry_a_wins( array( 'estado' => 'completo' ), array( 'estado' => 'completo' ) ) === false, 'empate total -> gana canonical (b)' );

// ganador de matricula
check( gnf_merge_matricula_a_wins( array( 'estado' => 'enviado' ), array( 'estado' => 'pendiente' ) ) === true, 'mat enviado gana' );

// union de retos
check( gnf_merge_union_reto_ids( array( 3, 1, '2' ), array( 2, '4' ) ) === array( 1, 2, 3, 4 ), 'union ordena y dedup' );
check( gnf_merge_union_reto_ids( array( 0, -1, 'x', 5 ), array( 5 ) ) === array( 5 ), 'union descarta no-positivos' );

// conteo de evidencias
check( gnf_merge_count_evidencias( '[{"file":"a"},{"file":"b"}]' ) === 2, 'cuenta evidencias JSON' );
check( gnf_merge_count_evidencias( '' ) === 0, 'evidencias vacia = 0' );

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
