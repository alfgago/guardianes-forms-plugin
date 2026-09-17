<?php
// Contrato de UX para el piloto de indicadores, galardones y reportes.

$root       = __DIR__ . '/..';
$reports    = file_get_contents( $root . '/app/src/panels/admin/pages/ReportesPage.tsx' );
$modal      = file_get_contents( $root . '/app/src/components/ui/Modal.tsx' );
$styles     = file_get_contents( $root . '/app/src/styles/components.css' );
$docente    = file_get_contents( $root . '/app/src/panels/docente/components/ProgressHero.tsx' );
$admin      = file_get_contents( $root . '/app/src/panels/admin/pages/CentroDetailPage.tsx' );
$supervisor = file_get_contents( $root . '/app/src/panels/supervisor/pages/CentroDetailPage.tsx' );

$tests = 0;
$fails = 0;

function check_pilot_ux( $condition, $message ) {
	global $tests, $fails;
	$tests++;
	if ( $condition ) {
		echo "  ok: {$message}\n";
	} else {
		$fails++;
		echo "  FAIL: {$message}\n";
	}
}

check_pilot_ux( false === strpos( $reports, 'Exportar CSV' ), 'Reportes no muestra una accion de exportacion inactiva' );
check_pilot_ux( false !== strpos( $reports, 'Buscar DRE o circuito' ), 'comparacion territorial permite buscar' );
check_pilot_ux( false !== strpos( $reports, 'No hay territorios que coincidan' ), 'comparacion territorial tiene estado vacio' );
check_pilot_ux( false !== strpos( $reports, 'Última actualización' ), 'indicadores muestran fecha de actualizacion' );
check_pilot_ux( false !== strpos( $modal, 'previousActiveElement' ) && false !== strpos( $modal, "event.key === 'Tab'" ), 'modal contiene y restaura el foco' );
check_pilot_ux( false !== strpos( $modal, 'aria-labelledby' ) && false !== strpos( $modal, 'aria-label' ), 'modal siempre tiene nombre accesible' );
check_pilot_ux( false !== strpos( $modal, 'overscrollBehavior' ), 'modal contiene el desplazamiento' );
check_pilot_ux( false !== strpos( $styles, '.gnf-modal-dialog' ) && false !== strpos( $styles, 'prefers-reduced-motion' ), 'animacion del modal respeta movimiento reducido' );
check_pilot_ux( false !== strpos( $docente, 'disabled={!reportAvailable}' ) && false !== strpos( $docente, 'Descargar reporte final PDF' ), 'docente descarga el reporte al estar disponible' );
check_pilot_ux( false !== strpos( $admin, 'reportPdfStatus' ) && false !== strpos( $supervisor, 'reportPdfStatus' ), 'revisores ven el estado del reporte' );

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );

