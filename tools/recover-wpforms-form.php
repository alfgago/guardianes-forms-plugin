<?php
/** Explicit, single-form recovery. Default is a read-only simulation. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit; }

function gnf_recovery_decode_definition( $content ) {
	$data = json_decode( (string) $content, true );
	if ( ! is_array( $data ) || empty( $data['fields'] ) || ! is_array( $data['fields'] ) ) { return null; }
	$ids = array();
	foreach ( $data['fields'] as $key => $field ) {
		if ( ! is_array( $field ) || ! isset( $field['id'], $field['type'] ) || ! is_numeric( $field['id'] ) || (int) $key !== (int) $field['id'] || isset( $ids[ (int) $field['id'] ] ) ) { return null; }
		$ids[ (int) $field['id'] ] = true;
	}
	return $data;
}

function gnf_recovery_value_present( $value ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $item ) { if ( gnf_recovery_value_present( $item ) ) { return true; } }
		return false;
	}
	return null !== $value && ( ! is_string( $value ) || '' !== trim( $value ) );
}

function gnf_recovery_record_usage( &$usage, $id, $kind, $entry_id ) {
	if ( ! isset( $usage[$id] ) ) { $usage[$id] = array( 'respuestas_vacias' => 0, 'respuestas_con_valor' => 0, 'evidencias_activas' => 0, 'entradas_ejemplo' => array() ); }
	$usage[$id][$kind]++;
	if ( count( $usage[$id]['entradas_ejemplo'] ) < 3 && ! in_array( $entry_id, $usage[$id]['entradas_ejemplo'], true ) ) { $usage[$id]['entradas_ejemplo'][] = $entry_id; }
}

/** Counts and question labels only; never emits saved values or full definitions. */
function gnf_recovery_revision_history( $form_id, $used, $file_ids, $problem_ids ) {
	global $wpdb;
	$history = array( 'revisiones_revisadas' => 0, 'revisiones_ilegibles' => 0, 'compatibles_total' => 0, 'revisiones_compatibles' => array(), 'campos_en_revisiones' => array() );
	$variants = array(); $before = PHP_INT_MAX;
	do {
		$wpdb->last_error = '';
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_modified_gmt, post_content FROM {$wpdb->posts} WHERE post_parent = %d AND post_type IN ('revision', 'wpforms_revision') AND ID < %d ORDER BY ID DESC LIMIT 200",
			$form_id, $before
		) );
		if ( $wpdb->last_error ) { throw new RuntimeException( 'No se pudo consultar el historial de revisiones. No se modifico el formulario.' ); }
		foreach ( (array) $rows as $revision ) {
			$before = (int) $revision->ID; $history['revisiones_revisadas']++;
			$data = gnf_recovery_decode_definition( $revision->post_content );
			if ( ! $data ) { $history['revisiones_ilegibles']++; continue; }
			$compatible = true;
			foreach ( $used as $id => $unused ) {
				if ( ! isset( $data['fields'][$id] ) || ( isset( $file_ids[$id] ) && 'file-upload' !== $data['fields'][$id]['type'] ) ) { $compatible = false; break; }
			}
			if ( $compatible ) {
				$history['compatibles_total']++;
				if ( count( $history['revisiones_compatibles'] ) < 10 ) { $history['revisiones_compatibles'][] = array( 'id' => (int) $revision->ID, 'modificado_utc' => $revision->post_modified_gmt ?? null, 'campos' => count( $data['fields'] ) ); }
			}
			foreach ( $problem_ids as $id ) {
				if ( ! isset( $data['fields'][$id] ) || count( $history['campos_en_revisiones'][$id] ?? array() ) >= 3 ) { continue; }
				$field = $data['fields'][$id];
				$label = trim( preg_replace( '/\s+/u', ' ', strip_tags( (string) ( $field['label'] ?? '' ) ) ) );
				$signature = md5( $field['type'] . ':' . $label );
				if ( isset( $variants[$id][$signature] ) ) { continue; }
				$variants[$id][$signature] = true;
				preg_match( '/^.{0,180}/us', $label, $label_match );
				$history['campos_en_revisiones'][$id][] = array( 'revision_id' => (int) $revision->ID, 'modificado_utc' => $revision->post_modified_gmt ?? null, 'tipo' => $field['type'], 'etiqueta' => $label_match[0] ?? '' );
			}
		}
	} while ( count( (array) $rows ) === 200 );
	$history['campos_no_encontrados'] = array_values( array_diff( $problem_ids, array_keys( $history['campos_en_revisiones'] ) ) );
	return $history;
}

function gnf_recover_wpforms_form( $year, $form_id, $revision_id, $apply = false, $diagnose = false ) {
	global $wpdb;
	if ( $apply && $diagnose ) { throw new RuntimeException( 'El diagnostico es de solo lectura y no puede aplicar cambios.' ); }
	$form = get_post( $form_id );
	if ( ! $form || 'wpforms' !== $form->post_type || 'trash' === $form->post_status ) { throw new RuntimeException( 'El destino no es un formulario WPForms activo.' ); }
	$summary = array( 'formulario' => $form_id, 'revision' => $revision_id );
	if ( gnf_recovery_decode_definition( $form->post_content ) ) { return array_merge( $summary, array( 'resultado' => 'ya_valido' ) ); }
	$revision = get_post( $revision_id );
	if ( ! $revision || (int) $revision->post_parent !== $form_id || ! in_array( $revision->post_type, array( 'revision', 'wpforms_revision' ), true ) ) { throw new RuntimeException( 'La revision no pertenece al formulario solicitado.' ); }
	$data = gnf_recovery_decode_definition( $revision->post_content );
	if ( ! $data ) { throw new RuntimeException( 'La revision no tiene una definicion valida con IDs coherentes.' ); }
	$reto_ids = array();
	foreach ( gnf_get_available_retos_for_year( $year ) as $reto ) {
		if ( $form_id === (int) gnf_get_reto_form_id_for_year( $reto->ID, $year ) ) { $reto_ids[] = (int) $reto->ID; }
	}
	if ( ! $reto_ids ) { throw new RuntimeException( 'El formulario no esta asociado a un reto del anio solicitado.' ); }
	$cursor = 0; $entries_checked = 0; $max_id = max( array_map( 'intval', array_keys( $data['fields'] ) ) );
	$missing = array(); $wrong_type = array(); $used = array(); $file_ids = array(); $usage = array();
	$placeholders = implode( ',', array_fill( 0, count( $reto_ids ), '%d' ) );
	do {
		$wpdb->last_error = '';
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, data, evidencias FROM {$wpdb->prefix}gn_reto_entries WHERE anio = %d AND reto_id IN ({$placeholders}) AND id > %d ORDER BY id LIMIT 200",
			...array_merge( array( $year ), $reto_ids, array( $cursor ) )
		) );
		if ( $wpdb->last_error ) { throw new RuntimeException( 'No se pudieron verificar las entradas; no se modifico el formulario.' ); }
		foreach ( (array) $rows as $row ) {
			$cursor = (int) $row->id; $entries_checked++;
			$values = '' === (string) $row->data ? array() : json_decode( (string) $row->data, true );
			$evidences = '' === (string) $row->evidencias ? array() : json_decode( (string) $row->evidencias, true );
			if ( ! is_array( $values ) || ! is_array( $evidences ) || ( isset( $values['__raw_values__'] ) && ! is_array( $values['__raw_values__'] ) ) ) { throw new RuntimeException( 'La entrada ' . $row->id . ' no se puede leer. Requiere revision manual.' ); }
			foreach ( (array) ( $values['__raw_values__'] ?? array() ) as $id => $value ) {
				if ( ! ctype_digit( (string) $id ) ) { continue; }
				$used[ (int) $id ] = true;
				if ( $diagnose ) { gnf_recovery_record_usage( $usage, (int) $id, gnf_recovery_value_present( $value ) ? 'respuestas_con_valor' : 'respuestas_vacias', (int) $row->id ); }
				$max_id = max( $max_id, (int) $id );
				if ( ! isset( $data['fields'][ (int) $id ] ) ) { $missing[ (int) $id ] = true; }
			}
			foreach ( $evidences as $evidence ) {
				if ( ! is_array( $evidence ) || ! isset( $evidence['field_id'] ) ) { throw new RuntimeException( 'Evidencia sin ID de campo en la entrada ' . $row->id . '.' ); }
				$id = (int) $evidence['field_id']; $max_id = max( $max_id, $id );
				if ( ! empty( $evidence['replaced'] ) ) { continue; }
				$used[$id] = true; $file_ids[$id] = true;
				if ( $diagnose ) { gnf_recovery_record_usage( $usage, $id, 'evidencias_activas', (int) $row->id ); }
				if ( ! isset( $data['fields'][$id] ) ) { $missing[$id] = true; }
				elseif ( 'file-upload' !== $data['fields'][$id]['type'] ) { $wrong_type[$id] = true; }
			}
		}
	} while ( count( (array) $rows ) === 200 );
	if ( $diagnose ) {
		$problems = array_values( array_unique( array_merge( array_keys( $missing ), array_keys( $wrong_type ) ) ) );
		return array_merge( $summary, array(
			'resultado' => 'diagnostico', 'entradas_verificadas' => $entries_checked,
			'campos_ausentes' => array_keys( $missing ), 'campos_archivo_otro_tipo' => array_keys( $wrong_type ),
			'uso_campos' => array_intersect_key( $usage, array_fill_keys( $problems, true ) ),
			'historial' => gnf_recovery_revision_history( $form_id, $used, $file_ids, $problems ),
		) );
	}
	if ( $missing || $wrong_type ) { throw new RuntimeException( 'Revision incompatible: campos ausentes [' . implode( ',', array_keys( $missing ) ) . '], campos de archivo con otro tipo [' . implode( ',', array_keys( $wrong_type ) ) . ']. No se modifico el formulario.' ); }
	$data['field_id'] = max( (int) ( $data['field_id'] ?? 1 ), $max_id + 1 );
	$summary['campos'] = count( $data['fields'] ); $summary['entradas_verificadas'] = $entries_checked;
	if ( ! $apply ) { return array_merge( $summary, array( 'resultado' => 'simulacion' ) ); }
	$current = get_post( $form_id );
	if ( ! $current || $current->post_content !== $form->post_content ) { throw new RuntimeException( 'El formulario cambio durante la comprobacion. Ejecuta nuevamente la simulacion.' ); }
	$content = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	if ( ! is_string( $content ) || json_decode( $content, true ) !== $data ) { throw new RuntimeException( 'No se pudo codificar la revision.' ); }
	$backup_key = 'gnf_form_recovery_backup_' . $form_id . '_' . md5( (string) $form->post_content );
	$backup = get_option( $backup_key, false );
	if ( false === $backup ) {
		$backup = array( 'form_id' => $form_id, 'revision_id' => $revision_id, 'created_at' => time(), 'post_content' => (string) $form->post_content );
		add_option( $backup_key, $backup, '', false );
	}
	if ( get_option( $backup_key, false ) !== $backup || ! is_array( $backup ) || $backup['post_content'] !== $form->post_content ) { throw new RuntimeException( 'No se pudo verificar el respaldo previo. No se modifico el formulario.' ); }
	$result = wp_update_post( wp_slash( array( 'ID' => $form_id, 'post_content' => $content ) ), true );
	if ( is_wp_error( $result ) || ! $result ) { throw new RuntimeException( 'WordPress no pudo guardar el formulario. El respaldo previo se conserva.' ); }
	$saved = get_post( $form_id );
	if ( ! $saved || json_decode( (string) $saved->post_content, true ) !== $data ) { throw new RuntimeException( 'La comprobacion del guardado fallo. El respaldo previo se conserva.' ); }
	return array_merge( $summary, array( 'resultado' => 'restaurado', 'respaldo' => $backup_key ) );
}

if ( ! function_exists( 'gnf_get_available_retos_for_year' ) ) { WP_CLI::error( 'Guardianes debe estar activo.' ); }
$year = (int) ( $args[0] ?? 0 ); $form_id = (int) ( $args[1] ?? 0 ); $revision_id = (int) ( $args[2] ?? 0 );
$mode = $args[3] ?? 'simular';
if ( $year < 2020 || $year > 2100 || $form_id < 1 || $revision_id < 1 || ! in_array( $mode, array( 'simular', 'aplicar', 'diagnosticar' ), true ) ) { WP_CLI::error( 'Uso: wp eval-file <archivo> 2026 <formulario_id> <revision_id> [simular|aplicar|diagnosticar]' ); }
try {
	WP_CLI::log( wp_json_encode( gnf_recover_wpforms_form( $year, $form_id, $revision_id, 'aplicar' === $mode, 'diagnosticar' === $mode ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
} catch ( Throwable $error ) { WP_CLI::error( $error->getMessage() ); }
