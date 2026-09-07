<?php
// Evita que el esquema ACF de matricula se renderice en la pagina de configuracion.

$root       = __DIR__ . '/..';
$acf_fields = file_get_contents( $root . '/includes/acf-fields.php' );
$matricula  = file_get_contents( $root . '/includes/matricula.php' );
$groups     = json_decode( file_get_contents( $root . '/seeders/acf-matricula-form-group.json' ), true );
$group      = is_array( $groups ) && isset( $groups[0] ) && is_array( $groups[0] ) ? $groups[0] : array();

$tests = 0;
$fails = 0;

function check_acf_config_isolation( $condition, $message ) {
	global $tests, $fails;
	$tests++;

	if ( $condition ) {
		echo "  ok: {$message}\n";
		return;
	}

	$fails++;
	echo "  FAIL: {$message}\n";
}

check_acf_config_isolation(
	'group_gnf_matricula_frontend' === ( $group['key'] ?? '' )
		&& empty( $group['location'] ),
	'el esquema versionado de matricula no tiene ubicacion administrativa'
);

check_acf_config_isolation(
	strpos( $acf_fields, "\$group['location'] = array();" ) !== false
		&& strpos( $acf_fields, 'acf_add_local_field_group($group);' ) !== false,
	'el registro PHP neutraliza reglas de ubicacion antiguas'
);

check_acf_config_isolation(
	strpos( $matricula, "'group_gnf_matricula_frontend'" ) !== false
		&& strpos( $matricula, 'acf_get_fields( $group )' ) !== false,
	'el panel docente conserva acceso al esquema por su clave'
);

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
