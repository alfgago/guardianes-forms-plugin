<?php
// Contrato de integracion del panel compartido; no depende de centros crudos.

$root       = __DIR__ . '/..';
$bootstrap  = file_get_contents( $root . '/guardianes-formularios.php' );
$impact     = file_get_contents( $root . '/includes/impact-metrics.php' );
$rest       = file_get_contents( $root . '/includes/rest-api.php' );
$acf        = file_get_contents( $root . '/includes/acf-fields.php' );
$shortcodes = file_get_contents( $root . '/includes/shortcodes.php' );
$api        = is_file( $root . '/app/src/api/reports.ts' ) ? file_get_contents( $root . '/app/src/api/reports.ts' ) : '';
$reports    = file_get_contents( $root . '/app/src/panels/admin/pages/ReportesPage.tsx' );
$panel      = is_file( $root . '/app/src/components/impact/ImpactPanel.tsx' ) ? file_get_contents( $root . '/app/src/components/impact/ImpactPanel.tsx' ) : '';
$table      = is_file( $root . '/app/src/components/impact/ImpactTable.tsx' ) ? file_get_contents( $root . '/app/src/components/impact/ImpactTable.tsx' ) : '';
$exports    = is_file( $root . '/app/src/components/impact/ImpactExports.tsx' ) ? file_get_contents( $root . '/app/src/components/impact/ImpactExports.tsx' ) : '';
$model      = is_file( $root . '/app/src/components/impact/model.ts' ) ? file_get_contents( $root . '/app/src/components/impact/model.ts' ) : '';
$supervisor = file_get_contents( $root . '/app/src/panels/supervisor/SupervisorPanel.tsx' );
$admin      = file_get_contents( $root . '/app/src/panels/admin/AdminPanel.tsx' );
$preview    = is_file( $root . '/app/preview-impact.html' ) ? file_get_contents( $root . '/app/preview-impact.html' ) : '';
$css        = is_file( $root . '/app/src/components/impact/impact.css' ) ? file_get_contents( $root . '/app/src/components/impact/impact.css' ) : '';

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
check_impact_ui( false !== strpos( $api, "'/reports/overview'" ) && false !== strpos( $api, "'/reports/refresh'" ), 'cliente usa endpoints compartidos' );
check_impact_ui( false !== strpos( $api, "sources.join(',')" ) && false !== strpos( $api, 'signal' ), 'fuentes multiples y cancelacion viajan al servidor' );
check_impact_ui( false !== strpos( $api, "'active' | 'approved'" ), 'modos activo y aprobado explicitos' );
check_impact_ui( false !== strpos( $reports, '<ImpactPanel' ) && false === strpos( $reports, 'adminApi' ), 'Reportes es wrapper sin endpoints admin' );
check_impact_ui( false !== strpos( $supervisor, '<ImpactPanel' ) && false !== strpos( $supervisor, "page: 'impacto'" ), 'DRE abre el mismo componente' );
check_impact_ui( false !== strpos( $admin, "label: 'Panel de Impacto'" ) && false !== strpos( $supervisor, "label: 'Panel de Impacto'" ), 'navegacion compartida' );
check_impact_ui( false !== strpos( $panel, "queryKey: ['reports-overview', year, region, circuit, mode, sources.join(',')]" ), 'cache separa todos los filtros' );
check_impact_ui( false !== strpos( $panel, 'getOverview(' ) && false !== strpos( $panel, 'availableRegions' ) && false !== strpos( $panel, 'availableCircuits' ), 'filtros y territorios vienen del backend scoped' );
check_impact_ui( false !== strpos( $panel, 'availableSources' ) && false !== strpos( $panel, 'type="checkbox"' ) && false === strpos( $panel, 'reto-agua' ), 'fuentes dinamicas multiples conservan catalogo completo' );
check_impact_ui( false !== strpos( $model, "params.get('mode') === 'active' ? 'active' : 'approved'" ) && false !== strpos( $panel, 'getInitialReportFilters(window.location.search)' ), 'quicklink conserva filtros y modo inicial aprobado' );
check_impact_ui( false !== strpos( $model, "'Sin datos'" ) && false !== strpos( $table, 'scope.coverage' ), 'nulos sin datos y cobertura por centro' );
check_impact_ui( false !== strpos( $panel, 'canRefresh' ) && false !== strpos( $panel, 'useMutation' ), 'refresh solamente segun permiso del servidor' );
check_impact_ui( false !== strpos( $panel, 'refetchInterval' ) && false !== strpos( $model, '!data.ready || data.refreshing' ) && false !== strpos( $model, '5000' ), 'poll de 5s solo preparando o actualizando' );
check_impact_ui( false !== strpos( $panel, 'Preparando indicadores' ) && false !== strpos( $panel, 'stale' ) && false !== strpos( $panel, 'generatedAt' ), 'estados frio desactualizado y timestamp visibles' );
check_impact_ui( false !== strpos( $panel, 'Reintentar' ) && false !== strpos( $panel, 'refetch()' ), 'errores recuperables' );
check_impact_ui( false !== strpos( $table, '<table' ) && false !== strpos( $table, 'groupIndicators' ) && false !== strpos( $table, 'Total del alcance' ), 'tabla agrupa todos los indicadores y total seleccionado' );
check_impact_ui( false !== strpos( $table, 'scope="col"' ) && false !== strpos( $table, 'scope="row"' ) && false !== strpos( $table, 'No disponible' ), 'tabla accesible y faltantes no se convierten a cero' );
check_impact_ui( false !== strpos( $panel, 'Direcciones Regionales' ) && false !== strpos( $panel, 'Circuitos educativos' ), 'comparacion DRE y circuitos' );
check_impact_ui( strpos( $panel, '<ImpactTable' ) < strpos( $panel, '<ImpactChart' ), 'tabla primero graficos secundarios' );
check_impact_ui( false !== strpos( $exports, 'indicators' ) && false !== strpos( $exports, 'centros' ) && false !== strpos( $exports, 'retos' ) && false !== strpos( $exports, 'pdfSummary' ) && false !== strpos( $exports, 'pdfFull' ), 'Excel tres datasets y PDF ejecutivo/completo' );
check_impact_ui( false !== strpos( $exports, 'href={url}' ) && false === strpos( $exports, 'URLSearchParams' ), 'descargas conservan URL firmada sin reescribir filtros' );
check_impact_ui( false === strpos( $panel, 'data?.centros' ) && false === strpos( $panel, '@/api/admin' ), 'panel no requiere centros crudos ni api admin' );
check_impact_ui( false !== strpos( $css, 'letter-spacing: 0' ) && false !== strpos( $css, 'overflow-x: auto' ) && false !== strpos( $css, '@media' ), 'layout estable responsive sin letterspacing' );
check_impact_ui( false !== strpos( $panel, 'paginateScopes(scopes, circuitPage)' ) && false !== strpos( $panel, 'Circuitos siguientes' ) && false !== strpos( $panel, 'circuitPagination.total' ), 'paginacion solamente en columnas de circuitos con rango y total' );
check_impact_ui( false !== strpos( $css, 'max-height: 620px' ) && false !== strpos( $css, 'top: 0' ) && false !== strpos( $css, 'position: sticky' ), 'tabla con scroll interno y encabezados fijos' );
check_impact_ui( false !== strpos( $css, '.gnf-impact-mobile-unit' ) && false !== strpos( $css, 'width: 110px' ) && false !== strpos( $css, 'width: max-content' ), 'movil conserva indicador y total con unidad integrada' );
check_impact_ui( false !== strpos( $panel, 'getInitialReportYear' ) && false === strpos( $panel, 'setSelectedYear' ), 'ano local acepta quicklink sin cambiar el store global' );
check_impact_ui( false !== strpos( $panel, 'Resultados validados' ) && false !== strpos( $panel, 'Resultados reportados' ) && false !== strpos( $panel, 'Evidencias pausadas excluidas' ), 'modos y corte visibles sin instrucciones de uso' );
check_impact_ui( false !== strpos( $preview, 'ImpactPanel' ) && false !== strpos( $preview, 'ready: false' ), 'preview usa panel real y mock frio' );

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
