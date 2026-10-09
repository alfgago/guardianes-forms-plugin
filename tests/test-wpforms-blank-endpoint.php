<?php
class WP_REST_Request { function get_param( $key ) { return 1; } }
class WP_Error { function __construct( ...$args ) {} }
function gnf_rest_get_active_panel_year() { return 2026; }
function get_current_user_id() { return $GLOBALS['user']; }
function gnf_get_centro_for_docente( $id ) { return $id * 10; }
function gnf_get_reto_form_id_for_year( ...$args ) { return 101; }
function gnf_rest_get_docente_entry_row( $center, ...$args ) { return (object) array( 'center' => $center ); }
function gnf_rest_build_saved_form_state( $entry, $year ) { return array( 'entry' => array( 'center' => $entry->center, 'evidencias' => array( array( 'nombre' => 'foto.jpg' ) ) ), 'savedValues' => array( 2 => (string) $entry->center ), 'savedAt' => '2026-10-08' ); }
function gnf_get_reto_field_points( ...$args ) { return array( 2 => array( 'puntos' => 5, 'tipo' => 'file-upload' ) ); }
function do_shortcode( $text ) { $GLOBALS['renders']++; return $GLOBALS['html']; }
function gnf_get_wpforms_form_definition( $id ) { return $GLOBALS['definition']; }
function gnf_required_evidence_field_ids( ...$args ) { return array( 2 ); }
function gnf_get_reto_canonical_slug( $text ) { return 'huerta'; }
function get_the_title( $id ) { return 'Reto Huerta'; }
function gnf_rest_get_wpforms_conditional_rules( $id ) { return array(); }
function gnf_get_reto_color( $id ) { return '#fff'; }
function gnf_get_reto_icon_url( ...$args ) { return ''; }
function gnf_get_reto_pdf_url( ...$args ) { return ''; }
function gnf_get_reto_max_points( ...$args ) { return 20; }
$source = file_get_contents( dirname( __DIR__ ) . '/includes/rest-api.php' );
$start = strpos( $source, 'function gnf_rest_docente_form_html(' );
eval( substr( $source, $start, strpos( $source, "\n}", $start ) + 2 - $start ) );
$user = 1; $renders = 0; $definition = array(); $html = '';
$tests = 0; $fails = 0;
function check_blank( $ok, $label ) { global $tests, $fails; $tests++; $fails += $ok ? 0 : 1; echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $label . "\n"; }
$result = gnf_rest_docente_form_html( new WP_REST_Request() );
check_blank( ! empty( $result['formError'] ) && '' === $result['html'], 'A damaged form returns an explicit recoverable error' );
check_blank( 0 === $renders, 'A corrupt definition is never rendered by WPForms' );
check_blank( 1 === count( $result['entry']['evidencias'] ) && '10' === $result['savedValues'][2], 'Existing evidence and answers remain available during a form failure' );
$definition = array( 'fields' => array( 2 => array( 'id' => 2, 'type' => 'file-upload' ) ) );
$result = gnf_rest_docente_form_html( new WP_REST_Request() );
check_blank( ! empty( $result['formError'] ), 'Empty shortcode output is not reported as a usable form' );
$html = '<form><div class="wpforms-field">Pregunta</div></form>';
$result = gnf_rest_docente_form_html( new WP_REST_Request() );
check_blank( empty( $result['formError'] ) && $html === $result['html'], 'A valid form renders normally after restoration' );
$user = 2; $other = gnf_rest_docente_form_html( new WP_REST_Request() );
check_blank( '20' === $other['savedValues'][2] && 20 === $other['entry']['center'], 'Responses are loaded live for the current user, never shared' );
echo "{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
