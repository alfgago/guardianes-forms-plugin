<?php
/** CLI-only migration. Preview by default; application requires its signature. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit; }

function gnf_prepare_impact_fields_cli( $args ) {
	$year = (string) ( $args[0] ?? '2026' ); $mode = $args[1] ?? 'simular';
	if ( '2026' !== $year || ! in_array( $mode, array( 'simular', 'aplicar' ), true ) || count( $args ) > 3 || ( 'simular' === $mode && count( $args ) > 2 ) ) { throw new RuntimeException( 'Uso: wp eval-file <archivo> 2026 [simular|aplicar firma=<firma_del_plan>]' ); }
	$plan = gnf_plan_impact_fields_for_year( 2026 );
	$summary = array( 'anio' => 2026, 'resultado' => $plan['errors'] ? 'simulacion_bloqueada' : 'simulacion', 'firma' => $plan['signature'], 'errores' => $plan['errors'], 'formularios' => array() );
	foreach ( $plan['forms'] as $form ) {
		$original = json_decode( $form['original'], true ); $added = array(); $updated = array();
		foreach ( $form['definition']['fields'] as $id => $field ) {
			if ( ! isset( $original['fields'][$id] ) ) { $added[] = array( 'id' => (int) $id, 'tipo' => $field['type'], 'pregunta' => $field['label'] ?? '' ); }
			elseif ( $original['fields'][$id] !== $field ) { $updated[] = (int) $id; }
		}
		$summary['formularios'][] = array( 'id' => $form['form_id'], 'reto' => $form['reto_title'], 'requiere_cambios' => $form['changed'], 'campos_nuevos' => $added, 'ids_actualizados' => $updated );
	}
	if ( 'aplicar' === $mode ) {
		if ( ! preg_match( '/^firma=([a-f0-9]{64})$/D', (string) ( $args[2] ?? '' ), $match ) || ! hash_equals( $plan['signature'], $match[1] ) ) { throw new RuntimeException( 'Falta la firma revisada o el plan cambio. Ejecuta y revisa una nueva simulacion.' ); }
		if ( $plan['errors'] ) { throw new RuntimeException( 'Hay formularios que requieren revision. No se aplica la preparacion.' ); }
		if ( ! gnf_ensure_impact_fields_for_year( 2026, $match[1] ) ) { throw new RuntimeException( 'La preparacion se detuvo por un cambio concurrente o un error de respaldo/guardado. Revisa los respaldos y ejecuta la auditoria global antes de reintentar.' ); }
		$summary['resultado'] = 'aplicado';
	}
	return $summary;
}

if ( ! function_exists( 'gnf_plan_impact_fields_for_year' ) ) { WP_CLI::error( 'Guardianes actualizado debe estar activo.' ); }
try {
	WP_CLI::log( wp_json_encode( gnf_prepare_impact_fields_cli( $args ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
} catch ( Throwable $error ) { WP_CLI::error( $error->getMessage() ); }
