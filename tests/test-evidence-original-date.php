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
		array( 'date' => '2025-04-05', 'source' => 'browser_file_metadata' )
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
		! empty( $browser_image['requires_year_validation'] )
			&& ! gnf_evidence_has_verifiable_date_issue( $browser_image ),
		'fecha de navegador alerta pero no auto-rechaza una imagen'
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

	$unknown = gnf_apply_evidence_original_date( array( 'tipo' => 'archivo' ), 2026 );
	check_evidence_original_date(
		array_key_exists( 'original_date', $unknown ) && null === $unknown['original_date'],
	'un archivo sin metadatos declara explicitamente fecha no disponible'
	);
}

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
