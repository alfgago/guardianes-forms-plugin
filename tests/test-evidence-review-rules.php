<?php
// Test plano para reglas puras de revision de evidencias.

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

require_once __DIR__ . '/../includes/evidence-review.php';

$tests = 0;
$fails = 0;

function check_evidence_review_rule( $cond, $msg ) {
	global $tests, $fails;
	$tests++;
	if ( $cond ) {
		echo "  ok: {$msg}\n";
	} else {
		$fails++;
		echo "  FAIL: {$msg}\n";
	}
}

$reasons = gnf_get_evidence_rejection_reasons();
check_evidence_review_rule( count( $reasons ) === 4, 'hay exactamente cuatro motivos de observacion' );
check_evidence_review_rule( $reasons['no_corresponde'] === 'Evidencia no corresponde.', 'incluye motivo: evidencia no corresponde' );
check_evidence_review_rule( $reasons['otra_accion_reto'] === 'Evidencia de otra acción o reto.', 'incluye motivo: otra accion o reto' );
check_evidence_review_rule( $reasons['ya_valorada'] === 'Evidencia ya valorada.', 'incluye motivo: evidencia ya valorada' );
check_evidence_review_rule( $reasons['accion_no_amigable'] === 'Acción debe ser amigable.', 'incluye motivo: accion debe ser amigable' );
check_evidence_review_rule( gnf_is_valid_evidence_rejection_reason( 'ya_valorada' ), 'valida una clave conocida' );
check_evidence_review_rule( ! gnf_is_valid_evidence_rejection_reason( 'otro' ), 'rechaza claves libres' );

check_evidence_review_rule(
	! gnf_evidence_has_verifiable_date_issue(
		array(
			'tipo'                     => 'pdf',
			'requires_year_validation' => true,
			'warning'                  => 'No se pudo validar la fecha.',
		)
	),
	'PDF legado con requires_year_validation no se auto-rechaza por fecha'
);

check_evidence_review_rule(
	! gnf_evidence_has_verifiable_date_issue(
		array(
			'tipo'                     => 'imagen',
			'nombre'                   => 'captura.png',
			'requires_year_validation' => true,
			'warning'                  => 'No se pudo validar la fecha.',
		)
	),
	'captura sin fecha EXIF verificable no se auto-rechaza'
);

check_evidence_review_rule(
	gnf_evidence_has_verifiable_date_issue(
		array(
			'tipo'                     => 'imagen',
			'requires_year_validation' => true,
			'photo_date'               => '2025-03-01',
		)
	),
	'imagen con fecha EXIF persistida conserva rechazo automatico'
);

check_evidence_review_rule(
	gnf_evidence_has_verifiable_date_issue(
		array(
			'tipo'                     => 'imagen',
			'requires_year_validation' => true,
			'exifYear'                 => 2025,
		)
	),
	'imagen con anio EXIF legado conserva rechazo automatico'
);

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );

