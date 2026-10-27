<?php
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
$hooks = array(); $removed = array(); $panel = false;
function add_filter( $name, $fn ) { $GLOBALS['hooks'][$name] = $fn; }
function add_action( $name, $fn ) { $GLOBALS['hooks'][$name] = $fn; }
function gnf_is_panel_request_path() { return $GLOBALS['panel']; }
function wp_dequeue_script( $name ) { $GLOBALS['removed'][] = $name; }
function wp_dequeue_style( $name ) { $GLOBALS['removed'][] = $name; }
$file = ABSPATH . 'includes/performance.php';
if ( ! is_file( $file ) ) { fwrite( STDERR, "FAIL: panel asset optimization module missing\n" ); exit( 1 ); }
require $file;
function check_assets( $ok, $message ) { if ( ! $ok ) { throw new Exception( $message ); } }
check_assets( gnf_panel_cf7_assets( true ) === true && gnf_panel_cf7_assets( false ) === false, 'Preserve existing CF7 decisions on public pages' );
gnf_trim_panel_assets(); check_assets( ! $removed, 'Public pages preserve their scripts' );
$panel = true;
check_assets( false === gnf_panel_cf7_assets( true ), 'Panels do not start unused CF7 schema requests' );
gnf_trim_panel_assets();
check_assets( in_array( 'contact-form-7', $removed, true ) && in_array( 'swv', $removed, true ), 'Remove CF7-only runtime from panels' );
check_assets( ! in_array( 'wpforms', $removed, true ) && ! in_array( 'google-recaptcha', $removed, true ), 'Keep WPForms and shared captcha runtime intact' );
echo "Panel asset isolation checks passed\n";
