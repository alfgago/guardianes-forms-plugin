<?php
// Test plano (sin WordPress) para la regla: activas para asignar, todas para consultar.

$root        = __DIR__ . '/..';
$helpers     = file_get_contents( $root . '/includes/helpers.php' );
$rest_api    = file_get_contents( $root . '/includes/rest-api.php' );
$cpts        = file_get_contents( $root . '/includes/cpts.php' );
$admin_panel = file_get_contents( $root . '/includes/admin-panel.php' );
$ajax        = file_get_contents( $root . '/includes/ajax-centros.php' );
$loader      = file_get_contents( $root . '/includes/react-loader.php' );
$usuarios    = file_get_contents( $root . '/app/src/panels/admin/pages/UsuariosPage.tsx' );
$centros     = file_get_contents( $root . '/app/src/panels/admin/pages/CentrosPage.tsx' );
$admin_users = file_get_contents( $root . '/includes/admin-users.php' );
$tests       = 0;
$fails       = 0;

function check_active_region_visibility( $cond, $msg ) {
	global $tests, $fails;
	$tests++;
	if ( $cond ) {
		echo "  ok: {$msg}\n";
	} else {
		$fails++;
		echo "  FAIL: {$msg}\n";
	}
}

check_active_region_visibility(
	strpos( $helpers, 'function gnf_is_region_active' ) !== false
		&& strpos( $helpers, "'gnf_dre_activa'" ) !== false,
	'existe helper unico para decidir si una DRE esta activa'
);

check_active_region_visibility(
	strpos( $rest_api, 'function gnf_rest_regions( WP_REST_Request $request )' ) !== false
		&& strpos( $rest_api, "get_param( 'active' )" ) !== false
		&& strpos( $rest_api, 'gnf_is_region_active' ) !== false,
	'REST /regions permite pedir solo DRE activas'
);

check_active_region_visibility(
	strpos( $cpts, 'gnf_is_region_active( $term->term_id )' ) !== false
		&& strpos( $admin_panel, 'gnf_is_region_active( $reg->term_id )' ) !== false
		&& strpos( $ajax, 'gnf_is_region_active( (int) $row->region )' ) !== false
		&& strpos( $loader, 'gnf_is_region_active( $term->term_id )' ) !== false,
	'formularios nativos, admin legacy y payloads de centros usan el helper'
);

check_active_region_visibility(
	strpos( $usuarios, 'assignableRegions' ) !== false
		&& strpos( $usuarios, "get<Region[]>('/regions', { active: 1 })" ) !== false
		&& strpos( $usuarios, 'visibleAssignableRegions' ) !== false
		&& substr_count( $usuarios, 'visibleAssignableRegions' ) >= 3,
	'Admin Usuarios separa regiones de filtro y regiones asignables activas'
);

check_active_region_visibility(
	strpos( $centros, 'activeRegions' ) !== false
		&& strpos( $centros, "get<Region[]>('/regions', { active: 1 })" ) !== false
		&& strpos( $centros, 'RegionFilter regions={regions ?? []}' ) !== false
		&& strpos( $centros, 'visibleFormRegions' ) !== false,
	'Admin Centros usa todas para filtro y activas para crear/editar'
);

check_active_region_visibility(
	strpos( $admin_users, '$assignable_regions' ) !== false
		&& strpos( $admin_users, 'gnf_get_assignable_region_terms' ) !== false,
	'Admin Usuarios legacy usa DRE activas para asignaciones'
);

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
