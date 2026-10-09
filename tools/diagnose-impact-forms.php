<?php
/** Run with wp eval-file; reports counts and IDs, never changes forms or evidence. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit; }

function gnf_diagnostic_file_status( $evidence, $uploads ) {
	$base = realpath( $uploads['basedir'] ?? '' );
	if ( ! $base ) { return 'no_verificables'; }
	$base = rtrim( wp_normalize_path( $base ), '/' ) . '/';
	$candidates = array();
	if ( ! empty( $evidence['path_local'] ) ) { $candidates[] = $evidence['path_local']; }
	$url_path = parse_url( (string) ( $evidence['ruta'] ?? $evidence['url'] ?? '' ), PHP_URL_PATH );
	$upload_path = rtrim( (string) parse_url( $uploads['baseurl'] ?? '', PHP_URL_PATH ), '/' ) . '/';
	if ( is_string( $url_path ) && 0 === strpos( $url_path, $upload_path ) ) {
		$relative = rawurldecode( substr( $url_path, strlen( $upload_path ) ) );
		if ( false === strpos( $relative, "\0" ) && ! preg_match( '#(^|[\\\\/])\.\.([\\\\/]|$)#', $relative ) ) { $candidates[] = $base . $relative; }
	}
	$local = false;
	foreach ( $candidates as $candidate ) {
		$normalized = wp_normalize_path( (string) $candidate );
		if ( false !== strpos( $normalized, "\0" ) || 0 !== strpos( $normalized, $base ) || preg_match( '#(^|/)\.\.(/|$)#', $normalized ) ) { continue; }
		$local = true;
		$resolved = realpath( $candidate );
		if ( $resolved && 0 === strpos( wp_normalize_path( $resolved ), $base ) && is_file( $resolved ) ) { return 'encontrados'; }
	}
	return $local ? 'no_encontrados' : 'no_verificables';
}

function gnf_diagnose_impact_forms( $center_name, $year ) {
	global $wpdb;
	$report = array( 'anio' => $year, 'busqueda' => $center_name, 'cache_objetos_persistente' => function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache(), 'centros' => array(), 'formularios' => array(), 'entradas' => array() );
	$wpdb->last_error = '';
	$centers = $wpdb->get_results( $wpdb->prepare(
		"SELECT ID, post_title, post_status FROM {$wpdb->posts} WHERE post_type = 'centro_educativo' AND post_title LIKE %s ORDER BY ID",
		'%' . $wpdb->esc_like( $center_name ) . '%'
	) );
	if ( $wpdb->last_error ) { throw new RuntimeException( 'No se pudo consultar los centros.' ); }
	$entries = array(); $reto_ids = array();
	foreach ( (array) $centers as $center ) {
		$report['centros'][] = array( 'id' => (int) $center->ID, 'nombre' => $center->post_title, 'estado' => $center->post_status );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, centro_id, reto_id, user_id, estado, updated_at, data, evidencias FROM {$wpdb->prefix}gn_reto_entries WHERE centro_id = %d AND anio = %d ORDER BY reto_id, id",
			$center->ID, $year
		) );
		if ( $wpdb->last_error ) { throw new RuntimeException( 'No se pudo consultar las entradas.' ); }
		foreach ( (array) $rows as $row ) { $entries[] = $row; $reto_ids[ (int) $row->reto_id ] = true; }
	}
	foreach ( gnf_get_available_retos_for_year( $year ) as $reto ) { $reto_ids[ (int) $reto->ID ] = true; }
	$form_fields = array();
	foreach ( array_keys( $reto_ids ) as $reto_id ) {
		$form_id = gnf_get_reto_form_id_for_year( $reto_id, $year );
		$post = $form_id ? get_post( $form_id ) : null;
		$data = $post ? json_decode( (string) $post->post_content, true ) : null;
		$error = $post && JSON_ERROR_NONE !== json_last_error() ? json_last_error_msg() : null;
		$valid = $post && 'wpforms' === $post->post_type && is_array( $data ) && ! empty( $data['fields'] ) && is_array( $data['fields'] );
		$fields = $valid ? $data['fields'] : null;
		$form_fields[ $reto_id ] = $fields;
		$revisions = array();
		if ( $form_id ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT ID, post_modified_gmt, post_content FROM {$wpdb->posts} WHERE post_parent = %d AND post_type IN ('revision', 'wpforms_revision') ORDER BY ID DESC LIMIT 50",
				$form_id
			) );
			if ( $wpdb->last_error ) { throw new RuntimeException( 'No se pudo consultar las revisiones.' ); }
			foreach ( (array) $rows as $revision ) {
				$decoded = json_decode( (string) $revision->post_content, true );
				if ( is_array( $decoded ) && ! empty( $decoded['fields'] ) && is_array( $decoded['fields'] ) ) {
					$revisions[] = array( 'id' => (int) $revision->ID, 'modificado_utc' => $revision->post_modified_gmt, 'campos' => count( $decoded['fields'] ) );
				}
			}
		}
		$backup = $form_id ? get_option( 'gnf_impact_form_backup_' . $year . '_' . $form_id, false ) : false;
		$backup_data = is_array( $backup ) ? json_decode( (string) ( $backup['post_content'] ?? '' ), true ) : null;
		$report['formularios'][] = array(
			'reto_id' => $reto_id, 'reto' => get_the_title( $reto_id ), 'formulario_id' => (int) $form_id,
			'estado' => $post->post_status ?? null, 'modificado_utc' => $post->post_modified_gmt ?? null,
			'json_valido' => is_array( $data ), 'error_json' => $error, 'campos' => $valid ? count( $fields ) : 0,
			'campos_validos' => (bool) $valid, 'respaldo_preparacion_valido' => ! empty( $backup_data['fields'] ) && is_array( $backup_data['fields'] ),
			'revisiones_validas' => $revisions,
		);
	}
	$uploads = wp_upload_dir( null, false );
	foreach ( $entries as $entry ) {
		$evidence = json_decode( (string) $entry->evidencias, true );
		$values = json_decode( (string) $entry->data, true );
		$fields = $form_fields[ (int) $entry->reto_id ] ?? null;
		$file_fields = array();
		foreach ( (array) $fields as $key => $field ) {
			if ( is_array( $field ) && 'file-upload' === ( $field['type'] ?? '' ) ) { $file_fields[ (int) ( $field['id'] ?? $key ) ] = true; }
		}
		$active = 0; $replaced = 0; $malformed = 0; $missing_fields = array();
		$files = array( 'encontrados' => 0, 'no_encontrados' => 0, 'no_verificables' => 0 );
		foreach ( (array) $evidence as $item ) {
			if ( ! is_array( $item ) ) { $malformed++; continue; }
			if ( ! empty( $item['replaced'] ) ) { $replaced++; continue; }
			$active++;
			$files[ gnf_diagnostic_file_status( $item, $uploads ) ]++;
			$field_id = (int) ( $item['field_id'] ?? 0 );
			if ( null !== $fields && ! isset( $file_fields[ $field_id ] ) ) { $missing_fields[ $field_id ] = true; }
		}
		$report['entradas'][] = array(
			'id' => (int) $entry->id, 'centro_id' => (int) $entry->centro_id, 'reto_id' => (int) $entry->reto_id, 'usuario_id' => (int) $entry->user_id,
			'estado' => $entry->estado, 'modificado' => $entry->updated_at, 'respuestas_json_valido' => is_array( $values ), 'respuestas_guardadas' => is_array( $values ) ? count( $values ) : 0,
			'evidencias_json_valido' => is_array( $evidence ), 'evidencias_activas' => $active, 'evidencias_reemplazadas' => $replaced, 'evidencias_malformadas' => $malformed,
			'archivos_activos' => $files, 'campos_archivo_ausentes' => null === $fields ? null : array_keys( $missing_fields ),
		);
	}
	return $report;
}

function gnf_diagnostic_summary( $report ) {
	$lines = array( 'Diagnostico ' . $report['anio'] . ': ' . $report['busqueda'] );
	$lines[] = 'Cache de objetos persistente: ' . ( ! empty( $report['cache_objetos_persistente'] ) ? 'activo' : 'inactivo' );
	foreach ( $report['centros'] as $center ) { $lines[] = 'Centro ' . $center['id'] . ': ' . $center['nombre'] . ' (' . $center['estado'] . ')'; }
	if ( empty( $report['centros'] ) ) { $lines[] = 'No se encontraron centros con ese nombre.'; }
	$lines[] = '';
	$lines[] = 'Formulario | Reto | Estado | Revision candidata (ID / UTC / campos)';
	foreach ( $report['formularios'] as $form ) {
		$revision = $form['revisiones_validas'][0] ?? null;
		$candidate = $revision ? $revision['id'] . ' / ' . $revision['modificado_utc'] . ' / ' . $revision['campos'] : 'sin revision valida entre las 50 mas recientes';
		$lines[] = $form['formulario_id'] . ' | ' . $form['reto'] . ' | ' . ( $form['campos_validos'] ? 'OK' : 'REVISAR' ) . ' | ' . $candidate;
	}
	$lines[] = '';
	$lines[] = 'Entrada | Reto | Respuestas | Evidencias activas | Archivos presentes | Campos ausentes';
	$totals = array( 'encontrados' => 0, 'no_encontrados' => 0, 'no_verificables' => 0 );
	foreach ( $report['entradas'] as $entry ) {
		foreach ( $totals as $key => $count ) { $totals[$key] += $entry['archivos_activos'][$key]; }
		$missing = null === $entry['campos_archivo_ausentes'] ? 'formulario ilegible' : implode( ',', $entry['campos_archivo_ausentes'] );
		$lines[] = $entry['id'] . ' | ' . $entry['reto_id'] . ' | ' . $entry['respuestas_guardadas'] . ' | ' . $entry['evidencias_activas'] . ' | ' . $entry['archivos_activos']['encontrados'] . ' | ' . ( $missing ?: 'ninguno' );
	}
	$lines[] = '';
	$lines[] = 'Archivos activos: ' . $totals['encontrados'] . ' encontrados, ' . $totals['no_encontrados'] . ' no encontrados, ' . $totals['no_verificables'] . ' no verificables';
	return implode( "\n", $lines );
}

if ( ! function_exists( 'gnf_get_reto_form_id_for_year' ) || ! function_exists( 'gnf_get_available_retos_for_year' ) ) { WP_CLI::error( 'Activa el plugin Guardianes antes de ejecutar el diagnostico.' ); }
$center_name = trim( (string) ( $args[0] ?? '' ) );
$year = (int) ( $args[1] ?? 2026 );
if ( '' === $center_name || $year < 2020 || $year > 2100 ) { WP_CLI::error( 'Uso: wp eval-file <archivo> "Nombre parcial del centro" 2026' ); }
try {
	$report = gnf_diagnose_impact_forms( $center_name, $year );
	WP_CLI::log( 'resumen' === ( $args[2] ?? '' ) ? gnf_diagnostic_summary( $report ) : wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
} catch ( Throwable $error ) {
	WP_CLI::error( $error->getMessage() );
}
