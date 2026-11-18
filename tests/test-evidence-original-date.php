<?php
// Regresiones para conservar y mostrar la fecha original de cualquier evidencia.

$root    = __DIR__ . '/..';
$support = @file_get_contents( $root . '/includes/evidence-dates.php' ) ?: '';
$upload  = file_get_contents( $root . '/includes/evidencias.php' );
$helpers = file_get_contents( $root . '/includes/helpers.php' );
$rest    = file_get_contents( $root . '/includes/rest-api.php' );
$review  = file_get_contents( $root . '/includes/evidence-review.php' );
$legacy  = file_get_contents( $root . '/assets/js/guardianes.js' );
$embed   = file_get_contents( $root . '/app/src/components/domain/WpFormsEmbed.tsx' );
$viewer  = file_get_contents( $root . '/app/src/components/domain/EvidenceViewer.tsx' );
$card    = file_get_contents( $root . '/app/src/panels/supervisor/components/EntryReviewCard.tsx' );
$notifs  = file_get_contents( $root . '/app/src/panels/supervisor/pages/NotificacionesPage.tsx' );
$types   = file_get_contents( $root . '/app/src/types/reto.ts' );
$api     = file_get_contents( $root . '/app/src/api/retos.ts' );

$tests = 0;
$fails = 0;

function wp_read_image_metadata( $path ) {
	return $GLOBALS['image_date_metadata'][ $path ] ?? array();
}

function check_evidence_original_date( $condition, $label ) {
	global $tests, $fails;
	$tests++;
	if ( $condition ) {
		echo "  ok: {$label}\n";
		return;
	}
	$fails++;
	echo "  FAIL: {$label}\n";
}

check_evidence_original_date(
	file_exists( $root . '/includes/evidence-dates.php' )
		&& false !== strpos( $support, 'function gnf_extract_evidence_original_date' )
		&& false !== strpos( $support, 'function gnf_apply_evidence_original_date' ),
	'existe un extractor generico y reutilizable de fecha original'
);

check_evidence_original_date(
	false !== strpos( $upload, 'gnf_apply_evidence_original_date' )
		&& false !== strpos( $helpers, 'gnf_apply_evidence_original_date' )
		&& false !== strpos( $rest, "'file_metadata'" ),
	'la fecha se guarda al subir, se recupera en legados y atraviesa el autosave REST'
);

check_evidence_original_date(
	false !== strpos( $api, 'fileMetadata?' )
		&& false !== strpos( $embed, 'fileMetadata' )
		&& false !== strpos( $legacy, 'lastModified' )
		&& false === strpos( $legacy, "fileType: 'image/webp'" ),
	'el navegador conserva la fecha del archivo y deja de destruir EXIF al convertir a WebP'
);

check_evidence_original_date(
	false !== strpos( $types, 'original_date?' )
		&& false !== strpos( $card, 'Fecha original del archivo')
		&& false !== strpos( $notifs, 'Fecha original del archivo')
		&& false !== strpos( $embed, 'Fecha original del archivo')
		&& false !== strpos( $viewer, 'dateDisplay'),
	'panel y notificaciones muestran siempre la fecha original o su ausencia'
);

check_evidence_original_date(
	false !== strpos( $review, "\$evidence['original_date']" ),
	'la validacion de imagen reconoce la nueva fecha generica'
);

check_evidence_original_date(
	false === strpos( $legacy, 'new Date(file.lastModified).getFullYear()' ),
	'el formulario clasico no advierte por el anio de modificacion del navegador'
);

if ( file_exists( $root . '/includes/evidence-dates.php' ) ) {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', $root . '/' );
	}
	require_once $root . '/includes/evidence-dates.php';
	require_once $root . '/includes/evidence-review.php';

	$temp_pdf = tempnam( sys_get_temp_dir(), 'gnf-date-pdf-' );
	file_put_contents( $temp_pdf, "%PDF-1.7\n1 0 obj << /CreationDate (D:20250301103000-06'00') >> endobj\n%%EOF" );
	$pdf_date = gnf_extract_evidence_original_date( $temp_pdf, 'pdf' );
	@unlink( $temp_pdf );
	check_evidence_original_date(
		'2025-03-01' === ( $pdf_date['date'] ?? '' ) && 'pdf_metadata' === ( $pdf_date['source'] ?? '' ),
	'extrae CreationDate de un PDF'
	);

	$temp_image = tempnam( sys_get_temp_dir(), 'gnf-date-image-' );
	file_put_contents( $temp_image, "RIFF....WEBP....EXIF....DateTimeOriginal\0 2025:07:14 08:09:10\0" );
	$image_date = gnf_extract_evidence_original_date( $temp_image, 'imagen' );
	@unlink( $temp_image );
	check_evidence_original_date(
		'2025-07-14' === ( $image_date['date'] ?? '' ) && 'image_metadata' === ( $image_date['source'] ?? '' ),
	'extrae fecha embebida incluso de evidencia WebP'
	);

	$pdf_evidence = gnf_apply_evidence_original_date(
		array( 'tipo' => 'pdf', 'nombre' => 'informe.pdf' ),
		2026,
		array( 'date' => '2025-04-05', 'source' => 'pdf_metadata' )
	);
	check_evidence_original_date(
		'2025-04-05' === ( $pdf_evidence['original_date'] ?? '' )
			&& ! empty( $pdf_evidence['requires_year_validation'] )
			&& ! gnf_evidence_has_verifiable_date_issue( $pdf_evidence ),
	'PDF de otro anio alerta pero no se auto-rechaza'
	);

	$image_evidence = gnf_apply_evidence_original_date(
		array( 'tipo' => 'imagen', 'nombre' => 'foto.jpg' ),
		2026,
		array( 'date' => '2025-04-05', 'source' => 'image_metadata' )
	);
	check_evidence_original_date(
		'2025-04-05' === ( $image_evidence['photo_date'] ?? '' )
			&& gnf_evidence_has_verifiable_date_issue( $image_evidence ),
		'imagen con fecha verificable de otro anio conserva validacion automatica'
	);

	$browser_image = gnf_apply_evidence_original_date(
		array( 'tipo' => 'imagen', 'nombre' => 'captura.png' ),
		2026,
		array( 'date' => '2025-04-05', 'source' => 'browser_file_metadata' )
	);
	check_evidence_original_date(
		null === $browser_image['original_date']
			&& 'unavailable' === $browser_image['date_source']
			&& empty( $browser_image['requires_year_validation'] )
			&& ! gnf_evidence_has_verifiable_date_issue( $browser_image ),
		'lastModified del navegador no es fecha original ni genera alerta anual'
	);
	$browser_pdf = gnf_apply_evidence_original_date(
		array( 'tipo' => 'pdf', 'nombre' => 'informe.pdf' ),
		2026,
		array( 'date' => '2026-10-30', 'source' => 'browser_file_metadata' )
	);
	check_evidence_original_date(
		null === $browser_pdf['original_date'] && empty( $browser_pdf['date_year_mismatch'] ),
		'un PDF sin fecha embebida tampoco usa la fecha de modificacion'
	);

	$temp_precedence = tempnam( sys_get_temp_dir(), 'gnf-date-priority-' );
	file_put_contents( $temp_precedence, "RIFF....WEBP....EXIF....DateTimeOriginal\0 2024:02:03 08:09:10\0" );
	$preferred_metadata = gnf_apply_evidence_original_date(
		array( 'tipo' => 'imagen', 'nombre' => 'foto.webp', 'path_local' => $temp_precedence ),
		2026,
		array( 'date' => '2025-04-05', 'source' => 'browser_file_metadata' )
	);
	@unlink( $temp_precedence );
	check_evidence_original_date(
		'2024-02-03' === ( $preferred_metadata['original_date'] ?? '' )
			&& 'image_metadata' === ( $preferred_metadata['date_source'] ?? '' ),
		'la fecha embebida confiable tiene prioridad sobre la fecha del navegador'
	);

	$temp_stored = tempnam( sys_get_temp_dir(), 'gnf-date-stored-' );
	file_put_contents( $temp_stored, "RIFF....WEBP....EXIF....DateTimeOriginal\0 2025:02:03 08:09:10\0" );
	$stored_browser = gnf_apply_evidence_original_date(
		array(
			'tipo' => 'imagen', 'path_local' => $temp_stored,
			'original_date' => '2026-10-30', 'photo_date' => '2026-10-30',
			'date_source' => 'browser_file_metadata', 'estado' => 'aprobada', 'puntos' => 5,
		),
		2026
	);
	@unlink( $temp_stored );
	check_evidence_original_date(
		'2025-02-03' === $stored_browser['original_date']
			&& '2025-02-03' === $stored_browser['photo_date']
			&& 'image_metadata' === $stored_browser['date_source'],
		'vuelve a extraer metadatos cuando la fecha guardada procedia del navegador'
	);
	check_evidence_original_date(
		'aprobada' === $stored_browser['estado'] && 5 === $stored_browser['puntos'],
		'corregir la fecha mostrada no modifica la revision ni los puntos guardados'
	);
	$stored_unknown = gnf_apply_evidence_original_date(
		array(
			'original_date' => '2026-10-30', 'photo_date' => '2026-10-30',
			'date_source' => 'browser_file_metadata', 'requires_year_validation' => true,
		),
		2026
	);
	check_evidence_original_date(
		null === $stored_unknown['original_date'] && empty( $stored_unknown['photo_date'] )
			&& 'unavailable' === $stored_unknown['date_source']
			&& empty( $stored_unknown['requires_year_validation'] ),
		'una fecha historica del navegador no se vuelve a presentar como original'
	);

	// Minimal TIFF with separate original and digitized EXIF timestamps.
	$temp_exif = tempnam( sys_get_temp_dir(), 'gnf-date-exif-' );
	$tiff = 'II' . pack( 'vV', 42, 8 ) . pack( 'v', 1 )
		. pack( 'vvVV', 0x8769, 4, 1, 26 ) . pack( 'V', 0 )
		. pack( 'v', 2 ) . pack( 'vvVV', 0x9003, 2, 20, 56 )
		. pack( 'vvVV', 0x9004, 2, 20, 76 ) . pack( 'V', 0 )
		. "2025:03:01 23:59:00\0" . "2026:10:30 10:00:00\0";
	file_put_contents( $temp_exif, $tiff );
	$GLOBALS['image_date_metadata'][ $temp_exif ] = array( 'created_timestamp' => strtotime( '2026-10-30 UTC' ) );
	if ( function_exists( 'exif_read_data' ) ) {
		$original_exif = gnf_extract_evidence_original_date( $temp_exif, 'imagen' );
		check_evidence_original_date(
			'2025-03-01' === $original_exif['date'],
			'EXIF DateTimeOriginal prevalece sobre la fecha digitalizada de WordPress'
		);
	}
	@unlink( $temp_exif );

	check_evidence_original_date(
		'2025-01-02' === gnf_extract_labeled_embedded_date(
			'<rdf:Description xmp:CreateDate="2026-10-30T10:00:00" exif:DateTimeOriginal="2025-01-02T10:00:00"/>'
		),
		'XMP DateTimeOriginal prevalece sobre CreateDate'
	);
	check_evidence_original_date(
		'' === gnf_extract_labeled_embedded_date( "DateTime\0 2026:10:30 10:00:00\0" ),
		'la etiqueta generica DateTime de modificacion no es fecha original'
	);

	$unknown = gnf_apply_evidence_original_date( array( 'tipo' => 'archivo' ), 2026 );
	check_evidence_original_date(
		array_key_exists( 'original_date', $unknown ) && null === $unknown['original_date'],
	'un archivo sin metadatos declara explicitamente fecha no disponible'
	);
}

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
