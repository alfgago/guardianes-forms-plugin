<?php
/** Global, read-only definition audit; no individual answers or file checks. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit; }

function gnf_audit_wpforms( $year ) {
	global $wpdb;
	$report = array( 'anio' => $year, 'total_formularios' => 0, 'formularios_ilegibles_o_vacios' => 0, 'formularios' => array(), 'retos_sin_formulario' => array() );
	$by_form = array(); $retos = array(); $counts = array(); $seen = array();
	foreach ( gnf_get_available_retos_for_year( $year ) as $reto ) {
		$form_id = (int) gnf_get_reto_form_id_for_year( $reto->ID, $year );
		$mapped = array( 'id' => (int) $reto->ID, 'nombre' => $reto->post_title, 'formulario_id' => $form_id );
		$retos[] = $mapped;
		if ( $form_id ) { $by_form[$form_id][] = $mapped; }
	}
	$wpdb->last_error = '';
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT reto_id, COUNT(*) AS entradas, COUNT(DISTINCT centro_id) AS centros FROM {$wpdb->prefix}gn_reto_entries WHERE anio = %d GROUP BY reto_id", $year ) );
	if ( $wpdb->last_error ) { throw new RuntimeException( 'No se pudieron consultar los conteos globales de participacion.' ); }
	foreach ( (array) $rows as $row ) { $counts[ (int) $row->reto_id ] = array( 'entradas' => (int) $row->entradas, 'centros' => (int) $row->centros ); }
	$cursor = 0;
	do {
		$wpdb->last_error = '';
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_title, post_status, post_modified_gmt, post_content FROM {$wpdb->posts} WHERE post_type = 'wpforms' AND post_status NOT IN ('trash', 'auto-draft') AND ID > %d ORDER BY ID LIMIT 200", $cursor
		) );
		if ( $wpdb->last_error ) { throw new RuntimeException( 'No se pudo consultar el catalogo completo de formularios.' ); }
		foreach ( (array) $rows as $form ) {
			$cursor = (int) $form->ID; $seen[$cursor] = true;
			$data = json_decode( (string) $form->post_content, true ); $error = json_last_error();
			$state = 'OK';
			if ( JSON_ERROR_NONE !== $error ) { $state = 'JSON_INVALIDO'; }
			elseif ( ! is_array( $data ) || ! is_array( $data['fields'] ?? null ) ) { $state = 'CAMPOS_INVALIDOS'; }
			elseif ( ! $data['fields'] ) { $state = 'SIN_PREGUNTAS'; }
			else {
				foreach ( $data['fields'] as $id => $field ) {
					if ( ! is_array( $field ) || ! isset( $field['id'] ) || ! is_numeric( $field['id'] ) || (int) $field['id'] !== (int) $id || ! is_string( $field['type'] ?? null ) || '' === $field['type'] ) { $state = 'CAMPOS_INVALIDOS'; break; }
				}
			}
			$mapped_retos = array();
			foreach ( $by_form[$cursor] ?? array() as $reto ) { $mapped_retos[] = array_merge( $reto, $counts[$reto['id']] ?? array( 'entradas' => 0, 'centros' => 0 ) ); }
			$report['total_formularios']++;
			if ( 'OK' !== $state ) { $report['formularios_ilegibles_o_vacios']++; }
			$report['formularios'][] = array(
				'id' => $cursor, 'nombre' => $form->post_title, 'estado_post' => $form->post_status, 'modificado_utc' => $form->post_modified_gmt ?? null,
				'estado_definicion' => $state, 'preguntas' => is_array( $data['fields'] ?? null ) ? count( $data['fields'] ) : 0, 'retos' => $mapped_retos,
			);
		}
	} while ( count( (array) $rows ) === 200 );
	foreach ( $retos as $reto ) { if ( ! isset( $seen[$reto['formulario_id']] ) ) { $report['retos_sin_formulario'][] = $reto; } }
	return $report;
}

function gnf_wpforms_audit_summary( $report ) {
	$lines = array( 'Auditoria global WPForms ' . $report['anio'], 'Formularios: ' . $report['total_formularios'] . '; ilegibles o sin preguntas: ' . $report['formularios_ilegibles_o_vacios'], 'Las preguntas retiradas y respuestas historicas no invalidan un formulario.', '', 'ID | Formulario | Estado post | Definicion | Preguntas | Participacion anual por reto' );
	foreach ( $report['formularios'] as $form ) {
		$scopes = array();
		foreach ( $form['retos'] as $reto ) { $scopes[] = $reto['id'] . ': ' . $reto['centros'] . ' centros / ' . $reto['entradas'] . ' entradas'; }
		$title = trim( preg_replace( '/\s+/u', ' ', strip_tags( $form['nombre'] ) ) );
		$lines[] = $form['id'] . ' | ' . $title . ' | ' . $form['estado_post'] . ' | ' . $form['estado_definicion'] . ' | ' . $form['preguntas'] . ' | ' . ( $scopes ? implode( '; ', $scopes ) : 'sin retos vigentes asociados para este anio' );
	}
	foreach ( $report['retos_sin_formulario'] as $reto ) { $lines[] = 'RETO SIN FORMULARIO: ' . $reto['id'] . ' | ' . $reto['nombre'] . ' | enlace ' . $reto['formulario_id']; }
	return implode( "\n", $lines );
}

if ( ! function_exists( 'gnf_get_available_retos_for_year' ) || ! function_exists( 'gnf_get_reto_form_id_for_year' ) ) { WP_CLI::error( 'Guardianes debe estar activo.' ); }
$year = (int) ( $args[0] ?? 2026 ); $mode = $args[1] ?? 'resumen';
if ( $year < 2020 || $year > 2100 || ! in_array( $mode, array( 'resumen', 'json' ), true ) ) { WP_CLI::error( 'Uso: wp eval-file <archivo> 2026 [resumen|json]' ); }
try {
	$report = gnf_audit_wpforms( $year );
	WP_CLI::log( 'json' === $mode ? wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : gnf_wpforms_audit_summary( $report ) );
} catch ( Throwable $error ) { WP_CLI::error( $error->getMessage() ); }
