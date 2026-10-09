<?php
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require ABSPATH . 'includes/impact-metrics.php';
$tests = 0; $fails = 0;
function check_public_coverage( $condition, $message ) {
	global $tests, $fails;
	$tests++; $fails += $condition ? 0 : 1;
	echo ( $condition ? 'ok: ' : 'FAIL: ' ) . $message . "\n";
}
$scope = array( 'id' => 'territory', 'label' => 'Heredia', 'values' => array( 'selected' => 0, 'private' => 99 ), 'coverage' => array( 'selected' => 1, 'private' => 4 ) );
$report = array(
	'catalog' => array( array( 'key' => 'selected' ), array( 'key' => 'private' ) ),
	'total' => $scope,
	'regions' => array( 2 => $scope ),
	'circuits' => array( '2|01' => $scope ),
);
foreach ( array( array(), array( 'selected' ), array( 'unknown' ) ) as $keys ) {
	$filtered = gnf_filter_impact_report_metrics( $report, $keys );
	$expected = in_array( 'selected', $keys, true ) ? array( 'selected' ) : array();
	check_public_coverage( $expected === array_column( $filtered['catalog'], 'key' ), 'Catalog respects selection ' . json_encode( $keys ) );
	foreach ( array( $filtered['total'], $filtered['regions'][2], $filtered['circuits']['2|01'] ) as $index => $item ) {
		check_public_coverage( $expected === array_keys( $item['values'] ), 'Scope ' . $index . ' values respect selection ' . json_encode( $keys ) );
		check_public_coverage( $expected === array_keys( $item['coverage'] ), 'Scope ' . $index . ' coverage respects selection ' . json_encode( $keys ) );
		check_public_coverage( $item['label'] === $scope['label'], 'Scope ' . $index . ' territorial metadata is preserved' );
	}
}
$filtered = gnf_filter_impact_report_metrics( $report, array( 'selected' ) );
check_public_coverage( 0 === $filtered['total']['values']['selected'] && 1 === $filtered['total']['coverage']['selected'], 'Selected zero and its coverage survive filtering' );
check_public_coverage( 4 === $report['total']['coverage']['private'], 'Filtering does not mutate the original report' );
$legacy = array( 'catalog' => array(), 'total' => array( 'values' => array() ), 'regions' => array(), 'circuits' => array() );
check_public_coverage( ! array_key_exists( 'coverage', gnf_filter_impact_report_metrics( $legacy, array() )['total'] ), 'Reports without coverage retain their legacy shape' );
echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
