<?php
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require ABSPATH . 'includes/evidence-review.php';
function absint( $value ) { return abs( (int) $value ); }
function gnf_normalize_year( $year ) { return (int) $year; }
function load_function( $file, $name ) {
    $source = file_get_contents( ABSPATH . $file );
    $start = strpos( $source, 'function ' . $name . '(' );
    if ( false === $start ) { throw new Exception( 'Missing function: ' . $name ); }
    eval( substr( $source, $start, strpos( $source, "\n}", $start ) + 2 - $start ) );
}
function check( $condition, $message ) { if ( ! $condition ) { throw new Exception( $message ); } }
load_function( 'includes/helpers.php', 'gnf_get_reto_entry_computed_status' );
load_function( 'includes/helpers.php', 'gnf_get_pending_review_counts' );
class ReviewDB {
    public $prefix = 'wp_';
    public $posts = 'wp_posts';
    public $term_relationships = 'wp_term_relationships';
    public $term_taxonomy = 'wp_term_taxonomy';
    public $terms = 'wp_terms';
    public $queries = array();
    public $rows = array();
    public function prepare( $query, ...$args ) {
        $args = count( $args ) === 1 && is_array( $args[0] ) ? $args[0] : $args;
        foreach ( $args as $arg ) { $query = preg_replace( '/%d/', (string) (int) $arg, $query, 1 ); }
        return $query;
    }
    public function get_results( $query ) {
        $this->queries[] = $query;
        if ( strpos( $query, 'SELECT e.*' ) === 0 ) {
            check( strpos( $query, "e.estado IN" ) === false, 'Report cannot require manual submission' );
            check( strpos( $query, 'e.anio = 2026' ) !== false, 'Annual report filter retained' );
            check( strpos( $query, 't.term_id = 7' ) !== false, 'Regional report filter retained' );
            return array_map( function( $row ) { return clone $row; }, array_slice( $this->rows, 0, 5 ) );
        }
        preg_match( '/id > (\d+)/', $query, $after );
        preg_match( '/anio = (\d+)/', $query, $year );
        preg_match( '/centro_id IN \(([^)]+)\)/', $query, $centers );
        return array_slice( array_values( array_filter( $this->rows, function( $row ) use ( $after, $year, $centers ) {
            return $row->id > (int) $after[1] && $row->anio === (int) $year[1]
                && ( ! $centers || in_array( $row->centro_id, array_map( 'intval', explode( ',', $centers[1] ) ), true ) );
        } ) ), 0, 200 );
    }
}
$wpdb = new ReviewDB();
$files = array(
    array( array( 'field_id' => 12, 'puntos' => null, 'estado' => 'pendiente' ), array( 'field_id' => 13, 'estado' => 'pendiente' ) ),
    array( array( 'field_id' => 12, 'estado' => 'aprobada' ) ),
    array( array( 'field_id' => 12, 'estado' => 'rechazada' ) ),
    array( array( 'field_id' => 12, 'estado' => 'en_pausa' ) ),
    array( array( 'field_id' => 12, 'replaced' => true, 'estado' => 'pendiente' ), array( 'puntos' => null ) ),
);
for ( $i = 1; $i <= 205; $i++ ) {
    $wpdb->rows[] = (object) array( 'id' => $i, 'anio' => 2026, 'centro_id' => 10, 'reto_id' => 20,
        'estado' => 'en_progreso', 'updated_at' => '2026-10-20 12:00:00', 'evidencias' => json_encode( $files[ ( $i - 1 ) % 5 ] ) );
}
$wpdb->rows[] = (object) array( 'id' => 206, 'anio' => 2025, 'centro_id' => 10, 'reto_id' => 20, 'updated_at' => '2025-10-20', 'evidencias' => json_encode( $files[0] ) );
$wpdb->rows[] = (object) array( 'id' => 207, 'anio' => 2026, 'centro_id' => 11, 'reto_id' => 21, 'updated_at' => '2026-09-20', 'evidencias' => json_encode( $files[0] ) );
$counts = gnf_get_pending_review_counts( 2026, array( 10 ) );
check( 41 === $counts['total'], 'Count challenges, not files; include autosaved unscored activities, exclude reviewed/replaced/annexes' );
check( array( 10 => 41 ) === $counts['centros'] && array( 20 => 41 ) === $counts['retos'], 'Scoped aggregation by center and challenge' );
check( array( 10 => 41 ) === $counts['meses'], 'Monthly aggregation' );
check( 2 === count( $wpdb->queries ), 'Bounded batches include the second page' );
check( 42 === gnf_get_pending_review_counts( 2026 )['total'], 'Global annual aggregation excludes the prior year' );
$queries = count( $wpdb->queries );
check( 0 === gnf_get_pending_review_counts( 2026, array() )['total'], 'Empty authorized scope does not become global' );
check( $queries === count( $wpdb->queries ), 'No query for an empty scope' );
load_function( 'includes/reports.php', 'gnf_get_report_data' );
$wpdb->rows[0]->puntaje = 25;
$report = gnf_get_report_data( 7, 2026 );
check( 5 === count( $report ), 'Autosaved records and unreviewed responses remain in the report' );
check( array( 'Por revisar', 'Evidencias aprobadas', 'Requiere correcciones', 'En pausa', 'en_progreso' ) === array_column( $report, 'estado' ), 'Report reflects evidence decisions without a final send' );
check( 25 === $report[0]->puntaje && 'en_progreso' === $wpdb->rows[0]->estado, 'Reporting does not mutate scores or persisted entry states' );
$wizard = file_get_contents( ABSPATH . 'includes/wizard.php' );
$template = file_get_contents( ABSPATH . 'templates/wizard.php' );
check( false === strpos( $wizard, "'id'          => 'enviar'" ), 'Legacy wizard has no final send step' );
check( false === strpos( $template, 'gnf-wizard-enviar-participacion' ) && false === strpos( $template, 'gnf-wizard-finalizar-reto' ), 'Legacy template does not require separate completion or submission' );
$dashboard = file_get_contents( ABSPATH . 'templates/docente-dashboard.php' );
check( false === strpos( $dashboard, 'gnf-docente-enviar-participacion' ), 'Legacy teacher dashboard cannot reintroduce annual submission' );
echo "Autosaved review counts checks passed\n";
