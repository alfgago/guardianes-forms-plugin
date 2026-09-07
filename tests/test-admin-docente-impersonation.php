<?php
// Contrato estatico para el acceso administrativo al panel docente.

$root        = __DIR__ . '/..';
$impersonate = file_get_contents( $root . '/includes/impersonate.php' );
$rest        = file_get_contents( $root . '/includes/rest-api.php' );
$users       = file_get_contents( $root . '/includes/admin-users.php' );
$cpts        = file_get_contents( $root . '/includes/cpts.php' );
$centros     = file_get_contents( $root . '/app/src/panels/admin/pages/CentrosPage.tsx' );
$stop_start  = strpos( $impersonate, 'function gnf_handle_impersonate_stop()' );
$stop_end    = strpos( $impersonate, "add_action( 'admin_post_gnf_impersonate_stop'", $stop_start );
$stop_source = false !== $stop_start && false !== $stop_end
	? substr( $impersonate, $stop_start, $stop_end - $stop_start )
	: '';

$tests = 0;
$fails = 0;

function check_admin_docente_impersonation( $condition, $message ) {
	global $tests, $fails;
	$tests++;
	if ( $condition ) {
		echo "  ok: {$message}\n";
	} else {
		$fails++;
		echo "  FAIL: {$message}\n";
	}
}

check_admin_docente_impersonation(
	strpos( $impersonate, 'function gnf_get_primary_docente_for_centro' ) !== false,
	'existe selector canonico de docente activo'
);
check_admin_docente_impersonation(
	strpos( $impersonate, 'function gnf_get_primary_docentes_for_centros' ) !== false
		&& substr_count( $rest, '$primary_docentes = gnf_get_primary_docentes_for_centros( $centro_ids );' ) >= 2,
	'los listados React resuelven docentes activos por lote'
);
check_admin_docente_impersonation(
	strpos( $impersonate, 'function gnf_build_impersonate_url' ) !== false
		&& strpos( $impersonate, "current_user_can( 'manage_options' )" ) !== false,
	'la impersonacion conserva el control administrativo'
);
check_admin_docente_impersonation(
	strpos( $impersonate, 'GNF_IMPERSONATE_RETURN_COOKIE' ) !== false
		&& strpos( $impersonate, 'gnf_get_impersonate_return_url' ) !== false
		&& strpos( $users, 'gnf_get_current_admin_return_url' ) !== false
		&& strpos( $cpts, 'gnf_get_current_admin_return_url' ) !== false,
	'el retorno al listado de origen usa una cookie firmada'
);
check_admin_docente_impersonation(
	strpos( $stop_source, "check_admin_referer( 'gnf_impersonate' )" ) !== false,
	'la salida de impersonacion valida el nonce'
);
check_admin_docente_impersonation(
	strpos( $rest, "'docenteImpersonateUrl'" ) !== false,
	'el payload REST de centros expone la URL'
);
check_admin_docente_impersonation(
	strpos( $users, 'Entrar como docente' ) !== false
		&& strpos( $cpts, 'Entrar como docente' ) !== false
		&& strpos( $centros, 'Entrar como docente' ) !== false,
	'las superficies administrativas muestran la accion'
);

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
