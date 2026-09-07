<?php
// Test estatico para el contrato de revision de evidencias en React.

$root               = __DIR__ . '/..';
$entry_review       = file_get_contents( $root . '/app/src/panels/supervisor/components/EntryReviewCard.tsx' );
$notifications_page = file_get_contents( $root . '/app/src/panels/supervisor/pages/NotificacionesPage.tsx' );
$wpforms_embed      = file_get_contents( $root . '/app/src/components/domain/WpFormsEmbed.tsx' );
$supervisor_api     = file_get_contents( $root . '/app/src/api/supervisor.ts' );
$evidence_review    = file_get_contents( $root . '/app/src/utils/evidenceReview.ts' );
$types_reto         = file_get_contents( $root . '/app/src/types/reto.ts' );
$types_notification = file_get_contents( $root . '/app/src/types/notification.ts' );
$rest_api           = file_get_contents( $root . '/includes/rest-api.php' );

$tests = 0;
$fails = 0;

function check_evidence_ui_contract( $cond, $msg ) {
	global $tests, $fails;
	$tests++;
	if ( $cond ) {
		echo "  ok: {$msg}\n";
	} else {
		$fails++;
		echo "  FAIL: {$msg}\n";
	}
}

$reason_labels = array(
	'Evidencia no corresponde.',
	'Evidencia de otra acción o reto.',
	'Evidencia ya valorada.',
	'Acción debe ser amigable.',
);

foreach ( $reason_labels as $label ) {
	check_evidence_ui_contract(
		strpos( $evidence_review, $label ) !== false,
		"motivo canonico definido: {$label}"
	);
}

check_evidence_ui_contract(
	strpos( $entry_review, 'REJECTION_REASON_OPTIONS' ) !== false
		&& strpos( $notifications_page, 'REJECTION_REASON_OPTIONS' ) !== false,
	'ambas superficies usan las opciones canonicas compartidas'
);

check_evidence_ui_contract(
	strpos( $entry_review, "comment: reviewComment.trim()" ) !== false,
	'EntryReviewCard manda comentario opcional al aprobar'
);

check_evidence_ui_contract(
	strpos( $notifications_page, "comment: reviewComment.trim()" ) !== false,
	'Notificaciones manda comentario opcional al aprobar'
);

check_evidence_ui_contract(
	strpos( $entry_review, 'Comentar aprobación' ) === false
		&& strpos( $notifications_page, 'Comentar aprobación' ) === false
		&& strpos( $entry_review, 'Editar comentario' ) !== false
		&& strpos( $notifications_page, 'Editar comentario' ) !== false,
	'el boton unificado es Editar comentario y reemplaza Comentar aprobación'
);

check_evidence_ui_contract(
	strpos( $entry_review, "setReviewMode('aprobar')" ) !== false
		&& strpos( $entry_review, "setReviewMode('rechazar')" ) !== false
		&& strpos( $notifications_page, "setReviewMode('aprobar')" ) !== false
		&& strpos( $notifications_page, "setReviewMode('rechazar')" ) !== false,
	'aprobar y rechazar abren formulario de comentario antes de guardar'
);

check_evidence_ui_contract(
	strpos( $entry_review, 'disabled={isApproved || mutation.isPending}' ) === false
		&& strpos( $notifications_page, 'disabled={isApproved || mutation.isPending}' ) === false,
	'comentario no queda bloqueado despues de aprobar'
);

check_evidence_ui_contract(
	strpos( $entry_review, "setReviewComment(evidence.supervisor_comment ?? '')" ) !== false
		&& strpos( $notifications_page, "setReviewComment(evidence.supervisorComment ?? '')" ) !== false,
	'Editar comentario abre el comentario anterior pre-escrito'
);

check_evidence_ui_contract(
	strpos( $entry_review, 'const canApprove = !isApproved;' ) !== false
		&& strpos( $entry_review, 'const canReject = !isRejected;' ) !== false
		&& strpos( $notifications_page, 'const canApprove = !isApproved;' ) !== false
		&& strpos( $notifications_page, 'const canReject = !isRejected;' ) !== false
		&& strpos( $entry_review, '!isReviewed ? (' ) === false
		&& strpos( $notifications_page, '!isReviewed ? (' ) === false,
	'evidencias ya revisadas pueden cambiarse entre aprobada y rechazada'
);

check_evidence_ui_contract(
	strpos( $entry_review, "reviewReason: action === 'rechazar' ? rejectReason : undefined" ) !== false
		&& strpos( $notifications_page, "reviewReason: action === 'rechazar' ? rejectReason : undefined" ) !== false
		&& strpos( $supervisor_api, 'reviewReason?: string' ) !== false,
	'payload de rechazo incluye reviewReason tipificado'
);

check_evidence_ui_contract(
	strpos( $wpforms_embed, "requires_year_validation ? 'rechazada'" ) === false
		&& strpos( $entry_review, "requires_year_validation ? 'rechazada'" ) === false,
	'frontend no convierte requires_year_validation legado en rechazo por si solo'
);

check_evidence_ui_contract(
	strpos( $wpforms_embed, 'getRejectionReasonLabel' ) !== false
		&& strpos( $wpforms_embed, "label: getRejectionReasonLabel(file.review_reason) || 'Rechazada'" ) !== false
		&& strpos( $wpforms_embed, "label: 'Observada'" ) === false,
	'docente ve la causa tipificada o Rechazada, no Observada'
);

check_evidence_ui_contract(
	strpos( $rest_api, 'fue rechazada por' ) !== false
		&& strpos( $rest_api, 'fue observada por' ) === false,
	'notificacion docente usa rechazada en vez de observada'
);

check_evidence_ui_contract(
	strpos( $types_reto, 'review_reason?: string | null;' ) !== false
		&& strpos( $types_notification, 'reviewReason?: string | null;' ) !== false,
	'tipos exponen review_reason/reviewReason'
);

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
