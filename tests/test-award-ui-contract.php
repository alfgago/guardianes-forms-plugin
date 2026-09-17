<?php
// Contrato de presentacion del galardon calculado en los paneles.

$root       = __DIR__ . '/..';
$rest       = file_get_contents( $root . '/includes/rest-api.php' );
$component  = @file_get_contents( $root . '/app/src/components/domain/AwardSummary.tsx' ) ?: '';
$docente    = file_get_contents( $root . '/app/src/panels/docente/pages/ResumenPage.tsx' );
$admin      = file_get_contents( $root . '/app/src/panels/admin/pages/CentroDetailPage.tsx' );
$supervisor = file_get_contents( $root . '/app/src/panels/supervisor/pages/CentroDetailPage.tsx' );
$types      = file_get_contents( $root . '/app/src/types/centro.ts' );

$tests = 0;
$fails = 0;

function check_award_ui( $condition, $message ) {
	global $tests, $fails;
	$tests++;
	if ( $condition ) {
		echo "  ok: {$message}\n";
	} else {
		$fails++;
		echo "  FAIL: {$message}\n";
	}
}

check_award_ui( false !== strpos( $types, 'export interface AwardResult' ), 'TypeScript tipa el resultado de galardon' );
check_award_ui( false !== strpos( $component, 'Galardón ' ) && false !== strpos( $component, '{year}' ), 'componente compartido identifica el galardon y su año' );
check_award_ui( false !== strpos( $component, 'Progreso estimado' ) && false !== strpos( $component, 'Rúbrica aplicada' ), 'resumen distingue puntaje y rubrica' );
check_award_ui( false !== strpos( $component, 'Requisitos base pendientes' ), 'resumen explica requisitos pendientes' );
check_award_ui( false !== strpos( $docente, 'assignedAward={dashboard.assignedAward}' ) && false === strpos( $docente, '<AwardSummary' ), 'panel docente muestra solo el resultado asignado' );
check_award_ui( false !== strpos( $admin, '<AwardSummary award={centro.annual.award}' ), 'detalle administrativo muestra resultado' );
check_award_ui( false !== strpos( $supervisor, '<AwardSummary award={centro.annual.award}' ), 'detalle supervisor muestra resultado' );
check_award_ui( false !== strpos( $rest, 'gnf_get_center_award_bundle( $centro_id, $anio, true' ), 'vistas de detalle recalculan ambos resultados' );
check_award_ui( false !== strpos( $types, 'export interface AwardBundle' ), 'frontend tipa resultados proyectados y validados' );
check_award_ui( false !== strpos( $component, 'Progreso estimado' ) && false !== strpos( $component, 'Resultado validado' ), 'componente distingue estimacion de resultado oficial' );
check_award_ui( false !== strpos( $component, 'Siguiente estrella' ), 'componente muestra progreso hacia la siguiente estrella' );
check_award_ui( false !== strpos( $rest, 'gnf_get_center_award_bundle' ), 'REST entrega ambos modos mediante un contrato compartido' );
check_award_ui( false !== strpos( $rest, "gnf_feature_is_enabled_for_center( 'awards'" ), 'REST aplica rollout antes de exponer galardones' );

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
