<?php
/**
 * Shared evidence review rules.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical reasons supervisors can use when observing evidence.
 *
 * @return array<string,string>
 */
function gnf_get_evidence_rejection_reasons() {
	return array(
		'no_corresponde'    => 'Evidencia no corresponde.',
		'otra_accion_reto'  => 'Evidencia de otra acción o reto.',
		'ya_valorada'       => 'Evidencia ya valorada.',
		'accion_no_amigable' => 'Acción debe ser amigable.',
	);
}

/**
 * Checks if a rejection reason key is part of the allowed taxonomy.
 *
 * @param string $reason Reason key.
 * @return bool
 */
function gnf_is_valid_evidence_rejection_reason( $reason ) {
	$reason = trim( (string) $reason );
	return '' !== $reason && array_key_exists( $reason, gnf_get_evidence_rejection_reasons() );
}

/**
 * Returns the label for an allowed rejection reason.
 *
 * @param string $reason Reason key.
 * @return string
 */
function gnf_get_evidence_rejection_reason_label( $reason ) {
	$reasons = gnf_get_evidence_rejection_reasons();
	$reason  = trim( (string) $reason );
	return $reasons[ $reason ] ?? '';
}

/**
 * Legacy year-validation flags are only actionable when we have an actual
 * image date to compare. PDFs, generic files, and screenshots without EXIF
 * should remain pending for manual review.
 *
 * @param array $evidence Evidence object.
 * @return bool
 */
function gnf_evidence_has_verifiable_date_issue( $evidence ) {
	if ( ! is_array( $evidence ) || empty( $evidence['requires_year_validation'] ) ) {
		return false;
	}

	$tipo      = (string) ( $evidence['tipo'] ?? $evidence['type'] ?? '' );
	$file_name = (string) ( $evidence['nombre'] ?? $evidence['filename'] ?? '' );
	$is_image  = 'imagen' === $tipo || ( '' !== $file_name && 1 === preg_match( '/\.(jpe?g|png|gif|webp|heic|heif|tiff?)$/i', $file_name ) );
	if ( ! $is_image ) {
		return false;
	}
	if ( 'browser_file_metadata' === (string) ( $evidence['date_source'] ?? '' ) ) {
		return false;
	}

	if ( ! empty( $evidence['original_date'] ) || ! empty( $evidence['photo_date'] ) || ! empty( $evidence['exifYear'] ) ) {
		return true;
	}

	$comment = (string) ( $evidence['supervisor_comment'] ?? $evidence['warning'] ?? '' );
	return 1 === preg_match( '/\b(19|20)\d{2}\b/', $comment );
}
