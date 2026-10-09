<?php
function absint( $value ) { return abs( (int) $value ); }
function wpforms() { return (object) array( 'form' => new DefinitionProvider() ); }
class DefinitionProvider {
	function get( $id ) { return (object) array( 'post_content' => $GLOBALS['content'] ); }
}
function get_transient( $key ) { throw new RuntimeException( 'Definitions must not add wp_options reads' ); }
function set_transient( $key, $value, $ttl ) { throw new RuntimeException( 'Definitions must not add wp_options writes' ); }
function wp_cache_get( $key, $group ) { $GLOBALS['cache_reads']++; return $GLOBALS['cache'][ $key ] ?? false; }
function wp_cache_set( $key, $value, $group, $ttl ) { $GLOBALS['cache'][ $key ] = $value; $GLOBALS['ttls'][] = $ttl; return true; }
$source = file_get_contents( dirname( __DIR__ ) . '/includes/helpers.php' );
$start = strpos( $source, 'function gnf_get_wpforms_form_definition(' );
eval( substr( $source, $start, strpos( $source, "\n}", $start ) + 2 - $start ) );
$cache = array(); $ttls = array(); $cache_reads = 0; $tests = 0; $fails = 0;
function check_definition( $condition, $label ) { global $tests, $fails; $tests++; $fails += $condition ? 0 : 1; echo ( $condition ? 'ok: ' : 'FAIL: ' ) . $label . "\n"; }
$definition = array( 'fields' => array( 1 => array( 'id' => 1, 'type' => 'radio', 'label' => 'Plantaron arboles', 'choices' => array( 1 => array( 'label' => 'Si' ) ) ) ) );
$content = json_encode( $definition );
check_definition( gnf_get_wpforms_form_definition( 77530 ) === $definition, 'Returns the live form definition' );
check_definition( 1 === count( $cache ) && array( 7200 ) === $ttls, 'Shared definition is cached for two hours' );
$reads = $cache_reads;
check_definition( gnf_get_wpforms_form_definition( 77530 ) === $definition && $reads === $cache_reads, 'Repeated reads reuse the request cache' );
$definition['fields'][1]['label'] = 'Pregunta corregida'; $content = json_encode( $definition );
check_definition( gnf_get_wpforms_form_definition( 77530 ) === $definition, 'An edit invalidates the cached definition immediately' );
$before = count( $ttls ); $content = '{broken';
check_definition( array() === gnf_get_wpforms_form_definition( 77530 ), 'A corrupt current definition never falls back to a stale cached form' );
check_definition( count( $ttls ) === $before, 'Invalid definitions are never cached' );
$content = json_encode( $definition );
check_definition( gnf_get_wpforms_form_definition( 77530 ) === $definition, 'A restored definition becomes available immediately' );
check_definition( array() === gnf_get_wpforms_form_definition( 0 ), 'No lookup for a missing form ID' );
$loader = file_get_contents( dirname( __DIR__ ) . '/includes/react-loader.php' );
$start = strpos( $loader, 'function gnf_get_wpforms_form_data(' );
eval( substr( $loader, $start, strpos( $loader, "\n}", $start ) + 2 - $start ) );
$cached_reads = $cache_reads;
$runtime = gnf_get_wpforms_form_data( 77530 );
check_definition( 77530 === $runtime['id'] && $cache_reads === $cached_reads, 'Runtime preloading reuses the same definition cache' );
check_definition( ! isset( gnf_get_wpforms_form_definition( 77530 )['id'] ), 'Runtime preparation does not mutate the shared cached definition' );
$definition['fields'][2] = array( 'id' => 2, 'type' => 'text', 'label' => 'Pregunta vigente' ); $content = json_encode( $definition );
gnf_get_wpforms_form_definition( 77530 );
unset( $definition['fields'][1] ); $content = json_encode( $definition );
$current = gnf_get_wpforms_form_definition( 77530 );
check_definition( $current === $definition && ! isset( $current['fields'][1] ), 'Deleting a question refreshes the cache and keeps the remaining definition available' );
echo "{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
