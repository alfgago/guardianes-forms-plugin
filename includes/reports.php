<?php
/**
 * Reportes y exportaciones.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exporta CSV por region o global.
 */
function gnf_export_csv( $region_id = null, $anio = null ) {
	if ( ! current_user_can( 'manage_options' ) && ! gnf_user_has_role( wp_get_current_user(), 'supervisor' ) ) {
		wp_die( 'Sin permisos' );
	}

	$anio  = gnf_normalize_year( $anio );
	$items = gnf_get_report_data( $region_id, $anio );

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=guardianes-' . ( $region_id ? 'region-' . $region_id : 'global' ) . '-' . $anio . '.csv' );
	$output = fopen( 'php://output', 'w' );
	fputcsv( $output, array( 'Centro', 'Region', 'Reto ID', 'Puntaje', 'Estado', 'Año' ) );
	foreach ( $items as $item ) {
		fputcsv(
			$output,
			array(
				$item->centro,
				$item->region,
				$item->reto_id,
				$item->puntaje,
				$item->estado,
				$item->anio,
			)
		);
	}
	fclose( $output );
	exit;
}

/**
 * Obtiene datos para el reporte (CSV o Tabla).
 */
function gnf_get_report_data( $region_id = null, $anio = null ) {
	global $wpdb;
	$anio  = gnf_normalize_year( $anio );
	$table = $wpdb->prefix . 'gn_reto_entries';

	$where   = $wpdb->prepare( 'WHERE e.anio = %d', $anio );
	$join    = 'INNER JOIN ' . $wpdb->posts . ' c ON c.ID = e.centro_id';
	$join   .= ' LEFT JOIN ' . $wpdb->term_relationships . ' tr ON tr.object_id = c.ID';
	$join   .= ' LEFT JOIN ' . $wpdb->term_taxonomy . ' tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = "gn_region"';
	$join   .= ' LEFT JOIN ' . $wpdb->terms . ' t ON t.term_id = tt.term_id';

	if ( $region_id ) {
		$where .= $wpdb->prepare( ' AND t.term_id = %d', $region_id );
	}

	return $wpdb->get_results(
		"SELECT e.*, c.post_title as centro, t.name as region FROM {$table} e {$join} {$where} AND e.estado IN ('aprobado','enviado')"
	);
}

/**
 * Endpoint admin_post para exportar.
 */
function gnf_handle_export_csv() {
	$region = isset( $_GET['region'] ) ? absint( $_GET['region'] ) : null;
	$anio   = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : gnf_get_context_year( gnf_get_active_year() );
	gnf_export_csv( $region, $anio );
}
add_action( 'admin_post_gnf_export_csv', 'gnf_handle_export_csv' );

/**
 * Resuelve el alcance permitido para exportaciones de centros.
 *
 * @param int|null $region_id Region solicitada.
 * @param string   $circuito  Circuito solicitado.
 * @return array{region_ids:int[],circuito:string}
 */
function gnf_get_centros_export_scope( $region_id = null, $circuito = '' ) {
	$user     = wp_get_current_user();
	$circuito = function_exists( 'gnf_normalize_circuito' ) ? gnf_normalize_circuito( $circuito ) : trim( (string) $circuito );

	if ( current_user_can( 'manage_options' ) ) {
		return array(
			'region_ids' => $region_id ? array( absint( $region_id ) ) : array(),
			'circuito'   => $circuito,
		);
	}

	if ( ! gnf_user_has_role( $user, 'supervisor' ) && ! gnf_user_has_role( $user, 'comite_bae' ) ) {
		wp_die( 'Sin permisos' );
	}

	$allowed_regions = function_exists( 'gnf_get_user_regions' ) ? gnf_get_user_regions( $user->ID ) : array();
	$allowed_regions = array_values( array_filter( array_map( 'absint', (array) $allowed_regions ) ) );

	if ( empty( $allowed_regions ) ) {
		wp_die( 'Sin region asignada' );
	}

	if ( $region_id ) {
		$requested_region = absint( $region_id );
		if ( ! in_array( $requested_region, $allowed_regions, true ) ) {
			wp_die( 'Sin permisos para esta region' );
		}
		$allowed_regions = array( $requested_region );
	}

	$user_circuito = function_exists( 'gnf_get_user_circuito' ) ? gnf_get_user_circuito( $user->ID ) : '';
	if ( '' !== $user_circuito ) {
		if ( '' !== $circuito && $circuito !== $user_circuito ) {
			wp_die( 'Sin permisos para este circuito' );
		}
		$circuito = $user_circuito;
	}

	return array(
		'region_ids' => $allowed_regions,
		'circuito'   => $circuito,
	);
}

/**
 * Query base para centros matriculados exportables.
 *
 * @param int[]  $region_ids Region term IDs. Empty = all regions.
 * @param string $circuito   Circuito normalizado.
 * @param int    $anio       Año.
 * @return WP_Post[]
 */
function gnf_get_centros_matriculados_for_export( $region_ids, $circuito, $anio ) {
	$centros_ids = gnf_get_centros_with_matricula( $anio );
	if ( empty( $centros_ids ) ) {
		return array();
	}

	$args = array(
		'post_type'      => 'centro_educativo',
		'posts_per_page' => -1,
		'post__in'       => $centros_ids,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);

	if ( ! empty( $region_ids ) ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'gn_region',
				'field'    => 'term_id',
				'terms'    => array_values( array_map( 'absint', (array) $region_ids ) ),
			),
		);
	}

	if ( '' !== $circuito ) {
		$args['meta_query'] = array(
			array(
				'key'     => 'circuito',
				'value'   => function_exists( 'gnf_get_circuito_query_values' ) ? gnf_get_circuito_query_values( $circuito ) : array( $circuito ),
				'compare' => 'IN',
			),
		);
	}

	return get_posts( $args );
}

function gnf_get_centro_export_region_name( $centro_id ) {
	$terms = wp_get_object_terms( $centro_id, 'gn_region' );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return '';
	}

	return (string) $terms[0]->name;
}

function gnf_get_centros_export_filename( $prefix, $region_id, $circuito, $anio ) {
	$scope = $region_id ? 'region-' . absint( $region_id ) : 'dre';
	if ( '' !== $circuito ) {
		$scope .= '-circuito-' . sanitize_title( $circuito );
	}

	return sprintf( '%s-%s-%d.csv', sanitize_title( $prefix ), $scope, absint( $anio ) );
}

/**
 * Exporta CSV con listado de centros matriculados (una fila por centro).
 *
 * Columnas: Centro, Código MEP, Dirección Regional, Retos seleccionados, Año.
 *
 * @param int|null $region_id Filtrar por región (term_id). Null = todas.
 * @param int|null $anio      Año. Default: año activo.
 */
function gnf_export_centros_csv( $region_id = null, $anio = null, $circuito = '' ) {
	if ( ! current_user_can( 'manage_options' ) && ! gnf_user_has_role( wp_get_current_user(), 'supervisor' ) && ! gnf_user_has_role( wp_get_current_user(), 'comite_bae' ) ) {
		wp_die( 'Sin permisos' );
	}

	$anio    = gnf_normalize_year( $anio );
	$scope   = gnf_get_centros_export_scope( $region_id, $circuito );
	$centros = gnf_get_centros_matriculados_for_export( $scope['region_ids'], $scope['circuito'], $anio );

	$filename = gnf_get_centros_export_filename( 'centros-matriculados', $region_id, $scope['circuito'], $anio );
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );

	$output = fopen( 'php://output', 'w' );
	fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // BOM UTF-8.
	fputcsv( $output, array( 'Centro Educativo', 'Código MEP', 'Dirección Regional', 'Circuito', 'Retos Seleccionados', 'Año' ) );

	foreach ( $centros as $centro ) {
		$codigo     = get_post_meta( $centro->ID, 'codigo_mep', true );
		$region_name = gnf_get_centro_export_region_name( $centro->ID );
		$circuito    = function_exists( 'gnf_normalize_circuito' ) ? gnf_normalize_circuito( get_post_meta( $centro->ID, 'circuito', true ) ) : (string) get_post_meta( $centro->ID, 'circuito', true );
		$retos_ids  = gnf_get_centro_retos_seleccionados( $centro->ID, $anio );
		$num_retos  = count( $retos_ids );

		fputcsv( $output, array(
			$centro->post_title,
			$codigo ?: '',
			$region_name,
			$circuito,
			$num_retos,
			$anio,
		) );
	}

	fclose( $output );
	exit;
}

/**
 * Endpoint admin_post para exportar centros matriculados.
 */
function gnf_handle_export_centros_csv() {
	$region   = isset( $_GET['region'] ) ? absint( $_GET['region'] ) : null;
	$anio     = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : gnf_get_context_year( gnf_get_active_year() );
	$circuito = isset( $_GET['circuito'] ) ? sanitize_text_field( wp_unslash( $_GET['circuito'] ) ) : '';
	gnf_export_centros_csv( $region, $anio, $circuito );
}
add_action( 'admin_post_gnf_export_centros_csv', 'gnf_handle_export_centros_csv' );

/**
 * Exporta una lista simple de centros matriculados para DRE: nombre y codigo.
 */
function gnf_export_centros_matriculados_simple_csv( $region_id = null, $anio = null, $circuito = '' ) {
	$anio    = gnf_normalize_year( $anio );
	$scope   = gnf_get_centros_export_scope( $region_id, $circuito );
	$centros = gnf_get_centros_matriculados_for_export( $scope['region_ids'], $scope['circuito'], $anio );

	$filename = gnf_get_centros_export_filename( 'inscritos', $region_id, $scope['circuito'], $anio );
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );

	$output = fopen( 'php://output', 'w' );
	fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
	fputcsv( $output, array( 'Centro Educativo', 'Código MEP', 'Dirección Regional', 'Circuito', 'Retos Seleccionados', 'Año' ) );

	foreach ( $centros as $centro ) {
		$circuito_centro = function_exists( 'gnf_normalize_circuito' ) ? gnf_normalize_circuito( get_post_meta( $centro->ID, 'circuito', true ) ) : (string) get_post_meta( $centro->ID, 'circuito', true );
		$retos_ids       = gnf_get_centro_retos_seleccionados( $centro->ID, $anio );
		fputcsv(
			$output,
			array(
				$centro->post_title,
				get_post_meta( $centro->ID, 'codigo_mep', true ) ?: '',
				gnf_get_centro_export_region_name( $centro->ID ),
				$circuito_centro,
				count( $retos_ids ),
				$anio,
			)
		);
	}

	fclose( $output );
	exit;
}

/**
 * Endpoint admin_post para lista simple de centros matriculados DRE.
 */
function gnf_handle_export_centros_matriculados_simple_csv() {
	$region   = isset( $_GET['region'] ) ? absint( $_GET['region'] ) : null;
	$anio     = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : gnf_get_context_year( gnf_get_active_year() );
	$circuito = isset( $_GET['circuito'] ) ? sanitize_text_field( wp_unslash( $_GET['circuito'] ) ) : '';
	gnf_export_centros_matriculados_simple_csv( $region, $anio, $circuito );
}
add_action( 'admin_post_gnf_export_centros_matriculados_simple_csv', 'gnf_handle_export_centros_matriculados_simple_csv' );

/**
 * Helper para PDF resumen del docente (placeholder).
 *
 * Se podria integrar una libreria PDF; por ahora se listan PDFs por reto almacenados en evidencias.
 */
function gnf_generate_docente_pdf_stub( $user_id ) {
	return null;
}

/**
 * Selecciona entries aptos para reporte final (aprobado o enviado).
 */
function gnf_get_entries_for_report( $centro_id, $anio ) {
	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE centro_id = %d AND anio = %d AND estado IN ('aprobado','enviado')",
			$centro_id,
			$anio
		)
	);
}
