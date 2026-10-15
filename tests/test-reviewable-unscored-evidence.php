<?php
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require ABSPATH . 'includes/evidence-review.php';
$tests = $fails = 0;
function check_reviewable( $ok, $label ) { global $tests, $fails; $tests++; $fails += $ok ? 0 : 1; echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $label . "\n"; }
check_reviewable( function_exists( 'gnf_evidence_is_reviewable' ), 'Shared reviewability rule exists independently of points' );
if ( ! function_exists( 'gnf_evidence_is_reviewable' ) ) { exit( 1 ); }
check_reviewable( gnf_evidence_is_reviewable( array( 'field_id' => 10, 'puntos' => null ) ), 'An activity upload with missing points can be reviewed' );
check_reviewable( gnf_evidence_is_reviewable( array( 'field_id' => 10, 'puntos' => 0 ) ), 'Explicit zero-point activity can be reviewed' );
check_reviewable( gnf_evidence_is_reviewable( array( 'puntos' => 5 ) ), 'Legacy scored evidence remains reviewable' );
check_reviewable( ! gnf_evidence_is_reviewable( array( 'puntos' => null ) ), 'Unassigned informational annex remains informational' );
check_reviewable( ! gnf_evidence_is_reviewable( array( 'field_id' => 10, 'puntos' => 5, 'replaced' => true ) ), 'Replaced files remain excluded' );
$source = file_get_contents( ABSPATH . 'includes/helpers.php' );
foreach ( array( 'gnf_enrich_evidencias', 'gnf_get_reto_entry_computed_status' ) as $fn ) {
    $start = strpos( $source, 'function ' . $fn . '(' );
    eval( substr( $source, $start, strpos( $source, "\n}", $start ) + 2 - $start ) );
}
function gnf_get_reto_field_points() { return array(); }
function gnf_get_reviewer_display_name() { return 'Revision'; }
$items = gnf_enrich_evidencias( array( array( 'field_id' => 10, 'puntos' => null ) ), 1, 2026 );
check_reviewable( $items[0]['puntos'] === null && $items[0]['reviewable'] && 'pendiente' === $items[0]['estado'], 'Enrichment exposes reviewability without inventing points' );
$status = gnf_get_reto_entry_computed_status( (object) array( 'evidencias' => json_encode( $items ) ) );
check_reviewable( 1 === $status['total'] && 1 === $status['pendientes'], 'Unscored activity evidence is counted as awaiting review' );
$items[0]['estado'] = 'aprobada';
$status = gnf_get_reto_entry_computed_status( (object) array( 'evidencias' => json_encode( $items ) ) );
check_reviewable( 1 === $status['aprobadas'] && 'completo' === $status['status'], 'Approval completes an unscored activity without awarding points' );
echo "\n{$tests} checks, {$fails} failures\n"; exit( $fails ? 1 : 0 );
