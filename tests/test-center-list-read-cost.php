<?php
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require ABSPATH . 'includes/evidence-review.php';
function absint( $v ) { return abs( (int) $v ); }
function gnf_normalize_year( $v ) { return (int) $v; }
function current_user_can() { return false; }
function gnf_rest_build_centro_base( $id ) { return array( 'id' => $id ); }
function gnf_rest_build_centro_annual( $id, $year ) { return array( 'centroId' => $id, 'year' => $year, 'retosSeleccionados' => array( 1 ) ); }
function load_cost_function( $file, $name ) {
    $source = file_get_contents( ABSPATH . $file ); $start = strpos( $source, 'function ' . $name . '(' );
    if ( false === $start ) { throw new Exception( 'Missing function ' . $name ); }
    eval( substr( $source, $start, strpos( $source, "\n}", $start ) + 2 - $start ) );
}
class CostDB {
    public $prefix = 'wp_'; public $queries = 0;
    function prepare( $sql, ...$args ) { return $sql; }
    function get_results( $sql ) {
        $this->queries++;
        if ( strpos( $sql, 'GROUP BY' ) !== false ) { return array( (object) array( 'centro_id' => 10, 'estado' => 'en_progreso', 'cnt' => 1 ), (object) array( 'centro_id' => 20, 'estado' => 'aprobado', 'cnt' => 1 ) ); }
        return array(
            (object) array( 'id' => 1, 'centro_id' => 10, 'reto_id' => 1, 'updated_at' => '2026-10-27', 'evidencias' => json_encode( array( array( 'field_id' => 4, 'puntos' => null, 'estado' => 'en_pausa' ), array( 'field_id' => 5, 'estado' => 'pendiente' ) ) ) ),
            (object) array( 'id' => 2, 'centro_id' => 20, 'reto_id' => 1, 'updated_at' => '2026-10-27', 'evidencias' => json_encode( array( array( 'field_id' => 6, 'estado' => 'aprobada' ), array( 'field_id' => 7, 'estado' => 'rechazada', 'replaced' => true ) ) ) ),
        );
    }
}
$wpdb = new CostDB();
load_cost_function( 'includes/helpers.php', 'gnf_get_reto_entry_computed_status' );
load_cost_function( 'includes/helpers.php', 'gnf_get_pending_review_counts' );
load_cost_function( 'includes/rest-api.php', 'gnf_rest_get_entry_counts_by_centro' );
load_cost_function( 'includes/rest-api.php', 'gnf_rest_build_centro_with_stats' );
$counts = gnf_rest_get_entry_counts_by_centro( array( 10, 20, 30 ), 2026 );
$cost = $wpdb->queries;
$list = array_map( static function ( $id ) use ( $counts ) { return gnf_rest_build_centro_with_stats( $id, 2026, $counts ); }, array( 10, 20, 30 ) );
if ( 2 !== $cost || $wpdb->queries !== $cost ) { throw new Exception( 'List rows, including empty centers, must not issue per-center evidence reads' ); }
if ( 1 !== $list[0]['evPendientes'] || 1 !== $list[0]['evPausadas'] || 2 !== $list[0]['evTotal'] || 1 !== $list[1]['evAprobadas'] || 0 !== $list[2]['evTotal'] ) { throw new Exception( 'Batch counters must preserve all reviewable states and exclude replaced files' ); }
if ( 1 !== $list[0]['enviados'] || 0 !== $list[1]['enviados'] || $list[0]['canImpersonateDocente'] ) { throw new Exception( 'Pending challenge semantics and action permissions must remain unchanged' ); }
echo "Center list batch read checks passed\n";
