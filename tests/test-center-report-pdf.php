<?php
// Contrato del reporte final PDF por centro educativo.

$root       = __DIR__ . '/..';
$module     = @file_get_contents( $root . '/includes/report-pdf.php' ) ?: '';
$bootstrap  = file_get_contents( $root . '/guardianes-formularios.php' );
$rest       = file_get_contents( $root . '/includes/rest-api.php' );
$composer   = file_get_contents( $root . '/composer.json' );
$docente    = file_get_contents( $root . '/app/src/panels/docente/pages/ResumenPage.tsx' );
$admin      = file_get_contents( $root . '/app/src/panels/admin/pages/CentroDetailPage.tsx' );
$supervisor = file_get_contents( $root . '/app/src/panels/supervisor/pages/CentroDetailPage.tsx' );

$tests = 0;
$fails = 0;

function check_center_report_pdf( $condition, $message ) {
	global $tests, $fails;
	$tests++;
	if ( $condition ) {
		echo "  ok: {$message}\n";
	} else {
		$fails++;
		echo "  FAIL: {$message}\n";
	}
}

check_center_report_pdf( false !== strpos( $bootstrap, "require_once 'includes/report-pdf.php';" ), 'bootstrap carga el generador PDF' );
check_center_report_pdf( false !== strpos( $composer, '"dompdf/dompdf": "3.1.6"' ), 'Dompdf queda fijado y empaquetado' );
check_center_report_pdf( false !== strpos( $module, 'function gnf_build_center_report_data' ), 'servicio construye el reporte desde datos reales' );
check_center_report_pdf( false !== strpos( $module, 'function gnf_render_center_report_html' ), 'plantilla HTML separada del motor PDF' );
check_center_report_pdf( false !== strpos( $module, 'function gnf_generate_center_report_pdf' ), 'generador produce el archivo PDF' );
check_center_report_pdf( false !== strpos( $module, 'function gnf_report_safe_url' ), 'enlaces de evidencia se validan antes de renderizar' );
check_center_report_pdf( false !== strpos( $module, 'admin_post_gnf_download_centro_report_pdf' ), 'descarga administrativa registrada' );
check_center_report_pdf( false !== strpos( $module, 'check_admin_referer' ) && false !== strpos( $module, 'gnf_user_can_download_center_report' ), 'descarga valida nonce y acceso al centro' );
check_center_report_pdf( false !== strpos( $rest, "'reportPdfUrl'" ), 'REST publica URL firmada del reporte' );
check_center_report_pdf( false !== strpos( $docente, 'Descargar reporte final PDF' ), 'panel docente ofrece el reporte' );
check_center_report_pdf( false !== strpos( $admin, 'Descargar reporte final PDF' ), 'detalle administrativo ofrece el reporte' );
check_center_report_pdf( false !== strpos( $supervisor, 'Descargar reporte final PDF' ), 'detalle supervisor ofrece el reporte' );
check_center_report_pdf( false !== strpos( $module, 'function gnf_center_report_status_from_entries' ), 'reporte determina si es borrador o final' );
check_center_report_pdf( false !== strpos( $module, "gnf_feature_is_enabled_for_center( 'reports'" ), 'descarga respeta el lanzamiento por centro' );

if ( file_exists( $root . '/includes/report-pdf.php' ) ) {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', $root . '/' );
	}
	require_once $root . '/includes/report-pdf.php';
	check_center_report_pdf( '' === gnf_report_safe_url( 'javascript:alert(1)' ), 'rechaza enlaces con protocolos inseguros' );
	check_center_report_pdf( 'draft' === gnf_center_report_status_from_entries( array( 1, 2 ), array( 1 => 'aprobado', 2 => 'enviado' ) ), 'un reto pendiente produce borrador' );
	check_center_report_pdf( 'final' === gnf_center_report_status_from_entries( array( 1, 2 ), array( 1 => 'aprobado', 2 => 'aprobado' ) ), 'todos los retos aprobados producen reporte final' );

	if ( function_exists( 'gnf_render_center_report_html' ) ) {
		$sample = array(
			'year'        => 2026,
			'generatedAt' => '2026-09-04 09:30:00',
			'status'      => 'draft',
				'center'      => array(
					'nombre'                    => 'Escuela Las Brisas <script>alert(1)</script>',
					'codigo_mep'                => '1234',
					'region_name'               => 'Nicoya',
					'circuito'                  => '01',
					'tipologia'                 => 'Tipo IV',
					'tipo_centro'               => 'Direccion I',
					'total_estudiantes'         => 72,
					'estudiantes_hombres'       => 34,
					'estudiantes_mujeres'       => 38,
					'estudiantes_migrantes'     => 3,
					'coordinador_nombre'        => 'Ana Docente',
					'coordinador_telefono'      => '8888-8888',
					'contact_emails'            => array( 'centro@example.org' ),
				),
				'award'       => array(
					'projected' => array( 'stars' => 3, 'score' => 125, 'rubricLabel' => 'Tipo IV y V' ),
					'validated' => array( 'stars' => 2, 'score' => 90, 'rubricLabel' => 'Tipo IV y V' ),
				),
				'retos'       => array(
					array(
						'titulo'       => 'Reto Agua',
						'estado'       => 'correccion',
						'puntaje'      => 30,
						'puntajeMaximo'=> 60,
						'responses'     => array(
							array( 'label' => 'Cantidad captada', 'displayValue' => '150 litros', 'hasValue' => true, 'puntos' => 10 ),
						),
						'evidencias'   => array(
							array(
								'questionLabel'      => 'Registro de captacion',
								'nombre'             => 'evidencia-agua.pdf',
								'url'                => 'https://example.org/evidencia-agua.pdf',
								'original_date'      => '2025-08-12',
								'estado'             => 'rechazada',
								'review_reason'       => 'evidencia_otro_reto',
								'supervisor_comment'  => 'Corresponde a otra accion.',
								'puntos'              => 10,
							),
						),
					),
				),
			);
		$html = gnf_render_center_report_html( $sample );

		check_center_report_pdf( false !== strpos( $html, 'Escuela Las Brisas' ) && false === strpos( $html, '<script>' ), 'escapa datos del centro' );
		check_center_report_pdf( false !== strpos( $html, 'Información del centro educativo' ) && false !== strpos( $html, 'Matrícula y contacto' ), 'incluye informacion completa y matricula' );
		check_center_report_pdf( false !== strpos( $html, 'Galardón calculado' ) && false !== strpos( $html, 'Puntaje validado' ), 'incluye resultado dinamico de galardon' );
		check_center_report_pdf( false !== strpos( $html, 'Reto Agua' ) && false !== strpos( $html, '30 / 60 puntos' ), 'incluye puntaje por reto' );
		check_center_report_pdf( false !== strpos( $html, 'Cantidad captada' ) && false !== strpos( $html, '150 litros' ), 'incluye respuestas registradas' );
		check_center_report_pdf( false !== strpos( $html, 'evidencia-agua.pdf' ) && false !== strpos( $html, '12/08/2025' ), 'incluye evidencia y fecha original' );
		check_center_report_pdf( false !== strpos( $html, 'Evidencia de otra acción o reto' ) && false !== strpos( $html, 'Corresponde a otra accion.' ), 'incluye tipificacion y comentario de revision' );
		check_center_report_pdf( false === stripos( $html, 'Meta de estrellas' ), 'omite Meta de estrellas' );
		check_center_report_pdf( false !== strpos( $html, 'Borrador de participación' ) && false !== strpos( $html, 'Documento preliminar' ), 'un reporte incompleto se identifica como borrador' );

		$temp                    = tempnam( sys_get_temp_dir(), 'gnf-report-test-' );
		$previous_error_reporting = error_reporting();
		error_reporting( $previous_error_reporting & ~E_DEPRECATED & ~E_USER_DEPRECATED );
		$result = gnf_generate_center_report_pdf( $sample, $temp );
		error_reporting( $previous_error_reporting );
		$signature = file_exists( $temp ) ? file_get_contents( $temp, false, null, 0, 4 ) : '';
		@unlink( $temp );
		check_center_report_pdf( true === $result && '%PDF' === $signature, 'Dompdf genera un archivo PDF real' );
	}
}

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
