<?php
define( 'WP_CLI', true ); define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require ABSPATH . 'includes/evidence-review.php';
class WP_CLI { static function log( $message ) { $GLOBALS['audit_output'] = $message; } static function error( $message ) { throw new RuntimeException( $message ); } }
function gnf_get_reto_field_points() { return array( 1 => array( 'puntos' => 5 ) ); }
function gnf_get_reto_form_id_for_year() { return 77468; }
function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
class AuditReviewDB {
    public $prefix = 'wp_', $last_error = '', $calls = 0;
    function prepare( $sql, ...$args ) { return array( $sql, $args ); }
    function get_results( $query ) {
        $this->calls++;
        if ( $this->calls > 1 ) { return array(); }
        return array(
            (object) array( 'id' => 1, 'centro_id' => 10, 'reto_id' => 20, 'evidencias' => json_encode( array( array( 'field_id' => 1, 'puntos' => null ), array( 'field_id' => 99, 'puntos' => null ), array( 'field_id' => 0, 'puntos' => null ), array( 'field_id' => 99, 'puntos' => null, 'replaced' => true ) ) ) ),
            (object) array( 'id' => 2, 'centro_id' => 11, 'reto_id' => 20, 'evidencias' => '[{"field_id":99,"puntos":null}]' ),
        );
    }
    function update() { throw new RuntimeException( 'Read-only audit attempted a write' ); }
}
$wpdb = new AuditReviewDB(); $args = array( 2026 );
$path = ABSPATH . 'tools/audit-evidence-review.php';
if ( ! file_exists( $path ) ) { echo "FAIL: Read-only global evidence audit exists\n"; exit( 1 ); }
require $path;
$report = json_decode( $audit_output, true );
$tests = $fails = 0;
function check_audit_review( $ok, $label ) { global $tests, $fails; $tests++; $fails += $ok ? 0 : 1; echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $label . "\n"; }
check_audit_review( 2 === $report['entradas'], 'Global audit includes every processed entry' );
check_audit_review( 4 === $report['evidencias_activas'] && 1 === $report['anexos_informativos'], 'Replaced and informational files are distinguished' );
check_audit_review( 2 === $report['revisables_sin_puntaje'] && 2 === $report['centros_afectados'], 'Audit counts affected schools without exposing their identities' );
check_audit_review( array( 99 ) === $report['retos'][0]['campos_sin_puntaje'], 'Configured points and unconfigured field IDs are differentiated' );
check_audit_review( 2 === $wpdb->calls, 'Audit uses bounded batches and a final empty read' );
echo "\n{$tests} checks, {$fails} failures\n"; exit( $fails ? 1 : 0 );
