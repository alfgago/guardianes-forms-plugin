<?php
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
function get_bloginfo( $name ) { return 'Guardianes'; }
function esc_html( $value ) { return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function wp_slash( $value ) { return is_array( $value ) ? array_map( 'wp_slash', $value ) : ( is_string( $value ) ? addslashes( $value ) : $value ); }
class WP_Error {}
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function wp_insert_post( $data, $error = false ) {
	$GLOBALS['error_requested'] = $error;
	if ( $GLOBALS['insert_failure'] ) { return new WP_Error(); }
	if ( $GLOBALS['insert_zero'] ) { return 0; }
	$GLOBALS['stored_content'] = $GLOBALS['saved_corruption'] ? '{invalid' : stripslashes( $data['post_content'] );
	$GLOBALS['stored_title'] = stripslashes( $data['post_title'] );
	return 101;
}
function get_post( $id ) { return (object) array( 'ID' => $id, 'post_content' => $GLOBALS['stored_content'] ); }
require ABSPATH . 'seeders/seed-wpforms.php';
$seeder = new GNF_WPForms_Seeder();
$method = new ReflectionMethod( $seeder, 'create_wpforms_form' ); $method->setAccessible( true );
$fields = array( 1 => array( 'id' => 1, 'type' => 'file-upload', 'label' => 'Foto del "mural"', 'description' => '<a href="https://example.test">Guia</a>', 'path' => 'C:\\docs\\archivo', 'conditionals' => array( array( array( 'field' => 2, 'value' => 'Si' ) ) ) ), 2 => array( 'id' => 2, 'type' => 'text', 'label' => 'Comentarios' ) );
$tests = 0; $fails = 0;
function check_seed_integrity( $ok, $label ) { global $tests, $fails; $tests++; $fails += $ok ? 0 : 1; echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $label . "\n"; }
$insert_failure = false; $insert_zero = false; $saved_corruption = false; $stored_content = '';
$id = $method->invoke( $seeder, 'Reto "Agua"', $fields, 15 );
$decoded = json_decode( $stored_content, true );
check_seed_integrity( 101 === $id && is_array( $decoded ), 'Seeder persists readable JSON across the WordPress unslashing boundary' );
check_seed_integrity( $fields === ( $decoded['fields'] ?? null ), 'Seeder preserves quoted labels, HTML, paths and conditional logic' );
check_seed_integrity( 'Reto "Agua"' === $stored_title && $error_requested, 'Seeder protects title and requests explicit WordPress persistence errors' );
foreach ( array( 'insert_failure', 'insert_zero', 'saved_corruption' ) as $failure ) {
	$insert_failure = false; $insert_zero = false; $saved_corruption = false; $GLOBALS[$failure] = true;
	ob_start(); $id = $method->invoke( $seeder, 'Reto', $fields, 15 ); ob_end_clean();
	check_seed_integrity( false === $id, $failure . ': an invalid or failed form cannot be reported as created or linked to a reto' );
}
echo "{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
