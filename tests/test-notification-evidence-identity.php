<?php
// Regresiones puras para que una notificacion no cambie de foto al retirar evidencias.

$root    = __DIR__ . '/..';
$support = @file_get_contents( $root . '/includes/evidence-notifications.php' ) ?: '';
$helpers = file_get_contents( $root . '/includes/helpers.php' );
$rest    = file_get_contents( $root . '/includes/rest-api.php' );

$tests = 0;
$fails = 0;

function check_notification_evidence_identity( $condition, $message ) {
	global $tests, $fails;
	$tests++;
	if ( $condition ) {
		echo "  ok: {$message}\n";
	} else {
		$fails++;
		echo "  FAIL: {$message}\n";
	}
}

check_notification_evidence_identity(
	file_exists( $root . '/includes/evidence-notifications.php' )
		&& strpos( $support, 'function gnf_get_evidence_notification_key' ) !== false
		&& strpos( $support, 'function gnf_notification_should_include_evidence' ) !== false
		&& strpos( $support, 'function gnf_filter_notification_evidences' ) !== false,
	'existe una identidad estable y un selector puro de evidencia'
);

check_notification_evidence_identity(
	strpos( $rest, 'gnf_get_evidence_notification_relation_type( $ev )' ) !== false
		&& strpos( $helpers, "0 === strpos( \$relation_type, 'reto_entry_evidence:' )" ) !== false,
	'la revision guarda y resuelve la identidad de evidencia en la relacion'
);

if ( file_exists( $root . '/includes/evidence-notifications.php' ) ) {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', $root . '/' );
	}
	require_once $root . '/includes/evidence-notifications.php';

	$removed = array(
		'field_id' => 10,
		'nombre'   => 'rechazada-a.jpg',
		'ruta'     => 'https://example.org/rechazada-a.jpg',
		'estado'   => 'rechazada',
	);
	$remaining = array(
		'field_id' => 20,
		'nombre'   => 'rechazada-b.jpg',
		'ruta'     => 'https://example.org/rechazada-b.jpg',
		'estado'   => 'rechazada',
	);
	$relation = gnf_get_evidence_notification_relation_type( $removed );
	$reviewed = array_merge(
		$removed,
		array(
			'estado'            => 'rechazada',
			'supervisor_comment' => 'Evidencia no corresponde.',
			'reviewed_by'        => 25,
		)
	);

	check_notification_evidence_identity(
		strlen( $relation ) <= 100 && $relation === gnf_get_evidence_notification_relation_type( $reviewed ),
		'la identidad cabe en la columna y no cambia con los datos de revision'
	);

	check_notification_evidence_identity(
		! gnf_notification_should_include_evidence( 'evidencia_rechazada', $relation, 'rechazada-a.jpg fue rechazada', $remaining ),
		'al retirar una rechazada no muestra otra evidencia rechazada'
	);
	check_notification_evidence_identity(
		gnf_notification_should_include_evidence( 'evidencia_rechazada', $relation, 'rechazada-a.jpg fue rechazada', $removed ),
		'la misma evidencia se reconoce aunque cambie de indice'
	);
	check_notification_evidence_identity(
		! gnf_notification_should_include_evidence( 'evidencia_rechazada', 'reto_entry', 'rechazada-a.jpg fue rechazada', $remaining ),
		'notificaciones antiguas no muestran fotos rechazadas que no menciona el mensaje'
	);

	$same_comment = array_merge(
		$remaining,
		array( 'supervisor_comment' => 'Evidencia no corresponde.' )
	);
	check_notification_evidence_identity(
		! gnf_notification_should_include_evidence(
			'evidencia_rechazada',
			'reto_entry',
			'rechazada-a.jpg fue rechazada. Comentario: Evidencia no corresponde.',
			$same_comment
		),
		'un comentario generico no hace que una notificacion antigua tome otra foto'
	);

	if ( function_exists( 'gnf_filter_notification_evidences' ) ) {
		$duplicate_a = array_merge( $removed, array( 'nombre' => 'duplicada.jpg', 'ruta' => 'https://example.org/a/duplicada.jpg' ) );
		$duplicate_b = array_merge( $removed, array( 'nombre' => 'duplicada.jpg', 'ruta' => 'https://example.org/b/duplicada.jpg' ) );
		$filtered    = gnf_filter_notification_evidences(
			'evidencia_rechazada',
			'reto_entry',
			'La evidencia "duplicada.jpg" fue rechazada.',
			array( $duplicate_a, $duplicate_b )
		);
		check_notification_evidence_identity(
			array() === $filtered,
			'un nombre historico ambiguo omite la foto en vez de escoger una al azar'
		);
	}
}

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
