<?php
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require ABSPATH . 'includes/report-pdf.php';
$tests = $fails = 0;
function check_polish( $ok, $label ) { global $tests, $fails; $tests++; $fails += $ok ? 0 : 1; echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $label . "\n"; }
$report = array( 'year' => 2026, 'center' => array( 'nombre' => 'Escuela Prueba', 'correo_institucional' => 'centro@example.org', 'contact_emails' => array( 'centro@example.org', 'otro@example.org', 'otro@example.org' ),
    'coordinador_nombre' => 'Ana Persona', 'coordinador_cargo' => 'Docente', 'coordinador_telefono' => '88888888', 'matricula_docente_nombre' => 'Ana Persona', 'matricula_docente_cargo' => 'Docente', 'matricula_docente_telefono' => '88888888',
    'matriculaFields' => array( array( 'key' => 'centro_correo_institucional', 'label' => 'Correo confirmado', 'value' => 'centro@example.org' ), array( 'key' => 'dato_especial', 'label' => 'Dato adicional', 'value' => 'Se conserva' ) ) ),
    'retos' => array( array( 'titulo' => 'Eco Lonchera', 'evidencias' => array( array( 'questionLabel' => 'Fotografía de meriendas saludables', 'nombre' => 'merienda.jpeg', 'estado' => 'en_pausa', 'supervisor_comment' => 'Adjuntar el cierre de la actividad.' ) ) ) ) );
$html = gnf_render_center_report_html( $report );
check_polish( 1 === substr_count( $html, 'centro@example.org' ), 'Institutional email is printed only once' );
check_polish( 1 === substr_count( $html, 'otro@example.org' ), 'Contact emails are deduplicated' );
check_polish( 1 === substr_count( $html, 'Ana Persona' ), 'Identical coordinator and registrant share one contact block' );
check_polish( false !== strpos( $html, 'Se conserva' ), 'Unique enrollment answers are preserved' );
check_polish( false !== strpos( $html, 'data:image/png;base64,' ) && false !== strpos( $html, 'MEP, Bandera Azul Ecológica y Guardianes' ), 'Official three-brand header is embedded locally' );
check_polish( false === strpos( $html, '.cover{page-break-after:always' ), 'Short cover no longer forces a large blank first page' );
check_polish( false === strpos( $html, '<div class="page-break"></div>' ), 'Challenges flow after the enrollment section without a forced blank page' );
check_polish( false !== strpos( $html, 'margin:8px auto' ), 'Evidence photos are centered without stretching' );
check_polish( false !== strpos( $html, 'Fotografía de meriendas saludables' ) && false !== strpos( $html, 'En pausa' ) && false !== strpos( $html, 'Adjuntar el cierre' ), 'Evidence activity, pause state and review note remain visible' );
check_polish( function_exists( 'gnf_report_evidence_label' ), 'Evidence labels have a shared fallback resolver' );
if ( function_exists( 'gnf_report_evidence_label' ) ) {
    check_polish( 'Pregunta vigente' === gnf_report_evidence_label( array( 'field_id' => 10 ), array( 10 => 'Pregunta vigente' ), array( 10 => array( 'label' => 'Etiqueta anterior' ) ) ), 'Current question label is preferred' );
    check_polish( 'Etiqueta anterior' === gnf_report_evidence_label( array( 'field_id' => 10 ), array(), array( 10 => array( 'label' => 'Etiqueta anterior' ) ) ), 'A retired question retains its configured description' );
    check_polish( 'Descripción guardada' === gnf_report_evidence_label( array( 'field_id' => 10, 'questionLabel' => 'Descripción guardada' ), array(), array() ), 'Persisted evidence label is available after question removal' );
    check_polish( false === stripos( gnf_report_evidence_label( array( 'field_id' => 10 ), array(), array() ), 'campo 10' ), 'An unknown legacy field is not presented as an internal field number' );
}
if ( getenv( 'GNF_REPORT_REVIEW_PATH' ) ) {
    $sample = $report;
    $sample['generatedAt'] = '2026-10-15 10:00:00';
    $sample['center'] = array_merge( $sample['center'], array( 'nombre' => 'Centro Educativo de Prueba', 'codigo_mep' => '0000', 'codigo_presupuestario' => '00000', 'region_name' => 'Heredia', 'circuito' => '01', 'provincia' => 'Heredia', 'canton' => 'Central', 'distrito' => 'Central', 'poblado' => 'Centro', 'direccion' => 'Dirección ficticia para verificación visual.', 'dependencia' => 'Pública', 'zona' => 'Urbana', 'nivel_educativo' => 'Primaria', 'jornada' => 'Diurna', 'tipo_centro' => 'Dirección I', 'tipologia' => 'Tipo IV', 'telefono' => '0000-0000', 'total_estudiantes' => 72, 'estudiantes_hombres' => 36, 'estudiantes_mujeres' => 36, 'estudiantes_migrantes' => 3, 'matricula_estado' => 'aprobada' ) );
    $sample['award'] = array( 'projected' => array( 'score' => 125, 'rubricLabel' => 'Tipo IV/V' ), 'validated' => array( 'score' => 90, 'rubricLabel' => 'Tipo IV/V' ) );
    $sample['retos'][0] = array_merge( $sample['retos'][0], array( 'puntaje' => 15, 'puntajeMaximo' => 45, 'estado' => 'correccion', 'responses' => array( array( 'label' => 'Cantidad de estudiantes participantes', 'displayValue' => '36', 'hasValue' => true ) ) ) );
    $sample['retos'][0]['evidencias'][0]['imageDataUri'] = gnf_report_image_data_uri( ABSPATH . 'assets/logo-guardiana.png' );
    $sample['retos'][0]['evidencias'][0]['questionLabel'] .= ' (imagen de prueba, no es evidencia real)';
    $sample['retos'][0]['evidencias'][0]['puntos'] = 5;
    $previous = error_reporting(); error_reporting( $previous & ~E_DEPRECATED & ~E_USER_DEPRECATED );
    $generated = gnf_generate_center_report_pdf( $sample, getenv( 'GNF_REPORT_REVIEW_PATH' ) );
    error_reporting( $previous );
    check_polish( true === $generated, 'Review sample is a real PDF generated by the production template' );
}
echo "\n{$tests} checks, {$fails} failures\n"; exit( $fails ? 1 : 0 );
