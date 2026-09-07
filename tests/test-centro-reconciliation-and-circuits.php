<?php
// Test estatico para conteos/exportes de centros y asignacion de circuitos.

$root                  = __DIR__ . '/..';
$reports               = file_get_contents( $root . '/includes/reports.php' );
$rest_api              = file_get_contents( $root . '/includes/rest-api.php' );
$admin_api             = file_get_contents( $root . '/app/src/api/admin.ts' );
$types_user            = file_get_contents( $root . '/app/src/types/user.ts' );
$centros_page          = file_get_contents( $root . '/app/src/panels/admin/pages/CentrosPage.tsx' );
$usuarios_page         = file_get_contents( $root . '/app/src/panels/admin/pages/UsuariosPage.tsx' );
$pending_users_section = file_get_contents( $root . '/app/src/panels/admin/components/PendingUsersSection.tsx' );
$inicio_page           = file_get_contents( $root . '/app/src/panels/admin/pages/InicioPage.tsx' );
$supervisor_table      = file_get_contents( $root . '/app/src/panels/supervisor/components/CentroTable.tsx' );

$tests = 0;
$fails = 0;

function check_centro_reconciliation_contract( $cond, $msg ) {
	global $tests, $fails;
	$tests++;
	if ( $cond ) {
		echo "  ok: {$msg}\n";
	} else {
		$fails++;
		echo "  FAIL: {$msg}\n";
	}
}

$export_columns = array(
	'Tipo de Centro Educativo',
	'Tipología',
	'Docentes asociados',
	'Registrado por docente',
	'Tiene matrícula del año',
	'Visible en panel DRE',
	'Diagnóstico',
);

foreach ( $export_columns as $column ) {
	check_centro_reconciliation_contract(
		strpos( $reports, $column ) !== false,
		"exporte incluye columna: {$column}"
	);
}

check_centro_reconciliation_contract(
	strpos( $reports, 'function gnf_export_centros_diagnostico_csv' ) !== false
		&& strpos( $reports, "admin_post_gnf_export_centros_diagnostico_csv" ) !== false,
	'exporte diagnostico completo registrado en admin-post'
);

check_centro_reconciliation_contract(
	strpos( $reports, 'gnf_get_registered_centro_ids()' ) !== false
		&& strpos( $reports, 'gnf_get_centros_with_matricula( $anio )' ) !== false,
	'diagnostico cruza centros registrados por docente contra matricula del año'
);

check_centro_reconciliation_contract(
	strpos( $reports, "Registrado por docente sin matrícula del año" ) !== false
		&& strpos( $reports, "Cuenta docente sin centro educativo asociado" ) !== false,
	'diagnostico explica los casos que descuadran los conteos'
);

check_centro_reconciliation_contract(
	strpos( $centros_page, "action', 'gnf_export_centros_diagnostico_csv'" ) !== false
		&& strpos( $usuarios_page, "action', 'gnf_export_centros_diagnostico_csv'" ) === false,
	'admin descarga el diagnostico solo desde Centros Educativos'
);

check_centro_reconciliation_contract(
	strpos( $centros_page, "header: 'Tipo'" ) !== false
		&& strpos( $centros_page, 'tipoCentroEducativo' ) !== false
		&& strpos( $supervisor_table, "header: 'Tipo'" ) !== false
		&& strpos( $supervisor_table, 'tipoCentroEducativo' ) !== false,
	'listados admin y DRE/supervisor muestran tipo de centro'
);

check_centro_reconciliation_contract(
	strpos( $usuarios_page, 'Docentes sin centro' ) !== false
		&& strpos( $usuarios_page, 'Centros asignados' ) !== false
		&& strpos( $usuarios_page, "`user-\${user.id}`" ) === false,
	'conteo de usuarios no infla centros con docentes sin centro asociado'
);

check_centro_reconciliation_contract(
	strpos( $inicio_page, 'Cuentas docentes' ) !== false
		&& strpos( $inicio_page, 'Centros educativos" value={stats.totalUsers}' ) === false,
	'dashboard admin no rotula cuentas docentes como centros educativos'
);

check_centro_reconciliation_contract(
	strpos( $rest_api, "'/admin/circuitos'" ) !== false
		&& strpos( $rest_api, 'function gnf_rest_admin_circuitos' ) !== false
		&& strpos( $admin_api, 'getCircuitos' ) !== false,
	'API admin expone circuitos asignables por region'
);

check_centro_reconciliation_contract(
	strpos( $rest_api, "'circuito'      => gnf_get_user_circuito" ) !== false
		&& strpos( $rest_api, "update_user_meta( \$user_id, 'circuito'" ) !== false
		&& strpos( $rest_api, "delete_user_meta( \$user_id, 'circuito' )" ) !== false,
	'REST de usuarios lee, guarda y limpia circuito del supervisor'
);

check_centro_reconciliation_contract(
	substr_count( $rest_api, 'gnf_get_user_circuito( $user_id )' ) >= 2,
	'panel supervisor aplica circuito asignado en listados y conteos desde backend'
);

check_centro_reconciliation_contract(
	strpos( $types_user, 'circuito?: string;' ) !== false
		&& strpos( $admin_api, 'circuito?: string' ) !== false
		&& strpos( $usuarios_page, 'Circuito asignado' ) !== false
		&& strpos( $usuarios_page, 'Ver toda la DRE' ) !== false
		&& strpos( $pending_users_section, 'Circuito:' ) !== false,
	'frontend permite editar y ver circuito asignado al supervisor'
);

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
