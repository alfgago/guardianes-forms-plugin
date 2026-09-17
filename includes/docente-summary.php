<?php
/** Evidence counters and requirement fields for the teacher panel. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gnf_summarize_docente_entries( $entries, $selected_ids ) {
	$selected = array_fill_keys( array_map( 'intval', (array) $selected_ids ), true );
	$result = array( 'aprobados' => 0, 'enviados' => 0, 'correccion' => 0, 'en_progreso' => 0,
		'evidenceCounts' => array( 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0 ) );
	$reviewed_ids = array();
	foreach ( (array) $entries as $entry ) {
		if ( ! isset( $selected[ (int) $entry->reto_id ] ) ) {
			continue;
		}
		$states = array( 'aprobado' => 'aprobados', 'enviado' => 'enviados', 'correccion' => 'correccion' );
		$result[ $states[ $entry->estado ] ?? 'en_progreso' ]++;
		$evidences = json_decode( (string) ( $entry->evidencias ?? '[]' ), true );
		if ( function_exists( 'gnf_enrich_evidencias' ) ) {
			$evidences = gnf_enrich_evidencias( is_array( $evidences ) ? $evidences : array(), (int) $entry->reto_id, $entry->anio ?? null );
		}
		$entry_total = 0;
		$entry_approved = 0;
		foreach ( (array) $evidences as $evidence ) {
			if ( ! is_array( $evidence ) || ! empty( $evidence['replaced'] ) ) {
				continue;
			}
			$state = $evidence['estado'] ?? 'pendiente';
			$key = 'aprobada' === $state ? 'approved' : ( 'rechazada' === $state ? 'rejected' : 'pending' );
			$result['evidenceCounts'][ $key ]++;
			$result['evidenceCounts']['total']++;
			$entry_total++;
			$entry_approved += 'approved' === $key ? 1 : 0;
		}
		$reviewed_ids[ (int) $entry->reto_id ] = $entry_total > 0 && $entry_total === $entry_approved;
	}
	$result['allComplete'] = count( $selected ) > 0 && count( array_filter( $reviewed_ids ) ) === count( $selected );
	return $result;
}

function gnf_required_evidence_field_ids( $slug, $fields ) {
	if ( ! in_array( $slug, array( 'agua', 'electricidad', 'residuos' ), true ) ) {
		return array();
	}
	$ids = array();
	foreach ( (array) $fields as $field ) {
		if ( isset( $field['id'] ) && 'file-upload' === ( $field['type'] ?? '' )
			&& preg_match( '/\brequisito\b/iu', strip_tags( (string) ( $field['label'] ?? '' ) ) ) ) {
			$ids[] = (int) $field['id'];
		}
	}
	return $ids;
}
