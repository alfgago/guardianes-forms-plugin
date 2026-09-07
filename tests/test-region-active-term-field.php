<?php
// Test plano (sin WordPress) para asegurar el campo nativo de activacion DRE.

$source = file_get_contents( __DIR__ . '/../includes/cpts.php' );
$tests  = 0;
$fails  = 0;

function check_region_term_field( $cond, $msg ) {
	global $tests, $fails;
	$tests++;
	if ( $cond ) {
		echo "  ok: {$msg}\n";
	} else {
		$fails++;
		echo "  FAIL: {$msg}\n";
	}
}

check_region_term_field(
	strpos( $source, 'gnf_render_region_active_add_field' ) !== false
		&& strpos( $source, "add_action( 'gn_region_add_form_fields'" ) !== false,
	'alta de gn_region muestra el campo de activacion'
);

check_region_term_field(
	strpos( $source, 'gnf_render_region_active_edit_field' ) !== false
		&& strpos( $source, "add_action( 'gn_region_edit_form_fields'" ) !== false,
	'edicion de gn_region muestra el campo de activacion'
);

check_region_term_field(
	strpos( $source, 'gnf_save_region_active_meta' ) !== false
		&& strpos( $source, "add_action( 'created_gn_region'" ) !== false
		&& strpos( $source, "add_action( 'edited_gn_region'" ) !== false,
	'crear o editar gn_region guarda gnf_dre_activa'
);

check_region_term_field(
	strpos( $source, 'gnf_dre_activa_present' ) !== false,
	'el guardado distingue formulario nativo de cambios programaticos'
);

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );

