<?php
// Contrato de integracion de indicadores en REST, ajustes, shortcode y React.

$root       = __DIR__ . '/..';
$bootstrap  = file_get_contents( $root . '/guardianes-formularios.php' );
$impact     = file_get_contents( $root . '/includes/impact-metrics.php' );
$rest       = file_get_contents( $root . '/includes/rest-api.php' );
$acf        = file_get_contents( $root . '/includes/acf-fields.php' );
$shortcodes = file_get_contents( $root . '/includes/shortcodes.php' );
$api        = file_get_contents( $root . '/app/src/api/admin.ts' );
$reports    = file_get_contents( $root . '/app/src/panels/admin/pages/ReportesPage.tsx' );

$tests = 0;
$fails = 0;

function check_impact_ui( $condition, $message ) {
	global $tests, $fails;
	$tests++;
	if ( $condition ) {
		echo "  ok: {$message}\n";
	} else {
		$fails++;
		echo "  FAIL: {$message}\n";
	}
}

check_impact_ui( false !== strpos( $bootstrap, "require_once 'includes/impact-metrics.php';" ), 'bootstrap carga indicadores' );
check_impact_ui( false !== strpos( $impact, 'function gnf_build_impact_report' ), 'servicio construye reporte real' );
check_impact_ui( false !== strpos( $impact, 'posts_per_page' ) || false !== strpos( $impact, 'array_chunk' ), 'procesa centros en lotes' );
check_impact_ui( false !== strpos( $rest, "'/admin/impact'" ) && false !== strpos( $rest, "'/impact'" ), 'REST separa endpoint administrativo y publico' );
check_impact_ui( false !== strpos( $rest, "'permission_callback' => 'gnf_rest_is_admin'" ), 'endpoint administrativo conserva permiso' );
check_impact_ui( false !== strpos( $acf, 'public_impact_metrics' ), 'ajustes permiten seleccionar indicadores publicos' );
check_impact_ui( false !== strpos( $shortcodes, "'gnf_indicadores_impacto'" ), 'shortcode publico registrado' );
check_impact_ui( false !== strpos( $api, 'getImpact' ), 'cliente administrativo consume indicadores' );
check_impact_ui( false !== strpos( $reports, "queryKey: ['admin-impact'" ), 'Reportes carga indicadores por ano' );
check_impact_ui( false !== strpos( $reports, 'Indicadores de impacto' ) && false !== strpos( $reports, 'Direcciones Regionales' ), 'UI muestra indicador y comparacion regional' );
check_impact_ui( false !== strpos( $reports, 'Circuitos educativos' ) && false !== strpos( $reports, "setScopeView('circuits')" ), 'UI permite comparar circuitos' );

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
