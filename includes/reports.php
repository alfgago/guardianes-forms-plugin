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
 * Exporta CSV con listado de centros matriculados (una fila por centro).
 *
 * Columnas: Centro, Código MEP, Dirección Regional, Retos seleccionados, Año.
 *
 * @param int|null $region_id Filtrar por región (term_id). Null = todas.
 * @param int|null $anio      Año. Default: año activo.
 */
function gnf_export_centros_csv( $region_id = null, $anio = null ) {
	if ( ! current_user_can( 'manage_options' ) && ! gnf_user_has_role( wp_get_current_user(), 'supervisor' ) ) {
		wp_die( 'Sin permisos' );
	}

	$anio = gnf_normalize_year( $anio );

	// Obtener IDs de centros con matrícula en este año.
	$centros_ids = gnf_get_centros_with_matricula( $anio );
	if ( empty( $centros_ids ) ) {
		$centros_ids = array( 0 );
	}

	$args = array(
		'post_type'      => 'centro_educativo',
		'posts_per_page' => -1,
		'post__in'       => $centros_ids,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);
	if ( $region_id ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'gn_region',
				'field'    => 'term_id',
				'terms'    => (int) $region_id,
			),
		);
	}

	$centros = get_posts( $args );

	$filename = 'centros-matriculados-' . ( $region_id ? 'region-' . $region_id . '-' : '' ) . $anio . '.csv';
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );

	$output = fopen( 'php://output', 'w' );
	fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // BOM UTF-8.
	fputcsv( $output, array( 'Centro Educativo', 'Código MEP', 'Dirección Regional', 'Retos Seleccionados', 'Año' ) );

	foreach ( $centros as $centro ) {
		$codigo     = get_post_meta( $centro->ID, 'codigo_mep', true );
		$region_meta = get_post_meta( $centro->ID, 'region', true );
		$region_term = $region_meta ? get_term( (int) $region_meta, 'gn_region' ) : null;
		$region_name = ( $region_term && ! is_wp_error( $region_term ) ) ? $region_term->name : '';
		$retos_ids  = gnf_get_centro_retos_seleccionados( $centro->ID, $anio );
		$num_retos  = count( $retos_ids );

		fputcsv( $output, array(
			$centro->post_title,
			$codigo ?: '',
			$region_name,
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
	$region = isset( $_GET['region'] ) ? absint( $_GET['region'] ) : null;
	$anio   = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : gnf_get_context_year( gnf_get_active_year() );
	gnf_export_centros_csv( $region, $anio );
}
add_action( 'admin_post_gnf_export_centros_csv', 'gnf_handle_export_centros_csv' );

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
