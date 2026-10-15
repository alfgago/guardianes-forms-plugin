<?php
define( 'ABSPATH', __DIR__ . '/../' );
require_once __DIR__ . '/../includes/docente-summary.php';
function absint( $value ) { return abs( (int) $value ); }
function gnf_get_reto_form_id_for_year() { return 1; }
function gnf_get_wpforms_form_definition() { return array( 'fields' => $GLOBALS['fields'] ); }
function gnf_get_reto_field_points() { return array( 5 => array( 'puntos' => 10 ) ); }
function gnf_get_reto_canonical_slug( $title ) { return $title; }
function get_the_title() { return $GLOBALS['slug']; }
function gnf_is_wpforms_layout_field( $type ) { return $type === 'divider'; }
function gnf_get_wpforms_display_value( $field, $value ) { return (string) $value; }
function gnf_reto_entry_value_has_content( $value ) { return $value !== null && $value !== ''; }
$source = file_get_contents( __DIR__ . '/../includes/helpers.php' );
$start = strpos( $source, 'function gnf_build_reto_form_responses(' );
eval( substr( $source, $start, strpos( $source, "\n}", $start ) + 2 - $start ) );
$fields = array(
    array( 'id' => 5, 'type' => 'file-upload', 'label' => 'REQUISITO: Registro de consumo' ),
    array( 'id' => 6, 'type' => 'file-upload', 'label' => 'Actividad complementaria' ),
    array( 'id' => 7, 'type' => 'text', 'label' => 'Descripcion del requisito' ),
);
$evidence = array( array( 'field_id' => 5, 'nombre' => 'actual.pdf' ), array( 'field_id' => 5, 'nombre' => 'anterior.pdf', 'replaced' => true ) );
$checks = 0;
function check_review_response( $condition, $label ) {
    if ( ! $condition ) { fwrite( STDERR, "FAIL: $label\n" ); exit( 1 ); }
    $GLOBALS['checks']++; echo "ok: $label\n";
}
foreach ( array( 'agua', 'electricidad', 'residuos' ) as $slug ) {
    $result = gnf_build_reto_form_responses( 1, 2026, array( 7 => 'Respuesta existente' ), $evidence );
    check_review_response( $result[0]['isRequiredEvidence'], "$slug highlights the same requirement as the teacher panel" );
    check_review_response( ! $result[1]['isRequiredEvidence'] && ! $result[2]['isRequiredEvidence'], "$slug excludes optional uploads and text questions" );
}
check_review_response( $result[2]['displayValue'] === 'Respuesta existente', 'Existing answers are preserved' );
check_review_response( $result[0]['puntos'] === 10 && count( $result[0]['evidencias'] ) === 1, 'Scores and active evidence remain unchanged' );
$slug = 'huerta';
check_review_response( ! gnf_build_reto_form_responses( 1, 2026 )[0]['isRequiredEvidence'], 'Other retos are not incorrectly marked as base requirements' );
echo "$checks review response checks passed\n";
