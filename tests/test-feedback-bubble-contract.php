<?php
// Contrato estatico para la burbuja de retroalimentacion (iframe same-domain).

$root       = __DIR__ . '/..';
$feedback   = @file_get_contents( $root . '/includes/feedback.php' ) ?: '';
$acf        = file_get_contents( $root . '/includes/acf-fields.php' );
$loader     = file_get_contents( $root . '/includes/react-loader.php' );
$docente    = file_get_contents( $root . '/app/src/panels/docente/DocentePanel.tsx' );
$supervisor = file_get_contents( $root . '/app/src/panels/supervisor/SupervisorPanel.tsx' );
$component  = @file_get_contents( $root . '/app/src/components/domain/FeedbackBubble.tsx' ) ?: '';

$tests = 0;
$fails = 0;

function check_feedback_bubble_contract( $condition, $message ) {
	global $tests, $fails;
	$tests++;
	if ( $condition ) {
		echo "  ok: {$message}\n";
	} else {
		$fails++;
		echo "  FAIL: {$message}\n";
	}
}

check_feedback_bubble_contract(
	strpos( $acf, 'feedback_page_url' ) !== false,
	'la URL de la pagina es configurable'
);
check_feedback_bubble_contract(
	strpos( $feedback, 'GNF_FEEDBACK_DEFAULT_URL' ) !== false
		&& strpos( $feedback, 'califica-el-pilotaje-de-innovaciones-pbae' ) !== false,
	'existe una URL por defecto cuando la opcion esta vacia'
);
check_feedback_bubble_contract(
	strpos( $feedback, "if ( in_array( 'comite_bae', \$roles, true ) || user_can( \$user, 'manage_options' ) )" ) !== false,
	'comite y administradores quedan excluidos aun con roles multiples'
);
check_feedback_bubble_contract(
	strpos( $loader, 'gnf_feedback_user_is_allowed' ) !== false
		&& strpos( $loader, 'gnf_get_feedback_page_url' ) !== false
		&& strpos( $loader, 'feedbackEnabled' ) !== false
		&& strpos( $loader, 'feedbackUrl' ) !== false,
	'el loader limita roles y expone la URL en init data'
);
check_feedback_bubble_contract(
	strpos( $component, '<iframe' ) !== false
		&& strpos( $component, 'onLoad={handleFrameLoad}' ) !== false,
	'el componente muestra la pagina en iframe'
);
check_feedback_bubble_contract(
	strpos( $component, 'ResizeObserver' ) !== false
		&& strpos( $component, 'body.scrollHeight' ) !== false,
	'el iframe ajusta su altura al contenido same-domain'
);
check_feedback_bubble_contract(
	strpos( $component, "'.wpforms-confirmation-container-full, .wpforms-confirmation-container'" ) !== false
		&& strpos( $component, 'MutationObserver' ) !== false,
	'la confirmacion WPForms dentro del iframe dispara la pantalla de exito'
);
check_feedback_bubble_contract(
	strpos( $component, "if (state !== 'success') return;" ) !== false
		&& strpos( $component, "window.setTimeout(() => setState('closed'), 2500)" ) !== false,
	'el cierre automatico pertenece al estado de exito'
);
check_feedback_bubble_contract(
	strpos( $component, 'Math.round(window.innerHeight * 0.7)' ) !== false,
	'un override cross-origin cae en altura fija por viewport'
);
check_feedback_bubble_contract(
	strpos( $component, 'if (!enabled || !url) return null;' ) !== false,
	'sin URL o deshabilitado no se monta la burbuja'
);
check_feedback_bubble_contract(
	strpos( $docente, '<FeedbackBubble' ) !== false
		&& strpos( $docente, 'initData.feedbackUrl' ) !== false
		&& strpos( $supervisor, '<FeedbackBubble' ) !== false
		&& strpos( $supervisor, 'initData.feedbackUrl' ) !== false,
	'docente y supervisor montan el componente con la URL inyectada'
);
check_feedback_bubble_contract(
	! file_exists( $root . '/app/src/api/feedback.ts' ),
	'el modulo REST de feedback fue retirado'
);

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
