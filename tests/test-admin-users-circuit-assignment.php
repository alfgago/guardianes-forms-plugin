<?php
// Contrato del selector de circuitos en la pantalla clasica de usuarios.

$root        = __DIR__ . '/..';
$admin_users = file_get_contents( $root . '/includes/admin-users.php' );
$helpers     = file_get_contents( $root . '/includes/helpers.php' );
$rest_api    = file_get_contents( $root . '/includes/rest-api.php' );

$tests = 0;
$fails = 0;

function check_admin_users_circuit_contract( $condition, $message ) {
	global $tests, $fails;
	$tests++;

	if ( $condition ) {
		echo "  ok: {$message}\n";
		return;
	}

	$fails++;
	echo "  FAIL: {$message}\n";
}

check_admin_users_circuit_contract(
	strpos( $helpers, 'function gnf_get_region_circuitos' ) !== false
		&& strpos( $rest_api, 'gnf_get_region_circuitos( $region_id )' ) !== false,
	'la pantalla clasica y REST comparten la fuente canonica de circuitos'
);

check_admin_users_circuit_contract(
	strpos( $admin_users, 'name="user_circuito"' ) !== false
		&& strpos( $admin_users, 'id="user_circuito"' ) !== false
		&& strpos( $admin_users, 'Circuito asignado' ) !== false
		&& strpos( $admin_users, 'Toda la DRE' ) !== false,
	'el editor clasico muestra el selector de circuito para supervisores'
);

check_admin_users_circuit_contract(
	strpos( $admin_users, "sanitize_text_field( wp_unslash( \$_POST['user_circuito']" ) !== false
		&& strpos( $admin_users, 'gnf_normalize_circuito' ) !== false
		&& strpos( $admin_users, "update_user_meta( \$user_id, 'circuito'" ) !== false
		&& strpos( $admin_users, "delete_user_meta( \$user_id, 'circuito' )" ) !== false,
	'el guardado normaliza, actualiza y limpia el circuito'
);

check_admin_users_circuit_contract(
	strpos( $admin_users, 'Circuito no valido para la Direccion Regional seleccionada.' ) !== false
		&& strpos( $admin_users, 'gnf_get_region_circuitos( $new_region )' ) !== false,
	'el servidor valida que el circuito pertenezca a la DRE'
);

check_admin_users_circuit_contract(
	strpos( $admin_users, "addEventListener('change', loadCircuitos)" ) !== false
		&& strpos( $admin_users, "'/gnf/v1/admin/circuitos'" ) !== false,
	'el selector recarga circuitos cuando cambia la DRE'
);

check_admin_users_circuit_contract(
	strpos( $admin_users, '<th>Circuito</th>' ) !== false
		&& strpos( $admin_users, 'gnf_get_user_circuito($user->ID)' ) !== false,
	'el listado muestra el circuito actual del supervisor'
);

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
